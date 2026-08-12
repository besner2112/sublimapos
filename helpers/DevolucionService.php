<?php
// ==========================================================
// Servicio de Devoluciones — Fase 6
// Lógica transaccional de devoluciones de ventas (completa y
// parcial). Reutiliza InventarioService::aplicarMovimiento()
// para reintegrar stock (tipo DEVOLUCION_VENTA, entrada) y
// registra el dinero devuelto en movimientos_caja como
// EGRESO_DEVOLUCION, TODO dentro de la misma transacción.
//
// Reglas de negocio (documentadas, Fase 6):
//   - La venta original DEBE existir y estar 'Completada'
//     (nunca se devuelve sobre una venta anulada).
//   - Solo se pueden devolver productos que pertenezcan a la
//     venta y cantidades que no excedan lo realmente vendido
//     menos lo ya devuelto (completa o parcial).
//   - El historial de la venta original NO se modifica: ni la
//     cabecera ni el detalle se tocan (trazabilidad intacta).
//   - El monto devuelto usa el precio_unitario ORIGINAL del
//     detalle de la venta (ISV incluido), no el precio actual.
//   - El stock se reintegra vía el servicio central de Kardex
//     (recalcula disponibilidad, nunca toca 'Descontinuado').
// ==========================================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/InventarioService.php';

class DevolucionService {

    // Tipo de movimiento Kardex de reintegro por devolución.
    const KARDEX_TIPO = 'DEVOLUCION_VENTA';
    const KARDEX_REF  = 'DEVOLUCION';

    // Tipo de movimiento de caja para el dinero devuelto.
    const CAJA_TIPO_EGRESO_DEVOLUCION = 'EGRESO_DEVOLUCION';

