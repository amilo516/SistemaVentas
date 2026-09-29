-- ============================================================
-- SISTEMA DE VENTAS
-- Base de datos completa para PHP + XAMPP + MySQL/MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS sistema_ventas
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistema_ventas;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- ROLES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS rol_permisos;
DROP TABLE IF EXISTS permisos;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PERMISOS
-- ------------------------------------------------------------
CREATE TABLE permisos (
    id_permiso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE rol_permisos (
    id_rol INT UNSIGNED NOT NULL,
    id_permiso INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rol_permisos_rol
        FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rol_permisos_permiso
        FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- USUARIOS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT UNSIGNED NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id_rol)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_usuario_estado (estado),
    INDEX idx_usuario_rol (rol_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CLIENTES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS clientes;

CREATE TABLE clientes (
    id_cliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(30) NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    direccion VARCHAR(200) NULL,
    ciudad VARCHAR(100) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cliente_nombre (nombre),
    INDEX idx_cliente_estado (estado)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIAS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS categorias;

CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_categoria_estado (estado)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PRODUCTOS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS productos;

CREATE TABLE productos (
    id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    codigo_barras VARCHAR(100) NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    stock_minimo DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    unidad_medida VARCHAR(20) NOT NULL DEFAULT 'unidad',
    imagen VARCHAR(255) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_producto_categoria
        FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_producto_nombre (nombre),
    INDEX idx_producto_categoria (id_categoria),
    INDEX idx_producto_estado (estado),
    INDEX idx_producto_stock (stock)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- METODOS DE PAGO
-- ------------------------------------------------------------
DROP TABLE IF EXISTS metodos_pago;

CREATE TABLE metodos_pago (
    id_metodo_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CAJAS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS cajas;

CREATE TABLE cajas (
    id_caja INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- APERTURAS / CIERRES DE CAJA
-- ------------------------------------------------------------
DROP TABLE IF EXISTS aperturas_caja;

CREATE TABLE aperturas_caja (
    id_apertura INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_caja INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    fecha_cierre DATETIME NULL,
    monto_final DECIMAL(12,2) NULL,
    monto_esperado DECIMAL(12,2) NULL,
    diferencia DECIMAL(12,2) NULL,
    observaciones TEXT NULL,
    estado ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
    CONSTRAINT fk_apertura_caja
        FOREIGN KEY (id_caja) REFERENCES cajas(id_caja)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_apertura_estado (estado),
    INDEX idx_apertura_fecha (fecha_apertura),
    INDEX idx_apertura_caja (id_caja)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CARRITOS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS carrito_detalle;
DROP TABLE IF EXISTS carritos;

CREATE TABLE carritos (
    id_carrito INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    estado ENUM('activo','convertido','cancelado') NOT NULL DEFAULT 'activo',
    CONSTRAINT fk_carrito_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_carrito_usuario_estado (id_usuario, estado)
) ENGINE=InnoDB;

CREATE TABLE carrito_detalle (
    id_carrito_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_carrito INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_carrito_detalle_carrito
        FOREIGN KEY (id_carrito) REFERENCES carritos(id_carrito)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_carrito_detalle_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uk_carrito_producto (id_carrito, id_producto),
    INDEX idx_carrito_detalle_producto (id_producto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VENTAS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS venta_pagos;
DROP TABLE IF EXISTS detalle_venta;
DROP TABLE IF EXISTS ventas;

CREATE TABLE ventas (
    id_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    id_cliente INT UNSIGNED NULL,
    id_usuario INT UNSIGNED NOT NULL,
    id_apertura INT UNSIGNED NULL,
    fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    impuesto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente','pagada','anulada') NOT NULL DEFAULT 'pagada',
    observaciones TEXT NULL,
    CONSTRAINT fk_venta_cliente
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_venta_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_venta_apertura
        FOREIGN KEY (id_apertura) REFERENCES aperturas_caja(id_apertura)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_venta_fecha (fecha_venta),
    INDEX idx_venta_cliente (id_cliente),
    INDEX idx_venta_usuario (id_usuario),
    INDEX idx_venta_estado (estado),
    INDEX idx_venta_apertura (id_apertura)
) ENGINE=InnoDB;

CREATE TABLE detalle_venta (
    id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_detalle_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_detalle_venta (id_venta),
    INDEX idx_detalle_producto (id_producto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PAGOS DE LAS VENTAS
-- ------------------------------------------------------------
CREATE TABLE venta_pagos (
    id_venta_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_metodo_pago INT UNSIGNED NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    referencia VARCHAR(100) NULL,
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_pago_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_venta_pago_metodo
        FOREIGN KEY (id_metodo_pago) REFERENCES metodos_pago(id_metodo_pago)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_venta_pago_venta (id_venta),
    INDEX idx_venta_pago_metodo (id_metodo_pago),
    INDEX idx_venta_pago_fecha (fecha_pago)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MOVIMIENTOS DE CAJA
-- ------------------------------------------------------------
DROP TABLE IF EXISTS movimientos_caja;

CREATE TABLE movimientos_caja (
    id_movimiento_caja INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_apertura INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    id_venta INT UNSIGNED NULL,
    tipo ENUM('ingreso','egreso','venta','devolucion') NOT NULL,
    concepto VARCHAR(255) NULL,
    monto DECIMAL(12,2) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_caja_apertura
        FOREIGN KEY (id_apertura) REFERENCES aperturas_caja(id_apertura)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_caja_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_caja_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_mov_caja_apertura (id_apertura),
    INDEX idx_mov_caja_tipo (tipo),
    INDEX idx_mov_caja_fecha (fecha),
    INDEX idx_mov_caja_venta (id_venta)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MOVIMIENTOS DE INVENTARIO
-- ------------------------------------------------------------
DROP TABLE IF EXISTS movimientos_inventario;

CREATE TABLE movimientos_inventario (
    id_movimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    id_venta INT UNSIGNED NULL,
    tipo ENUM('entrada','salida','ajuste','devolucion') NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    stock_anterior DECIMAL(12,3) NULL,
    stock_nuevo DECIMAL(12,3) NULL,
    motivo VARCHAR(255) NULL,
    referencia VARCHAR(100) NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_inv_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_inv_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_inv_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_mov_inv_producto (id_producto),
    INDEX idx_mov_inv_tipo (tipo),
    INDEX idx_mov_inv_fecha (fecha_movimiento),
    INDEX idx_mov_inv_venta (id_venta)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- DEVOLUCIONES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS detalle_devolucion;
DROP TABLE IF EXISTS devoluciones;

CREATE TABLE devoluciones (
    id_devolucion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    id_apertura INT UNSIGNED NULL,
    fecha_devolucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(255) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente','procesada','anulada') NOT NULL DEFAULT 'procesada',
    observaciones TEXT NULL,
    CONSTRAINT fk_devolucion_venta
        FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_devolucion_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_devolucion_apertura
        FOREIGN KEY (id_apertura) REFERENCES aperturas_caja(id_apertura)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_devolucion_venta (id_venta),
    INDEX idx_devolucion_fecha (fecha_devolucion),
    INDEX idx_devolucion_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE detalle_devolucion (
    id_detalle_devolucion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_devolucion INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    precio_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_det_devolucion
        FOREIGN KEY (id_devolucion) REFERENCES devoluciones(id_devolucion)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_det_devolucion_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_det_devolucion (id_devolucion),
    INDEX idx_det_devolucion_producto (id_producto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CONFIGURACION
-- ------------------------------------------------------------
DROP TABLE IF EXISTS configuracion;

CREATE TABLE configuracion (
    id_configuracion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- AUDITORIA
-- ------------------------------------------------------------
DROP TABLE IF EXISTS auditoria;

CREATE TABLE auditoria (
    id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NULL,
    modulo VARCHAR(100) NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(100) NULL,
    id_registro BIGINT UNSIGNED NULL,
    descripcion TEXT NULL,
    ip VARCHAR(45) NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_auditoria_usuario (id_usuario),
    INDEX idx_auditoria_modulo (modulo),
    INDEX idx_auditoria_accion (accion),
    INDEX idx_auditoria_fecha (fecha)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- DATOS INICIALES
-- ------------------------------------------------------------

-- Roles
INSERT INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso completo al sistema'),
('Vendedor', 'Realiza ventas y consulta información necesaria'),
('Cajero', 'Gestiona caja, pagos y ventas'),
('Supervisor', 'Supervisa ventas, inventario y reportes');

-- Permisos
INSERT INTO permisos (nombre, descripcion) VALUES
('dashboard.ver', 'Ver panel principal'),
('usuarios.ver', 'Ver usuarios'),
('usuarios.crear', 'Crear usuarios'),
('usuarios.editar', 'Editar usuarios'),
('usuarios.eliminar', 'Eliminar/desactivar usuarios'),
('roles.gestionar', 'Gestionar roles y permisos'),
('clientes.ver', 'Ver clientes'),
('clientes.crear', 'Crear clientes'),
('clientes.editar', 'Editar clientes'),
('clientes.eliminar', 'Eliminar/desactivar clientes'),
('categorias.ver', 'Ver categorías'),
('categorias.gestionar', 'Crear, editar y desactivar categorías'),
('productos.ver', 'Ver productos'),
('productos.crear', 'Crear productos'),
('productos.editar', 'Editar productos'),
('productos.eliminar', 'Eliminar/desactivar productos'),
('inventario.ver', 'Consultar inventario'),
('inventario.entrada', 'Registrar entradas'),
('inventario.salida', 'Registrar salidas'),
('inventario.ajuste', 'Realizar ajustes'),
('ventas.ver', 'Consultar ventas'),
('ventas.crear', 'Crear ventas'),
('ventas.anular', 'Anular ventas'),
('facturas.ver', 'Consultar facturas'),
('facturas.imprimir', 'Imprimir facturas'),
('caja.ver', 'Consultar caja'),
('caja.abrir', 'Abrir caja'),
('caja.movimientos', 'Registrar movimientos de caja'),
('caja.cerrar', 'Cerrar caja'),
('devoluciones.ver', 'Consultar devoluciones'),
('devoluciones.crear', 'Registrar devoluciones'),
('reportes.ver', 'Consultar reportes'),
('configuracion.gestionar', 'Gestionar configuración'),
('auditoria.ver', 'Consultar auditoría');

-- Administrador: todos los permisos
INSERT INTO rol_permisos (id_rol, id_permiso)
SELECT 1, id_permiso FROM permisos;

-- Vendedor
INSERT INTO rol_permisos (id_rol, id_permiso)
SELECT 2, id_permiso
FROM permisos
WHERE nombre IN (
    'dashboard.ver',
    'clientes.ver',
    'clientes.crear',
    'clientes.editar',
    'productos.ver',
    'ventas.ver',
    'ventas.crear',
    'facturas.ver',
    'facturas.imprimir'
);

-- Cajero
INSERT INTO rol_permisos (id_rol, id_permiso)
SELECT 3, id_permiso
FROM permisos
WHERE nombre IN (
    'dashboard.ver',
    'clientes.ver',
    'clientes.crear',
    'clientes.editar',
    'productos.ver',
    'ventas.ver',
    'ventas.crear',
    'facturas.ver',
    'facturas.imprimir',
    'caja.ver',
    'caja.abrir',
    'caja.movimientos',
    'caja.cerrar',
    'devoluciones.ver',
    'devoluciones.crear'
);

-- Supervisor
INSERT INTO rol_permisos (id_rol, id_permiso)
SELECT 4, id_permiso
FROM permisos
WHERE nombre IN (
    'dashboard.ver',
    'clientes.ver',
    'clientes.crear',
    'clientes.editar',
    'categorias.ver',
    'productos.ver',
    'productos.crear',
    'productos.editar',
    'inventario.ver',
    'inventario.entrada',
    'inventario.salida',
    'inventario.ajuste',
    'ventas.ver',
    'facturas.ver',
    'facturas.imprimir',
    'caja.ver',
    'devoluciones.ver',
    'devoluciones.crear',
    'reportes.ver'
);

-- Métodos de pago
INSERT INTO metodos_pago (nombre) VALUES
('Efectivo'),
('Tarjeta'),
('Transferencia'),
('Nequi'),
('Daviplata');

-- Caja principal
INSERT INTO cajas (nombre) VALUES
('Caja Principal');

-- Configuración inicial
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('nombre_empresa', 'Mi Empresa', 'Nombre de la empresa'),
('nit', '', 'NIT de la empresa'),
('direccion', '', 'Dirección de la empresa'),
('telefono', '', 'Teléfono de la empresa'),
('email', '', 'Correo electrónico de la empresa'),
('logo', '', 'Ruta del logo'),
('moneda', 'COP', 'Moneda utilizada'),
('prefijo_factura', 'FAC', 'Prefijo de facturación'),
('impuesto', '0', 'Porcentaje de impuesto predeterminado');

-- Cliente genérico
INSERT INTO clientes (documento, nombre, telefono, email, direccion, ciudad)
VALUES ('222222222222', 'Cliente General', NULL, NULL, NULL, NULL);

-- Usuario administrador inicial
-- Contraseña: Admin123*
-- IMPORTANTE: cambiarla desde el sistema después del primer ingreso.
INSERT INTO usuarios (nombre, usuario, password, rol_id)
VALUES (
    'Administrador',
    'admin',
    '$2y$12$Eqdy02ijfddc4nwCAxfCzecCzuiKHtlfJxgsIZtDH3DfJZlCmCjm2',
    1
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DE LA BASE DE DATOS
-- ============================================================
