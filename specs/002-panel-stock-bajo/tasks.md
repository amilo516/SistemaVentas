---

description: "Task list for feature implementation: Panel de productos con stock bajo en el dashboard"
---

# Tasks: Panel de productos con stock bajo en el dashboard

**Input**: Design documents from `/specs/002-panel-stock-bajo/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: INCLUDED. Obligatorios por la constitución (Principio V) y pedidos en el plan. Escribe
cada prueba primero y comprueba que FALLA antes de implementar.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Rutas relativas a la raíz del repositorio, según la estructura MVC de plan.md

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Alinear los documentos de diseño con la especificación actual y preparar el apoyo de pruebas

- [ ] T001 [P] Alinear el desempate de research.md R3 con spec.md FR-004: nombre "sin distinguir mayúsculas; las tildes y los números se comparan como texto" (quitar "ni tildes" y `strcoll`), y después `id_producto` ascendente, en specs/002-panel-stock-bajo/research.md
- [ ] T002 [P] Actualizar specs/002-panel-stock-bajo/contracts/panel-dashboard.md con lo que añadió spec.md: los agotados llevan el texto "Agotado" además del estilo (FR-003); el panel va después de los paneles existentes y se muestra aunque el usuario no vea los de ventas (FR-001); solo se muestran nombre, stock, mínimo y unidad (FR-003)
- [ ] T003 Ampliar la lista de vistas permitidas de tests/Integration/fixtures/ejecutar_vista.php para aceptar también `public/dashboard.php` (research.md R6), manteniendo la comprobación de que `DB_NAME` sea `sistema_ventas_test` y el rechazo de cualquier otra ruta

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: El filtro `limite` de `Producto::listar()`, del que depende el panel (plan.md, Complexity Tracking; research.md R2)

**⚠️ CRITICAL**: Ninguna historia puede empezar hasta completar esta fase

- [ ] T004 Crear tests/Integration/ProductoListarLimiteTest.php (extiende BaseDatosTestCase): sin `limite`, `listar()` sigue devolviendo como máximo 500 filas en el orden actual (por estado y nombre); con `limite` = 3 devuelve 3; un `limite` de 0, negativo o no numérico se trata como 1; `limite` funciona junto con `estado` y `stock_bajo`
- [ ] T005 En models/Producto.php, `listar()`: añadir el filtro opcional `limite` (entero, mínimo 1, por defecto 500) en el `LIMIT`, convertido con `(int)` como en `buscarParaVenta()`, sin cambiar nada más de la consulta (depende de T004)

**Checkpoint**: `listar()` admite `limite` y las pantallas que ya la usan no cambian

---

## Phase 3: User Story 1 - Ver en el dashboard qué productos reponer (Priority: P1) 🎯 MVP

**Goal**: El dashboard muestra a quien tiene permiso para ver productos los productos activos en alerta, del más crítico al menos crítico, con nombre, stock, mínimo y "Agotado" cuando corresponde.

**Independent Test**: Con tres productos activos en alerta (0/5, 1/10 y 40/50) y uno por encima de su mínimo, un cajero ve en el dashboard solo los tres, en el orden 0/5 → 1/10 → 40/50, y el primero con "Agotado".

### Tests for User Story 1 ⚠️

- [ ] T006 [P] [US1] Crear tests/Unit/OrdenCriticidadTest.php sin base de datos: un caso por cada fila de "Casos mínimos de prueba" de contracts/orden-criticidad.md, más: desempate por nombre sin distinguir mayúsculas (tildes como texto, spec.md FR-004), desempate final por `id_producto`, 0/100 y 0/1 empatados entre agotados (se ordenan por nombre), valores numéricos recibidos como texto (como los devuelve PDO), salida reindexada y arreglo de entrada sin modificar
- [ ] T007 [P] [US1] Crear tests/Integration/DashboardStockBajoTest.php (extiende BaseDatosTestCase) para `DashboardController::stockBajo()` según data-model.md "Resultado del panel": límite inclusivo (stock = mínimo entra, US1/AC2); stock por encima no entra (AC3); inactivo no entra (AC4); activo de una categoría inactiva sí entra (caso límite); orden de FR-004 (AC5); como máximo 10 filas con `total` real; con 501 productos en alerta, el más crítico (el último por orden alfabético) aparece primero (research.md R2); `total` igual que en la vista completa (`resumenStock()['bajos']`); y devuelve `null` si la consulta falla (FR-010), por ejemplo renombrando temporalmente la tabla `productos` en la base de pruebas y restaurándola en `finally`
- [ ] T008 [P] [US1] Crear tests/Integration/PanelStockBajoVistaTest.php (extiende BaseDatosTestCase) que ejecute la vista real `public/dashboard.php` con tests/Integration/fixtures/ejecutar_vista.php, según contracts/panel-dashboard.md:
  - visibilidad: un cajero ve el panel; un rol personalizado con `dashboard.ver` y sin `productos.ver` no lo ve, ni ningún dato de stock (US1/AC6, SC-004); un rol con `productos.ver` y sin `ventas.ver` sí lo ve (US1/AC8);
  - posición y contenido: el panel aparece después de los paneles existentes (FR-001); cada fila muestra nombre, stock y mínimo con su unidad y no muestra precio ni código (FR-003); un agotado lleva el texto "Agotado" (US1/AC7);
  - límite y errores: "Ver todos (N) →" hacia `views/productos/stock.php` aparece con 11 productos en alerta y no con 10 (FR-006); con la consulta fallando se ve "Sin datos disponibles" y el resto del dashboard sigue (FR-010); un nombre con `<b>` se muestra escapado
- [ ] T024 [US1] Añadir a tests/Integration/PanelStockBajoVistaTest.php la prueba del cambio de permisos con la sesión abierta (spec.md FR-008, segunda frase). Usa la misma sesión en todas las cargas, sin volver a iniciarla:
  - quitar el permiso: un cajero carga el dashboard y ve el panel; en la base de pruebas se le quita `productos.ver` a su rol y se renueva `permisos_version`, como hace `marcarCambioPermisos()`; la siguiente carga ya no muestra el panel ni ningún dato de stock;
  - dar el permiso: un rol sin `productos.editar` lo recibe y, tras renovar `permisos_version`, la siguiente carga muestra los nombres enlazados a la edición.

  Mismo archivo que T008, así que va después de T008 y antes de T014. La ejecuta T013.

### Implementation for User Story 1

- [ ] T009 [P] [US1] Añadir a helpers/productos.php la función pura `ordenarPorCriticidad(array $productos): array` según contracts/orden-criticidad.md y spec.md FR-004: agotados (`stock <= 0`) primero, por stock ascendente; el resto por proporción stock ÷ mínimo ascendente, comparada multiplicando en cruz (sin dividir); desempate por `mb_strtolower(nombre)` con comparación de texto y luego `id_producto`; sin base de datos ni sesión, sin modificar la entrada y con la salida reindexada
- [ ] T010 [US1] Añadir a controllers/DashboardController.php el método `stockBajo(int $max = 10): ?array` (research.md R4; data-model.md "Resultado del panel"): requerir models/Producto.php y helpers/productos.php; `total` = `Producto::resumenStock()['bajos']`; lista = `Producto::listar(['estado' => '1', 'stock_bajo' => true, 'limite' => max(500, total)])`, ordenada con `ordenarPorCriticidad()` y recortada a `$max`; devolver `['productos' => …, 'total' => (int)…]`, o `null` con `error_log('Dashboard: …')` si hay una `PDOException`, como `consultar()` (depende de T005 y T009)
- [ ] T011 [US1] En public/dashboard.php: requerir helpers/productos.php; si `tienePermiso('productos.ver')`, obtener `$stockBajo = $dash->stockBajo()`; pintar una `<section class="card">` "Stock bajo" **después** del bloque `.dash-paneles` y fuera de la condición `ventas.ver` (FR-001), según contracts/panel-dashboard.md:
  - cabecera con "Ver todos ({total}) →" hacia `views/productos/stock.php` solo si `total > 10` (FR-006);
  - una fila por producto con el nombre como texto, "Stock: {cantidad()} {unidad}" y "Mínimo: {cantidad()} {unidad}", todo con `e()`; para los agotados, una clase de estilo y el texto "Agotado" (FR-003);
  - "Sin datos disponibles" si `$stockBajo` es `null` (FR-010).

  Depende de T010.
- [ ] T012 [P] [US1] En assets/css/style.css: estilos de la lista del panel (reutilizando `.card` y `.vacio-panel`), estilo del agotado que acompañe al texto "Agotado", y nombres largos que pasen de línea (`overflow-wrap:anywhere`) sin desplazamiento horizontal (caso límite de spec.md)
- [ ] T013 [US1] Ejecutar `vendor/bin/phpunit --filter "OrdenCriticidadTest|DashboardStockBajoTest|PanelStockBajoVistaTest|ProductoListarLimiteTest"` y dejar en verde las pruebas de US1 y de la fase 2

**Checkpoint**: US1 funcional y probada de forma independiente (MVP)

---

## Phase 4: User Story 2 - Saber que no hay nada que reponer (Priority: P2)

**Goal**: Sin productos en alerta, el panel se muestra con "No hay productos con stock bajo."

**Independent Test**: Con todos los productos activos por encima de su mínimo, el panel muestra el mensaje; al bajar uno a su mínimo y recargar, aparece.

### Tests for User Story 2 ⚠️

- [ ] T014 [US2] Añadir a tests/Integration/PanelStockBajoVistaTest.php: sin productos en alerta, el panel se ve con "No hay productos con stock bajo." y sin "Ver todos" (US2/AC1, FR-005); tras bajar un producto a su mínimo en la base de pruebas, la siguiente carga lo muestra (US2/AC2, FR-008). Es el mismo archivo que T008, así que va después

### Implementation for User Story 2

- [ ] T015 [US2] En public/dashboard.php, sección "Stock bajo": si `total` es 0, mostrar `<div class="vacio-panel">` con "No hay productos con stock bajo.", con el mismo patrón que los paneles vacíos existentes (depende de T011)
- [ ] T016 [US2] Ejecutar `vendor/bin/phpunit --filter PanelStockBajoVistaTest` y dejarla en verde

**Checkpoint**: US1 y US2 funcionan de forma independiente

---

## Phase 5: User Story 3 - Ir a la edición del producto desde el panel (Priority: P3)

**Goal**: Quien puede editar productos llega a la edición desde el panel; los demás ven el nombre sin enlace.

**Independent Test**: Un supervisor ve cada nombre enlazado a `views/productos/editar.php?id=…`; un cajero ve los mismos nombres sin ningún enlace a la edición.

### Tests for User Story 3 ⚠️

- [ ] T017 [US3] Añadir a tests/Integration/PanelStockBajoVistaTest.php: el Supervisor y el Administrador ven cada nombre como enlace a `views/productos/editar.php?id={id}` del producto correcto (US3/AC1, FR-007); un cajero no ve en el panel ningún enlace a `views/productos/editar.php` (US3/AC2). Mismo archivo que T008 y T014, así que va después

### Implementation for User Story 3

- [ ] T018 [US3] En public/dashboard.php, filas del panel: envolver el nombre en un enlace a `views/productos/editar.php?id={(int)id}` solo si `tienePermiso('productos.editar')`; si no, dejarlo como texto (FR-007; aclaración Q1) (depende de T011)
- [ ] T019 [US3] Ejecutar `vendor/bin/phpunit --filter PanelStockBajoVistaTest` y dejarla en verde

**Checkpoint**: las tres historias funcionan de forma independiente

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Cierre según los criterios de "terminado" de la constitución

- [ ] T020 Revisar el cumplimiento de la constitución en los archivos modificados: ningún SQL en public/dashboard.php; toda salida dinámica con `e()` o convertida a entero; el `LIMIT` de `listar()` siempre entero; el panel y el enlace protegidos con `tienePermiso()`
- [ ] T021 Medir SC-005 en `sistema_ventas_test`: con 5.000 productos activos, 1.000 de ellos en alerta, generar `public/dashboard.php` con tests/Integration/fixtures/ejecutar_vista.php y registrar el tiempo (debe ser menos de 2 s). No usar la base real
- [ ] T022 Ejecutar la suite completa `vendor/bin/phpunit` y confirmar que queda toda en verde (Principio V)
- [ ] T023 Validación manual en el navegador según specs/002-panel-stock-bajo/quickstart.md (pasos 1–6). La hace el responsable en su base; registrar el resultado de cada paso

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias. T001, T002 y T003 en paralelo
- **Foundational (Phase 2)**: depende de Setup. T004 → T005. BLOQUEA todas las historias
- **US1 (Phase 3)**: depende de la fase 2
- **US2 (Phase 4)** y **US3 (Phase 5)**: dependen de T011 (la sección del panel existe); son independientes entre sí, pero comparten public/dashboard.php y tests/Integration/PanelStockBajoVistaTest.php, así que se hacen en secuencia
- **Polish (Phase 6)**: depende de las historias que se vayan a entregar

### User Story Dependencies

- **US1 (P1)**: solo la fase 2
- **US2 (P2)**: US1 (T011)
- **US3 (P3)**: US1 (T011)

### Within Each User Story

- Pruebas primero (deben fallar) → función pura → controlador → vista y CSS → pruebas en verde
- public/dashboard.php: T011 → T015 → T018 en secuencia
- tests/Integration/PanelStockBajoVistaTest.php: T008 → T014 → T017 en secuencia

### Parallel Opportunities

- Fase 1: T001, T002 y T003
- US1: T006, T007 y T008 (pruebas, archivos distintos); después T009 y T012

---

## Parallel Example: User Story 1

```bash
# Pruebas de US1 en paralelo (deben fallar al inicio):
Task: "T006 tests/Unit/OrdenCriticidadTest.php"
Task: "T007 tests/Integration/DashboardStockBajoTest.php"
Task: "T008 tests/Integration/PanelStockBajoVistaTest.php"

# Función pura y estilos en paralelo:
Task: "T009 helpers/productos.php::ordenarPorCriticidad()"
Task: "T012 assets/css/style.css"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Fase 1 (alinear documentos y apoyo de pruebas) y fase 2 (`limite` en `listar()`)
2. Fase 3 (US1): el panel ya muestra qué reponer, en orden, con los permisos correctos
3. **STOP and VALIDATE**: T013 en verde y quickstart pasos 1–3
4. Entregar si se acepta, por ahora, sin el mensaje de vacío ni los enlaces a la edición

### Incremental Delivery

1. Setup + Foundational → base lista
2. US1 → probar → entregar (MVP)
3. US2 → probar → entregar (mensaje "sin alertas")
4. US3 → probar → entregar (enlaces a la edición)
5. Polish → constitución, rendimiento, suite completa y quickstart

---

## Notes

- [P] = archivos distintos y sin dependencias pendientes
- La constitución exige la suite PHPUnit en verde antes de integrar a `main` (T022)
- Las pruebas de integración usan solo `sistema_ventas_test`; T021 y las pruebas de la vista no tocan la base real
- Se trabaja en `recuperacion/gutierrez` (excepción documentada en plan.md, Complexity Tracking); no crear ramas
- Commit after each task or logical group
