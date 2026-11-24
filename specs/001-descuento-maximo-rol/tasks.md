---

description: "Task list for feature implementation: Descuento máximo permitido por rol"
---

# Tasks: Descuento máximo permitido por rol

**Input**: Design documents from `/specs/001-descuento-maximo-rol/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUDED. Son obligatorios según la constitución (Principio V: PHPUnit antes de dar
por terminada una funcionalidad) y el plan los pide explícitamente. Escribe cada prueba primero
y comprueba que FALLA antes de implementar.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Rutas relativas a la raíz del repositorio (`C:\xampp\htdocs\Sistema-Ventas-main`), según la estructura MVC de plan.md

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Incorporar PHPUnit (primera tarea exigida por el usuario y el Principio V)

- [ ] T001 Agregar PHPUnit como dependencia de desarrollo con `composer require --dev phpunit/phpunit:^10.5` (actualiza composer.json y composer.lock) y añadir en composer.json el script `"test": "phpunit"` (research.md R7)
- [ ] T002 Crear phpunit.xml en la raíz: bootstrap `tests/bootstrap.php`; suites `Unit` (`tests/Unit`) e `Integration` (`tests/Integration`); `<env name="DB_NAME" value="sistema_ventas_test" force="true"/>`; `cacheDirectory=".phpunit.cache"`; `failOnWarning="true"`
- [ ] T003 Crear tests/bootstrap.php: carga `vendor/autoload.php` y `config/constantes.php`, inicializa `$_SESSION = []` y `$_SERVER['REMOTE_ADDR'] = '127.0.0.1'` sin llamar a `session_start()` y sin incluir `config/config.php` (envía cabeceras)
- [ ] T004 [P] Añadir `.phpunit.cache/` a .gitignore

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema, base de pruebas aislada y utilidades compartidas por las tres historias

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

- [ ] T005 Hacer que config/database.php lea el nombre de la base con `getenv('DB_NAME') ?: 'sistema_ventas'` (inicializado en el constructor). Sin la variable, el comportamiento web no cambia (plan.md, Constitution Check)
- [ ] T006 [P] Crear actualizacion_descuento_maximo.sql en la raíz, con el estilo de actualizacion_asesor_ia.sql: `USE sistema_ventas`; añadir la columna `data-model.md` Rol.descuento_maximo con `ALTER TABLE roles ADD COLUMN IF NOT EXISTS`; `UPDATE roles SET descuento_maximo = 100.00 WHERE id_rol = 1`; refrescar `permisos_version` con `UUID()` (research.md R6)
- [ ] T007 [P] Actualizar database.sql: añadir `data-model.md` Rol.descuento_maximo al `CREATE TABLE roles` y fijar 100.00 para el Administrador en el `INSERT INTO roles` inicial (database.sql:474), para instalaciones nuevas
- [ ] T008 Crear tests/Integration/BaseDatosTestCase.php (clase abstracta que extiende `PHPUnit\Framework\TestCase`): en `setUpBeforeClass()` recrea `sistema_ventas_test` desde database.sql (reemplazando el nombre de la base y su `USE`) y aborta si `getenv('DB_NAME')` no es `sistema_ventas_test`; en `setUp()` restaura los datos semilla. Expone utilidades para crear rol, usuario, cliente, producto con stock, carrito con ítems y apertura de caja, y una utilidad `iniciarSesionComo(int $idUsuario)` que llena `$_SESSION` (`id_usuario`, `rol_id`, `rol`, `permisos`, `permisos_version`). Depende de T005 y T007
- [ ] T009 Ejecutar `vendor/bin/phpunit` y confirmar que arranca sin errores con las suites vacías (BaseDatosTestCase conecta y crea `sistema_ventas_test`)

**Checkpoint**: Base lista. Las historias pueden comenzar

---

## Phase 3: User Story 1 - Rechazar ventas con descuento superior al permitido (Priority: P1) 🎯 MVP

**Goal**: Al finalizar una venta, si descuento ÷ subtotal supera el máximo del rol del usuario, la venta no se registra y el usuario ve el porcentaje aplicado y el límite.

**Independent Test**: Con el rol Cajero en 10 % (fijado por SQL en la prueba), una venta de $100.000 con $15.000 de descuento se rechaza sin tocar ventas, stock ni caja; con $10.000 se registra.

### Tests for User Story 1 ⚠️

- [ ] T010 [P] [US1] Crear tests/Unit/ReglaDescuentoTest.php sin base de datos: un caso por cada fila de la tabla "Casos mínimos de prueba" de contracts/regla-descuento.md (incluye el límite inclusivo, 10,01 %, un máximo de 0 %, `null` = Administrador, subtotal 0 y 7,5 %); las propiedades de `DescuentoExcedidoException`; el texto exacto de `getMessage()`; y el formato de porcentaje (`15`, `7,5`, `10,01`)
- [ ] T011 [P] [US1] Crear tests/Integration/VentaDescuentoMaximoTest.php (extiende BaseDatosTestCase) con los escenarios 1–4 de spec.md US1: rechazo con el mensaje exacto; ningún registro nuevo en `ventas`, `detalle_venta`, `venta_pagos`, `movimientos_caja` ni `movimientos_inventario`; stock sin cambios; carrito sigue `activo`; descuento igual al límite aceptado; sin descuento aceptado; Administrador con 90 % aceptado; cambio del límite en la base aplicado en la siguiente venta sin reiniciar `$_SESSION` (FR-013); reglas actuales intactas (negativo y mayor que el subtotal, FR-015); y `tienePermiso('ventas.crear')` en `false` para un rol sin ese permiso

### Implementation for User Story 1

- [ ] T012 [P] [US1] Crear helpers/descuentos.php sin dependencias de base de datos ni de sesión, según contracts/regla-descuento.md: `porcentajeDescuento()`, `validarDescuentoMaximo()` con comparación en enteros (research.md R2), `formatearPorcentaje(float): string` con coma decimal y sin ceros sobrantes, y `final class DescuentoExcedidoException extends RuntimeException` con propiedades de solo lectura `subtotal`, `descuento`, `porcentaje`, `maximo` y `rol`
- [ ] T013 [P] [US1] Añadir a models/Rol.php el método `descuentoMaximoDeUsuario(int $idUsuario): ?array`, que devuelve `['rol' => nombre, 'maximo' => ?float]` con una sentencia preparada sobre `usuarios INNER JOIN roles` leyendo `data-model.md` Rol.descuento_maximo; `maximo` es `null` si `id_rol === ROL_ADMINISTRADOR` (research.md R4)
- [ ] T014 [US1] En controllers/VentaController.php: añadir `require_once` de helpers/descuentos.php y models/Rol.php; en `finalizar()`, justo después de la validación "mayor que el subtotal" (línea 69) y antes de cualquier INSERT, obtener el límite con `Rol::descuentoMaximoDeUsuario($usuarioId)` y llamar a `validarDescuentoMaximo($t['subtotal'], $t['descuento'], $maximo, $rol)`. La excepción, al ser `RuntimeException`, se relanza tal cual por el `catch` existente (depende de T012 y T013)
- [ ] T015 [US1] En controllers/VentaController.php, `datosFormulario()`: añadir la clave `descuento_maximo` (`?float`, `null` = sin límite) con el límite del usuario en sesión
- [ ] T016 [US1] En views/ventas/crear.php: junto al campo Descuento (línea 60), mostrar "Máximo para tu rol: X %" o "Sin límite" usando `formatearPorcentaje()` y `e()`, y añadir `data-descuento-maximo` al `#pos` (línea 28)
- [ ] T017 [US1] En assets/js/ventas.js: leer `pos.dataset.descuentoMaximo` y mostrar un aviso informativo, sin bloquear el envío, cuando el descuento escrito supere el máximo (contracts/formularios.md; el servidor decide)
- [ ] T018 [US1] Ejecutar `vendor/bin/phpunit --filter "ReglaDescuentoTest|VentaDescuentoMaximoTest"` y dejarlas en verde

