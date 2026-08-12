<?php
// ==========================================================
// Servicio de Compras — Fase 5
// Lógica transaccional de compras a proveedores.
// Reutiliza InventarioService::aplicarMovimiento() para todo
// cambio de stock (misma transacción => Kardex atómico).
// ==========================================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/InventarioService.php';

class CompraService {

    // Estados de compra (coinciden con el ENUM de la BD).
    const ESTADO_BORRADOR   = 'BORRADOR';
    const ESTADO_CONFIRMADA = 'CONFIRMADA';
    const ESTADO_ANULADA    = 'ANULADA';

    // Impuesto de compra: misma tasa ISV del sistema (ventas).
    const IMPUESTO_TASA = 0.16;

    /**
     * Valida un detalle de compra (reglas comunes de la Fase 5).
     *
     * @param PDO   $pdo
     * @param array $d  ['producto_id' => int, 'cantidad' => int, 'costo_unitario' => float]
     * @return array ['ok' => bool, 'message' => string|null, 'producto' => array|null]
     */
    private static function validarDetalle($pdo, $d) {
        $producto_id = intval($d['producto_id'] ?? 0);
        $cantidad    = intval($d['cantidad'] ?? 0);
        $costo       = floatval($d['costo_unitario'] ?? -1);

        if ($producto_id <= 0) {
            return ['ok' => false, 'message' => 'Producto inexistente en el detalle de compra.', 'producto' => null];
        }
        if ($cantidad <= 0) {
            return ['ok' => false, 'message' => "La cantidad del producto ID $producto_id debe ser mayor a cero.", 'producto' => null];
        }
        if ($costo < 0) {
            return ['ok' => false, 'message' => "El costo unitario del producto ID $producto_id no puede ser negativo.", 'producto' => null];
        }

        $st = $pdo->prepare("SELECT id, nombre, activo, disponibilidad FROM productos WHERE id = :id");
        $st->execute([':id' => $producto_id]);
        $prod = $st->fetch();
        if (!$prod) {
            return ['ok' => false, 'message' => "El producto ID $producto_id no existe.", 'producto' => null];
        }
        if (intval($prod['activo']) !== 1) {
            return ['ok' => false, 'message' => "El producto '{$prod['nombre']}' está desactivado.", 'producto' => null];
        }
        if ($prod['disponibilidad'] === 'Descontinuado') {
            return ['ok' => false, 'message' => "El producto '{$prod['nombre']}' está descontinuado y no puede comprarse.", 'producto' => null];
        }
        return ['ok' => true, 'message' => null, 'producto' => $prod];
    }

    /**
     * Crea una compra en estado BORRADOR con sus detalles.
     * NO modifica stock ni Kardex (eso ocurre solo al confirmar).
     *
     * @param int         $proveedor_id
     * @param int|null    $usuario_id
     * @param string|null $numero_documento
     * @param string|null $observaciones
     * @param array       $detalles  Lista de ['producto_id','cantidad','costo_unitario']
     * @return array
     */
    public static function crearBorrador($proveedor_id, $usuario_id, $numero_documento, $observaciones, $detalles) {
        $proveedor_id = intval($proveedor_id);
        $numero_documento = trim((string)$numero_documento);
        $observaciones = trim((string)$observaciones);

        if (!is_array($detalles) || count($detalles) === 0) {
            return ['success' => false, 'message' => 'La compra debe incluir al menos un producto.'];
        }

        $pdo = getDB();
        try {
            $pdo->beginTransaction();

            // Proveedor debe existir y estar activo.
            $st = $pdo->prepare("SELECT id, nombre FROM proveedores WHERE id = :id AND activo = 1");
            $st->execute([':id' => $proveedor_id]);
            $prov = $st->fetch();
            if (!$prov) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'El proveedor seleccionado no existe o está inactivo.'];
            }

