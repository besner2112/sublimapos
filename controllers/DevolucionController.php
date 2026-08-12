<?php
// ==========================================
// Controlador de Devoluciones (Fase 6)
// Devuelve productos de una venta (completa o
// parcial), reintegra stock vía Kardex y registra
// el egreso en la caja. Disponible para Cajero y
// Administrador (operación de POS), siempre con
// turno de caja abierto.
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../helpers/DevolucionService.php';
require_once __DIR__ . '/AuditoriaController.php';
require_once __DIR__ . '/CajaController.php';

class DevolucionController {

    /**
     * Procesa una devolución validando que el usuario tenga un
     * turno de caja abierto (misma regla que las ventas: el
     * dinero devuelto sale de la caja del turno actual).
     *
     * @param int         $venta_id
     * @param string|null $motivo
     * @param array       $items  [['producto_id'=>int,'cantidad'=>int], ...]
     * @return array
     */
    public static function procesarDevolucion($venta_id, $motivo, $items) {
        $usuario_id = intval($_SESSION['usuario_id'] ?? 0);

        // Validación de caja activa (defensa en profundidad, igual que VentaController).
        if (empty($_SESSION['caja_sesion_id'])) {
            return ['success' => false, 'message' => 'No puedes registrar devoluciones: no tienes ninguna caja abierta. Realiza la Apertura de Caja primero.'];
        }
        $caja_sesion = CajaController::obtenerSesionActiva($usuario_id);
        if (!$caja_sesion || $caja_sesion['id'] != $_SESSION['caja_sesion_id']) {
            unset($_SESSION['caja_sesion_id']);
            return ['success' => false, 'message' => 'No puedes registrar devoluciones: tu turno de caja no está activo. Realiza la Apertura de Caja primero.'];
        }

        $res = DevolucionService::procesarDevolucion(
            $usuario_id,
            intval($caja_sesion['id']),
            $venta_id,
            $motivo,
            $items
        );

        if (!empty($res['success'])) {
            AuditoriaController::registrar(
                $usuario_id,
                $_SESSION['usuario_nombre'] ?? 'Usuario',
                $_SESSION['usuario_rol'] ?? 'Cajero',
                'Procesar Devolución',
                'POS / Devoluciones',
                "Devolución #{$res['devolucion_id']} ({$res['num_devolucion']}) de la venta #$venta_id por L. " . number_format($res['monto_total'], 2) . ". Stock reintegrado."
            );
        }
        return $res;
    }

    /**
     * Datos de la venta para el formulario de devolución.
     * IDOR (Fase 3, extendida): el cajero solo puede operar
     * sobre SUS propias ventas; el administrador, todas.
     *
     * @param int $venta_id
     * @return array|null
     */
    public static function obtenerVentaParaDevolucion($venta_id) {
        $data = DevolucionService::obtenerVentaParaDevolucion(intval($venta_id));
        if (!$data) return null;

        $es_admin = ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
        if (!$es_admin && intval($data['venta']['usuario_id']) !== intval($_SESSION['usuario_id'] ?? 0)) {
            return 'FORBIDDEN';
        }
        return $data;
    }

    public static function listarDevoluciones($limite = 100) {
        return DevolucionService::listarDevoluciones($limite);
    }

    public static function obtenerDevolucionConDetalles($devolucion_id) {
        return DevolucionService::obtenerDevolucionConDetalles($devolucion_id);
    }
}