**Checkpoint**: US1 funcional y probada de forma independiente (MVP)

---

## Phase 4: User Story 2 - Configurar el porcentaje máximo de cada rol (Priority: P1)

**Goal**: Quien tiene `roles.gestionar` define el máximo de cada rol (0–100 %). El valor se ve en el listado, el Administrador queda fijo e ilimitado, nadie salvo el Administrador cambia el de su propio rol y cada cambio se audita.

**Independent Test**: Como Administrador, cambiar Cajero a 5 % → el listado lo muestra y la auditoría registra `10 % → 5 %`; un Supervisor con `roles.gestionar` no puede cambiar el rol Supervisor pero sí Cajero; 150 se rechaza.

### Tests for User Story 2 ⚠️

- [ ] T019 [P] [US2] Crear tests/Unit/NormalizarDescuentoMaximoTest.php: `normalizarDescuentoMaximo()` acepta `5`, `7,5`, `7.5`, `0`, `100` y `" 10 "`, y rechaza (`null`) `-1`, `100.01`, `abc` y la cadena vacía, según `data-model.md` Rol.descuento_maximo
- [ ] T020 [P] [US2] Crear tests/Integration/RolDescuentoMaximoTest.php (extiende BaseDatosTestCase) con los escenarios 1–6 de spec.md US2 y contracts/formularios.md: guardado válido; error de rango con el valor anterior conservado; rol nuevo con 10 % por defecto; Administrador siempre en 100 aunque se envíe otro valor; un Supervisor no puede cambiar su propio rol (mensaje exacto, sin cambios en nombre, descripción ni valor) pero sí el de Cajero; registro `roles / descuento maximo` con valor anterior → nuevo solo si cambió; `permisos_version` cambiado tras guardar; y `tienePermiso('roles.gestionar')` en `false` para Cajero

