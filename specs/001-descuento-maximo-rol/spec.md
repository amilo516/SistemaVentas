# Feature Specification: Descuento máximo permitido por rol

**Feature Branch**: `001-descuento-maximo-rol`

**Created**: 2026-11-24

**Status**: Draft

**Input**: User description: "Quiero que cada rol tenga un porcentaje máximo de descuento permitido al registrar una venta. Si un usuario aplica un descuento mayor al máximo de su rol, la venta debe rechazarse con un mensaje que indique el límite permitido. Un administrador debe poder configurar ese porcentaje por rol. Esto evita que se otorguen descuentos excesivos sin autorización. Cada intento rechazado debe quedar registrado en auditoría."

## Clarifications

### Session 2026-11-24

- Q: ¿Con qué porcentaje máximo de descuento arrancan los roles existentes y los que se creen
  después, mientras el administrador no lo configure? → A: 10 % para todos los roles
  (existentes y nuevos), excepto el Administrador, que no tiene límite.
- Q: ¿Quién puede cambiar el porcentaje máximo de descuento de un rol? → A: cualquier usuario
  con el permiso "Gestionar roles y permisos", excepto para el límite de su propio rol, que solo
  puede cambiar el Administrador.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Rechazar ventas con descuento superior al permitido (Priority: P1)

Un vendedor o cajero registra una venta y escribe un descuento. Si ese descuento, expresado
como porcentaje del subtotal de la venta, supera el máximo permitido para su rol, el sistema no
registra la venta y le muestra un mensaje con el límite que tiene permitido. El carrito y los
datos que ya había ingresado se conservan para que corrija el descuento y vuelva a intentarlo.

**Why this priority**: es el control que evita los descuentos excesivos sin autorización; sin
él la funcionalidad no tiene efecto.

**Independent Test**: con un rol configurado al 10 %, registrar una venta de subtotal $100.000
con un descuento de $15.000 (15 %) y comprobar que se rechaza con el mensaje del límite; luego
registrarla con $10.000 (10 %) y comprobar que se acepta.

**Acceptance Scenarios**:

1. **Given** un usuario cuyo rol permite hasta 10 % de descuento y una venta con subtotal de
   $100.000, **When** aplica un descuento de $15.000, **Then** la venta no se registra y ve el
   mensaje "El descuento (15 %) supera el máximo permitido para tu rol (10 %)".
2. **Given** el mismo usuario y venta, **When** aplica un descuento de exactamente $10.000
   (10 %), **Then** la venta se registra normalmente.
3. **Given** el mismo usuario y venta, **When** no aplica descuento, **Then** la venta se
   registra normalmente.
4. **Given** una venta rechazada por descuento, **When** el usuario vuelve a la pantalla de
   venta, **Then** el carrito, el cliente y el método de pago siguen como estaban y el stock y
   la caja no cambiaron.

---

### User Story 2 - Configurar el porcentaje máximo de cada rol (Priority: P1)

Un administrador entra a la gestión de roles y, para cada rol, define el porcentaje máximo de
descuento permitido (de 0 % a 100 %). Ve el valor actual de cada rol en el listado de roles y
puede cambiarlo cuando lo necesite. El cambio aplica a partir de la siguiente venta de los
usuarios de ese rol.

**Why this priority**: sin la configuración no hay límites que aplicar; es igual de necesaria
que la validación para tener un producto utilizable.

**Independent Test**: como administrador, cambiar el máximo del rol Cajero a 5 %, verificar
que el listado de roles lo muestra y que un cajero ya no puede registrar una venta con 6 % de
descuento.

**Acceptance Scenarios**:

1. **Given** un administrador en la edición de un rol, **When** guarda un máximo de 5 %,
   **Then** el valor queda guardado y se muestra en el listado de roles.
2. **Given** un administrador, **When** intenta guardar un valor menor que 0, mayor que 100 o
   no numérico, **Then** el sistema rechaza el cambio con un mensaje claro y conserva el valor
   anterior.
3. **Given** un usuario sin el permiso "Gestionar roles y permisos", **When** intenta cambiar
   el porcentaje (incluso accediendo directamente a la dirección), **Then** recibe "Acceso
   denegado" y el valor no cambia.
