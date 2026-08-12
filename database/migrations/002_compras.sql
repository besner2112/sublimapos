-- ==========================================================
-- Migración 002: Proveedores y Compras (Fase 5)
-- Sublimation POS — database/migrations/002_compras.sql
-- ==========================================================
-- Crea las tablas de proveedores, compras y detalle de compras.
-- IDEMPOTENTE: CREATE TABLE IF NOT EXISTS — si se ejecuta de
-- nuevo no duplica estructuras ni elimina datos existentes.
-- ==========================================================

USE `sublimation_db`;

-- ----------------------------------------------------------
-- PROVEEDORES
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL,
  `rtn` VARCHAR(20) NULL,
  `telefono` VARCHAR(30) NULL,
  `correo` VARCHAR(150) NULL,
  `direccion` VARCHAR(255) NULL,
  `contacto` VARCHAR(150) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_proveedores_nombre` (`nombre`),
  KEY `idx_proveedores_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- COMPRAS
--   estado: BORRADOR (no toca stock) / CONFIRMADA (aplica
--   stock + Kardex ENTRADA_COMPRA) / ANULADA (reservado;
--   la anulación de compra se implementará en una fase
--   posterior — requiere tipos de Kardex de reversión).
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `proveedor_id` INT NOT NULL,
  `usuario_id` INT NULL,
  `numero_documento` VARCHAR(100) NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `impuesto` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `estado` ENUM('BORRADOR','CONFIRMADA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
  `fecha_compra` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL,
  KEY `idx_compras_proveedor` (`proveedor_id`),
  KEY `idx_compras_usuario` (`usuario_id`),
  KEY `idx_compras_estado` (`estado`),
  KEY `idx_compras_fecha` (`fecha_compra`),
  FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- DETALLE_COMPRAS
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `compra_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `costo_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  KEY `idx_detalle_compra` (`compra_id`),
  KEY `idx_detalle_producto` (`producto_id`),
  FOREIGN KEY (`compra_id`) REFERENCES `compras`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