    /**
     * Procesa una devolución (completa o parcial, uno o varios
     * productos) de forma ATÓMICA:
     *   venta FOR UPDATE -> validar líneas contra detalle_ventas
     *   y devoluciones previas -> insertar devolución + detalle
     *   -> Kardex DEVOLUCION_VENTA por producto -> movimientos_caja
     *   EGRESO_DEVOLUCION -> COMMIT. Cualquier error: ROLLBACK total.
     *
     * @param int         $usuario_id
     * @param int         $caja_sesion_id  Turno de caja desde el que se devuelve el dinero
     * @param int         $venta_id        Venta original (Completada)
     * @param string|null $motivo
     * @param array       $items           Lista ['producto_id' => int, 'cantidad' => int]
     * @return array
     */
    public static function procesarDevolucion($usuario_id, $caja_sesion_id, $venta_id, $motivo, $items) {
        $usuario_id = !empty($usuario_id) ? intval($usuario_id) : null;
        $caja_sesion_id = intval($caja_sesion_id);
        $venta_id = intval($venta_id);
        $motivo = trim((string)$motivo);

        if ($venta_id <= 0) {
            return ['success' => false, 'message' => 'Debe indicar la venta a devolver.'];
        }
        if ($caja_sesion_id <= 0) {
            return ['success' => false, 'message' => 'No tienes un turno de caja abierto para registrar la devolución.'];
        }
        if (!is_array($items) || count($items) === 0) {
            return ['success' => false, 'message' => 'La devolución debe incluir al menos un producto.'];
        }

        // Consolidar duplicados del mismo producto en una sola línea.
        $consolidado = [];
        foreach ($items as $it) {
            $pid = intval($it['producto_id'] ?? 0);
            $cant = intval($it['cantidad'] ?? 0);
            if ($pid <= 0) {
                return ['success' => false, 'message' => 'Producto inválido en la devolución.'];
            }
            if ($cant <= 0) {
                return ['success' => false, 'message' => "La cantidad a devolver del producto ID $pid debe ser mayor a cero."];
            }
            $consolidado[$pid] = ($consolidado[$pid] ?? 0) + $cant;
        }

        $pdo = getDB();
        try {
            $pdo->beginTransaction();

            // 1. La venta debe existir y NO estar anulada (bloqueo de fila).
            $st = $pdo->prepare(
                "SELECT v.* FROM ventas v WHERE v.id = :id FOR UPDATE"
            );
            $st->execute([':id' => $venta_id]);
            $venta = $st->fetch();
            if (!$venta || $venta['estado'] !== 'Completada') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La venta no existe o ya fue anulada; no se puede devolver.'];
            }

            // 2. El turno de caja debe existir y estar abierto (defensa en profundidad).
            $st = $pdo->prepare("SELECT id, estado FROM cajas_sesiones WHERE id = :id");
            $st->execute([':id' => $caja_sesion_id]);
            $caja = $st->fetch();
            if (!$caja || $caja['estado'] !== 'Abierta') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'El turno de caja especificado no existe o ya está cerrado.'];
            }

            // 3. Validar cada línea contra el detalle ORIGINAL de la venta
            //    y contra lo ya devuelto anteriormente.
            $monto_total = 0.0;
            $lineas = [];
            $stDet   = $pdo->prepare(
                "SELECT dv.*, p.nombre AS nombre_producto
                 FROM detalle_ventas dv
                 JOIN productos p ON dv.producto_id = p.id
                 WHERE dv.venta_id = :vid AND dv.producto_id = :pid FOR UPDATE"
            );
            $stDevuelto = $pdo->prepare(
                "SELECT COALESCE(SUM(dd.cantidad), 0) AS devuelto
                 FROM detalle_devoluciones dd
                 JOIN devoluciones d ON d.id = dd.devolucion_id
                 WHERE d.venta_id = :vid AND dd.producto_id = :pid"
            );

            foreach ($consolidado as $pid => $cant) {
                $stDet->execute([':vid' => $venta_id, ':pid' => $pid]);
                $det = $stDet->fetch();
                if (!$det) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "El producto ID $pid no pertenece a la venta #$venta_id; no se puede devolver."];
                }

                $stDevuelto->execute([':vid' => $venta_id, ':pid' => $pid]);
                $ya_devuelto = intval($stDevuelto->fetchColumn());
                $vendido = intval($det['cantidad']);
                $disponible = $vendido - $ya_devuelto;

                if ($cant > $disponible) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => "Devolución rechazada: del producto '{$det['nombre_producto']}' solo quedan $disponible unidad(es) por devolver (se vendieron $vendido y ya se devolvieron $ya_devuelto).",
                    ];
                }

                $precio = floatval($det['precio_unitario']);
                $monto_total += $precio * $cant;
                $lineas[] = [
                    'producto_id'    => $pid,
                    'nombre_producto'=> $det['nombre_producto'],
                    'cantidad'       => $cant,
                    'precio_unitario'=> $precio,
                    'subtotal'       => round($precio * $cant, 2),
                ];
            }
            $monto_total = round($monto_total, 2);

            // 4. Insertar cabecera de la devolución.
            $numero = 'DEV-' . date('YmdHis') . '-' . mt_rand(10, 99);
            $ins = $pdo->prepare(
                "INSERT INTO devoluciones
                    (venta_id, caja_sesion_id, usuario_id, num_devolucion, motivo,
                     monto_total, metodo_pago_venta)
                 VALUES
                    (:vid, :cid, :uid, :num, :mot, :monto, :metodo)"
            );
            $ins->execute([
                ':vid'    => $venta_id,
                ':cid'    => $caja_sesion_id,
                ':uid'    => $usuario_id,
                ':num'    => $numero,
                ':mot'    => $motivo !== '' ? mb_substr($motivo, 0, 500) : null,
                ':monto'  => $monto_total,
                ':metodo' => in_array($venta['metodo_pago'], ['Efectivo', 'Tarjeta', 'Transferencia']) ? $venta['metodo_pago'] : 'Efectivo',
            ]);
            $devolucion_id = $pdo->lastInsertId();

            // 5. Detalle de la devolución (precios ORIGINALES de la venta).
            $insd = $pdo->prepare(
                "INSERT INTO detalle_devoluciones
                    (devolucion_id, producto_id, cantidad, precio_unitario, subtotal)
                 VALUES (:did, :pid, :cant, :precio, :sub)"
            );
            foreach ($lineas as $l) {
                $insd->execute([
                    ':did'    => $devolucion_id,
                    ':pid'    => $l['producto_id'],
                    ':cant'   => $l['cantidad'],
                    ':precio' => $l['precio_unitario'],
                    ':sub'    => $l['subtotal'],
                ]);
            }

            // 6. Reintegrar stock vía servicio central de Kardex (misma
            //    transacción). DEVOLUCION_VENTA es ENTRADA: la cantidad
            //    queda positiva y se rastrea a la devolución.
            $movimientos = [];
            foreach ($lineas as $l) {
                $res = InventarioService::aplicarMovimiento(
                    $pdo,
                    $l['producto_id'],
                    $usuario_id,
                    self::KARDEX_TIPO,
                    $l['cantidad'],
                    $devolucion_id,
                    self::KARDEX_REF,
                    "Devolución $numero de la venta #$venta_id." . ($motivo !== '' ? " Motivo: $motivo" : ''),
                    null
                );
                $movimientos[] = [
                    'producto_id'    => $l['producto_id'],
                    'nombre_producto'=> $l['nombre_producto'],
                    'cantidad'       => $res['cantidad'],
                    'stock_anterior' => $res['stock_anterior'],
                    'stock_nuevo'    => $res['stock_nuevo'],
                ];
            }

            // 7. Movimiento de caja: el dinero devuelto sale del turno.
            $insc = $pdo->prepare(
                "INSERT INTO movimientos_caja
                    (caja_sesion_id, tipo, monto, referencia_tipo, referencia_id, usuario_id, observaciones)
                 VALUES (:cid, :tipo, :monto, :rtipo, :rid, :uid, :obs)"
            );
            $insc->execute([
                ':cid'   => $caja_sesion_id,
                ':tipo'  => self::CAJA_TIPO_EGRESO_DEVOLUCION,
                ':monto' => $monto_total,
                ':rtipo' => self::KARDEX_REF,
                ':rid'   => $devolucion_id,
                ':uid'   => $usuario_id,
                ':obs'   => "Dinero devuelto al cliente por $numero — venta #$venta_id (" . $venta['metodo_pago'] . ").",
            ]);

            $pdo->commit();

            return [
                'success'         => true,
                'message'         => "Devolución #$devolucion_id registrada: L. " . number_format($monto_total, 2) . " devueltos al cliente. El stock de " . count($lineas) . " producto(s) fue reintegrado.",
                'devolucion_id'   => $devolucion_id,
                'num_devolucion'  => $numero,
                'venta_id'        => $venta_id,
                'monto_total'     => $monto_total,
                'movimientos'     => $movimientos,
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Error al procesar devolución: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Datos de la venta para el formulario de devolución: cabecera
     * + detalles con lo vendido, lo ya devuelto y lo disponible.
     *
     * @param int $venta_id
     * @return array|null  ['venta' => array, 'detalles' => array] o null
     */
    public static function obtenerVentaParaDevolucion($venta_id) {
        $venta_id = intval($venta_id);
        try {
            $pdo = conectarBD();
            $st = $pdo->prepare(
                "SELECT v.*, u.nombre AS nombre_usuario, c.nombre AS nombre_cliente
                 FROM ventas v
                 JOIN usuarios u ON v.usuario_id = u.id
                 LEFT JOIN clientes c ON v.cliente_id = c.id
                 WHERE v.id = :id"
            );
            $st->execute([':id' => $venta_id]);
            $venta = $st->fetch();
            if (!$venta) return null;

            $st = $pdo->prepare(
                "SELECT dv.id AS detalle_id, dv.producto_id, dv.cantidad AS vendido,
                        dv.precio_unitario, dv.subtotal AS subtotal_original,
                        p.nombre AS nombre_producto,
                        COALESCE(
                            (SELECT SUM(dd.cantidad) FROM detalle_devoluciones dd
                             JOIN devoluciones d ON d.id = dd.devolucion_id
                             WHERE d.venta_id = dv.venta_id AND dd.producto_id = dv.producto_id), 0
                        ) AS devuelto
                 FROM detalle_ventas dv
                 JOIN productos p ON dv.producto_id = p.id
                 WHERE dv.venta_id = :id
                 ORDER BY dv.id"
            );
            $st->execute([':id' => $venta_id]);
            $detalles = [];
            foreach ($st->fetchAll() as $d) {
                $d['devuelto']   = intval($d['devuelto']);
                $d['disponible'] = intval($d['vendido']) - $d['devuelto'];
                $detalles[] = $d;
            }
            return ['venta' => $venta, 'detalles' => $detalles];
        } catch (PDOException $e) {
            error_log("Error al obtener venta para devolución: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Listado de devoluciones (con venta y usuario).
     *
     * @param int|null $limite
     * @return array
     */
    public static function listarDevoluciones($limite = 100) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT d.*, v.num_factura, v.total AS total_venta, v.estado AS estado_venta,
                           u.nombre AS nombre_usuario
                    FROM devoluciones d
                    JOIN ventas v ON d.venta_id = v.id
                    LEFT JOIN usuarios u ON d.usuario_id = u.id
                    ORDER BY d.id DESC LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':lim', intval($limite), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al listar devoluciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Devolución con sus líneas (para el modal de detalle).
     *
     * @param int $devolucion_id
     * @return array|null ['devolucion' => array, 'detalles' => array]
     */
    public static function obtenerDevolucionConDetalles($devolucion_id) {
        $devolucion_id = intval($devolucion_id);
        try {
            $pdo = conectarBD();
            $st = $pdo->prepare(
                "SELECT d.*, v.num_factura, u.nombre AS nombre_usuario
                 FROM devoluciones d
                 JOIN ventas v ON d.venta_id = v.id
                 LEFT JOIN usuarios u ON d.usuario_id = u.id
                 WHERE d.id = :id"
            );
            $st->execute([':id' => $devolucion_id]);
            $dev = $st->fetch();
            if (!$dev) return null;

            $st = $pdo->prepare(
                "SELECT dd.*, p.nombre AS nombre_producto
                 FROM detalle_devoluciones dd
                 JOIN productos p ON dd.producto_id = p.id
                 WHERE dd.devolucion_id = :id ORDER BY dd.id"
            );
            $st->execute([':id' => $devolucion_id]);
            return ['devolucion' => $dev, 'detalles' => $st->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error al obtener devolución: " . $e->getMessage());
            return null;
        }
    }
}