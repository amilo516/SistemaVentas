# Contrato: regla pura de descuento máximo (`helpers/descuentos.php`)

Sin dependencias de base de datos, sesión ni E/S. Se prueba en `tests/Unit`.

## `porcentajeDescuento(float $subtotal, float $descuento): float`

- Devuelve `descuento / subtotal × 100`, redondeado a 2 decimales, solo para mostrarlo.
- `subtotal <= 0` → `0.0`.

## `validarDescuentoMaximo(float $subtotal, float $descuento, ?float $maximoPct, string $rol = ''): void`

- `$maximoPct === null` → ilimitado (Administrador): nunca lanza.
- Compara en enteros: `round(descuento*100) * 10000 > round(subtotal*100) * round(maximoPct*100)`.
- Si excede → lanza `DescuentoExcedidoException`; si no → retorna sin efecto.
- El límite es inclusivo: un descuento igual al máximo **no** lanza.
- `descuento <= 0` nunca lanza (incluido un máximo de 0 %).

## `final class DescuentoExcedidoException extends RuntimeException`

Propiedades públicas de solo lectura: `subtotal`, `descuento`, `porcentaje` (2 decimales),
`maximo`, `rol`.

`getMessage()`: `El descuento ({porcentaje} %) supera el máximo permitido para tu rol ({maximo} %).`
Los números se formatean con coma decimal y sin decimales innecesarios (`15`, `7,5`, `10,01`).

## Casos mínimos de prueba (Unit)

| Subtotal | Descuento | Máximo | Resultado |
|----------|-----------|--------|-----------|
| 100000 | 15000 | 10 | lanza; porcentaje 15, maximo 10 |
| 100000 | 10000 | 10 | no lanza (límite inclusivo) |
| 100000 | 10010 | 10 | lanza (10,01 %) |
| 100000 | 0 | 0 | no lanza |
| 100000 | 1 | 0 | lanza |
| 100000 | 99999 | null | no lanza (Administrador) |
| 0 | 0 | 10 | no lanza |
| 33333.33 | 3333.33 | 10 | no lanza (9,99999 %) |
| 1000 | 75 | 7.5 | no lanza |
| 1000 | 75.01 | 7.5 | lanza |