### Implementation for User Story 2

- [ ] T021 [US2] Añadir a helpers/descuentos.php la función pura `normalizarDescuentoMaximo(mixed $valor): ?float`, que acepta coma o punto, recorta espacios, redondea a 2 decimales y devuelve `null` si el valor es inválido o está fuera de 0–100, según `data-model.md` Rol.descuento_maximo (si US1 aún no creó el archivo, créalo)
- [ ] T022 [US2] En models/Rol.php: `crear(string $nombre, ?string $descripcion, float $descuentoMaximo = 10.00)` incluye `data-model.md` Rol.descuento_maximo en el INSERT preparado; nuevo método `actualizarDescuentoMaximo(int $id, float $valor): void` con una sentencia preparada. `listar()` y `buscar()` ya devuelven la columna vía `r.*`
- [ ] T023 [US2] En controllers/RolController.php, `guardar()`: requerir helpers/descuentos.php; leer `$post['descuento_maximo']` (10 si se omite al crear) con `normalizarDescuentoMaximo()` y añadir el error "El descuento máximo debe ser un número entre 0 y 100." si es inválido; para `ROL_ADMINISTRADOR` forzar 100.00 e ignorar lo enviado; si `$id === (int)$_SESSION['rol_id']`, el usuario no es Administrador y el valor difiere del actual, devolver el error "Solo el Administrador puede cambiar el descuento máximo de tu propio rol." (research.md R5). Todos los errores se devuelven antes de cualquier escritura. Al guardar, si el valor cambió, registrar `Auditoria::registrar('roles','descuento maximo','roles',$id,"Rol {nombre}: descuento máximo {anterior} % → {nuevo} %")` (data-model.md, tabla de auditoría). `marcarCambioPermisos()` ya se invoca (depende de T021 y T022)
- [ ] T024 [P] [US2] En views/roles/editar.php: campo "Descuento máximo (%)" (`name="descuento_maximo"`, `inputmode="decimal"`, conserva `$_POST` si hay error), de solo lectura con "Sin límite" para el Administrador y de solo lectura con una nota cuando `$id === (int)$_SESSION['rol_id']` y el usuario no es Administrador
- [ ] T025 [P] [US2] En views/roles/index.php: columna "Desc. máx." en el listado (`10 %`, `7,5 %`, "Sin límite" para el Administrador, usando `formatearPorcentaje()` y `e()`) y campo opcional `descuento_maximo` con valor 10 en el formulario de creación
- [ ] T026 [US2] Ejecutar `vendor/bin/phpunit --filter "NormalizarDescuentoMaximoTest|RolDescuentoMaximoTest"` y dejarlas en verde

**Checkpoint**: US1 y US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Registrar en auditoría cada intento rechazado (Priority: P2)

**Goal**: Cada venta rechazada por exceder el límite deja un registro de auditoría que sobrevive al rollback.

**Independent Test**: Provocar un rechazo y encontrar en `auditoria` (módulo `ventas`, acción `descuento rechazado`) el usuario, el subtotal, el monto, el porcentaje y el máximo, sin que exista la venta.

**Depende de**: US1 (T012 y T014: la excepción y su punto de lanzamiento)

### Tests for User Story 3 ⚠️

- [ ] T027 [P] [US3] Crear tests/Integration/AuditoriaDescuentoRechazadoTest.php (extiende BaseDatosTestCase) con los escenarios 1–3 de spec.md US3: el registro existe tras el rechazo con la descripción exacta de data-model.md (`Descuento rechazado: $15.000 (15 %) sobre subtotal $100.000. Máximo del rol Cajero: 10 %`), el `id_usuario` correcto y ninguna venta creada; dos intentos generan dos registros; no hay registro para rechazos por stock, descuento negativo o mayor que el subtotal, ni para ventas aceptadas; y si la escritura de auditoría falla, el usuario recibe igualmente el mensaje del límite

### Implementation for User Story 3

- [ ] T028 [US3] En controllers/VentaController.php, `finalizar()`, bloque `catch` (línea 108): después de `$this->db->rollBack()`, si `$e instanceof DescuentoExcedidoException`, registrar `Auditoria::registrar('ventas','descuento rechazado','ventas',0, ...)` con la descripción de data-model.md usando `dinero()` y `formatearPorcentaje()`, fuera de la transacción (research.md R1). Envolver ese registro en `try/catch (Throwable)` con `error_log()` para que un fallo de auditoría no oculte el mensaje al usuario; después relanzar `$e`
- [ ] T029 [P] [US3] En models/Auditoria.php, `resumen()`: añadir `'descuento rechazado'` a la lista de acciones sensibles (research.md R8)
- [ ] T030 [US3] Ejecutar `vendor/bin/phpunit --filter AuditoriaDescuentoRechazadoTest` y dejarla en verde