            // Validar y calcular totales SOLO desde la BD (nunca del cliente).
            $subtotal = 0.0;
            $detalles_limpios = [];
            foreach ($detalles as $d) {
                $v = self::validarDetalle($pdo, $d);
                if (!$v['ok']) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => $v['message']];
                }
                $cant = intval($d['cantidad']);
                $costo = round(floatval($d['costo_unitario']), 2);
                $subtotal += $cant * $costo;
                $detalles_limpios[] = [
                    'producto_id' => intval($d['producto_id']),
                    'cantidad'    => $cant,
                    'costo_unitario' => $costo,
                ];
            }
            $subtotal = round($subtotal, 2);
            $impuesto = round($subtotal * self::IMPUESTO_TASA, 2);
            $total = round($subtotal + $impuesto, 2);

            $ins = $pdo->prepare(
                "INSERT INTO compras (proveedor_id, usuario_id, numero_documento, subtotal, impuesto, total, estado, observaciones)
                 VALUES (:prov, :uid, :doc, :sub, :imp, :tot, '" . self::ESTADO_BORRADOR . "', :obs)"
            );
            $ins->execute([
                ':prov' => $proveedor_id,
                ':uid'  => $usuario_id !== null ? intval($usuario_id) : null,
                ':doc'  => $numero_documento !== '' ? mb_substr($numero_documento, 0, 100) : null,
                ':sub'  => $subtotal,
                ':imp'  => $impuesto,
                ':tot'  => $total,
                ':obs'  => $observaciones !== '' ? mb_substr($observaciones, 0, 500) : null,
            ]);
            $compra_id = $pdo->lastInsertId();

            $insd = $pdo->prepare(
                "INSERT INTO detalle_compras (compra_id, producto_id, cantidad, costo_unitario, subtotal)
                 VALUES (:cid, :pid, :cant, :costo, :sub)"
            );
            foreach ($detalles_limpios as $dl) {
                $insd->execute([
                    ':cid'   => $compra_id,
                    ':pid'   => $dl['producto_id'],
                    ':cant'  => $dl['cantidad'],
                    ':costo' => $dl['costo_unitario'],
                    ':sub'   => round($dl['cantidad'] * $dl['costo_unitario'], 2),
                ]);
            }

            $pdo->commit();

            return [
                'success'  => true,
                'message'  => 'Compra guardada en BORRADOR. La confirmación aplicará el stock.',
                'compra_id' => $compra_id,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total'    => $total,
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Error al crear compra: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar la compra.'];
        }
    }

    /**
     * Confirma una compra en BORRADOR: aplica el stock y crea el
     * Kardex ENTRADA_COMPRA por cada detalle, TODO en una única
     * transacción. IDEMPOTENTE: una compra ya confirmada o anulada
     * se rechaza y nunca vuelve a aumentar stock.
     *
     * @param int      $compra_id
     * @param int|null $usuario_id  Usuario que confirma (queda en el Kardex)
     * @return array
     */
    public static function confirmarCompra($compra_id, $usuario_id) {
        $compra_id = intval($compra_id);
        if ($compra_id <= 0) {
            return ['success' => false, 'message' => 'Compra inválida.'];
        }

        $pdo = getDB();
        try {
            $pdo->beginTransaction();

            // Bloquear la compra contra confirmaciones concurrentes.
            $st = $pdo->prepare("SELECT * FROM compras WHERE id = :id FOR UPDATE");
            $st->execute([':id' => $compra_id]);
            $compra = $st->fetch();
            if (!$compra) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La compra no existe.'];
            }
            if ($compra['estado'] !== self::ESTADO_BORRADOR) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => "La compra ya fue " . strtolower($compra['estado']) . " y no puede confirmarse de nuevo.",
                ];
            }

            // Proveedor debe seguir activo al momento de confirmar.
            $st = $pdo->prepare("SELECT id, nombre FROM proveedores WHERE id = :id AND activo = 1");
            $st->execute([':id' => $compra['proveedor_id']]);
            if (!$st->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'El proveedor de esta compra está inactivo; no puede confirmarse.'];
            }

            $st = $pdo->prepare("SELECT * FROM detalle_compras WHERE compra_id = :cid ORDER BY id");
            $st->execute([':cid' => $compra_id]);
            $detalles = $st->fetchAll();
            if (count($detalles) === 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La compra no tiene productos; no puede confirmarse.'];
            }

            $movimientos = [];
            foreach ($detalles as $d) {
                // Validación de producto al confirmar (pudo desactivarse/descontinuarse
                // después de crear el borrador). El servicio bloquea la fila y valida stock.
                $v = self::validarDetalle($pdo, $d);
                if (!$v['ok']) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => $v['message']];
                }
                $res = InventarioService::aplicarMovimiento(
                    $pdo,
                    $d['producto_id'],
                    $usuario_id,
                    'ENTRADA_COMPRA',
                    intval($d['cantidad']),
                    $compra_id,
                    'COMPRA',
                    "Compra #$compra_id a proveedor. " . ($compra['observaciones'] ?? ''),
                    $d['costo_unitario']
                );
                $movimientos[] = [
                    'producto_id' => intval($d['producto_id']),
                    'stock_anterior' => $res['stock_anterior'],
                    'stock_nuevo'    => $res['stock_nuevo'],
                    'cantidad'       => $res['cantidad'],
                ];
            }

            // Recalcular totales desde los detalles de la BD (defensa en profundidad).
            $st = $pdo->query("SELECT SUM(cantidad * costo_unitario) AS sub FROM detalle_compras WHERE compra_id = " . intval($compra_id));
            $subtotal = round(floatval($st->fetchColumn() ?? 0), 2);
            $impuesto = round($subtotal * self::IMPUESTO_TASA, 2);
            $total    = round($subtotal + $impuesto, 2);

            $upd = $pdo->prepare(
                "UPDATE compras SET estado = :est, subtotal = :sub, impuesto = :imp, total = :tot WHERE id = :id"
            );
            $upd->execute([
                ':est' => self::ESTADO_CONFIRMADA,
                ':sub' => $subtotal,
                ':imp' => $impuesto,
                ':tot' => $total,
                ':id'  => $compra_id,
            ]);

            $pdo->commit();

            return [
                'success'     => true,
                'message'     => "Compra #$compra_id confirmada: el stock de " . count($movimientos) . " producto(s) fue actualizado.",
                'compra_id'   => $compra_id,
                'subtotal'    => $subtotal,
                'impuesto'    => $impuesto,
                'total'       => $total,
                'movimientos' => $movimientos,
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Error al confirmar compra: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lista de compras (con proveedor y usuario).
     *
     * @param int|null $estado Filtro opcional por estado.
     * @return array
     */
    public static function listarCompras($estado = null) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT c.*, p.nombre AS proveedor_nombre, u.nombre AS usuario_nombre
                    FROM compras c
                    JOIN proveedores p ON c.proveedor_id = p.id
                    LEFT JOIN usuarios u ON c.usuario_id = u.id";
            $params = [];
            if (!empty($estado)) {
                $sql .= " WHERE c.estado = :est";
                $params[':est'] = $estado;
            }
            $sql .= " ORDER BY c.id DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al listar compras: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compra con sus detalles (para la vista/modal).
     *
     * @param int $compra_id
     * @return array|null  ['compra' => array, 'detalles' => array] o null si no existe
     */
    public static function obtenerCompraConDetalles($compra_id) {
        $compra_id = intval($compra_id);
        try {
            $pdo = conectarBD();
            $st = $pdo->prepare("SELECT c.*, p.nombre AS proveedor_nombre, u.nombre AS usuario_nombre
                                 FROM compras c
                                 JOIN proveedores p ON c.proveedor_id = p.id
                                 LEFT JOIN usuarios u ON c.usuario_id = u.id
                                 WHERE c.id = :id");
            $st->execute([':id' => $compra_id]);
            $compra = $st->fetch();
            if (!$compra) return null;
            $st = $pdo->prepare(
                "SELECT dc.*, pr.nombre AS producto_nombre, pr.codigo_barras
                 FROM detalle_compras dc
                 JOIN productos pr ON dc.producto_id = pr.id
                 WHERE dc.compra_id = :id ORDER BY dc.id"
            );
            $st->execute([':id' => $compra_id]);
            return ['compra' => $compra, 'detalles' => $st->fetchAll()];
        } catch (PDOException $e) {
            error_log("Error al obtener compra: " . $e->getMessage());
            return null;
        }
    }
}
