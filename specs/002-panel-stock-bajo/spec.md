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
  un enlace a la vista completa de stock bajo, indicando cuántos hay en total.
- **Cantidades con decimales** (productos por peso o volumen): se muestran con el mismo formato
  de cantidades que usa el resto del sistema, junto a su unidad de medida.
- **Nombres con caracteres especiales**: se muestran tal cual, sin alterar el diseño del panel.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El dashboard MUST mostrar un panel "Stock bajo" solo a los usuarios con permiso
  para ver productos; la verificación MUST hacerse en el servidor.
- **FR-002**: El panel MUST incluir únicamente productos activos cuyo stock actual sea menor o
  igual que su stock mínimo.
- **FR-003**: Por cada producto, el panel MUST mostrar el nombre, el stock actual y el stock
  mínimo, con su unidad de medida.
- **FR-004**: Los productos MUST ordenarse del más crítico al menos crítico: primero los
  agotados (stock 0 o menos), del stock más bajo al más alto; después el resto, por la
  proporción stock ÷ mínimo de menor a mayor (1 de 10 = 10 % va antes que 40 de 50 = 80 %).
  Ante un empate, MUST ordenarse por nombre.
- **FR-005**: Si no hay productos en alerta, el panel MUST mostrarse con el mensaje "No hay
  productos con stock bajo."
- **FR-006**: El panel MUST mostrar como máximo 10 productos; si hay más, MUST indicar el total
  y enlazar a la vista completa de stock bajo.
- **FR-007**: Cada producto MUST enlazar a su edición solo para los usuarios que pueden editar
  productos; para los demás MUST mostrarse el nombre sin enlace.
- **FR-008**: El panel MUST reflejar el stock vigente cada vez que se carga el dashboard.
- **FR-009**: Mostrar el panel MUST NOT modificar datos ni requerir permisos nuevos.

### Key Entities

- **Producto**: artículo del inventario. Datos relevantes: nombre, stock actual, stock mínimo,
  unidad de medida y estado (activo o inactivo).
- **Alerta de stock bajo**: condición de un producto activo cuyo stock actual es menor o igual
  que su stock mínimo; no es un dato guardado, se calcula al mostrar el panel.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario con permiso para ver productos identifica qué reponer en menos de
  10 segundos desde que entra al dashboard, sin abrir otra pantalla.
- **SC-002**: El 100 % de los productos activos en o por debajo de su mínimo, hasta 10, aparecen
  en el panel, y ninguno por encima de su mínimo o inactivo aparece.
- **SC-003**: El orden del panel coincide en el 100 % de los casos con la regla de criticidad de
  FR-004.
- **SC-004**: Ningún usuario sin permiso para ver productos ve datos de stock en el dashboard.
- **SC-005**: El dashboard sigue cargando en menos de 2 segundos con 5.000 productos activos.

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
