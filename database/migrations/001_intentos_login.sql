-- ==========================================
-- Migración 001: Tabla de intentos de login (rate limit)
-- Fase 3 — Seguridad de autenticación
-- CREATE TABLE IF NOT EXISTS (no destructiva)
-- ==========================================

USE `sublimation_db`;

CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(45) NOT NULL,
  `usuario` VARCHAR(60) NOT NULL,
  `intentos` INT UNSIGNED NOT NULL DEFAULT 1,
  `ultimo_intento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `bloqueado_hasta` DATETIME NULL,
  UNIQUE KEY `uq_intentos_ip_usuario` (`ip`, `usuario`),
  KEY `idx_intentos_bloqueado` (`bloqueado_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
