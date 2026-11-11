<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/AsesorDatos.php';
require_once __DIR__ . '/../config/ia.php';

// Agente con IA (modelo de lenguaje): ciclo "razonar -> usar herramienta -> observar" hasta dar la respuesta final.
// El modelo nunca toca la base de datos: pide una herramienta y PHP ejecuta la consulta fija de AsesorDatos.
class AgenteIA {
    public array $herramientasUsadas = [];
    public int $tokensEntrada = 0;
    public int $tokensSalida = 0;

    private const SISTEMA = <<<'TXT'
Eres el asesor comercial de una tienda en Colombia que usa un sistema de ventas (moneda: pesos colombianos, COP).
Tu trabajo es dar recomendaciones concretas y accionables para el dueño o el supervisor: qué reabastecer y cuánto,
qué productos promocionar, qué precios revisar y qué problemas de ventas o devoluciones atender.

Reglas:
- Basa cada recomendación en datos que obtengas con las herramientas. Nunca inventes productos, cifras ni fechas.
- Consulta las herramientas que necesites (puedes llamar varias a la vez) antes de concluir.
- Cada recomendación debe citar las cifras que la justifican y una acción específica (por ejemplo, cuántas unidades pedir).
- Prioridad "alta" solo para lo urgente: productos agotados o por agotarse en menos de 3 días, ventas con pérdida o caídas fuertes.
- Máximo 8 recomendaciones, de la más a la menos importante. Si no hay datos suficientes, dilo en el resumen.
- Escribe en español, en tono profesional y claro, sin tecnicismos.
- Si el usuario hace una pregunta, respóndela en el resumen y deja en recomendaciones solo lo que se relacione con ella.
TXT;

    public function __construct(private AsesorDatos $datos) {}

    public static function disponible(): bool {
        return configIA()['api_key'] !== '' && is_file(__DIR__ . '/../vendor/autoload.php');
    }