4. **Given** un Supervisor con el permiso "Gestionar roles y permisos", **When** intenta cambiar
   el porcentaje máximo del rol Supervisor, **Then** el sistema lo rechaza con el mensaje "Solo
   el Administrador puede cambiar el descuento máximo de tu propio rol" y el valor no cambia;
   **And** sí puede cambiar el del rol Cajero.
5. **Given** un cambio de 10 % a 5 % en el rol Cajero, **When** un cajero con sesión ya
   iniciada registra su siguiente venta, **Then** se aplica el nuevo límite de 5 %.
6. **Given** un cambio del porcentaje de un rol, **When** se consulta la auditoría, **Then**
   aparece un registro con el rol, el valor anterior, el valor nuevo y quién lo cambió.

---

### User Story 3 - Registrar en auditoría cada intento rechazado (Priority: P2)

Cada vez que el sistema rechaza una venta por exceder el descuento permitido, deja constancia
en la auditoría de quién lo intentó, cuándo, el descuento solicitado (monto y porcentaje), el
límite de su rol y el subtotal de la venta, para que el administrador detecte intentos
repetidos o patrones de abuso.

**Why this priority**: aporta control y trazabilidad; el bloqueo ya protege el negocio aunque
el registro se entregue después.

**Independent Test**: provocar un rechazo y luego consultar la auditoría filtrando por el
módulo de ventas; debe aparecer el intento con todos sus datos.

**Acceptance Scenarios**:

1. **Given** un intento rechazado de 15 % con límite de 10 %, **When** el administrador consulta
   la auditoría, **Then** ve un registro con el usuario, la fecha y hora, el descuento
   solicitado ($15.000 y 15 %), el límite del rol (10 %) y el subtotal ($100.000).
2. **Given** una venta rechazada por descuento, **When** se consulta la auditoría, **Then** el
   registro del intento existe aunque la venta no se haya guardado.
3. **Given** una venta rechazada por otro motivo (stock insuficiente, cliente inválido, etc.),
   **When** se consulta la auditoría, **Then** no aparece como intento de descuento excesivo.

---

### Edge Cases

- **Rol Administrador**: no tiene límite de descuento (equivale a 100 %), en coherencia con que
  el Administrador siempre tiene todos los permisos; su valor no se puede reducir.
- **Rol con máximo 0 %**: cualquier descuento mayor que cero se rechaza; las ventas sin
  descuento funcionan normalmente.
- **Valor inicial de los roles existentes y de los roles nuevos**: al activar la funcionalidad,
  todos los roles existentes distintos del Administrador quedan con un máximo de 10 %, y cada
  rol nuevo se crea con 10 % salvo que el administrador indique otro valor al crearlo. Un usuario
  que hoy da descuentos mayores al 10 % será rechazado hasta que el administrador suba el límite
  de su rol.
- **Porcentajes con decimales**: el máximo admite hasta dos decimales (por ejemplo, 7,5 %). La
  comparación usa el porcentaje exacto del descuento sobre el subtotal, sin redondear a favor
  del usuario: 10,01 % con límite de 10 % se rechaza.
- **Descuento igual al límite**: se acepta (el límite es inclusivo).
- **Cambio de rol de un usuario**: a partir de su siguiente venta aplica el máximo del nuevo rol.
- **Cambio del máximo mientras un usuario tiene una venta en curso**: se aplica el valor vigente
  en el momento en que confirma la venta.
- **Reglas actuales de descuento**: se mantienen (no negativo y no mayor que el subtotal); un
  descuento rechazado por esas reglas no se registra como intento de descuento excesivo.
- **Intentos repetidos**: cada intento rechazado genera su propio registro de auditoría.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Cada rol MUST tener un porcentaje máximo de descuento, con valores de 0 a 100 y
  hasta dos decimales.
- **FR-002**: Solo los usuarios con el permiso "Gestionar roles y permisos" MUST poder consultar
  y modificar el porcentaje máximo en la gestión de roles; la verificación MUST hacerse en el servidor en cada solicitud.
- **FR-002a**: Un usuario MUST NOT poder modificar el porcentaje máximo de su propio rol, salvo
  que sea Administrador; el intento MUST rechazarse con un mensaje claro y sin cambiar el valor.
- **FR-003**: El sistema MUST rechazar valores fuera del rango 0–100 o no numéricos, mostrando
  un mensaje claro y conservando el valor anterior.
