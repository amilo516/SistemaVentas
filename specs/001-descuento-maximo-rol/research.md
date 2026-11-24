# Research: Descuento máximo permitido por rol

Fase 0. El contexto técnico no tenía puntos sin resolver (`NEEDS CLARIFICATION`); aquí se
registran las decisiones de diseño y su justificación.

## R1. Conservar la auditoría del intento rechazado tras el rollback

- **Decision**: la validación ocurre dentro de la transacción de `finalizar()`, en cuanto se
  conoce el subtotal y antes de cualquier `INSERT`. Si se excede el límite se lanza
  `DescuentoExcedidoException` (extiende `RuntimeException`) con subtotal, descuento,
  porcentaje y máximo. En el `catch`, primero `rollBack()` y luego, si la excepción es de ese
  tipo, `Auditoria::registrar('ventas','descuento rechazado',...)`, ya fuera de cualquier
  transacción, en modo autocommit. Después se relanza la excepción para que la vista muestre
  el mensaje.
- **Rationale**: es el cambio mínimo sobre el flujo actual. La vista ya captura
  `RuntimeException`, no hace falta una segunda conexión y el registro queda confirmado aunque
  la venta se deshaga.
- **Alternatives considered**:
  - *Validar antes de `beginTransaction()`*: obligaría a leer el carrito dos veces (fuera y
    dentro de la transacción con bloqueo) y abriría una carrera con cambios del carrito.
  - *Segunda conexión PDO solo para auditoría*: funciona, pero añade una conexión por venta y
    complejidad innecesaria.
  - *`SAVEPOINT`*: no sirve, porque el rollback total también descarta lo hecho tras el
    savepoint.

## R2. Comparación exacta del porcentaje (sin errores de coma flotante)

- **Decision**: la regla trabaja en enteros. El descuento y el subtotal se pasan a centavos y el
  máximo a centésimas de punto porcentual (`round(x*100)`). Se excede si
  `descuentoCent * 10000 > subtotalCent * maximoCentPct`. El porcentaje que se muestra se
  calcula aparte, redondeado a 2 decimales y truncado hacia arriba solo para mostrarlo (nunca
  para decidir).
- **Rationale**: la especificación exige que 10,01 % con un límite de 10 % se rechace y que
  10 % exacto se acepte; la aritmética con `float` puede fallar en ese borde.
- **Alternatives considered**: `bcmath` (extensión no garantizada en XAMPP) y `float` con épsilon
  (ambiguo en el borde).

## R3. Dónde vive la regla pura

- **Decision**: `helpers/descuentos.php`, con las funciones `porcentajeDescuento()` y
  `validarDescuentoMaximo()` y la clase `DescuentoExcedidoException`. No incluye
  `config/database.php` ni usa `$_SESSION`.
- **Rationale**: sigue el patrón de `helpers/factura.php` (`calcularTotales()`), respeta el
  Principio I y permite probarla en `tests/Unit` sin base de datos.
- **Alternatives considered**: un método en `VentaController` (no se puede probar sin base de
  datos) o una clase de servicio en una carpeta `services/` nueva (capa que no existe en el
  proyecto).

## R4. Origen del límite vigente al finalizar la venta

- **Decision**: `Rol::descuentoMaximoDeUsuario(int $idUsuario): ?float` consulta
  `usuarios JOIN roles` con el id de la sesión, en el momento de finalizar. Si el rol es
  `ROL_ADMINISTRADOR` devuelve `null`, que significa ilimitado.
- **Rationale**: cumple FR-013 (el cambio de límite o de rol aplica desde la siguiente venta,
  sin volver a iniciar sesión) sin depender de la caché de sesión.
- **Alternatives considered**: guardar el límite en `$_SESSION` y refrescarlo con
  `sincronizarSesion()`. Funciona gracias a `marcarCambioPermisos()`, pero acopla la regla al
  ciclo de sesión y añade un caso más que probar.

## R5. Restricción sobre el propio rol

- **Decision**: en `RolController::guardar()`, si el rol editado es el del usuario en sesión
  (`$_SESSION['rol_id']`), el usuario no es Administrador y el valor enviado difiere del actual,
  se devuelve el error "Solo el Administrador puede cambiar el descuento máximo de tu propio
  rol" y no se guarda nada del formulario. En la vista el campo se muestra de solo lectura,
  como ayuda visual; la validación real está en el servidor.
- **Rationale**: cierra la vía de autoaprobación (aclaración Q2) sin un permiso nuevo.

## R6. Script de actualización y compatibilidad MariaDB/MySQL

- **Decision**: `actualizacion_descuento_maximo.sql`, con el mismo estilo que
  `actualizacion_asesor_ia.sql`. Usa `ALTER TABLE roles ADD COLUMN IF NOT EXISTS
  descuento_maximo DECIMAL(5,2) NOT NULL DEFAULT 10.00` (sintaxis de MariaDB, la base de
  XAMPP), seguido de `UPDATE roles SET descuento_maximo=100 WHERE id_rol=1` y de la
  actualización de `permisos_version`. `database.sql` incluye la columna para instalaciones
  nuevas.
- **Rationale**: el script se puede ejecutar varias veces sin error y deja todos los roles en
  10 % (aclaración Q1).
- **Alternatives considered**: un procedimiento con `information_schema` para MySQL 8, que es
  más complejo; si se usa MySQL puro, se documenta en quickstart quitar `IF NOT EXISTS`.

## R7. PHPUnit y base de datos de pruebas

- **Decision**: `phpunit/phpunit ^10.5` en `require-dev`, que soporta PHP 8.1 (PHPUnit 11
  exige 8.2). `phpunit.xml` define las suites `Unit` e `Integration` y
  `<env name="DB_NAME" value="sistema_ventas_test"/>`. `config/database.php` lee
  `getenv('DB_NAME') ?: 'sistema_ventas'`. `tests/Integration/BaseDatosTestCase.php` crea la
  base de pruebas desde `database.sql` (cambiando el nombre de la base) y limpia las tablas
  entre pruebas.
- **Rationale**: aísla los datos de producción (Principio V) con un cambio mínimo y
  retrocompatible; la suite `Unit` se ejecuta aunque MySQL esté apagado.
- **Alternatives considered**: SQLite en memoria (dialecto distinto: `ENUM`, `ON DUPLICATE KEY`,
  `FOR UPDATE`) y mocks de PDO (no prueban el rollback real, que es justo lo crítico).

## R8. Nombres de auditoría

- **Decision**:
  - Intento rechazado: módulo `ventas`, acción `descuento rechazado`, tabla `ventas`, id `0`.
    Descripción: `Descuento rechazado: $15.000 (15 %) sobre subtotal $100.000. Máximo del rol
    Cajero: 10 %`.
  - Cambio de límite: módulo `roles`, acción `descuento maximo`, tabla `roles`, id del rol.
    Descripción: `Rol Cajero: descuento máximo 10 % → 5 %`.
  - Se añade `descuento rechazado` a las acciones "sensibles" del resumen de auditoría.
- **Rationale**: permite filtrar en el módulo de auditoría existente por módulo y acción
  (FR-011, FR-012).
