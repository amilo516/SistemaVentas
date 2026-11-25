# Feature Specification: Panel de productos con stock bajo en el dashboard

**Feature Branch**: `recuperacion/gutierrez` (carpeta de la funcionalidad: `specs/002-panel-stock-bajo`)

**Created**: 2026-11-24

**Status**: Draft

**Input**: User description: "Quiero que, al entrar al dashboard, un usuario con permiso para ver productos vea un panel con los productos activos cuyo stock esté en o por debajo de su stock mínimo, ordenados del más crítico al menos crítico, con nombre, stock actual y stock mínimo. Lo necesita para saber qué reponer sin entrar a la lista de productos. Si no hay productos en alerta, el panel debe indicarlo. Cada producto debe enlazar a su edición."

## Clarifications

### Session 2026-11-24

- Q: ¿Qué ven en el panel los usuarios que pueden ver productos pero no editarlos? → A: el
  nombre sin enlace; el enlace a la edición solo aparece para quien puede editar productos.
- Q: ¿Qué define "más crítico"? → A: primero los agotados (stock 0 o menos) y luego la
  proporción stock ÷ mínimo, de menor a mayor.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver en el dashboard qué productos reponer (Priority: P1)

Un usuario con permiso para ver productos entra al dashboard y, sin navegar a otra pantalla, ve
un panel "Stock bajo" con los productos activos cuyo stock está en o por debajo de su stock
mínimo. Los más urgentes aparecen primero, y de cada uno ve el nombre, el stock actual y el
stock mínimo, para decidir qué reponer.

**Why this priority**: es el valor central de la funcionalidad: saber qué reponer sin entrar a
la lista de productos.

**Independent Test**: con tres productos activos en alerta y uno por encima de su mínimo, entrar
al dashboard como un usuario con permiso para ver productos y comprobar que el panel lista solo
los tres en alerta, en orden de urgencia, con sus datos.

**Acceptance Scenarios**:

1. **Given** un producto activo con stock 2 y mínimo 5, **When** un usuario con permiso para ver
   productos entra al dashboard, **Then** el panel lo muestra con su nombre, "2" como stock actual
   y "5" como stock mínimo.
2. **Given** un producto activo con stock exactamente igual a su mínimo (5 y 5), **When** se
   entra al dashboard, **Then** aparece en el panel (el límite es inclusivo).
3. **Given** un producto activo con stock 6 y mínimo 5, **When** se entra al dashboard, **Then**
   no aparece en el panel.
4. **Given** un producto inactivo con stock 0 y mínimo 5, **When** se entra al dashboard,
   **Then** no aparece en el panel.
5. **Given** varios productos en alerta, **When** se entra al dashboard, **Then** aparecen
   ordenados del más crítico al menos crítico según la regla de FR-004.
6. **Given** un usuario sin permiso para ver productos, **When** entra al dashboard, **Then** no
   ve el panel ni ningún dato de stock.
7. **Given** un producto activo con stock 0 y mínimo 5, **When** se entra al dashboard, **Then**
   aparece con el texto "Agotado" además del estilo que distingue a los agotados.
8. **Given** un usuario con permiso para ver productos pero no ventas (sin los paneles de
   ventas), **When** entra al dashboard, **Then** ve el panel "Stock bajo" igualmente.

---

### User Story 2 - Saber que no hay nada que reponer (Priority: P2)

Si ningún producto activo está en o por debajo de su mínimo, el panel se muestra igualmente con
un mensaje que lo indica, para que el usuario sepa que el inventario está al día y no piense
que el panel falló.

**Why this priority**: evita confusiones; la información principal (la lista) ya aporta valor
sin este estado.

**Independent Test**: con todos los productos activos por encima de su mínimo, entrar al
dashboard y comprobar que el panel muestra el mensaje de "sin alertas".

**Acceptance Scenarios**:

