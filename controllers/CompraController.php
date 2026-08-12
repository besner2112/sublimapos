<?php
// ==========================================
// Controlador de Compras (Fase 5)
// Crea borradores, confirma compras y consulta
// el historial. Todo cambio de stock pasa por
// CompraService / InventarioService.
// Solo Administrador (validado en la ruta).
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../helpers/CompraService.php';
require_once __DIR__ . '/AuditoriaController.php';

class CompraController {

    /**
     * Crea una compra en BORRADOR (no toca stock).
     * Espera los detalles como array de
     * ['producto_id','cantidad','costo_unitario'].
     */
    public static function crearCompra($proveedor_id, $numero_documento, $observaciones, $detalles) {
        $res = CompraService::crearBorrador(
            $proveedor_id,
            $_SESSION['usuario_id'] ?? null,
            $numero_documento,
            $observaciones,
            $detalles
        );

        if (!empty($res['success']) && !empty($res['compra_id'])) {
            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Crear Compra (BORRADOR)',
                'Compras',
                "Se creó la compra #{$res['compra_id']} en BORRADOR (proveedor ID $proveedor_id). Total: L. {$res['total']}"
            );
        }
        return $res;
    }

    /**
     * Confirma una compra BORRADOR aplicando stock + Kardex en
     * una única transacción (idempotente).
     */
    public static function confirmarCompra($compra_id) {
        $res = CompraService::confirmarCompra($compra_id, $_SESSION['usuario_id'] ?? null);

        if (!empty($res['success'])) {
            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Confirmar Compra',
                'Compras',
                "Compra #{$res['compra_id']} CONFIRMADA. Subtotal L. {$res['subtotal']}, total L. {$res['total']}."
            );
        }
        return $res;
    }

    public static function listarCompras($estado = null) {
        return CompraService::listarCompras($estado);
    }

    public static function obtenerCompraConDetalles($compra_id) {
        return CompraService::obtenerCompraConDetalles($compra_id);
    }
}
