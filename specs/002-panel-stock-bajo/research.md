# Research: Panel de productos con stock bajo en el dashboard

Fase 0. El contexto técnico no tenía puntos sin resolver; aquí quedan las decisiones de diseño.

## R1. Reutilizar la consulta y la regla de stock bajo

- **Decision**: la lista sale de `Producto::listar(['estado' => '1', 'stock_bajo' => true])` y el
  total de `Producto::resumenStock()['bajos']`. Las dos aplican la misma regla, `stock <= stock_minimo`
  sobre productos activos, que ya usan `views/productos/index.php` y `views/productos/stock.php`.
- **Rationale**: el panel y la vista "Stock bajo" muestran siempre lo mismo (supuesto de la
  especificación) y no se duplica la regla, como pide el plan.
- **Alternatives considered**: una consulta propia para el dashboard con `ORDER BY` y `LIMIT 10`
  (más eficiente, pero duplicaría la regla de stock bajo y la de orden).

## R2. Límite de 500 de `listar()`

- **Decision**: añadir a `listar()` un filtro opcional `limite` (entero, mínimo 1, por defecto
  500). `DashboardController::stockBajo()` pide `max(500, total)` para recibir **todos** los
  productos en alerta y elegir los 10 más críticos.
- **Rationale**: `listar()` ordena por nombre y corta en 500; con más de 500 productos en alerta,
  el recorte por nombre podría dejar fuera a los más críticos (FR-004). Con el valor por defecto
  sin cambios, las pantallas actuales no se ven afectadas.
- **Alternatives considered**: ignorar el caso (riesgo real en inventarios grandes) u ordenar en
  SQL (duplicaría la regla de orden).

## R3. Regla de orden en una función pura

- **Decision**: `ordenarPorCriticidad(array $productos): array` en `helpers/productos.php`.
  Recibe las filas del modelo (con `stock`, `stock_minimo` y `nombre`) y devuelve una copia ordenada
  con `usort`:
  1. Grupo: agotados (`stock <= 0`) antes que el resto.
  2. Dentro de los agotados: por `stock` ascendente (un negativo va antes que 0).
  3. Dentro del resto: por proporción `stock / stock_minimo` ascendente. Sin división por cero: el
     resto solo contiene filas con `stock > 0` y `stock <= stock_minimo`, así que `stock_minimo > 0`.
  4. Desempate: `nombre`, sin distinguir mayúsculas (`mb_strtolower` y comparación de texto); las
     tildes y los números se comparan como texto (spec.md FR-004), así que no se necesita la
     extensión `intl`. Por último, `id_producto` (orden de alta) para que el orden sea estable.
- **Rationale**: la regla se prueba sin base de datos (Principio V) y no depende de la consulta.
- **Comparación de proporciones**: se compara `stockA × mínimoB` con `stockB × mínimoA`
  (multiplicación cruzada) en lugar de dividir, para evitar errores de coma flotante en empates
  como 1/10 frente a 2/20.

## R4. Panel en el controlador del dashboard

- **Decision**: `DashboardController::stockBajo(int $max = 10): ?array` devuelve
  `['productos' => [...los $max más críticos...], 'total' => int]`, o `null` si la consulta falla.
  Usa el modelo `Producto` con la conexión del controlador y registra el error con `error_log()`,
  como `consultar()`/`consultarVarias()`.
- **Rationale**: sigue el patrón de los demás paneles: si falla, solo ese panel muestra "Sin datos
  disponibles" y el resto del dashboard sigue funcionando.

## R5. Permisos y enlaces en la vista

- **Decision**: en `public/dashboard.php`, el panel se pinta solo si `tienePermiso('productos.ver')`.
  El nombre es un enlace a `views/productos/editar.php?id=…` solo si
  `tienePermiso('productos.editar')`; si no, es texto. El enlace "Ver todos (N) →" a
  `views/productos/stock.php` aparece solo si hay más de 10 en alerta (FR-006).
- **Rationale**: aclaración Q1 de la especificación; mismo patrón que los paneles actuales, que se
  muestran u ocultan con `tienePermiso()`.

## R6. Pruebas de la vista

- **Decision**: reutilizar `tests/Integration/fixtures/ejecutar_vista.php` y ampliar su lista de
  vistas permitidas para aceptar `public/dashboard.php`. Mantiene la protección de abortar si
  `DB_NAME` no es `sistema_ventas_test`.
- **Rationale**: prueba el HTML real (panel visible u oculto, enlaces según permiso, mensaje de
  vacío, "Ver todos") sin navegador.