1. **Given** ningún producto activo en alerta, **When** un usuario con permiso para ver
   productos entra al dashboard, **Then** el panel muestra "No hay productos con stock bajo."
2. **Given** el panel en estado "sin alertas", **When** un producto baja a su mínimo y el usuario
   vuelve a entrar al dashboard, **Then** el panel lo muestra.

---

### User Story 3 - Ir a la edición del producto desde el panel (Priority: P3)

Desde cada producto del panel, el usuario puede ir directamente a su edición, por ejemplo para
revisar o ajustar el stock mínimo.

**Why this priority**: es un atajo de navegación; el panel ya cumple su propósito sin él.

**Independent Test**: desde el panel, seguir el enlace de un producto y comprobar que se abre la
edición de ese producto.

**Acceptance Scenarios**:

1. **Given** un usuario que puede editar productos, **When** sigue el enlace de un producto del
   panel, **Then** se abre la edición de ese producto.
2. **Given** un usuario que puede ver productos pero no editarlos (por ejemplo, un cajero),
   **When** ve el panel, **Then** cada producto muestra su nombre sin enlace, y no hay en el
   panel ningún enlace a la edición de productos.

---

### Edge Cases

- **Stock igual al mínimo**: el producto está en alerta (FR-002, límite inclusivo).
- **Stock mínimo 0**: el producto solo entra en alerta si su stock es 0 o menor, igual que la
  regla de stock bajo que ya usa el sistema en la lista de productos.
- **Stock negativo**: si existiera, cuenta como agotado y va antes que un stock 0 (FR-004).
- **Agotado con mínimo 0**: no tiene proporción stock ÷ mínimo, pero entra en el grupo de
  agotados, así que se ordena sin dividir por cero.
- **Muchos productos en alerta**: el panel muestra como máximo 10 (los más críticos) y ofrece
  un enlace a la vista completa de stock bajo, indicando cuántos hay en total. Con exactamente
  10 no hay enlace; con 11 o más, sí (FR-006).
- **Orden en la vista completa**: la vista completa de stock bajo conserva su orden actual
  (alfabético), distinto del orden por criticidad del panel. Es intencional: el panel prioriza
  qué reponer primero y la vista completa sirve para buscar un producto concreto.
- **Productos de una categoría inactiva**: si el producto está activo, entra en el panel aunque
  su categoría esté inactiva, para que el total coincida con la vista completa de stock bajo.
- **Stock mínimo negativo**: no puede ocurrir; la edición de productos ya lo rechaza.
- **Cantidades con decimales** (productos por peso o volumen): se muestran con el mismo formato
  de cantidades que usa el resto del sistema, junto a su unidad de medida.
- **Nombres largos o con caracteres especiales**: se muestran completos y tal cual (sin
  interpretarse como formato); si no caben, pasan a la línea siguiente y el panel nunca obliga a
  desplazarse horizontalmente.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El dashboard MUST mostrar un panel "Stock bajo" solo a los usuarios con permiso
  para ver productos; la verificación MUST hacerse en el servidor. El panel forma parte del
  dashboard, así que solo lo ven quienes además pueden entrar al dashboard. MUST aparecer después
  de los paneles existentes y MUST mostrarse aunque el usuario no vea los paneles de ventas.
- **FR-002**: El panel MUST incluir únicamente productos activos cuyo stock actual sea menor o
  igual que su stock mínimo.
- **FR-003**: Por cada producto, el panel MUST mostrar el nombre, el stock actual y el stock
  mínimo, con su unidad de medida, y MUST NOT mostrar otros datos del producto (por ejemplo,
  precios o códigos). Los agotados (stock 0 o menos, también los negativos) MUST llevar el texto
  "Agotado" además de un estilo visual que los distinga, para que no dependa solo del color.
