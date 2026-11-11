SISTEMA DE VENTAS - PHP + XAMPP

1. Copia esta carpeta dentro de C:\xampp\htdocs\
2. Importa database.sql en phpMyAdmin.
   (Opcional) Importa datos_prueba.sql para tener categorías y productos de ejemplo.
3. Revisa config/database.php si tu MySQL usa otra contraseña.
4. Abre http://localhost/sistema_ventas/public/
   (si la carpeta tiene otro nombre, por ejemplo Sistema-Ventas al clonar con git,
   usa ese nombre: http://localhost/Sistema-Ventas/public/)
5. Usuario inicial: admin
6. Contraseña inicial: Admin123*

EJECUTAR DESDE LA TERMINAL (VISUAL STUDIO CODE)
- Con MySQL encendido en XAMPP, en la carpeta del proyecto:
    C:\xampp\php\php.exe -S localhost:8000
- Abre http://localhost:8000/ (Ctrl+C en la terminal para detenerlo).

MÓDULOS
- Dashboard: ventas, cajas y devoluciones del día.
- Ventas: punto de venta, listado, detalle, anulación y factura (ticket).
- Caja: apertura, ingresos/egresos y cierre con cuadre.
- Devoluciones: parciales o totales, con reembolso y reintegro al inventario.
- Productos y categorías, Inventario (kardex, entradas, salidas y ajustes).
- Clientes, Reportes (con exportación a Excel/CSV), Usuarios, Roles y permisos,
  Configuración (datos de la empresa, factura, métodos de pago) y Auditoría.

- Asesor IA: agente que analiza ventas, inventario, márgenes y devoluciones y
  recomienda qué reabastecer, qué promocionar y qué precios revisar.

ASESOR IA
- Sin configurar nada funciona en "modo local" (reglas de negocio, sin internet).
- Para el "modo IA" (modelo de lenguaje, con preguntas libres):
  1. Instala Composer (https://getcomposer.org) y en la carpeta del proyecto ejecuta:
       composer install
  2. Copia config/ia.local.ejemplo.php como config/ia.local.php y pega tu clave
     del servicio de IA (este archivo no se sube a git).
- Si tu base de datos es anterior a este módulo, importa actualizacion_asesor_ia.sql.
- Lo ven el Administrador y el Supervisor (permiso asesor.ver).

Cada rol ve solo los módulos de sus permisos (Roles y permisos). Los cambios de
permisos se aplican de inmediato, sin volver a iniciar sesión.
