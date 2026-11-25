<?php
declare(strict_types=1);

require_once __DIR__ . '/EjecutaVistas.php';

/**
 * T040 (specs/001-descuento-maximo-rol, FR-002a): con la vista real views/roles/editar.php, un intento bloqueado de
 * cambiar el descuento máximo del propio rol no guarda nada del formulario, tampoco los permisos.
 */
final class RolEditarVistaTest extends BaseDatosTestCase {
    use EjecutaVistas;

    protected function setUp(): void { parent::setUp(); $this->prepararSesiones(); }
    protected function tearDown(): void { $this->borrarSesion(); parent::tearDown(); }

    private function estadoDelRol(int $id): array {
        $rol = self::$db->query("SELECT nombre, descripcion, descuento_maximo FROM roles WHERE id_rol = {$id}")->fetch(PDO::FETCH_ASSOC);
        $permisos = self::$db->query("SELECT p.nombre FROM rol_permisos rp JOIN permisos p ON p.id_permiso = rp.id_permiso
            WHERE rp.id_rol = {$id} ORDER BY p.nombre")->fetchAll(PDO::FETCH_COLUMN);
        return $rol + ['permisos' => $permisos];
    }

    public function testIntentoSobreElPropioRolNoGuardaNadaNiLosPermisos(): void {
        $this->asignarPermisos(ROL_SUPERVISOR, ['roles.gestionar']);
        $super = $this->crearUsuario('super1', ROL_SUPERVISOR);
        $antes = $this->estadoDelRol(ROL_SUPERVISOR);

        $this->iniciarSesionComo($super);
        $html = $this->ejecutarVista('views/roles/editar.php', 'POST', [
            'sesion' => $_SESSION + ['csrf' => 'token-prueba'],
            'get' => ['id' => (string)ROL_SUPERVISOR],
            'post' => ['csrf' => 'token-prueba', 'nombre' => 'Supervisor jefe', 'descripcion' => 'Otra descripción',
                'descuento_maximo' => '50',
                // lista distinta de la actual: quita casi todo y añade permisos de usuarios
                'permisos' => ['dashboard.ver', 'roles.gestionar', 'usuarios.ver', 'usuarios.editar']],
        ]);

        $this->assertStringContainsString('Solo el Administrador puede cambiar el descuento máximo de tu propio rol.', $html);
        $this->assertSame($antes, $this->estadoDelRol(ROL_SUPERVISOR), 'Ni el límite, ni el nombre, ni la descripción, ni los permisos cambian.');
        $this->assertSame(1, $this->contar('auditoria', "modulo = 'roles' AND accion = 'descuento maximo denegado'"));
        $this->assertSame(0, $this->contar('auditoria', "modulo = 'roles' AND accion = 'permisos'"), 'No hay registro de cambio de permisos.');
    }

    public function testSinElIntentoBloqueadoLaVistaSiGuardaLosPermisos(): void {
        // Control: la misma vista, editando otro rol, sí guarda los permisos (la prueba anterior no pasa por casualidad).
        $this->asignarPermisos(ROL_SUPERVISOR, ['roles.gestionar']);
        $this->iniciarSesionComo($this->crearUsuario('super1', ROL_SUPERVISOR));
        $this->ejecutarVista('views/roles/editar.php', 'POST', [
            'sesion' => $_SESSION + ['csrf' => 'token-prueba'],
            'get' => ['id' => (string)ROL_CAJERO],
            'post' => ['csrf' => 'token-prueba', 'nombre' => 'Cajero', 'descripcion' => 'Gestiona caja, pagos y ventas',
                'descuento_maximo' => '10', 'permisos' => ['dashboard.ver', 'ventas.ver', 'ventas.crear']],
        ]);
        $this->assertSame(['dashboard.ver', 'ventas.crear', 'ventas.ver'], $this->estadoDelRol(ROL_CAJERO)['permisos']);
    }
}
