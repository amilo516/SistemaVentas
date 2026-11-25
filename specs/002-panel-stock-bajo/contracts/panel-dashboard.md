# Contrato: panel "Stock bajo" en `public/dashboard.php`

Pantalla existente protegida por `requirePermission('dashboard.ver')`. Sin formularios ni POST.

## Visibilidad (FR-001)

| Permisos del usuario | Panel |
|----------------------|-------|
| Sin `productos.ver` | No se pinta, ni ningún dato de stock |
| `productos.ver` sin `productos.editar` (Vendedor, Cajero) | Se pinta; nombres **sin** enlace |
| `productos.ver` y `productos.editar` (Supervisor, Administrador) | Se pinta; cada nombre enlaza a `views/productos/editar.php?id={id}` |

## Contenido

| Situación | Se muestra |
|-----------|------------|
| Hay productos en alerta | Título "Stock bajo" y una fila por producto (máximo 10) con nombre, "Stock: {stock} {unidad}" y "Mínimo: {mínimo} {unidad}", en el orden de `contracts/orden-criticidad.md`. Los agotados se distinguen visualmente. |
| Más de 10 en alerta | Además, en la cabecera: "Ver todos ({total}) →" hacia `views/productos/stock.php` |
| Ninguno en alerta | "No hay productos con stock bajo." (FR-005) |
| La consulta falló | "Sin datos disponibles", como los demás paneles; el resto del dashboard se muestra |

- Nombres escapados con `e()`; cantidades con `cantidad()` y la unidad de medida.
- El panel se recalcula en cada carga del dashboard (FR-008); no modifica datos (FR-009).
