# Contrato: `ordenarPorCriticidad()` (`helpers/productos.php`)

Función pura: sin base de datos, sesión ni E/S. Se prueba en `tests/Unit`.

## `ordenarPorCriticidad(array $productos): array`

- **Entrada**: lista de filas con al menos `stock`, `stock_minimo`, `nombre` e `id_producto`
  (números como `int`, `float` o texto numérico, tal como los devuelve PDO).
- **Salida**: la misma lista, reindexada desde 0, ordenada del más crítico al menos crítico. No
  filtra: ordenar no decide qué está en alerta (eso lo hace la consulta).
- **Orden** (FR-004):
  1. Agotados (`stock <= 0`) antes que el resto.
  2. Entre agotados: `stock` ascendente (−2 antes que 0).
  3. Entre el resto: proporción `stock ÷ stock_minimo` ascendente, comparada por multiplicación
     cruzada (sin dividir).
  4. Empate: `nombre` sin distinguir mayúsculas; después `id_producto` ascendente.
- **No modifica** el arreglo recibido.

## Casos mínimos de prueba (Unit)

| Entrada (nombre: stock / mínimo) | Orden esperado |
|----------------------------------|----------------|
| A: 40/50 · B: 1/10 | B (10 %), A (80 %) |
| A: 5/5 · B: 0/5 | B (agotado), A |
| A: 0/5 · B: −2/5 | B (−2), A (0) |
| A: 0/0 · B: 1/10 | A (agotado, mínimo 0, sin dividir), B |
| A: 1/10 · B: 2/20 | A, B (misma proporción 10 %, desempate por nombre) |
| Beta: 1/10 · alfa: 1/10 | alfa, Beta (nombre sin distinguir mayúsculas) |
| A: 0,5/2 · B: 1/2 (decimales) | A (25 %), B (50 %) |
| `[]` | `[]` |
| Entrada con índices 5 y 9 | salida con índices 0 y 1 |