- **FR-004**: Los productos MUST ordenarse del más crítico al menos crítico: primero los
  agotados (stock 0 o menos), del stock más bajo al más alto; después el resto, por la
  proporción stock ÷ mínimo de menor a mayor (1 de 10 = 10 % va antes que 40 de 50 = 80 %;
  con decimales igual: 0,5 de 2 = 25 % va antes que 1 de 2 = 50 %). La proporción no tiene
  unidades, así que compara productos medidos en unidades distintas. Entre agotados solo cuenta
  el stock, no el mínimo: 0 de 100 y 0 de 1 empatan, de forma intencional, porque ambos ya no
  se pueden vender. Ante un empate, MUST ordenarse por nombre sin distinguir mayúsculas (las
  tildes y los números se comparan como texto) y, si el nombre también coincide, por el orden
  en que se dieron de alta los productos, para que el orden sea el mismo en cada carga.
- **FR-005**: Si no hay productos en alerta, el panel MUST mostrarse con el mensaje "No hay
  productos con stock bajo."
- **FR-006**: El panel MUST mostrar como máximo 10 productos; si hay más, MUST mostrar en su
  cabecera el enlace "Ver todos (N) →", con N el total de productos en alerta, hacia la vista
  completa de stock bajo, que exige el mismo permiso que el panel.
- **FR-007**: Cada producto MUST enlazar a su edición solo para los usuarios que pueden editar
  productos (incluido el Administrador, que tiene todos los permisos); para los demás MUST
  mostrarse el nombre sin enlace.
- **FR-008**: El panel MUST reflejar el stock vigente cada vez que se carga el dashboard. Un
  cambio en los permisos de un usuario con la sesión abierta MUST aplicarse desde su siguiente
  carga del dashboard, sin volver a iniciar sesión.
- **FR-009**: Mostrar el panel MUST NOT modificar datos ni requerir permisos nuevos.
- **FR-010**: Si no se pueden obtener los datos de stock, el panel MUST mostrar "Sin datos
  disponibles" y el resto del dashboard MUST seguir mostrándose.

### Key Entities

- **Producto**: artículo del inventario. Datos relevantes: nombre, stock actual, stock mínimo,
  unidad de medida y estado (activo o inactivo).
- **Alerta de stock bajo**: condición de un producto activo cuyo stock actual es menor o igual
  que su stock mínimo; no es un dato guardado, se calcula al mostrar el panel.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Al cargar el dashboard, los productos en alerta (hasta 10) se ven en el panel sin
  abrir otra pantalla ni usar filtros, y quien puede editar productos llega a la edición de
  cualquiera de ellos con un solo clic.
- **SC-002**: El 100 % de los productos activos en o por debajo de su mínimo, hasta 10, aparecen
  en el panel, y ninguno por encima de su mínimo o inactivo aparece.
- **SC-003**: El orden del panel coincide en el 100 % de los casos con la regla de criticidad de
  FR-004.
- **SC-004**: Ningún usuario sin permiso para ver productos ve datos de stock en el dashboard.
- **SC-005**: Con 5.000 productos activos, 1.000 de ellos en alerta, el servidor genera el
  dashboard completo (todos los paneles) en menos de 2 segundos, medido en el entorno XAMPP de
  desarrollo.

## Assumptions

- "Permiso para ver productos" es el permiso existente de consulta de productos; "editar" es el
  permiso existente de edición de productos. No se crean permisos nuevos.
- La regla de alerta es la misma que ya usa el sistema para el filtro de stock bajo de la lista
  de productos, para que el panel y esa vista muestren lo mismo.
- La "vista completa de stock bajo" es la pantalla de stock bajo que ya existe.
- Consultar el panel no es una acción relevante para la auditoría (no modifica datos), así que no
  se registra.
- El panel se calcula al cargar el dashboard; no se actualiza solo mientras la pantalla está
  abierta.
- No se envían avisos por correo ni notificaciones; el panel es la única forma de alerta en esta
  funcionalidad.
- Depende del dashboard, de la gestión de productos y de la vista de stock bajo existentes.
