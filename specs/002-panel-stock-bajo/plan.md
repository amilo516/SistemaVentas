# Implementation Plan: Panel de productos con stock bajo en el dashboard

**Branch**: `recuperacion/gutierrez` (carpeta: `specs/002-panel-stock-bajo`) | **Date**: 2026-11-24 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-panel-stock-bajo/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

El dashboard mostrará un panel "Stock bajo" a quien tenga `productos.ver`. Los datos salen de lo
que ya existe en el modelo `Producto`: `listar(['estado'=>'1','stock_bajo'=>true])`, con la
regla `stock <= stock_minimo` sobre productos activos, y `resumenStock()['bajos']` para el total.
No se duplica ninguna consulta ni regla. El orden "más crítico primero" (agotados del stock más
bajo al más alto, luego proporción stock ÷ mínimo, desempate por nombre) se aísla en una función
pura, `ordenarPorCriticidad()`, en `helpers/productos.php`, probada sin base de datos.
`DashboardController::stockBajo()` combina las dos cosas y devuelve los 10 primeros y el total.
`public/dashboard.php` pinta el panel con `tienePermiso('productos.ver')`, y el enlace a la
edición solo si `tienePermiso('productos.editar')`.

## Technical Context

**Language/Version**: PHP 8.1+ (`composer.json` exige `^8.1`; entorno local 8.2.12)

**Primary Dependencies**: PDO (pdo_mysql); modelo `Producto`; helpers `permisos.php`,
`funciones.php` (`e()`), `factura.php` (`cantidad()`), `productos.php`. Sin dependencias nuevas.

**Storage**: MySQL/MariaDB (`sistema_ventas`). Sin cambios de esquema: usa `productos.stock`,
`stock_minimo`, `unidad_medida`, `estado` y `nombre`.

**Testing**: PHPUnit 10.5 (ya instalado). Suite `Unit` para la regla de orden; suite
`Integration` contra `sistema_ventas_test` con `BaseDatosTestCase` y el apoyo
`tests/Integration/fixtures/ejecutar_vista.php` para ejecutar la vista real.

**Target Platform**: Apache + PHP en XAMPP (Windows), navegador de escritorio y móvil.

**Project Type**: Aplicación web monolítica PHP con MVC propio.

**Performance Goals**: SC-005, el servidor genera el dashboard completo en menos de 2 s con 5.000
productos activos, 1.000 de ellos en alerta, en el entorno XAMPP de desarrollo. La consulta es la
de `listar()`, filtrada por estado y stock; el orden se hace en PHP sobre las filas en alerta (en
la medición de SC-005, 1.000 filas).

**Constraints**: no duplicar la regla ni la consulta de stock bajo; no cambiar el comportamiento
de `listar()` para quienes ya lo usan (`views/productos/index.php`, `views/productos/stock.php`);
si la consulta falla, el resto del dashboard sigue funcionando (patrón actual de
`DashboardController`).

**Scale/Scope**: 1 función pura nueva, 1 método nuevo en el controlador, 1 filtro opcional en el
modelo, 1 sección nueva en la vista y estilos mínimos.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cómo se cumple | Estado |
|-----------|----------------|--------|
| I. Arquitectura MVC | La consulta sigue en `models/Producto.php`; la regla de orden pura va en `helpers/productos.php`; `DashboardController::stockBajo()` orquesta; `public/dashboard.php` solo pinta. La vista no ejecuta SQL. | ✅ |
| II. Sentencias preparadas | Se reutiliza `listar()`, que ya usa `prepare()`/`execute()`. El nuevo filtro `limite` entra como entero (`(int)`, mínimo 1), igual que el `LIMIT {$limite}` tipado de `buscarParaVenta()`; no se interpola texto del usuario. | ✅ |
| III. Permisos con `helpers/permisos.php` | El panel se muestra con `tienePermiso('productos.ver')`, el enlace con `tienePermiso('productos.editar')`, y el dashboard sigue protegido por `requirePermission('dashboard.ver')`. La edición sigue protegida por `requirePermission('productos.editar')` en su propia vista. | ✅ |
| IV. Auditoría | Mostrar el panel es una lectura, no una acción relevante (no modifica datos). No se audita, como el resto de paneles del dashboard (spec, supuestos). | ✅ (no aplica) |
| V. PHPUnit antes de terminar | Pruebas unitarias de la regla de orden; integración del controlador (regla inclusiva, inactivos, orden, límite de 10, total, más de 500 en alerta); vista real con permiso permitido y denegado y con y sin enlace de edición. | ✅ |
| Restricciones técnicas | Salida escapada con `e()`; cantidades con `cantidad()`; sin cambios de esquema, así que no hace falta script SQL. | ✅ |
| Flujo de desarrollo (rama propia) | Se trabaja en `recuperacion/gutierrez` por decisión del responsable (misma excepción que 001). | ⚠️ excepción documentada |

**Resultado previo a la fase 0**: PASA, con la misma excepción de flujo documentada.

**Re-evaluación tras la fase 1**: PASA. El único cambio a código existente (filtro opcional
`limite` en `Producto::listar()`) mantiene el valor por defecto de 500 y está cubierto por las
pruebas de integración.

## Project Structure

### Documentation (this feature)

```text
specs/002-panel-stock-bajo/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/
│   ├── orden-criticidad.md   # Contrato de la función pura
│   └── panel-dashboard.md    # Contrato de la interfaz del panel
├── checklists/
│   └── requirements.md
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
models/
└── Producto.php                      # listar(): filtro opcional 'limite' (entero, por defecto 500)
helpers/
└── productos.php                     # NUEVO: ordenarPorCriticidad(array): array (pura)
controllers/
└── DashboardController.php           # NUEVO método stockBajo(int $max = 10): ?array ['productos','total']
public/
└── dashboard.php                     # sección "Stock bajo" con permisos y enlaces condicionales
assets/css/
└── style.css                         # estilos mínimos de la lista del panel (reutiliza .card y .vacio-panel)
tests/
├── Unit/
│   └── OrdenCriticidadTest.php       # NUEVO: regla de orden sin base de datos
└── Integration/
    ├── DashboardStockBajoTest.php    # NUEVO: controlador contra sistema_ventas_test
    ├── PanelStockBajoVistaTest.php   # NUEVO: vista real (permisos, enlaces, vacío, "ver todos")
    └── fixtures/ejecutar_vista.php   # ampliar la lista de vistas permitidas a public/dashboard.php
```

**Structure Decision**: se mantiene el MVC existente. La función de orden va en
`helpers/productos.php`, que ya agrupa funciones puras de productos (`unidadesMedida()`,
`permiteDecimales()`). El panel se calcula en `DashboardController`, como los demás paneles del
dashboard.

## Complexity Tracking

| Desviación | Por qué | Alternativa descartada |
|------------|---------|------------------------|
| Se trabaja en `recuperacion/gutierrez` y no en una rama propia | Decisión del responsable para todo el trabajo de recuperación | Rama `002-panel-stock-bajo`: descartada por indicación explícita del responsable |
| Filtro opcional `limite` en `Producto::listar()` | `listar()` corta en 500 filas ordenadas por nombre; con más de 500 productos en alerta, los más críticos podrían quedar fuera y se incumpliría FR-004 | Ordenar en SQL (duplicaría la regla de orden, contra el plan) o una consulta nueva solo para el panel (duplicaría la regla de stock bajo) |
