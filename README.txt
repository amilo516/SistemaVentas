SISTEMA DE VENTAS - PHP + XAMPP

1. Copia esta carpeta dentro de C:\xampp\htdocs\
2. Importa database.sql en phpMyAdmin.
   (Opcional) Importa datos_prueba.sql para tener categorías y productos de ejemplo.
3. Revisa config/database.php si tu MySQL usa otra contraseña.
4. Abre http://localhost/sistema_ventas/public/
5. Usuario inicial: admin
6. Contraseña inicial: Admin123*

MÓDULOS
- Dashboard: ventas, cajas y devoluciones del día.
- Ventas: punto de venta, listado, detalle, anulación y factura (ticket).
- Caja: apertura, ingresos/egresos y cierre con cuadre.
- Devoluciones: parciales o totales, con reembolso y reintegro al inventario.
- Productos y categorías, Inventario (kardex, entradas, salidas y ajustes).
- Clientes, Reportes (con exportación a Excel/CSV), Usuarios, Roles y permisos,
  Configuración (datos de la empresa, factura, métodos de pago) y Auditoría.

Cada rol ve solo los módulos de sus permisos (Roles y permisos). Los cambios de
permisos se aplican de inmediato, sin volver a iniciar sesión.
