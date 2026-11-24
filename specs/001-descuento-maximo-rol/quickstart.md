# Quickstart: validar el descuento máximo por rol

## Prerrequisitos

- XAMPP con Apache y MySQL/MariaDB encendidos; PHP 8.1+ y Composer en el PATH.
- Base `sistema_ventas` importada (`database.sql`) y usuarios de prueba (`datos_prueba.sql`).

## 1. Instalar dependencias de desarrollo

```bash
composer install
```

Debe aparecer `vendor/bin/phpunit`.

## 2. Aplicar el script de actualización

En phpMyAdmin → Importar → `actualizacion_descuento_maximo.sql` (o
`mysql -u root sistema_ventas < actualizacion_descuento_maximo.sql`).

Verificación: `SELECT id_rol, nombre, descuento_maximo FROM roles;` muestra el Administrador en
100.00 y el resto en 10.00. Ejecutarlo una segunda vez no debe dar error.

> En MySQL (no MariaDB), `ADD COLUMN IF NOT EXISTS` no existe: quítalo si el script falla.

## 3. Ejecutar las pruebas

```bash
vendor/bin/phpunit --testsuite Unit          # regla pura, sin base de datos
vendor/bin/phpunit --testsuite Integration   # usa sistema_ventas_test (se crea sola)
vendor/bin/phpunit                           # suite completa: debe quedar en verde
```

La suite `Integration` nunca debe tocar `sistema_ventas`; verifica que `phpunit.xml` define
`DB_NAME=sistema_ventas_test`.

## 4. Validación manual de punta a punta

Casos detallados en [contracts/formularios.md](contracts/formularios.md).

1. **Configurar**: como Administrador, en Roles → Cajero, guarda un descuento máximo de `5`. El
   listado muestra `5 %`. En Auditoría (módulo roles) aparece `Rol Cajero: descuento máximo
   10 % → 5 %`.
2. **Valor inválido**: guarda `150` → error de rango; el valor sigue en 5 %.
3. **Propio rol**: con un Supervisor que tenga `roles.gestionar`, intenta cambiar el rol
   Supervisor → error "Solo el Administrador…". Cambiar el rol Cajero sí funciona.
4. **Rechazo**: con un cajero que ya tenía la sesión iniciada antes del paso 1 (así se comprueba
   que el nuevo límite aplica sin volver a iniciar sesión), agrega productos por $100.000 y aplica un descuento de $6.000 → mensaje "El descuento (6 %)
   supera el máximo permitido para tu rol (5 %)". El carrito sigue intacto, el stock y la caja no
   cambian, y en Auditoría (módulo ventas, acción `descuento rechazado`) aparece el intento.
5. **Límite inclusivo**: con el mismo carrito, descuento de $5.000 → la venta se registra.
6. **Administrador**: como Administrador, una venta con 90 % de descuento se registra.

## Resultado esperado

Todas las pruebas en verde y los seis casos manuales con el resultado indicado, que
corresponden a SC-001 … SC-006 de la [especificación](spec.md).
