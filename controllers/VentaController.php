<?php
// ==========================================
// Controlador de Ventas y Facturación
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';
require_once __DIR__ . '/CajaController.php';
require_once __DIR__ . '/../helpers/InventarioService.php';

class VentaController {

    /**
     * Procesa una venta en la caja de POS. Realizado bajo transacciones SQL para robustez.
     *
     * @param int $usuario_id ID del cajero/administrador
     * @param int|null $cliente_id ID del cliente (opcional)
     * @param array $carrito Array de ítems del carrito: [ ['id' => 1, 'cantidad' => 2], ... ]
     * @param string $metodo_pago 'Efectivo', 'Tarjeta', 'Transferencia'
     * @param float $monto_pagado Dinero proporcionado por el cliente
     * @return array Respuesta de éxito o fracaso
     */
    public static function procesarVenta($usuario_id, $cliente_id, $carrito, $metodo_pago, $monto_pagado) {
        $usuario_id = intval($usuario_id);
        $cliente_id = !empty($cliente_id) ? intval($cliente_id) : 1; // 1 = Público General por defecto
        $metodo_pago = in_array($metodo_pago, ['Efectivo', 'Tarjeta', 'Transferencia']) ? $metodo_pago : 'Efectivo';
        $monto_pagado = limpiarMonto($monto_pagado);

        if (empty($carrito) || !is_array($carrito)) {
            return ['success' => false, 'message' => 'El carrito está vacío. Agregue productos.'];
        }

        // 1. Validación obligatoria e inmediata de la sesión de caja.
        // Ninguna venta puede procesarse si no existe una caja abierta
        // asignada a la sesión de PHP del usuario actual.
        if (empty($_SESSION['caja_sesion_id'])) {
            return ['success' => false, 'message' => 'No puedes procesar ventas: no tienes ninguna caja abierta. Realiza la Apertura de Caja primero.'];
        }

        $pdo = getDB();

        // 2. Confirmar contra la base de datos que ese turno sigue realmente
        // abierto (defensa en profundidad: la variable de sesión por sí
        // sola no es suficiente si la caja fue cerrada desde otra pestaña).
        $caja_sesion = CajaController::obtenerSesionActiva($usuario_id);
        if (!$caja_sesion || $caja_sesion['id'] != $_SESSION['caja_sesion_id']) {
            unset($_SESSION['caja_sesion_id']);
            return ['success' => false, 'message' => 'No puedes procesar ventas: tu turno de caja no está activo. Realiza la Apertura de Caja primero.'];
        }
        $caja_sesion_id = $caja_sesion['id'];

        try {
            // 2. Iniciar Transacción SQL
            $pdo->beginTransaction();

            $subtotal_acumulado = 0.00;
            $items_procesados = [];

            // 3. Validar stock y disponibilidad física de cada producto
            foreach ($carrito as $item) {
                $prod_id = intval($item['id'] ?? 0);
                $cantidad = intval($item['cantidad'] ?? 0);

                if ($prod_id <= 0 || $cantidad <= 0) {
                    throw new Exception("Datos de producto inválidos en el carrito.");
                }

                // Consultar producto con bloqueo de fila select...for update para evitar race conditions en stock
                $sql_prod = "SELECT * FROM productos WHERE id = :id AND activo = 1 FOR UPDATE";
                $stmt_prod = $pdo->prepare($sql_prod);
                $stmt_prod->execute([':id' => $prod_id]);
                $producto = $stmt_prod->fetch();

                if (!$producto) {
                    throw new Exception("El producto seleccionado ID $prod_id no está disponible en la base de datos.");
                }

                if ($producto['disponibilidad'] == 'Descontinuado') {
                    throw new Exception("El producto '{$producto['nombre']}' ha sido descontinuado y no se puede vender.");
                }

                if ($producto['stock'] < $cantidad) {
                    throw new Exception("Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock']} unidades, solicitado: $cantidad.");
                }

                // Cálculo financiero unitario
                $precio_unitario = floatval($producto['precio_venta']);
                $subtotal_item = $precio_unitario * $cantidad;
                $subtotal_acumulado += $subtotal_item;

                $items_procesados[] = [
                    'producto_id'    => $prod_id,
                    'nombre'         => $producto['nombre'],
                    'cantidad'       => $cantidad,
                    'precio_unitario'=> $precio_unitario,
                    'subtotal'       => $subtotal_item,
                    'stock_actual'   => $producto['stock']
                ];
            }

            // 4. Calcular Impuestos y Cambio
            // Definimos IVA del 16% incluido en el total o agregado. Lo calcularemos como desglosado.
            // Para simplificación financiera estándar:
            // Subtotal es total / 1.16 | Impuesto es total - Subtotal (IVA incluido)
            $total_factura = $subtotal_acumulado;
            $subtotal_neto = $total_factura / 1.16;
            $impuesto = $total_factura - $subtotal_neto;

            if ($monto_pagado < $total_factura && $metodo_pago == 'Efectivo') {
                throw new Exception("El monto pagado (L. " . number_format($monto_pagado, 2) . ") es menor que el total de la compra (L. " . number_format($total_factura, 2) . ").");
            }

            // Cambiar monto pagado para Tarjetas/Transferencias
            if ($metodo_pago != 'Efectivo') {
                $monto_pagado = $total_factura;
            }

            $cambio = $monto_pagado - $total_factura;

            // Generar número de factura único
            $factura_codigo = "SUB-" . date("YmdHis") . "-" . mt_rand(10, 99);

            // 5. Insertar cabecera de la venta
            $sql_venta = "INSERT INTO ventas 
                          (caja_sesion_id, usuario_id, cliente_id, num_factura, subtotal, impuesto, total, metodo_pago, monto_pagado, cambio, estado) 
                          VALUES 
                          (:caja, :usuario, :cliente, :num_factura, :subtotal, :impuesto, :total, :metodo_pago, :monto, :cambio, 'Completada')";
            
            $stmt_venta = $pdo->prepare($sql_venta);
            $stmt_venta->execute([
                ':caja'        => $caja_sesion_id,
                ':usuario'     => $usuario_id,
                ':cliente'     => $cliente_id,
                ':num_factura' => $factura_codigo,
                ':subtotal'    => $subtotal_neto,
                ':impuesto'    => $impuesto,
                ':total'       => $total_factura,
                ':metodo_pago' => $metodo_pago,
                ':monto'       => $monto_pagado,
                ':cambio'      => $cambio
            ]);

            $venta_id = $pdo->lastInsertId();

            // 6.b (Fase 7): el efectivo que entra a la gaveta genera UN movimiento
            // INGRESO_VENTA en la misma transacción. Tarjeta/Transferencia no
            // incrementan el efectivo físico, por lo que no generan movimiento.
            if ($metodo_pago === 'Efectivo') {
                $sql_mov = "INSERT INTO movimientos_caja 
                            (caja_sesion_id, tipo, monto, referencia_tipo, referencia_id, usuario_id, observaciones) 
                            VALUES (:caja, 'INGRESO_VENTA', :monto, 'VENTA', :venta, :usuario, :obs)";
                $stmt_mov = $pdo->prepare($sql_mov);
                $stmt_mov->execute([
                    ':caja'    => $caja_sesion_id,
                    ':monto'   => $total_factura,
                    ':venta'   => $venta_id,
                    ':usuario' => $usuario_id,
                    ':obs'     => "Venta en efectivo (factura $factura_codigo)."
                ]);
            }

            // 6. Insertar detalle de venta y descontar stock con Kardex (Fase 4)
            $sql_det = "INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                        VALUES (:venta, :producto, :cantidad, :precio, :subtotal)";
            $stmt_det = $pdo->prepare($sql_det);

            foreach ($items_procesados as $item) {
                // Registrar detalle
                $stmt_det->execute([
                    ':venta'    => $venta_id,
                    ':producto' => $item['producto_id'],
                    ':cantidad' => $item['cantidad'],
                    ':precio'   => $item['precio_unitario'],
                    ':subtotal' => $item['subtotal']
                ]);

                // Descontar stock vía servicio central: bloquea la fila con
                // FOR UPDATE, recalcula disponibilidad (nunca toca Descontinuado)
                // y crea el movimiento Kardex SALIDA_VENTA en la misma transacción.
                InventarioService::aplicarMovimiento(
                    $pdo,
                    $item['producto_id'],
                    $usuario_id,
                    'SALIDA_VENTA',
                    $item['cantidad'],
                    $venta_id,
                    'VENTA',
                    "Salida por venta (factura $factura_codigo)."
                );
            }

            // 7. Enviar logs de auditoría
            $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Cajero';
            $usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';
            
            AuditoriaController::registrar(
                $usuario_id,
                $usuario_nombre,
                $usuario_rol,
                'Procesar Venta',
                'POS / Ventas',
                "Factura $factura_codigo creada por L. " . number_format($total_factura, 2) . " (Método: $metodo_pago). Venta ID: $venta_id"
            );

            // 8. Confirmar Transacción
            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Venta procesada con éxito.',
                'venta_id'=> $venta_id,
                'factura' => $factura_codigo,
                'cambio'  => $cambio,
                'total'   => $total_factura
            ];

        } catch (Exception $e) {
            // Revertir todo en caso de error/excepción y liberar locks
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al procesar venta: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Anula una venta (solo Admin). Reintegra el stock al inventario mediante
     * el servicio central de Kardex (tipo AJUSTE_ENTRADA, referencia
     * VENTA_ANULADA) dentro de la misma transacción. No modifica el estado
     * administrativo del producto (un producto Descontinuado sigue
     * descontinuado tras la anulación).
     *
     * @param int $venta_id ID de la factura
     * @return array
     */
    public static function anularVenta($venta_id) {
        $venta_id = intval($venta_id);
        $pdo = getDB();

        try {
            $pdo->beginTransaction();

            // 1. Obtener la venta
            $sql_v = "SELECT * FROM ventas WHERE id = :id AND estado = 'Completada' FOR UPDATE";
            $stmt_v = $pdo->prepare($sql_v);
            $stmt_v->execute([':id' => $venta_id]);
            $venta = $stmt_v->fetch();

            if (!$venta) {
                throw new Exception("La venta no existe, ya fue anulada o el turno está archivado.");
            }

            // 2. Obtener los detalles de la venta para reintegrar el stock
            $sql_d = "SELECT * FROM detalle_ventas WHERE venta_id = :venta_id";
            $stmt_d = $pdo->prepare($sql_d);
            $stmt_d->execute([':venta_id' => $venta_id]);
            $detalles = $stmt_d->fetchAll();

            foreach ($detalles as $det) {
                // Reintegro vía servicio central (Kardex) dentro de la misma
                // transacción. Decisión de diseño documentada (Fase 4):
                //   tipo = AJUSTE_ENTRADA — la anulación es una reversión
                //   administrativa que reintegra mercancía al inventario; NO es
                //   una devolución física del cliente (DEVOLUCION_VENTA queda
                //   reservado para la futura fase de devoluciones).
                //   referencia_tipo = VENTA_ANULADA — rastreable a la venta.
                // El servicio recalcula la disponibilidad derivada (Agotado ->
                // Disponible) pero NUNCA modifica el estado administrativo
                // 'Descontinuado' (corrige el bug de Fase 1: anular una venta
                // ya no reactiva productos descontinuados).
                InventarioService::aplicarMovimiento(
                    $pdo,
                    $det['producto_id'],
                    $_SESSION['usuario_id'] ?? null,
                    'AJUSTE_ENTRADA',
                    $det['cantidad'],
                    $venta_id,
                    'VENTA_ANULADA',
                    "Reintegro por anulación de la factura '{$venta['num_factura']}'."
                );
            }

            // 3. Cambiar estado de la venta a 'Anulada'
            $sql_anular = "UPDATE ventas SET estado = 'Anulada' WHERE id = :id";
            $pdo->prepare($sql_anular)->execute([':id' => $venta_id]);

            // 4. Registrar auditoría
            $usuario_id = $_SESSION['usuario_id'] ?? null;
            $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
            $usuario_rol = $_SESSION['usuario_rol'] ?? 'Administrador';
            
            AuditoriaController::registrar(
                $usuario_id,
                $usuario_nombre,
                $usuario_rol,
                'Anular Venta',
                'POS / Ventas',
                "Se anuló la factura '{$venta['num_factura']}' reinsertando stock. ID Venta anterior: $venta_id"
            );

            $pdo->commit();
            return ['success' => true, 'message' => 'Venta anulada con éxito. Stock reintegrado.'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al anular venta: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function obtenerVentaPorId($venta_id) {
        try {
            $pdo = getDB();
            $sql = "SELECT v.*, u.nombre as nombre_cajero, c.nombre as nombre_cliente, c.identificacion as ident_cliente 
                    FROM ventas v
                    JOIN usuarios u ON v.usuario_id = u.id
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    WHERE v.id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $venta_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerVentaPorId: " . $e->getMessage());
            return false;
        }
    }

    public static function obtenerDetallesVenta($venta_id) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT dv.*, p.nombre as nombre_producto, p.codigo_barras 
                    FROM detalle_ventas dv
                    JOIN productos p ON dv.producto_id = p.id
                    WHERE dv.venta_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $venta_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerDetallesVenta: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Carga el listado general de ventas recientes (para estadísticas o reporte general)
     */
    public static function obtenerUltimasVentas($limite = 50) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT v.*, u.nombre as nombre_cajero, c.nombre as nombre_cliente 
                    FROM ventas v
                    JOIN usuarios u ON v.usuario_id = u.id
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    ORDER BY v.fecha_venta DESC LIMIT :limite";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limite', intval($limite), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerUltimasVentas: " . $e->getMessage());
            return [];
        }
    }
}