    public function analizar(string $pregunta): array {
        require_once __DIR__ . '/../vendor/autoload.php';
        $cfg = configIA();
        $client = new \Anthropic\Client(apiKey: $cfg['api_key'], baseUrl: $cfg['base_url']);
        $messages = [['role' => 'user', 'content' => $pregunta]];

        for ($i = 0; $i < (int)$cfg['max_iteraciones']; $i++) {
            $response = $client->beta->messages->create(
                model: $cfg['modelo'],
                maxTokens: 16000,
                system: self::SISTEMA . "\nFecha de hoy: " . date('Y-m-d') . '.',
                tools: self::herramientas(),
                messages: $messages,
                outputConfig: ['effort' => $cfg['esfuerzo'], 'format' => ['type' => 'json_schema', 'schema' => self::esquema()]],
                fallbacks: 'default',   // si el modelo rechaza la solicitud, el servidor reintenta con otro modelo
                betas: ['server-side-fallback-2026-07-01'],
            );
            $this->tokensEntrada += (int)$response->usage->inputTokens;
            $this->tokensSalida += (int)$response->usage->outputTokens;

            if ($response->stopReason === 'refusal') throw new RuntimeException('El modelo no pudo atender la solicitud.');
            if ($response->stopReason === 'max_tokens') throw new RuntimeException('La respuesta del modelo quedó incompleta.');
            if ($response->stopReason !== 'tool_use') return $this->respuestaFinal($response->content);

            $resultados = [];
            foreach ($response->content as $bloque) {
                if ($bloque->type !== 'tool_use') continue;
                $this->herramientasUsadas[] = $bloque->name;
                try {
                    $contenido = json_encode($this->ejecutar($bloque->name, (array)$bloque->input), JSON_UNESCAPED_UNICODE);
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id, 'content' => $contenido];
                } catch (Throwable $e) {
                    error_log('Asesor IA, herramienta ' . $bloque->name . ': ' . $e->getMessage());
                    $resultados[] = ['type' => 'tool_result', 'toolUseID' => $bloque->id, 'content' => 'Error al consultar los datos.', 'isError' => true];
                }
            }
            // Historial solo se agrega (nunca se edita): turno del modelo + resultados de las herramientas.
            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user', 'content' => $resultados];
        }
        throw new RuntimeException('El agente superó el número máximo de pasos.');
    }

    // Despacho de herramientas: el nombre que pide el modelo -> la consulta fija correspondiente.
    private function ejecutar(string $nombre, array $in): array {
        $limitar = fn(array $filas) => array_slice($filas, 0, 25);
        return match ($nombre) {
            'consultar_inventario_critico' => $limitar($this->datos->inventarioCritico((int)($in['dias'] ?? 30))),
            'consultar_mas_vendidos' => $this->datos->masVendidos((int)($in['dias'] ?? 30), (int)($in['limite'] ?? 10)),
            'consultar_sin_movimiento' => $this->datos->sinMovimiento((int)($in['dias'] ?? 30)),
            'consultar_ventas' => ['por_dia' => $this->datos->ventasPorDia((int)($in['dias'] ?? 14)), 'comparativo_7_dias' => $this->datos->comparativo(7)],
            'consultar_devoluciones' => $this->datos->devoluciones((int)($in['dias'] ?? 30)),
            'consultar_margenes' => $this->datos->margenBajo((float)($in['margen_minimo'] ?? 15)),
            default => throw new InvalidArgumentException("Herramienta desconocida: $nombre"),
        };
    }

    private function respuestaFinal(array $contenido): array {
        foreach ($contenido as $bloque) {
            if ($bloque->type !== 'text') continue;
            $json = json_decode($bloque->text, true);
            if (is_array($json) && isset($json['resumen'], $json['recomendaciones'])) return $json;
            return ['resumen' => $bloque->text, 'recomendaciones' => []];
        }
        throw new RuntimeException('El modelo no devolvió texto.');
    }

    private static function herramientas(): array {
        $dias = ['type' => 'integer', 'description' => 'Días hacia atrás que se analizan (1 a 365).'];
        $tool = fn(string $nombre, string $descripcion, array $props) => [
            'name' => $nombre, 'description' => $descripcion, 'strict' => true,
            'inputSchema' => ['type' => 'object', 'properties' => $props, 'required' => array_keys($props), 'additionalProperties' => false],
        ];
        return [
            $tool('consultar_inventario_critico', 'Productos activos con stock en o bajo el mínimo, o que se agotarán en menos de 7 días. Devuelve stock, stock_minimo, promedio_diario de venta, dias_cobertura (null si no se vendió) y precio_compra.', ['dias' => $dias]),
            $tool('consultar_mas_vendidos', 'Productos más vendidos por ingreso en el periodo, con unidades, ingreso y costo.', ['dias' => $dias, 'limite' => ['type' => 'integer', 'description' => 'Cuántos productos devolver (1 a 30).']]),
            $tool('consultar_sin_movimiento', 'Productos con stock que no tuvieron ventas en el periodo, con el capital inmovilizado (stock x costo) y la fecha de su última venta.', ['dias' => $dias]),
            $tool('consultar_ventas', 'Total vendido y número de ventas por día, más el comparativo de los últimos 7 días frente a los 7 anteriores (variacion_pct).', ['dias' => $dias]),
            $tool('consultar_devoluciones', 'Productos devueltos en el periodo: unidades devueltas frente a vendidas, valor y motivos.', ['dias' => $dias]),
            $tool('consultar_margenes', 'Productos activos con margen de ganancia menor al indicado (margen_pct sobre el precio de venta; negativo = pérdida).', ['margen_minimo' => ['type' => 'number', 'description' => 'Margen mínimo aceptable en %, por ejemplo 15.']]),
        ];
    }

    private static function esquema(): array {
        return [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['resumen', 'recomendaciones'],
            'properties' => [
                'resumen' => ['type' => 'string', 'description' => 'Diagnóstico general en 2 o 3 frases, o la respuesta a la pregunta del usuario.'],
                'recomendaciones' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['prioridad', 'tipo', 'titulo', 'detalle', 'accion'],
                    'properties' => [
                        'prioridad' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
                        'tipo' => ['type' => 'string', 'enum' => ['reabastecer', 'promocion', 'precio', 'devoluciones', 'ventas']],
                        'titulo' => ['type' => 'string'],
                        'detalle' => ['type' => 'string', 'description' => 'Las cifras que justifican la recomendación.'],
                        'accion' => ['type' => 'string', 'description' => 'Qué hacer, de forma concreta.'],
                    ],
                ]],
            ],
        ];
    }
}