- **FR-004**: El listado de roles MUST mostrar el porcentaje máximo vigente de cada rol.
- **FR-005**: El rol Administrador MUST tener siempre descuento ilimitado (100 %) y su valor
  MUST NOT poder modificarse.
- **FR-006**: Al confirmar una venta, el sistema MUST calcular el porcentaje del descuento sobre
  el subtotal de la venta y compararlo con el máximo vigente del rol del usuario que la registra.
- **FR-007**: Si el porcentaje del descuento supera el máximo del rol, el sistema MUST NOT
  registrar la venta y MUST NOT afectar el stock, la caja ni el carrito.
- **FR-008**: Al rechazar la venta, el sistema MUST mostrar un mensaje que indique el porcentaje
  aplicado y el máximo permitido para el rol del usuario.
- **FR-009**: Un descuento igual o menor que el máximo del rol MUST aceptarse.
- **FR-010**: La pantalla de registro de venta MUST informar al usuario el descuento máximo
  permitido para su rol antes de confirmar la venta.
- **FR-011**: Cada intento rechazado por exceder el máximo MUST registrarse en la auditoría con:
  usuario, rol, fecha y hora, subtotal, descuento solicitado (monto y porcentaje) y máximo
  permitido; el registro MUST conservarse aunque la venta no se guarde.
- **FR-012**: Cada cambio del porcentaje máximo de un rol MUST registrarse en la auditoría con
  el rol, el valor anterior, el valor nuevo y el usuario que lo cambió.
- **FR-013**: Los cambios del porcentaje máximo o del rol de un usuario MUST aplicarse a partir
  de la siguiente venta, sin necesidad de que el usuario cierre e inicie sesión.
- **FR-014**: Al activar la funcionalidad, todos los roles existentes excepto el Administrador
  MUST quedar con un máximo de 10 %; los roles nuevos MUST crearse con 10 % por defecto, y el
  administrador puede cambiarlo al crearlos o después.
- **FR-015**: Las reglas actuales de descuento (no negativo, no mayor que el subtotal) MUST
  mantenerse sin cambios.

### Key Entities

- **Rol**: grupo de usuarios con permisos comunes. Nuevo dato: porcentaje máximo de descuento
  permitido (0–100 %).
- **Venta**: transacción con subtotal, descuento, impuesto y total; el descuento es un monto
  aplicado sobre el subtotal de la venta completa.
- **Usuario**: persona que registra la venta; pertenece a un único rol, que determina su límite.
- **Registro de auditoría**: constancia de una acción (quién, cuándo, módulo, acción y
  detalle); aquí, intentos rechazados por descuento y cambios del porcentaje máximo.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100 % de las ventas con un descuento superior al máximo del rol del usuario se
  rechazan; ninguna queda registrada.
- **SC-002**: El 100 % de las ventas con descuento igual o menor que el máximo se registran sin
  pasos adicionales respecto al flujo actual.
- **SC-003**: El 100 % de los intentos rechazados y de los cambios de porcentaje aparecen en la
  auditoría con todos los datos indicados.
- **SC-004**: Un administrador puede consultar y cambiar el porcentaje máximo de un rol en menos
  de 1 minuto.
- **SC-005**: El 90 % de los usuarios que reciben el rechazo entienden cuál es su límite y
  corrigen la venta en el primer intento, sin pedir ayuda.
- **SC-006**: Un cambio de porcentaje se aplica desde la siguiente venta de los usuarios
  afectados, sin que deban volver a iniciar sesión.

## Assumptions

- El descuento sigue siendo un monto único sobre el subtotal de la venta completa, como hoy; los
  descuentos por producto quedan fuera del alcance.
- El porcentaje se calcula como descuento ÷ subtotal × 100, antes de impuestos.
- La configuración se hace en la gestión de roles que ya existe y usa el permiso existente
  "Gestionar roles y permisos"; no se crea un permiso nuevo.
- No se incluye un flujo de aprobación o autorización de un supervisor para superar el límite;
  el usuario debe reducir el descuento o pedir a alguien con un límite mayor que registre la
  venta.
- La regla aplica a las ventas nuevas; las ventas ya registradas, las devoluciones y las
  anulaciones no se revisan ni se modifican.
- Depende de la gestión de roles, del registro de ventas y del módulo de auditoría existentes.
