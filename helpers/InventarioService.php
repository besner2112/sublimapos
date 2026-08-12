<?php
// ==========================================================
// Servicio Central de Inventario (Kardex) — Fase 4
// Única capa autorizada para modificar el stock de productos.
// Toda modificación de stock DEBE pasar por aquí y genera
// SIEMPRE un movimiento Kardex en la misma transacción.
// ==========================================================

require_once __DIR__ . '/../conexion/db.php';

class InventarioService {

    // Tipos de movimiento permitidos (coinciden con el ENUM de la BD).
    const TIPOS = [
        'INVENTARIO_INICIAL',
        'ENTRADA_COMPRA',
        'SALIDA_VENTA',
        'AJUSTE_ENTRADA',
        'AJUSTE_SALIDA',
        'DEVOLUCION_VENTA',
        'DEVOLUCION_COMPRA',
        'MERMA',
    ];

    // Tipos que restan stock (la cantidad se guarda negativa).
    const TIPOS_SALIDA = [
        'SALIDA_VENTA',
        'AJUSTE_SALIDA',
        'DEVOLUCION_COMPRA',
        'MERMA',
    ];

    /**
     * Aplica un movimiento de inventario de forma atómica.
     *
     * REQUISITO: debe existir una transacción activa en $pdo (BEGIN ya emitido
     * por el llamador). El servicio bloquea la fila del producto con
     * SELECT ... FOR UPDATE para evitar condiciones de carrera entre ventas
     * simultáneas del mismo producto.
     *
     * Orden garantizado dentro de la transacción del llamador:
     *   leer stock (FOR UPDATE) -> validar -> actualizar stock
     *   -> insertar Kardex.
     * Si cualquiera de los pasos falla, el llamador hace ROLLBACK y el stock
     * queda exactamente como estaba (nunca "stock sin Kardex" ni al revés).
     *
     * @param PDO      $pdo
     * @param int      $producto_id
     * @param int|null $usuario_id   Usuario que ejecuta la operación (puede ser NULL: migraciones).
     * @param string   $tipo         Tipo de movimiento (constantes de esta clase).
     * @param int      $cantidad     Cantidad positiva SIEMPRE; el servicio la
     *                               convierte a negativa para los tipos de salida.
     * @param int|null $referencia_id
     * @param string|null $referencia_tipo  'VENTA', 'VENTA_ANULADA', 'PRODUCTO', ...
     * @param string|null $observaciones
     * @param float|null $costo_unitario    Si es NULL se usa productos.precio_compra.
     * @return array{stock_anterior:int, stock_nuevo:int, cantidad:int}
     * @throws RuntimeException|Exception
     */
    public static function aplicarMovimiento($pdo, $producto_id, $usuario_id, $tipo, $cantidad,
                                             $referencia_id = null, $referencia_tipo = null,
                                             $observaciones = null, $costo_unitario = null) {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('Kardex: se requiere una transacción activa.');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new Exception('Tipo de movimiento de inventario no válido.');
        }

        $producto_id = intval($producto_id);
        $usuario_id  = !empty($usuario_id) ? intval($usuario_id) : null;
        $cantidad    = intval($cantidad);
        if ($producto_id <= 0 || $cantidad == 0) {
            throw new Exception('Movimiento de inventario inválido: producto o cantidad incorrectos.');
        }

        // Normalizar signo: entradas positivas, salidas negativas.
        $es_salida = in_array($tipo, self::TIPOS_SALIDA, true);
        if ($es_salida && $cantidad > 0)   { $cantidad = -$cantidad; }
        if (!$es_salida && $cantidad < 0)  { $cantidad = abs($cantidad); }

        // Bloquear la fila del producto contra escrituras concurrentes.
        $stmt = $pdo->prepare(
            "SELECT id, stock, disponibilidad, precio_compra FROM productos WHERE id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $producto_id]);
        $prod = $stmt->fetch();
        if (!$prod) {
            throw new Exception('El producto no existe o no está disponible.');
        }

        $stock_anterior = intval($prod['stock']);
        $stock_nuevo = $stock_anterior + $cantidad;
        if ($stock_nuevo < 0) {
            throw new Exception(
                "Stock insuficiente para '$tipo'. Disponible: $stock_anterior, solicitado: " . abs($cantidad) . "."
            );
        }

        // La disponibilidad derivada (Disponible/Agotado) se recalcula según
        // stock, pero NUNCA se toca el estado administrativo 'Descontinuado'
        // (eso lo deciden las operaciones de catálogo, no el inventario).
        $nueva_disp = $prod['disponibilidad'];
        if ($prod['disponibilidad'] !== 'Descontinuado') {
            $nueva_disp = $stock_nuevo <= 0 ? 'Agotado' : 'Disponible';
        }

        $upd = $pdo->prepare("UPDATE productos SET stock = :stock, disponibilidad = :disp WHERE id = :id");
        $upd->execute([':stock' => $stock_nuevo, ':disp' => $nueva_disp, ':id' => $producto_id]);

        $costo = null;
        if ($costo_unitario !== null && $costo_unitario !== '') {
            $costo = round(floatval($costo_unitario), 2);
        } elseif (floatval($prod['precio_compra']) > 0) {
            $costo = round(floatval($prod['precio_compra']), 2);
        }

        $ins = $pdo->prepare(
            "INSERT INTO movimientos_inventario
                (producto_id, usuario_id, tipo_movimiento, referencia_id, referencia_tipo,
                 cantidad, stock_anterior, stock_nuevo, costo_unitario, observaciones)
             VALUES
                (:pid, :uid, :tipo, :rid, :rtipo, :cant, :ant, :nuevo, :costo, :obs)"
        );
        $ins->execute([
            ':pid'   => $producto_id,
            ':uid'   => $usuario_id,
            ':tipo'  => $tipo,
            ':rid'   => $referencia_id !== null ? intval($referencia_id) : null,
            ':rtipo' => $referencia_tipo !== null ? substr(trim($referencia_tipo), 0, 50) : null,
            ':cant'  => $cantidad,
            ':ant'   => $stock_anterior,
            ':nuevo' => $stock_nuevo,
            ':costo' => $costo,
            ':obs'   => !empty($observaciones) ? mb_substr(trim($observaciones), 0, 500) : null,
        ]);

        return ['stock_anterior' => $stock_anterior, 'stock_nuevo' => $stock_nuevo, 'cantidad' => $cantidad];
    }

    /**
     * Lista los movimientos Kardex de un producto (o todos si $producto_id es null).
     *
     * @param int|null $producto_id
     * @param int      $limite
     * @return array
     */
    public static function obtenerMovimientos($producto_id = null, $limite = 50) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT m.*, p.nombre AS nombre_producto, u.nombre AS nombre_usuario
                    FROM movimientos_inventario m
                    JOIN productos p ON m.producto_id = p.id
                    LEFT JOIN usuarios u ON m.usuario_id = u.id";
            $params = [];
            if ($producto_id !== null && intval($producto_id) > 0) {
                $sql .= " WHERE m.producto_id = :pid";
                $params[':pid'] = intval($producto_id);
            }
            $sql .= " ORDER BY m.fecha DESC, m.id DESC LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            $params[':lim'] = intval($limite);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al obtener movimientos de inventario: " . $e->getMessage());
            return [];
        }
    }
}
