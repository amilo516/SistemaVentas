<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/AsesorDatos.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/factura.php';

// Asesor en modo local: motor de reglas sin conexión a internet.
// Usa las mismas herramientas que el agente con IA y devuelve el mismo formato de respuesta.
class AgenteLocal {
    private const DIAS_OBJETIVO = 15;   // días de venta que debería cubrir un pedido
    private const MARGEN_MINIMO = 15;   // % de margen por debajo del cual se alerta
    private const ORDEN = ['alta' => 0, 'media' => 1, 'baja' => 2];

    public function __construct(private AsesorDatos $datos) {}

    public function analizar(): array {
        $r = [];
        foreach ($this->datos->inventarioCritico(30) as $p) {
            $faltante = (float)$p['stock'] <= (float)$p['stock_minimo'];
            $sugerido = max(ceil((float)$p['promedio_diario'] * self::DIAS_OBJETIVO + (float)$p['stock_minimo'] - (float)$p['stock']),
                ceil((float)$p['stock_minimo'] * 2 - (float)$p['stock']), 1);
            $cobertura = $p['dias_cobertura'] === null ? 'sin ventas recientes' : 'alcanza para ' . cantidad($p['dias_cobertura']) . ' días';
            $r[] = $this->rec((float)$p['stock'] <= 0 || ($p['dias_cobertura'] !== null && $p['dias_cobertura'] < 3) ? 'alta' : ($faltante ? 'media' : 'baja'),
                'reabastecer', ($faltante ? 'Reabastecer ' : 'Se agotará pronto: ') . $p['nombre'],
                'Stock ' . cantidad($p['stock']) . ' ' . $p['unidad_medida'] . ' (mínimo ' . cantidad($p['stock_minimo']) . '), vende '
                    . cantidad($p['promedio_diario']) . ' al día; ' . $cobertura . '.',
                'Pedir ' . cantidad($sugerido) . ' ' . $p['unidad_medida'] . ' (≈ ' . dinero($sugerido * (float)$p['precio_compra']) . ' a precio de compra).');
        }
        foreach ($this->datos->sinMovimiento(30) as $p) {
            $r[] = $this->rec((float)$p['capital_inmovilizado'] >= 500000 ? 'media' : 'baja', 'promocion', 'Sin ventas en 30 días: ' . $p['nombre'],
                cantidad($p['stock']) . ' ' . $p['unidad_medida'] . ' en bodega = ' . dinero($p['capital_inmovilizado']) . ' inmovilizados'
                    . ($p['ultima_venta'] ? '; última venta el ' . date('d/m/Y', strtotime($p['ultima_venta'])) : '; nunca se ha vendido') . '.',
                'Crear una promoción o combo con un producto de alta rotación, o dejar de comprarlo.');
        }
        foreach ($this->datos->margenBajo(self::MARGEN_MINIMO) as $p) {
            $negativo = (float)$p['margen_pct'] < 0;
            $r[] = $this->rec($negativo ? 'alta' : 'media', 'precio', ($negativo ? 'Se vende con pérdida: ' : 'Margen bajo: ') . $p['nombre'],
                'Compra ' . dinero($p['precio_compra']) . ', venta ' . dinero($p['precio_venta']) . ' (margen ' . cantidad($p['margen_pct']) . '%).',
                'Subir el precio a ' . dinero(ceil((float)$p['precio_compra'] / (1 - self::MARGEN_MINIMO / 100) / 100) * 100) . ' para llegar al ' . self::MARGEN_MINIMO . '% o negociar con el proveedor.');
        }
        foreach ($this->datos->devoluciones(30) as $p) {
            $tasa = (float)$p['unidades_vendidas'] > 0 ? (float)$p['unidades_devueltas'] / (float)$p['unidades_vendidas'] * 100 : 100;
            if ($tasa < 10 || (float)$p['unidades_devueltas'] < 2) continue;
            $r[] = $this->rec($tasa >= 25 ? 'alta' : 'media', 'devoluciones', 'Muchas devoluciones: ' . $p['nombre'],
                'Se devolvió el ' . round($tasa) . '% de lo vendido en 30 días (' . cantidad($p['unidades_devueltas']) . ' ' . $p['unidad_medida'] . ').'
                    . ($p['motivos'] ? ' Motivos: ' . $p['motivos'] . '.' : ''),
                'Revisar la calidad del lote con el proveedor antes de volver a comprar.');
        }
        $c = $this->datos->comparativo(7);
        if ($c['variacion_pct'] !== null && abs($c['variacion_pct']) >= 20) {
            $baja = $c['variacion_pct'] < 0;
            $r[] = $this->rec($baja ? 'alta' : 'baja', 'ventas', $baja ? 'Las ventas bajaron ' . cantidad(abs($c['variacion_pct'])) . '% esta semana' : 'Las ventas subieron ' . cantidad($c['variacion_pct']) . '% esta semana',
                'Últimos 7 días: ' . dinero($c['actual']) . ' frente a ' . dinero($c['anterior']) . ' en los 7 anteriores.',
                $baja ? 'Revisar si faltó inventario de los productos más vendidos y reforzar promociones.' : 'Asegurar el inventario de los productos más vendidos para sostener la demanda.');
        }
        usort($r, fn($a, $b) => self::ORDEN[$a['prioridad']] <=> self::ORDEN[$b['prioridad']]);
        $r = array_slice($r, 0, 12);
        $altas = count(array_filter($r, fn($x) => $x['prioridad'] === 'alta'));
        return [
            'resumen' => $r ? 'Encontré ' . count($r) . ' recomendaciones' . ($altas ? ", $altas de prioridad alta." : '.') . ' Análisis por reglas sobre los últimos 30 días.'
                : 'No encontré alertas: el inventario, los márgenes y las ventas están en rangos normales.',
            'recomendaciones' => $r,
        ];
    }

    private function rec(string $prioridad, string $tipo, string $titulo, string $detalle, string $accion): array {
        return compact('prioridad', 'tipo', 'titulo', 'detalle', 'accion');
    }
}
