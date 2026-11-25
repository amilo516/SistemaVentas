# Quickstart: validar el panel de stock bajo

## Prerrequisitos

- XAMPP con Apache y MySQL/MariaDB encendidos; dependencias instaladas (`composer install`).
- Sin script de actualización: la funcionalidad no cambia el esquema.

## 1. Pruebas automáticas

```bash
vendor/bin/phpunit --testsuite Unit          # regla de orden, sin base de datos
vendor/bin/phpunit --testsuite Integration   # controlador y vista real, contra sistema_ventas_test
vendor/bin/phpunit                           # suite completa: debe quedar en verde
```

## 2. Validación manual en el navegador

Contrato de lo que debe verse: [contracts/panel-dashboard.md](contracts/panel-dashboard.md).
Regla de orden: [contracts/orden-criticidad.md](contracts/orden-criticidad.md).

> Estos pasos modifican productos de tu base. Úsalos sobre productos de prueba o restaura los
> valores al terminar.

1. **Datos**: deja tres productos activos en alerta (por ejemplo, stock 0 / mínimo 5, stock 1 /
   mínimo 10 y stock 40 / mínimo 50) y uno por encima de su mínimo.
2. **Supervisor o Administrador**: entra al dashboard. El panel lista los tres en el orden
   agotado → 10 % → 80 %, sin el que está por encima de su mínimo, y cada nombre enlaza a su
   edición.
3. **Cajero o Vendedor**: entra al dashboard. Ve el mismo panel, pero los nombres no son enlaces.
4. **Sin alertas**: sube el stock de los tres por encima de su mínimo y recarga. El panel muestra
   "No hay productos con stock bajo."
5. **Rol sin permiso de productos**: con un rol que tenga el dashboard pero no "Ver productos",
   el panel no aparece.
6. **Más de 10**: con 11 o más productos en alerta, el panel muestra 10 y "Ver todos (N) →", que
   lleva a la vista "Stock bajo".

## Resultado esperado

Suite en verde y los seis pasos con el resultado indicado (SC-001 a SC-004 de la
[especificación](spec.md)).
