<!--
Sync Impact Report
- Version change: (plantilla sin ratificar) → 1.0.0
- Modified principles: n/a (adopción inicial)
  - [PRINCIPLE_1_NAME] → I. Respeto a la Arquitectura MVC Existente
  - [PRINCIPLE_2_NAME] → II. Acceso a Datos con Sentencias Preparadas (NO NEGOCIABLE)
  - [PRINCIPLE_3_NAME] → III. Autorización por Rol vía helpers/permisos.php
  - [PRINCIPLE_4_NAME] → IV. Trazabilidad mediante Auditoría
  - [PRINCIPLE_5_NAME] → V. Pruebas con PHPUnit antes de Terminar (NO NEGOCIABLE)
- Added sections: Restricciones Técnicas; Flujo de Desarrollo y Criterios de Calidad; Governance
- Removed sections: ninguna
- Templates (leen la constitución en tiempo de ejecución; no se modifican aquí):
  - .specify/templates/plan-template.md ⚠ revisar que "Constitution Check" cubra los 5 principios
  - .specify/templates/tasks-template.md ⚠ las tareas de prueba son obligatorias (Principio V)
- Follow-up TODOs:
  - PHPUnit aún no figura en composer.json ni existe phpunit.xml / tests/; debe incorporarse
    antes de cerrar la primera funcionalidad nueva (ver Principio V).
-->
# Sistema de Ventas Constitution

## Core Principles

### I. Respeto a la Arquitectura MVC Existente

- Toda funcionalidad nueva MUST ubicarse en la capa que le corresponde:
  `controllers/` (orquestación y reglas de flujo), `models/` (acceso a datos y reglas de
  persistencia), `views/<modulo>/` (presentación), `helpers/` (funciones transversales
  reutilizables), `config/` (configuración) y `public/` (puntos de entrada).
- Las vistas MUST NOT ejecutar consultas SQL ni contener lógica de negocio; reciben datos
  ya preparados por el controlador.
- Los controladores MUST NOT construir SQL directamente; delegan en los modelos.
- Se MUST reutilizar los helpers, modelos y layouts existentes (`views/layouts/header.php`,
  `helpers/respuesta.php`, `helpers/funciones.php`, etc.) antes de crear piezas nuevas.
- Se MUST seguir las convenciones vigentes: `declare(strict_types=1)`, clases de controlador
  `XxxController`, modelos en singular con nombre de entidad, y conexión mediante la clase
  `Database` de `config/database.php`.
- Introducir frameworks, nuevas capas o reestructurar directorios MUST justificarse por
  escrito en el plan de la funcionalidad.

**Razón**: una estructura predecible reduce el costo de mantenimiento y permite que cualquier
integrante localice y modifique el código sin romper otras partes.

### II. Acceso a Datos con Sentencias Preparadas (NO NEGOCIABLE)

- Toda consulta que involucre datos variables MUST usar sentencias preparadas de PDO con
  parámetros enlazados (`prepare()` + `execute([...])`).
- Está prohibido concatenar o interpolar entradas del usuario (`$_GET`, `$_POST`,
  `$_SESSION`, cookies, archivos, respuestas de APIs) dentro de cadenas SQL.
- Identificadores dinámicos (nombres de columna, orden `ASC/DESC`) MUST validarse contra una
  lista blanca fija antes de incluirse en la consulta.
- Las operaciones que modifican varias tablas relacionadas (ventas, pagos, inventario, caja,
  devoluciones) MUST ejecutarse dentro de una transacción con `rollBack()` ante error.

**Razón**: el sistema maneja dinero, inventario y datos de clientes; la inyección SQL y las
escrituras parciales son riesgos inaceptables.

### III. Autorización por Rol vía helpers/permisos.php

- Toda página, acción o endpoint que exponga o modifique datos MUST validar acceso con
  `requirePermission()` o `tienePermiso()` de `helpers/permisos.php`; no se permiten
  comprobaciones de rol ad hoc (p. ej. comparar `$_SESSION['rol']` a mano).
- Los permisos nuevos MUST registrarse en las tablas `permisos` / `rol_permisos` y
  documentarse en el plan de la funcionalidad.
- La validación MUST hacerse en el servidor; ocultar botones o menús (`helpers/menu.php`) es
  complementario, nunca sustituto.
- Cualquier cambio en roles, permisos o estado/rol de un usuario MUST invocar
  `marcarCambioPermisos()` para que las sesiones activas se resincronicen.

