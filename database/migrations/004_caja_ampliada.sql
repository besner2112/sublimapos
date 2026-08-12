-- ==========================================
-- 004_caja_ampliada.sql
-- Fase 7: Caja Ampliada
-- Persistencia del arqueo ampliado en cajas_sesiones:
--   - monto_ventas_efectivo : ventas cobradas en efectivo durante el turno
--   - monto_ingresos        : total de ingresos extra (movimientos INGRESO)
--   - monto_retiros         : total de retiros (movimientos RETIRO)
--   - monto_devoluciones    : total devuelto en efectivo (EGRESO_DEVOLUCION)
--   - efectivo_esperado     : cálculo oficial del cierre
-- Idempotente (MariaDB 10.4+: ADD COLUMN IF NOT EXISTS).
-- ==========================================

USE sublimation_db;

ALTER TABLE cajas_sesiones
  ADD COLUMN IF NOT EXISTS monto_ventas_efectivo DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_ventas_calculado,
  ADD COLUMN IF NOT EXISTS monto_ingresos        DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_ventas_efectivo,
  ADD COLUMN IF NOT EXISTS monto_retiros         DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_ingresos,
  ADD COLUMN IF NOT EXISTS monto_devoluciones    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_retiros,
  ADD COLUMN IF NOT EXISTS efectivo_esperado     DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER monto_devoluciones;