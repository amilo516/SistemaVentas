# Implementation Plan: Descuento máximo permitido por rol

**Branch**: `recuperacion/gutierrez` (carpeta: `specs/001-descuento-maximo-rol`) | **Date**: 2026-11-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-descuento-maximo-rol/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Cada rol tendrá un porcentaje máximo de descuento (`roles.descuento_maximo`, 0–100 %, dos
decimales, 10 % por defecto; el Administrador es siempre ilimitado). Al finalizar una venta,
`VentaController::finalizar()` obtiene el máximo vigente del rol del usuario desde la base de
datos y lo compara con el descuento usando una regla pura (`helpers/descuentos.php`), sin
acceso a datos y probada con PHPUnit sin base de datos. Si el descuento excede el límite, se
lanza `DescuentoExcedidoException`. El `catch` deshace la transacción de la venta y **después**
registra el intento en auditoría, fuera de la transacción, para que el registro se conserve. La
configuración se hace en la gestión de roles existente (`roles.gestionar`), y nadie salvo el
Administrador puede cambiar el límite de su propio rol. Como primera tarea se incorpora PHPUnit
(dependencia de desarrollo, `phpunit.xml`, `tests/`), tal como exige el Principio V.

## Technical Context

**Language/Version**: PHP 8.1+ (`composer.json` exige `^8.1`; el entorno local tiene 8.2.12)

**Primary Dependencies**: PDO (pdo_mysql); helpers propios (`permisos.php`, `factura.php`,
`funciones.php`); modelo `Auditoria`. Nueva dependencia de desarrollo: `phpunit/phpunit ^10.5`.

**Storage**: MySQL/MariaDB (XAMPP), base `sistema_ventas`; columna nueva en `roles`. Las pruebas
de integración usan una base aislada, `sistema_ventas_test`.

**Testing**: PHPUnit 10.5 con dos suites: `Unit` (regla pura, sin base de datos) e
`Integration` (controladores contra `sistema_ventas_test`).

**Target Platform**: Apache + PHP en XAMPP (Windows), navegador de escritorio.

**Project Type**: Aplicación web monolítica PHP con MVC propio (sin framework).

**Performance Goals**: la validación añade una consulta indexada por venta; sin impacto
perceptible (< 5 ms).

**Constraints**: no romper el flujo de venta actual; el registro de auditoría del intento
rechazado sobrevive al `rollBack()`; el límite cambiado aplica desde la siguiente venta sin
volver a iniciar sesión.

**Scale/Scope**: menos de 20 roles y decenas de usuarios; 1 columna nueva, 1 helper nuevo,
1 excepción nueva y cambios en 2 controladores, 1 modelo y 3 vistas.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cómo se cumple | Estado |
|-----------|----------------|--------|
| I. Arquitectura MVC | Regla pura en `helpers/descuentos.php`; lectura y escritura del límite en `models/Rol.php`; orquestación en `VentaController` y `RolController`; la presentación solo en `views/roles/*` y `views/ventas/crear.php`. Las vistas no ejecutan SQL. | ✅ |
| II. Sentencias preparadas | Las consultas nuevas (`descuentoMaximoDeUsuario`, `actualizarDescuentoMaximo`, `crear`) usan `prepare()` + `execute([...])`. La venta sigue dentro de su transacción. | ✅ |
| III. Permisos con `helpers/permisos.php` | La configuración sigue protegida por `requirePermission('roles.gestionar')` y la venta por `requirePermission('ventas.crear')`. La restricción sobre el propio rol se valida en el servidor (`RolController`). Al cambiar el límite se llama a `marcarCambioPermisos()`. | ✅ |
| IV. Auditoría | `ventas / descuento rechazado` en cada intento rechazado (se conserva tras el rollback); `roles / descuento maximo` en cada cambio de valor, con el anterior y el nuevo; `roles / descuento maximo denegado` al intentar cambiar el propio rol. Sin datos sensibles. | ✅ |
| V. PHPUnit antes de terminar | La tarea 1 instala PHPUnit. Se prueban la regla pura (Unit), la autorización permitida/denegada, la restricción del propio rol, el registro de auditoría tras el rollback y el caso del Administrador (Integration, en una base de pruebas aislada). | ✅ |
| Restricciones técnicas | Script versionado `actualizacion_descuento_maximo.sql` y `database.sql` actualizado para instalaciones nuevas; salida escapada con `e()`; formularios con CSRF y validación en el servidor. | ✅ |
| Flujo de desarrollo (rama propia) | Se trabaja en `recuperacion/gutierrez` por decisión del responsable; excepción documentada en Complexity Tracking. | ⚠️ excepción documentada |

