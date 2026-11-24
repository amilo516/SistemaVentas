<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../controllers/RolController.php';

/**
 * US2: configurar el porcentaje máximo de cada rol (spec.md US2, FR-001..FR-005, FR-002a, FR-012, FR-014;
 * contracts/formularios.md). Roles iniciales: Administrador 100, Vendedor/Cajero/Supervisor 10.
 */
final class RolDescuentoMaximoTest extends BaseDatosTestCase {
    private const ERROR_RANGO = 'El descuento máximo debe ser un número entre 0 y 100, con hasta dos decimales.';
    private const ERROR_PROPIO = 'Solo el Administrador puede cambiar el descuento máximo de tu propio rol.';

    protected function setUp(): void {
        parent::setUp();
        $this->iniciarSesionComo(1); // Administrador
    }

    private function rol(int $id): array {
        return self::$db->query("SELECT nombre, descripcion, descuento_maximo FROM roles WHERE id_rol = {$id}")->fetch(PDO::FETCH_ASSOC);
    }

    private function auditoria(string $accion): array {
        $s = self::$db->prepare("SELECT * FROM auditoria WHERE modulo = 'roles' AND accion = :a ORDER BY id_auditoria");
        $s->execute(['a' => $accion]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function guardarCajero(string $valor, string $nombre = 'Cajero'): array {
        return (new RolController())->guardar(ROL_CAJERO, ['nombre' => $nombre, 'descripcion' => 'Gestiona caja, pagos y ventas', 'descuento_maximo' => $valor]);
    }

    // Escenario 1 + 6 (FR-012): guardar, ver en el listado y auditar el cambio
    public function testGuardaUnMaximoValidoLoListaYAuditaElCambio(): void {
        [$id, $errores] = $this->guardarCajero('5');
        $this->assertSame([], $errores);
        $this->assertSame(ROL_CAJERO, $id);
        $this->assertSame('5.00', $this->rol(ROL_CAJERO)['descuento_maximo']);

        $listado = array_column((new RolController())->listar(), 'descuento_maximo', 'nombre');
        $this->assertSame('5.00', $listado['Cajero'], 'FR-004: el listado trae el valor vigente.');

        $a = $this->auditoria('descuento maximo');
        $this->assertCount(1, $a);
        $this->assertSame('Rol Cajero: descuento máximo 10 % → 5 %', $a[0]['descripcion']);
        $this->assertSame(ROL_CAJERO, (int)$a[0]['id_registro']);
        $this->assertSame(1, (int)$a[0]['id_usuario']);
    }

    public function testAceptaComaDecimal(): void {
        [, $errores] = $this->guardarCajero('7,5');
        $this->assertSame([], $errores);
        $this->assertSame('7.50', $this->rol(ROL_CAJERO)['descuento_maximo']);
        $this->assertSame('Rol Cajero: descuento máximo 10 % → 7,5 %', $this->auditoria('descuento maximo')[0]['descripcion']);
    }

    // Escenario 2 (FR-003): valores inválidos, sin guardar nada ni auditar
    public static function valoresInvalidos(): array {
        return ['negativo' => ['-1'], 'mayor que 100' => ['100.01'], 'no numérico' => ['abc'],
            'vacío al editar' => [''], 'más de dos decimales' => ['7,555']];
    }

    #[DataProvider('valoresInvalidos')]
    public function testRechazaValoresInvalidosSinGuardarNada(string $valor): void {
        [$id, $errores] = $this->guardarCajero($valor, 'Cajero renombrado');
        $this->assertSame(0, $id);
        $this->assertSame([self::ERROR_RANGO], $errores);
        $this->assertSame(['nombre' => 'Cajero', 'descripcion' => 'Gestiona caja, pagos y ventas', 'descuento_maximo' => '10.00'], $this->rol(ROL_CAJERO));
        $this->assertSame(0, $this->contar('auditoria'), 'Los valores inválidos no se auditan.');
    }

    public function testNoAuditaSiElValorNoCambia(): void {
        [, $errores] = $this->guardarCajero('10,00');
        $this->assertSame([], $errores);
        $this->assertCount(0, $this->auditoria('descuento maximo'));
    }

    // FR-014: rol nuevo con 10 % por defecto, o con el valor indicado
    public function testRolNuevoUsaDiezPorCientoPorDefecto(): void {
        [$id, $errores] = (new RolController())->guardar(0, ['nombre' => 'Bodeguero']);
        $this->assertSame([], $errores);
        $this->assertSame('10.00', $this->rol($id)['descuento_maximo']);
    }

    // data-model.md: el valor inicial queda en el registro "crear" (no hay valor anterior para "descuento maximo")
    public function testCrearUnRolRegistraSuValorInicial(): void {
        [$id] = (new RolController())->guardar(0, ['nombre' => 'Bodeguero', 'descuento_maximo' => '7,5']);
        $crear = $this->auditoria('crear');
        $this->assertCount(1, $crear);
        $this->assertSame('Rol Bodeguero con descuento máximo 7,5 %', $crear[0]['descripcion']);
        $this->assertSame($id, (int)$crear[0]['id_registro']);
        $this->assertCount(0, $this->auditoria('descuento maximo'));
    }

    public function testRolNuevoConValorIndicado(): void {
        [$id, $errores] = (new RolController())->guardar(0, ['nombre' => 'Mayorista', 'descuento_maximo' => '3,25']);
        $this->assertSame([], $errores);
        $this->assertSame('3.25', $this->rol($id)['descuento_maximo']);
    }

    public function testRolNuevoConValorInvalidoNoSeCrea(): void {
        [$id, $errores] = (new RolController())->guardar(0, ['nombre' => 'Mayorista', 'descuento_maximo' => 'abc']);
        $this->assertSame(0, $id);
        $this->assertSame([self::ERROR_RANGO], $errores);
        $this->assertSame(0, $this->contar('roles', "nombre = 'Mayorista'"));
    }

    // FR-005: el Administrador siempre queda ilimitado
    public function testAdministradorQuedaSiempreEnCien(): void {
        [, $errores] = (new RolController())->guardar(ROL_ADMINISTRADOR, ['nombre' => 'Administrador', 'descuento_maximo' => '5']);
        $this->assertSame([], $errores);
        $this->assertSame('100.00', $this->rol(ROL_ADMINISTRADOR)['descuento_maximo']);
        $this->assertCount(0, $this->auditoria('descuento maximo'));
    }

    // Escenario 4 (FR-002a): nadie salvo el Administrador cambia el límite de su propio rol
    public function testSupervisorNoPuedeCambiarElLimiteDeSuPropioRol(): void {
        $this->asignarPermisos(ROL_SUPERVISOR, ['roles.gestionar']);
        $super = $this->crearUsuario('super1', ROL_SUPERVISOR);
        $this->iniciarSesionComo($super);

        [$id, $errores] = (new RolController())->guardar(ROL_SUPERVISOR,
            ['nombre' => 'Supervisor jefe', 'descripcion' => 'Otra', 'descuento_maximo' => '50']);
        $this->assertSame(0, $id);
        $this->assertSame([self::ERROR_PROPIO], $errores);
        $this->assertSame(['nombre' => 'Supervisor', 'descripcion' => 'Supervisa ventas, inventario y reportes', 'descuento_maximo' => '10.00'],
            $this->rol(ROL_SUPERVISOR), 'No se guarda nada del formulario.');

        $d = $this->auditoria('descuento maximo denegado');
        $this->assertCount(1, $d);
        $this->assertSame('Intento de cambiar el descuento máximo del propio rol Supervisor: 10 % → 50 %', $d[0]['descripcion']);
        $this->assertSame(ROL_SUPERVISOR, (int)$d[0]['id_registro']);
        $this->assertSame($super, (int)$d[0]['id_usuario']);

        // Sí puede cambiar el de otro rol.
        [, $errores] = $this->guardarCajero('20');
        $this->assertSame([], $errores);
        $this->assertSame('20.00', $this->rol(ROL_CAJERO)['descuento_maximo']);
    }

    public function testSupervisorPuedeEditarSuRolSinCambiarElLimite(): void {
        $this->asignarPermisos(ROL_SUPERVISOR, ['roles.gestionar']);
        $this->iniciarSesionComo($this->crearUsuario('super1', ROL_SUPERVISOR));
        [, $errores] = (new RolController())->guardar(ROL_SUPERVISOR,
            ['nombre' => 'Supervisor', 'descripcion' => 'Nueva descripción', 'descuento_maximo' => '10']);
        $this->assertSame([], $errores);
        $this->assertSame('Nueva descripción', $this->rol(ROL_SUPERVISOR)['descripcion']);
        $this->assertCount(0, $this->auditoria('descuento maximo denegado'));
    }

    // FR-013: el cambio avisa a las sesiones abiertas
    public function testGuardarRenuevaLaVersionDePermisos(): void {
        $antes = self::$db->query("SELECT valor FROM configuracion WHERE clave = 'permisos_version'")->fetchColumn();
        $this->guardarCajero('5');
        $despues = self::$db->query("SELECT valor FROM configuracion WHERE clave = 'permisos_version'")->fetchColumn();
        $this->assertNotFalse($despues);
        $this->assertNotSame($antes, $despues);
    }

    // Escenario 3: autorización permitida y denegada (la vista exige roles.gestionar)
    public function testSoloQuienGestionaRolesTieneElPermiso(): void {
        $this->assertTrue(tienePermiso('roles.gestionar'), 'Administrador: permitido.');
        $this->iniciarSesionComo($this->crearUsuario('cajero1', ROL_CAJERO));
        $this->assertFalse(tienePermiso('roles.gestionar'), 'Cajero: denegado.');
    }
}
