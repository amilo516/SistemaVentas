# Contrato: formularios y respuestas de usuario

Todas las solicitudes POST conservan `verificarCsrf()` y los `requirePermission()` actuales.

## Roles: crear (`views/roles/index.php`, POST) y editar (`views/roles/editar.php`, POST)

**Permiso**: `roles.gestionar` (sin él: 403, "Acceso denegado").

| Campo POST | Tipo | Obligatorio | Regla |
|------------|------|-------------|-------|
| `nombre` | texto | sí | existente |
| `descripcion` | texto | no | existente |
| `permisos[]` | lista | no | existente (solo editar) |
| **`descuento_maximo`** | número (`,` o `.` decimal) | no al crear (default 10); sí al editar | 0–100, como máximo dos decimales |

**Respuestas**:

| Situación | Resultado |
|-----------|-----------|
| Valor válido | Se guarda, se audita `roles / descuento maximo` si cambió, se llama a `marcarCambioPermisos()` y aparece el mensaje flash "Rol guardado…" |
| Valor fuera de rango, no numérico o con más de dos decimales | No se guarda nada; error "El descuento máximo debe ser un número entre 0 y 100, con hasta dos decimales."; el formulario conserva lo escrito; sin auditoría |
| Rol propio y usuario no Administrador, con un valor distinto del actual | No se guarda nada; error "Solo el Administrador puede cambiar el descuento máximo de tu propio rol."; se audita `roles / descuento maximo denegado` |
| Rol Administrador | El campo se muestra de solo lectura con "Sin límite"; un valor enviado se ignora |

**Listado** (`views/roles/index.php`): nueva columna "Desc. máx." con `10 %`, `7,5 %` o
"Sin límite" para el Administrador.

## Venta: finalizar (`views/ventas/crear.php`, POST)

**Permiso**: `ventas.crear` (existente). Los campos POST no cambian (`descuento` sigue siendo
un monto).

**Pantalla**: junto al campo Descuento se muestra "Máximo para tu rol: 10 %" (o "Sin límite").
Hay un aviso opcional en el navegador al superar el límite, solo informativo; el servidor
decide.

| Situación | Resultado |
|-----------|-----------|
| Descuento ≤ máximo | La venta se registra como hoy |
| Descuento > máximo | No se registra la venta; mensaje de error "El descuento (15 %) supera el máximo permitido para tu rol (10 %)."; carrito, cliente y método de pago se conservan; se audita `ventas / descuento rechazado` |
| Descuento negativo o mayor que el subtotal | Mensajes actuales, sin auditoría de descuento rechazado |
