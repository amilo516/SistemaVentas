<?php
declare(strict_types=1);

/**
 * Ejecuta una vista real en un proceso aparte con tests/Integration/fixtures/ejecutar_vista.php, manteniendo la
 * sesión en un archivo entre ejecuciones (como el navegador). Para clases que extienden BaseDatosTestCase.
 */
trait EjecutaVistas {
    private string $rutaSesiones;
    private string $sesionId;

    private function prepararSesiones(): void {
        $this->rutaSesiones = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sv_sesiones_pruebas';
        if (!is_dir($this->rutaSesiones)) mkdir($this->rutaSesiones);
        $this->sesionId = 'vista' . bin2hex(random_bytes(8));
    }

    private function borrarSesion(): void {
        @unlink($this->archivoSesion());
    }

    private function archivoSesion(): string {
        return $this->rutaSesiones . DIRECTORY_SEPARATOR . 'sess_' . $this->sesionId;
    }

    /** Ejecuta $vista y devuelve su HTML. $extra admite 'sesion', 'post' y 'get'. */
    private function ejecutarVista(string $vista, string $metodo, array $extra = []): string {
        $datos = ['vista' => $vista, 'metodo' => $metodo, 'sesion_id' => $this->sesionId, 'ruta_sesiones' => $this->rutaSesiones] + $extra;
        $cmd = [PHP_BINARY, __DIR__ . '/fixtures/ejecutar_vista.php', base64_encode(json_encode($datos, JSON_THROW_ON_ERROR))];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, null, array_merge(getenv(), ['DB_NAME' => self::BASE_PRUEBAS]));
        $this->assertIsResource($proc);
        $html = (string)stream_get_contents($tubos[1]);
        $errores = (string)stream_get_contents($tubos[2]);
        fclose($tubos[1]); fclose($tubos[2]);
        $this->assertSame(0, proc_close($proc), "La vista {$vista} terminó con error: {$errores}");
        $this->assertSame('', $errores, "La vista {$vista} no debe emitir avisos ni errores.");
        return $html;
    }

    /** Lo mismo que hace marcarCambioPermisos() al cambiar roles, permisos o el estado de un usuario. */
    private function renovarVersionDePermisos(): void {
        self::$db->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES ('permisos_version', :v, 'Control interno de cambios de permisos')
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute(['v' => bin2hex(random_bytes(8))]);
    }
}