**Razón**: un único punto de control de autorización evita accesos indebidos y mantiene la
coherencia entre lo que el rol permite y lo que el sistema ejecuta.

### IV. Trazabilidad mediante Auditoría

- Toda acción relevante MUST registrarse con `Auditoria::registrar()` (`models/Auditoria.php`)
  indicando módulo, acción, tabla, id del registro y un detalle legible.
- Se consideran relevantes, como mínimo: creación, edición, anulación o eliminación de
  registros; ventas, pagos, devoluciones y facturación; movimientos de inventario y de caja;
  cambios de usuarios, roles, permisos y configuración; consultas al asesor IA; e intentos de
  acceso o inicio de sesión fallidos cuando apliquen.
- El registro de auditoría MUST ocurrir solo tras el éxito de la operación (dentro de la
  misma transacción cuando exista) y MUST NOT almacenar contraseñas, tokens ni claves de API.

**Razón**: la auditoría permite reconstruir quién hizo qué y cuándo, requisito clave para
control interno, resolución de incidencias y responsabilidad sobre el dinero y el stock.

### V. Pruebas con PHPUnit antes de Terminar (NO NEGOCIABLE)

- Ninguna funcionalidad nueva o corrección de error se considera terminada sin pruebas
  automatizadas con PHPUnit que pasen en verde.
- Las pruebas MUST cubrir, como mínimo: la lógica de modelos y helpers involucrados, los
  casos de autorización (permitido y denegado) y el registro en auditoría de las acciones
  relevantes.
- Las correcciones de errores MUST incluir una prueba que reproduzca el fallo.
- Las pruebas MUST ejecutarse contra una base de datos de pruebas aislada, nunca contra
  datos de producción.
- La suite completa (`vendor/bin/phpunit`) MUST pasar antes de integrar cambios a `main`.

**Razón**: las pruebas son la evidencia objetiva de que la funcionalidad cumple su
especificación y de que no se rompió lo existente.

## Restricciones Técnicas

- Plataforma: PHP 8.1 o superior y MySQL/MariaDB, desplegado sobre Apache (XAMPP en
  desarrollo). Dependencias gestionadas exclusivamente con Composer.
- Conexión a base de datos únicamente a través de PDO usando `config/database.php`.
- Toda salida de datos en vistas MUST escaparse (`htmlspecialchars` o el helper equivalente)
  para prevenir XSS; los formularios que modifican datos MUST validarse en el servidor.
- Credenciales y claves de API (p. ej. del asesor IA) MUST residir en archivos de
  configuración locales excluidos del control de versiones, nunca en el código.
- Los cambios de esquema de base de datos MUST entregarse como scripts SQL versionados junto
  con la funcionalidad que los requiere.

## Flujo de Desarrollo y Criterios de Calidad

- Cada funcionalidad sigue el flujo Spec Kit: especificación → plan → tareas →
  implementación, y el plan MUST incluir una verificación explícita de esta constitución.
- Cada funcionalidad se desarrolla en su propia rama y se integra mediante pull request.
- Criterios para dar una tarea por terminada:
  1. Respeta la capa MVC correspondiente (Principio I).
  2. Todas las consultas nuevas usan sentencias preparadas (Principio II).
  3. Los accesos están protegidos por `helpers/permisos.php` (Principio III).
  4. Las acciones relevantes quedan auditadas (Principio IV).
  5. Existen pruebas PHPUnit y la suite completa pasa (Principio V).
- La revisión de código MUST rechazar cambios que incumplan cualquiera de estos criterios.

## Governance

- Esta constitución prevalece sobre cualquier otra práctica o guía del proyecto. Ante un
  conflicto, se aplica la constitución.
- Enmiendas: se proponen mediante pull request que modifique
  `.specify/memory/constitution.md`, con la justificación del cambio y, si afecta código
  existente, un plan de migración. Requieren aprobación del responsable del proyecto.
- Versionado semántico de la constitución:
  - MAJOR: eliminación o redefinición incompatible de un principio o regla de gobierno.
  - MINOR: nuevo principio o sección, o ampliación material de una guía.
  - PATCH: aclaraciones, redacción o correcciones sin cambio semántico.
- Cumplimiento: todo plan (`/speckit-plan`) MUST pasar la verificación de constitución y
  toda revisión de pull request MUST confirmarlo. Cualquier excepción MUST documentarse en el
  plan con su justificación y la alternativa más simple descartada.

**Version**: 1.0.0 | **Ratified**: 2026-11-24 | **Last Amended**: 2026-11-24