**Resultado previo a la fase 0**: PASA, con una excepción de flujo documentada.

**Re-evaluación tras la fase 1**: PASA. El diseño de [data-model.md](data-model.md) y
[contracts/](contracts/) no introduce capas nuevas ni consultas sin preparar. El único cambio
en un archivo de configuración (`config/database.php` lee `DB_NAME` del entorno) es mínimo,
necesario para aislar la base de pruebas (Principio V) y compatible con el comportamiento
actual.

## Project Structure

### Documentation (this feature)

```text
specs/001-descuento-maximo-rol/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── regla-descuento.md   # Interfaz de la regla pura y la excepción
│   └── formularios.md       # Campos POST, mensajes y registros de auditoría
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks, no lo crea este comando)
```

### Source Code (repository root)

```text
composer.json                         # + require-dev phpunit/phpunit ^10.5, autoload-dev, script "test"
phpunit.xml                           # NUEVO: suites Unit e Integration, bootstrap, DB_NAME=sistema_ventas_test
actualizacion_descuento_maximo.sql    # NUEVO: ALTER TABLE roles + valores iniciales
database.sql                          # roles.descuento_maximo para instalaciones nuevas
config/
└── database.php                      # el nombre de la base se puede sobrescribir con getenv('DB_NAME')
helpers/
└── descuentos.php                    # NUEVO: regla pura + DescuentoExcedidoException
models/
└── Rol.php                           # crear() con descuento; actualizarDescuentoMaximo(); descuentoMaximoDeUsuario(): ?array
controllers/
├── RolController.php                 # validar/guardar descuento_maximo, bloqueo del propio rol, auditoría
└── VentaController.php               # validar antes de insertar; auditar tras rollBack; datosFormulario() con el límite
views/
├── roles/index.php                   # columna "Desc. máx." y campo al crear
├── roles/editar.php                  # campo "Descuento máximo (%)" (solo lectura para Admin y para el propio rol)
└── ventas/crear.php                  # muestra "Descuento máximo para tu rol: X %"
tests/
├── bootstrap.php                     # NUEVO: autoload, sesión simulada, utilidades de base de datos
├── Unit/
│   ├── ReglaDescuentoTest.php        # NUEVO: sin base de datos
│   └── NormalizarDescuentoMaximoTest.php # NUEVO: validación del valor configurable
└── Integration/
    ├── BaseDatosTestCase.php         # NUEVO: esquema, datos y limpieza en sistema_ventas_test
    ├── RolDescuentoMaximoTest.php    # NUEVO: validación, permisos, propio rol, auditoría
    ├── VentaDescuentoMaximoTest.php  # NUEVO: rechazo, Administrador, límite inclusivo, cambio de rol
    └── AuditoriaDescuentoRechazadoTest.php # NUEVO: registro del intento tras el rollback
```

**Structure Decision**: se mantiene el MVC existente (`controllers/`, `models/`, `views/`,
`helpers/`). La regla de negocio pura va en `helpers/` porque es una función transversal sin
estado ni acceso a datos, igual que `calcularTotales()` en `helpers/factura.php`. Las pruebas
viven en `tests/` en la raíz, separadas en `Unit` e `Integration`.

## Complexity Tracking

| Desviación | Por qué | Alternativa descartada |
|------------|---------|------------------------|
| La funcionalidad se desarrolla en `recuperacion/gutierrez` y no en una rama propia | Decisión del responsable: todo el trabajo de recuperación se integra desde esa rama | Rama `001-descuento-maximo-rol`: descartada por indicación explícita del responsable (2026-11-24) |
