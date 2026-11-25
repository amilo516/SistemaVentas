# Data Model: Panel de productos con stock bajo en el dashboard

Sin cambios de esquema. La funcionalidad solo lee datos existentes.

## Producto (tabla `productos`, sin cambios)

| Campo | Uso en el panel |
|-------|-----------------|
| `id_producto` | Destino del enlace a la edición y último desempate del orden |
| `nombre` | Se muestra (escapado); desempate del orden |
| `stock` | Se muestra con `cantidad()`; decide el grupo (agotado si `<= 0`) y la proporción |
| `stock_minimo` | Se muestra con `cantidad()`; denominador de la proporción |
| `unidad_medida` | Se muestra junto a las cantidades |
| `estado` | Solo entran los activos (`estado = 1`) |

## Alerta de stock bajo (calculada, no se guarda)

- **Condición**: producto activo con `stock <= stock_minimo` (la regla existente de
  `Producto::listar()` con `stock_bajo`). Límite inclusivo (FR-002).
- **Grupo**: `agotado` si `stock <= 0`; si no, `bajo`.
- **Proporción** (solo grupo `bajo`): `stock ÷ stock_minimo`, siempre en (0, 1], porque en ese
  grupo `0 < stock <= stock_minimo`.

## Resultado del panel (`DashboardController::stockBajo()`)

```text
null                     ← la consulta falló (el panel muestra "Sin datos disponibles")
['productos' => lista,   ← como máximo 10 filas de Producto, ya ordenadas por criticidad (FR-004)
 'total'     => entero]  ← cuántos productos activos hay en alerta en total (FR-006)
```

`total = 0` ⇒ el panel muestra "No hay productos con stock bajo." (FR-005).
`total > 10` ⇒ el panel muestra "Ver todos (total) →" hacia la vista de stock bajo (FR-006).
