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
2. **Supervisor o Administrador**: entra al dashboard. El panel aparece después de los paneles
   existentes y lista los tres en el orden agotado → 10 % → 80 %, sin el que está por encima de
   su mínimo. El agotado lleva el texto "Agotado", y cada nombre enlaza a su edición. Solo se ven
   nombre, stock y mínimo con su unidad, sin precios ni códigos.
3. **Cajero o Vendedor**: entra al dashboard. Ve el mismo panel, pero los nombres no son enlaces.
4. **Sin alertas**: sube el stock de los tres por encima de su mínimo y recarga. El panel muestra
   "No hay productos con stock bajo."
5. **Rol sin permiso de productos**: con un rol que tenga el dashboard pero no "Ver productos",
   el panel no aparece.
6. **Borde de 10**: con exactamente 10 productos en alerta, el panel los muestra todos y no hay
   enlace "Ver todos". Con 11 o más, muestra 10 y "Ver todos (N) →", que lleva a la vista
   "Stock bajo".
7. **Rol sin ventas**: con un rol que tenga el dashboard y "Ver productos" pero no "Ver ventas",
   el panel aparece aunque no se vean los paneles de ventas.
8. **Nombre largo**: con un producto en alerta de nombre muy largo, el nombre se ve completo,
   pasa a la línea siguiente y la página no se desplaza horizontalmente, también en el móvil.
9. **Cambio de permisos sin cerrar sesión**: con un cajero que tiene la sesión abierta y ve el
   panel, quítale "Ver productos" a su rol desde Roles y permisos y recarga el dashboard del
   cajero: el panel desaparece sin volver a iniciar sesión.

El fallo al obtener los datos ("Sin datos disponibles") no se prueba a mano; lo cubren las
pruebas automáticas.

## Resultado esperado

Suite en verde y los nueve pasos con el resultado indicado (SC-001 a SC-004 de la
[especificación](spec.md)).
