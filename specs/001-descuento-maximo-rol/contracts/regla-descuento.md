# Contrato: regla pura de descuento máximo (`helpers/descuentos.php`)

Sin dependencias de base de datos, sesión ni E/S. Se prueba en `tests/Unit`.

## `porcentajeDescuento(float $subtotal, float $descuento): float`

- Devuelve el porcentaje redondeado HACIA ARRIBA a 2 decimales, calculado en enteros:
  `intdiv(descuentoCent * 10000 + subtotalCent - 1, subtotalCent) / 100`.
  Solo se usa para mostrar; así un descuento rechazado nunca se muestra igual al límite. No usa
  `ceil()` sobre `float` (15000 / 100000 × 100 da `15.000000000000002` y se mostraría 15,01).
- `subtotal <= 0` → `0.0`.

## `formatearPorcentaje(float $valor): string`

- Coma decimal, sin ceros sobrantes ni separador de miles: `15`, `7,5`, `10,01`, `100`.

## `normalizarDescuentoMaximo(mixed $valor): ?float`

- Recorta espacios y acepta coma o punto decimal (`7,5` → `7.5`).
- Devuelve `null` si el valor está vacío, no es numérico, es `< 0`, es `> 100` o tiene más de
  2 decimales (`7,555`). No redondea.

## `validarDescuentoMaximo(float $subtotal, float $descuento, ?float $maximoPct, string $rol = ''): void`

- `$maximoPct === null` → ilimitado (Administrador): nunca lanza.
- Compara en enteros: `round(descuento*100) * 10000 > round(subtotal*100) * round(maximoPct*100)`.
- Si excede → lanza `DescuentoExcedidoException`; si no → retorna sin efecto.
- El límite es inclusivo: un descuento igual al máximo **no** lanza.
- `descuento <= 0` nunca lanza (incluido un máximo de 0 %).
- `subtotal <= 0` nunca lanza: un descuento sobre subtotal 0 lo rechaza antes la regla existente
  "no mayor que el subtotal" (FR-015), y aquí produciría un mensaje sin sentido ("0 %").

## `final class DescuentoExcedidoException extends RuntimeException`

Propiedades públicas de solo lectura: `subtotal`, `descuento`, `porcentaje` (2 decimales),
`maximo`, `rol`.

`getMessage()`: `El descuento ({porcentaje} %) supera el máximo permitido para tu rol ({maximo} %).`
Los números se formatean con `formatearPorcentaje()`; `{porcentaje}` es el valor de
`porcentajeDescuento()` (redondeado hacia arriba).

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
| 0 | 500 | 10 | no lanza (lo rechaza FR-015 antes) |
| 33333.33 | 3333.33 | 10 | no lanza (9,99999 %) |
| 1000 | 75 | 7.5 | no lanza |
| 1000 | 75.01 | 7.5 | lanza |
| 100000 | 10004 | 10 | lanza; porcentaje mostrado 10,01 |
| 100000 | 15000 | 20 | no lanza; porcentaje mostrado 15 (sin error de float) |