**Checkpoint**: Las tres historias funcionan de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Cierre según los criterios de "terminado" de la constitución

- [ ] T031 [P] Documentar en README.txt la aplicación de actualizacion_descuento_maximo.sql, el uso de `composer install` y la ejecución de `vendor/bin/phpunit` con la base `sistema_ventas_test`
- [ ] T032 Revisar el cumplimiento de la constitución en los archivos modificados: SQL nuevo solo con `prepare()`/`execute()`, salida de vistas con `e()`, formularios con `verificarCsrf()`, vistas sin SQL y auditoría sin datos sensibles
- [ ] T033 Ejecutar la suite completa `vendor/bin/phpunit` y confirmar que queda toda en verde (Principio V)
- [ ] T034 Ejecutar la validación manual de specs/001-descuento-maximo-rol/quickstart.md (pasos 1–4) y registrar el resultado de cada caso

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias. T001 → T002 → T003; T004 en paralelo
- **Foundational (Phase 2)**: depende de Setup. T005, T006 y T007 en paralelo; T008 depende de T005 y T007; T009 al final. BLOQUEA todas las historias
- **US1 (Phase 3)** y **US2 (Phase 4)**: pueden empezar al terminar la fase 2 y son independientes entre sí
- **US3 (Phase 5)**: depende de US1 (T012 y T014)
- **Polish (Phase 6)**: depende de las historias que se vayan a entregar

### User Story Dependencies

- **US1 (P1)**: solo fase 2
- **US2 (P1)**: solo fase 2. Comparte helpers/descuentos.php y models/Rol.php con US1 (secciones distintas); si se trabaja en paralelo, coordinar esos dos archivos
- **US3 (P2)**: requiere US1

### Within Each User Story

- Pruebas primero (deben fallar) → regla pura y modelo → controlador → vistas y JS → pruebas en verde
- T014 y T015 (mismo archivo) en secuencia; T028 después de T014
- T013 y T022 (models/Rol.php) en secuencia si se trabajan a la vez

### Parallel Opportunities

- Fase 1: T004 en paralelo con T001–T003
- Fase 2: T005, T006 y T007
- US1: T010, T011, T012 y T013
- US2: T019 y T020; después T024 y T025
- US3: T027 y T029
- US1 y US2 completas, en paralelo entre sí, tras la fase 2

---

## Parallel Example: User Story 1

```bash
# Pruebas de US1 en paralelo (deben fallar al inicio):
Task: "T010 tests/Unit/ReglaDescuentoTest.php"
Task: "T011 tests/Integration/VentaDescuentoMaximoTest.php"

# Regla pura y modelo en paralelo:
Task: "T012 helpers/descuentos.php"
Task: "T013 models/Rol.php::descuentoMaximoDeUsuario()"
```

## Parallel Example: User Story 2

```bash
Task: "T019 tests/Unit/NormalizarDescuentoMaximoTest.php"
Task: "T020 tests/Integration/RolDescuentoMaximoTest.php"
# Tras T023:
Task: "T024 views/roles/editar.php"
Task: "T025 views/roles/index.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Fase 1 (PHPUnit) y fase 2 (esquema y base de pruebas)
2. Fase 3 (US1): con el valor por defecto de 10 % del script, ya se bloquean los descuentos excesivos
3. **STOP and VALIDATE**: T018 en verde y quickstart paso 4
4. Entregar si se acepta configurar los valores por SQL mientras llega US2

### Incremental Delivery

1. Setup + Foundational → base lista
2. US1 → probar → entregar (MVP: el control funciona)
3. US2 → probar → entregar (el administrador configura desde la interfaz)
4. US3 → probar → entregar (trazabilidad de intentos)
5. Polish → suite completa y quickstart

---

## Notes

- [P] = archivos distintos y sin dependencias pendientes
- La constitución exige la suite PHPUnit en verde antes de integrar a `main` (T033)
- El checklist checklists/security.md tiene 31 ítems sin revisar; entre ellos hay huecos de requisitos (CHK006/CHK007, escalada indirecta; CHK027, descuento por línea del carrito) que conviene resolver antes de `/speckit-implement`, porque ese comando consulta el estado de los checklists
- T028 incluye una decisión no fijada en la especificación (CHK014: un fallo de auditoría no oculta el mensaje); confírmala al revisar el checklist
- Commit after each task or logical group
