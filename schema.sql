-- ==========================================
-- SQL Script: Sublimation POS Database Schema
-- Compatible with MySQL 8.0+ / AWS RDS
-- ==========================================

CREATE DATABASE IF NOT EXISTS `sublimation_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sublimation_db`;

-- 1. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL,
  `usuario` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('Administrador', 'Cajero') NOT NULL DEFAULT 'Cajero',
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Sesiones de Caja (Apertura y Cierre)
CREATE TABLE IF NOT EXISTS `cajas_sesiones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NOT NULL,
  `monto_apertura` DECIMAL(10,2) NOT NULL,
  `monto_cierre_efectivo` DECIMAL(10,2) NULL,
  `monto_cierre_tarjeta` DECIMAL(10,2) NULL,
  `monto_ventas_calculado` DECIMAL(10,2) NULL,
  `diferencia` DECIMAL(10,2) NULL,
  `fecha_apertura` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` DATETIME NULL,
  `estado` ENUM('Abierta', 'Cerrada') NOT NULL DEFAULT 'Abierta',
  `observaciones` TEXT NULL,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Categorías de Productos
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL UNIQUE,
  `descripcion` TEXT NULL,
  `activo` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla de Productos (Stock e Inventario)
CREATE TABLE IF NOT EXISTS `productos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `categoria_id` INT NOT NULL,
  `codigo_barras` VARCHAR(50) NOT NULL UNIQUE,
  `nombre` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NULL,
  `precio_compra` DECIMAL(10,2) NOT NULL,
  `precio_venta` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `stock_minimo` INT NOT NULL DEFAULT 5,
  `disponibilidad` ENUM('Disponible', 'Agotado', 'Descontinuado') NOT NULL DEFAULT 'Disponible',
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de Clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `identificacion` VARCHAR(30) UNIQUE NULL, -- RFC, DNI, RUT, etc.
  `nombre` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20) NULL,
  `email` VARCHAR(150) NULL,
  `direccion` TEXT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de Ventas
CREATE TABLE IF NOT EXISTS `ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `caja_sesion_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `cliente_id` INT NULL,
  `num_factura` VARCHAR(50) NOT NULL UNIQUE,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `impuesto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL,
  `metodo_pago` ENUM('Efectivo', 'Tarjeta', 'Transferencia') NOT NULL DEFAULT 'Efectivo',
  `monto_pagado` DECIMAL(10,2) NOT NULL,
  `cambio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('Completada', 'Anulada') NOT NULL DEFAULT 'Completada',
  `fecha_venta` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`caja_sesion_id`) REFERENCES `cajas_sesiones`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Detalles de las Ventas
CREATE TABLE IF NOT EXISTS `detalle_ventas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venta_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7b. Kardex de Inventario (Fase 4)
-- Fuente de migración: database/migrations/001_kardex.sql (idempotente).
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `producto_id` INT NOT NULL,
  `usuario_id` INT NULL,
  `tipo_movimiento` ENUM(
    'INVENTARIO_INICIAL',
    'ENTRADA_COMPRA',
    'SALIDA_VENTA',
    'AJUSTE_ENTRADA',
    'AJUSTE_SALIDA',
    'DEVOLUCION_VENTA',
    'DEVOLUCION_COMPRA',
    'MERMA'
  ) NOT NULL,
  `referencia_id` INT NULL COMMENT 'ID de la entidad origen (venta, producto, futura compra/devolución)',
  `referencia_tipo` VARCHAR(50) NULL COMMENT 'VENTA, VENTA_ANULADA, PRODUCTO, COMPRA, DEVOLUCION...',
  `cantidad` INT NOT NULL COMMENT 'Con signo: negativo = salida, positivo = entrada',
  `stock_anterior` INT NOT NULL,
  `stock_nuevo` INT NOT NULL,
  `costo_unitario` DECIMAL(10,2) NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL,
  KEY `idx_kardex_producto_fecha` (`producto_id`, `fecha`),
  KEY `idx_kardex_referencia` (`referencia_tipo`, `referencia_id`),
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Registro de Auditoría y Logs de Seguridad
CREATE TABLE IF NOT EXISTS `auditoria_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `nombre_usuario` VARCHAR(150) NOT NULL,
  `rol_usuario` VARCHAR(50) NOT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `modulo` VARCHAR(50) NOT NULL,
  `detalles` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- SEED DATA (Datos Iniciales para Pruebas)
-- ==========================================

-- Contraseñas: `admin123` para admin, `cajero123` para cajero
-- Hashes regenerados el 2026-08-10 (Fase 2): los anteriores NO verificaban con password_verify().
INSERT INTO `usuarios` (`nombre`, `usuario`, `email`, `password_hash`, `rol`, `activo`) VALUES
('Administrador POS', 'admin', 'admin@sublimacion.com', '$2y$10$WTjZaWhcpFycJlAO2d/ME.iocL.c/XwstDF31c68RiZLU0LQ4mnxu', 'Administrador', 1),
('Cajero Turno A', 'cajero', 'cajero@sublimacion.com', '$2y$10$TlFDFNK24MUX0exTuZ8c1uHEJC4/ctGgQew0jsjxbzblxoEANfimS', 'Cajero', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Categorías
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Tazas', 'Tazas de cerámica, cristal y cónicas para sublimación', 1),
(2, 'Camisetas', 'Camisetas de microfibra, poliéster tacto algodón, distintos colores', 1),
(3, 'Termos', 'Termos de aluminio, acero inoxidable y botellas deportivas', 1),
(4, 'Gorras', 'Gorras tipo trucker con frente blanco sublimable', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Productos Iniciales
INSERT INTO `productos` (`id`, `categoria_id`, `codigo_barras`, `nombre`, `descripcion`, `precio_compra`, `precio_venta`, `stock`, `stock_minimo`, `disponibilidad`, `activo`) VALUES
(1, 1, '7501001', 'Taza Mágica Negra 11oz', 'Taza de cerámica que revela imagen con líquido caliente', 25.00, 75.00, 30, 5, 'Disponible', 1),
(2, 1, '7501002', 'Taza Blanca Estándar 11oz', 'Taza blanca clásica de cerámica calidad AA', 12.00, 45.00, 150, 10, 'Disponible', 1),
(3, 2, '7502001', 'Camiseta Poliéster Blanca M', 'Camiseta cuello redondo blanca 100% poliéster', 35.00, 95.00, 4, 8, 'Disponible', 1), -- Inicia con stock bajo (< stock_minimo)
(4, 3, '7503001', 'Termo Deportivo Aluminio 600ml', 'Termo de aluminio gris con tapa de rosca y mosquetón', 45.00, 120.00, 15, 3, 'Disponible', 1),
(5, 4, '7504001', 'Gorra Trucker Azul/Blanco', 'Gorra con broche ajustable y frente de espuma blanca', 18.00, 50.00, 0, 5, 'Agotado', 1) -- Inicia agotada
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Cliente General de Prueba
INSERT INTO `clientes` (`id`, `identificacion`, `nombre`, `telefono`, `email`, `direccion`) VALUES
(1, 'XAXX010101000', 'Público General', '0000000000', 'general@sublima.com', 'Ventas de mostrador')
ON DUPLICATE KEY UPDATE `id`=`id`;
