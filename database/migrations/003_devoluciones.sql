-- ==========================================================
-- Migración 003: Devoluciones y movimientos de caja (Fase 6)
-- Sublimation POS — database/migrations/003_devoluciones.sql
-- ==========================================================
-- Crea las tablas de devoluciones (cabecera + detalle) y de
-- movimientos de caja (trazabilidad del dinero devuelto).
-- IDEMPOTENTE: CREATE TABLE IF NOT EXISTS — si se ejecuta de
-- nuevo no duplica estructuras ni elimina datos existentes.
--
-- Nota de diseño (Fase 6):
--  * Las devoluciones NUNCA borran ni modifican la venta
--    original ni su detalle: conservan el historial (regla 13).
--  * El ENUM del Kardex ya incluía DEVOLUCION_VENTA desde la
--    migración 001 (Fase 4), por lo que NO se altera aquí.
--  * La tabla movimientos_caja es de TRAZABILIDAD: registra el
--    dinero que sale de la caja (EGRESO_DEVOLUCION). El cálculo
--    del arqueo actual (CajaController) NO cambia; la diferencia
--    del arqueo absorbe el efectivo devuelto, y el detalle queda
--    auditable en esta tabla para la Fase de Caja ampliada.
-- ==========================================================

USE `sublimation_db`;

-- ----------------------------------------------------------
-- DEVOLUCIONES (cabecera)
--   estado: 'Completada' — una vez registrada la devolución es
--   histórica; no se anula (se registra otra si aplica).
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devoluciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venta_id` INT NOT NULL,
  `caja_sesion_id` INT NOT NULL,
  `usuario_id` INT NULL,
  `num_devolucion` VARCHAR(30) NOT NULL,
  `motivo` TEXT NULL,
  `monto_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `metodo_pago_venta` ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL DEFAULT 'Efectivo' COMMENT 'Método con que se pagó la venta original (trazabilidad)',
  `estado` ENUM('Completada') NOT NULL DEFAULT 'Completada',
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_devoluciones_venta` (`venta_id`),
  KEY `idx_devoluciones_caja` (`caja_sesion_id`),
  KEY `idx_devoluciones_usuario` (`usuario_id`),
  KEY `idx_devoluciones_fecha` (`fecha`),
  FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`),
  FOREIGN KEY (`caja_sesion_id`) REFERENCES `cajas_sesiones`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- DETALLE_DEVOLUCIONES
--   Replica el precio unitario ORIGINAL del detalle de la
--   venta (con ISV incluido) para conservar el monto devuelto
--   aunque el precio del producto cambie después.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detalle_devoluciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `devolucion_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL COMMENT 'Precio original del detalle de la venta (ISV incluido)',
  `subtotal` DECIMAL(10,2) NOT NULL,
  KEY `idx_detalle_devolucion` (`devolucion_id`),
  KEY `idx_detalle_devolucion_producto` (`producto_id`),
  FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- MOVIMIENTOS_CAJA
--   Trazabilidad del flujo de dinero por turno de caja.
--   En esta fase solo se registra EGRESO_DEVOLUCION (el dinero
--   devuelto al cliente). Los futuros tipos (INGRESO_VENTA,
--   INGRESO/RETIRO) entrarán con la Fase de Caja ampliada.
--   monto SIEMPRE positivo; el sentido lo da el tipo.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_caja` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `caja_sesion_id` INT NOT NULL,
  `tipo` VARCHAR(30) NOT NULL COMMENT 'EGRESO_DEVOLUCION (esta fase); futuros: INGRESO_VENTA, RETIRO, INGRESO_EXTRA...',
  `monto` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Positivo; el signo lo define el tipo',
  `referencia_tipo` VARCHAR(50) NULL,
  `referencia_id` INT NULL,
  `usuario_id` INT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `observaciones` TEXT NULL,
  KEY `idx_mov_caja_sesion_fecha` (`caja_sesion_id`, `fecha`),
  KEY `idx_mov_caja_referencia` (`referencia_tipo`, `referencia_id`),
  KEY `idx_mov_caja_usuario` (`usuario_id`),
  FOREIGN KEY (`caja_sesion_id`) REFERENCES `cajas_sesiones`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;