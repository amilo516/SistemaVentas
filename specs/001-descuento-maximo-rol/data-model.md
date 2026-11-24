# Data Model: Descuento máximo permitido por rol

## Rol (tabla `roles`, modificada)

| Campo | Tipo | Regla |
|-------|------|-------|
| `id_rol` | INT UNSIGNED PK | existente |
| `nombre` | VARCHAR(50) UNIQUE | existente |
| `descripcion` | VARCHAR(255) NULL | existente |
| `estado` | TINYINT(1) | existente |
| **`descuento_maximo`** | **DECIMAL(5,2) NOT NULL DEFAULT 10.00** | **nuevo**; 0.00 ≤ valor ≤ 100.00, dos decimales |

**Reglas de validación** (en `RolController`, antes de llamar al modelo):

- Se acepta coma o punto decimal (`7,5` → `7.50`); se rechaza un valor vacío, no numérico, `< 0`,
  `> 100` o con más de dos decimales (`7,555`; no se redondea) con el mensaje "El descuento
  máximo debe ser un número entre 0 y 100, con hasta dos decimales." (FR-003).
- Si se omite al crear un rol, vale `10.00` (FR-014).
- El rol `ROL_ADMINISTRADOR` (id 1) se guarda siempre con `100.00` y cualquier valor enviado
  se ignora (FR-005). Para la regla se trata como ilimitado, independientemente del valor
  almacenado.
- Si el rol editado es el rol del usuario en sesión y este no es Administrador, el valor no
  puede cambiar y el intento se audita (FR-002a).

**Valores iniciales** (script de actualización): todos los roles existentes en `10.00`, y el
Administrador en `100.00`.

## Venta (tabla `ventas`, sin cambios de esquema)

Datos usados por la regla: `subtotal` y `descuento`, calculados por `calcularTotales()`. El
porcentaje es `descuento / subtotal × 100`, sobre el subtotal antes de impuestos. Si el subtotal
es 0, el porcentaje es 0. Una venta rechazada no crea filas en `ventas`, `detalle_venta`,
`venta_pagos`, `movimientos_caja` ni `movimientos_inventario`, y el carrito sigue `activo`.

## Usuario (tabla `usuarios`, sin cambios)

`rol_id` determina el límite. El límite se lee en cada venta (`usuarios JOIN roles`), así que
un cambio de rol o de límite aplica desde la siguiente venta.

## Registro de auditoría (tabla `auditoria`, sin cambios de esquema)

| Evento | `modulo` | `accion` | `tabla_afectada` | `id_registro` | `descripcion` (ejemplo) |
|--------|----------|----------|------------------|---------------|-------------------------|
| Intento rechazado | `ventas` | `descuento rechazado` | `ventas` | NULL (0) | `Descuento rechazado: $15.000 (15 %) sobre subtotal $100.000. Máximo del rol Cajero: 10 %` |
| Cambio de límite | `roles` | `descuento maximo` | `roles` | id del rol | `Rol Cajero: descuento máximo 10 % → 5 %` |
| Cambio denegado del propio rol | `roles` | `descuento maximo denegado` | `roles` | id del rol | `Intento de cambiar el descuento máximo del propio rol Supervisor: 20 % → 50 %` |

`id_usuario`, `fecha` e `ip` se completan como hoy en `Auditoria::registrar()`. El intento
rechazado se inserta **después** del `rollBack()` de la venta, por lo que persiste.

## Transición de estado de la venta (finalizar)

```text
carrito activo ──finalizar──► [validaciones existentes] ──► [regla de descuento máximo]
      │                                                         │ excede
      │                                                         ▼
      │                                      rollBack ──► auditar intento ──► mensaje al usuario
      │                                                    (carrito sigue activo)
      ▼ no excede
  INSERT venta/detalle/pagos/caja ──► carrito convertido ──► auditar "crear" ──► commit
```
