-- ============================================================
-- DATOS DE PRUEBA (opcional): categorías y productos para probar
-- el módulo de ventas. Importar en phpMyAdmin DESPUÉS de database.sql.
-- ============================================================
USE sistema_ventas;
SET NAMES utf8mb4;

INSERT INTO categorias (nombre, descripcion) VALUES
('Bebidas', 'Gaseosas, jugos y agua'),
('Snacks', 'Paquetes y dulces'),
('Aseo', 'Productos de aseo personal y del hogar'),
('Granos', 'Productos a granel');

INSERT INTO productos (id_categoria, codigo, codigo_barras, nombre, precio_compra, precio_venta, stock, stock_minimo, unidad_medida) VALUES
((SELECT id_categoria FROM categorias WHERE nombre='Bebidas'), 'BEB-001', '7702004003508', 'Gaseosa Cola 400 ml', 1800, 2800, 48, 12, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Bebidas'), 'BEB-002', '7702090018103', 'Agua sin gas 600 ml', 900, 1800, 60, 12, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Bebidas'), 'BEB-003', '7702354947402', 'Jugo de naranja 1 L', 3500, 5200, 20, 5, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Snacks'), 'SNK-001', '7702189010285', 'Papas fritas 110 g', 2600, 4000, 35, 10, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Snacks'), 'SNK-002', '7702007046321', 'Chocolatina 30 g', 1200, 2000, 80, 20, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Aseo'), 'ASE-001', '7702191163504', 'Jabón de baño x3', 5400, 8500, 15, 5, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Aseo'), 'ASE-002', '7702006204014', 'Detergente en polvo 1 kg', 7800, 11900, 12, 4, 'unidad'),
((SELECT id_categoria FROM categorias WHERE nombre='Granos'), 'GRA-001', NULL, 'Arroz a granel', 3200, 4600, 50, 10, 'kg'),
((SELECT id_categoria FROM categorias WHERE nombre='Granos'), 'GRA-002', NULL, 'Fríjol rojo a granel', 7000, 9800, 25, 5, 'kg');

-- Movimiento de inventario inicial para que el kardex de cada producto empiece completo.
INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo, cantidad, stock_anterior, stock_nuevo, motivo, referencia)
SELECT p.id_producto, (SELECT id_usuario FROM usuarios WHERE usuario='admin'), 'entrada', p.stock, 0, p.stock, 'Inventario inicial', p.codigo
FROM productos p
WHERE p.codigo IN ('BEB-001','BEB-002','BEB-003','SNK-001','SNK-002','ASE-001','ASE-002','GRA-001','GRA-002')
  AND NOT EXISTS (SELECT 1 FROM movimientos_inventario m WHERE m.id_producto = p.id_producto);
