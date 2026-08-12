-- ==========================================================
-- Migración 001: Kardex de Inventario (Fase 4)
-- Sublimation POS — database/migrations/001_kardex.sql
-- ==========================================================
-- Crea la tabla de movimientos de inventario (Kardex) y
-- registra UNA VEZ el stock inicial de los productos que ya
-- existían antes de implementar el Kardex.
--
-- IDEMPOTENTE: si se ejecuta de nuevo no duplica movimientos
-- (guarda por tipo INVENTARIO_INICIAL por producto).
-- ==========================================================

USE `sublimation_db`;

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

-- ----------------------------------------------------------
-- INVENTARIO INICIAL (una sola vez, sin alterar el stock)
-- ----------------------------------------------------------
-- Los productos ya tenían stock cargado antes del Kardex.
-- Historial anterior al Kardex: NO RECONSTRUIDO (no existían
-- ventas previas en la BD local, por lo que no hay movimientos
-- históricos que reconstruir). Solo se registra el saldo de
-- apertura de cada producto con stock > 0.
-- ----------------------------------------------------------
INSERT INTO `movimientos_inventario`
  (`producto_id`, `usuario_id`, `tipo_movimiento`, `referencia_id`, `referencia_tipo`,
   `cantidad`, `stock_anterior`, `stock_nuevo`, `costo_unitario`, `observaciones`)
SELECT p.id, NULL, 'INVENTARIO_INICIAL', p.id, 'PRODUCTO',
       p.stock, 0, p.stock, p.precio_compra,
       'Stock inicial migrado al Kardex (Fase 4). Historial anterior al Kardex: NO RECONSTRUIDO.'
FROM `productos` p
WHERE p.stock <> 0
  AND NOT EXISTS (
      SELECT 1 FROM `movimientos_inventario` m
      WHERE m.producto_id = p.id
        AND m.tipo_movimiento = 'INVENTARIO_INICIAL'
  );
