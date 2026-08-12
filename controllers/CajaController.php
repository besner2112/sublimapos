<?php
// ==========================================
// Controlador del Módulo de Caja (Arqueos)
// Fase 7: Caja Ampliada
// Movimientos soportados: APERTURA, INGRESO_VENTA, INGRESO, RETIRO,
// EGRESO_DEVOLUCION (Fase 6) y CIERRE.
// El efectivo disponible (saldo) se calcula SIEMPRE con la misma función:
//   saldo = monto_apertura + ventas en efectivo + ingresos
//           - retiros - devoluciones en efectivo
// (los movimientos APERTURA / INGRESO_VENTA / CIERRE son trazabilidad y
//  no se suman, evitando doble conteo con la tabla `ventas`).
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';

class CajaController {

    /**
     * Obtiene la sesión de caja activa de un usuario (o de cualquiera si es administrador).
     *
     * @param int $usuario_id
     * @return array|false Fila de la sesión si está abierta, de lo contrario false.
     */
    public static function obtenerSesionActiva($usuario_id) {
        try {
            $pdo = conectarBD();
            // Buscamos si hay alguna caja con estado 'Abierta'
            // En un POS real, cada cajero abre su caja, por lo que filtramos por su usuario_id,
            // pero si es admin queremos poder ver si la caja está abierta. Filtramos por usuario_id para cajeros.
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM cajas_sesiones c 
                    JOIN usuarios u ON c.usuario_id = u.id 
                    WHERE c.estado = 'Abierta' AND c.usuario_id = :usuario_id 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':usuario_id' => $usuario_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerSesionActiva: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Comprueba si existe alguna caja abierta en el sistema (sin importar el usuario).
     * Útil para flujos globales.
     */
    public static function obtenerCualquierSesionActiva() {
        try {
            $pdo = conectarBD();
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM cajas_sesiones c 
                    JOIN usuarios u ON c.usuario_id = u.id 
                    WHERE c.estado = 'Abierta' 
                    LIMIT 1";
            $stmt = $pdo->query($sql);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerCualquierSesionActiva: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inserta un movimiento en movimientos_caja dentro de la transacción activa.
     *
     * @param PDO $pdo Conexión con transacción iniciada.
     * @param int $caja_sesion_id
     * @param string $tipo APERTURA|INGRESO_VENTA|INGRESO|RETIRO|EGRESO_DEVOLUCION|CIERRE
     * @param float $monto
     * @param string|null $referencia_tipo
     * @param int|null $referencia_id
     * @param int|null $usuario_id
     * @param string|null $observaciones
     */
    private static function insertarMovimiento($pdo, $caja_sesion_id, $tipo, $monto, $referencia_tipo = null, $referencia_id = null, $usuario_id = null, $observaciones = null) {
        $sql = "INSERT INTO movimientos_caja (caja_sesion_id, tipo, monto, referencia_tipo, referencia_id, usuario_id, observaciones) 
                VALUES (:caja, :tipo, :monto, :ref_tipo, :ref_id, :usuario, :obs)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':caja'     => intval($caja_sesion_id),
            ':tipo'     => $tipo,
            ':monto'    => $monto,
            ':ref_tipo' => $referencia_tipo !== null ? $referencia_tipo : null,
            ':ref_id'   => $referencia_id !== null ? intval($referencia_id) : null,
            ':usuario'  => $usuario_id !== null ? intval($usuario_id) : null,
            ':obs'      => $observaciones
        ]);
    }

    /**
     * Abre un nuevo turno de caja (Apertura) y registra el movimiento APERTURA
     * en la misma transacción.
     *
     * @param int $usuario_id
     * @param float $monto_apertura
     * @return array
     */
    public static function abrirCaja($usuario_id, $monto_apertura) {
        $monto_apertura = limpiarMonto($monto_apertura);
        if ($monto_apertura < 0) {
            return ['success' => false, 'message' => 'El monto de apertura no puede ser negativo.'];
        }

        // Regla 5: un usuario NO puede tener dos cajas abiertas simultáneamente.
        $sesion_activa = self::obtenerSesionActiva($usuario_id);
        if ($sesion_activa) {
            return ['success' => false, 'message' => 'Ya posees un turno de caja abierto.'];
        }

        try {
            $pdo = conectarBD();
            $pdo->beginTransaction();

            $sql = "INSERT INTO cajas_sesiones (usuario_id, monto_apertura, estado) 
                    VALUES (:usuario_id, :monto_apertura, 'Abierta')";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                ':usuario_id'     => $usuario_id,
                ':monto_apertura' => $monto_apertura
            ]);

            if (!$result) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'No se pudo abrir la caja.'];
            }

            $session_id = $pdo->lastInsertId();

            // Movimiento APERTURA (trazabilidad del fondo inicial).
            self::insertarMovimiento(
                $pdo,
                $session_id,
                'APERTURA',
                $monto_apertura,
                'CAJA',
                $session_id,
                $usuario_id,
                'Apertura de turno de caja con fondo inicial.'
            );

            $pdo->commit();

            // Asignar el turno activo a la sesión PHP: sin esta variable
            // ninguna venta debe poder procesarse (ver VentaController).
            $_SESSION['caja_sesion_id'] = $session_id;

            $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Cajero';
            $usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';

            AuditoriaController::registrar(
                $usuario_id,
                $usuario_nombre,
                $usuario_rol,
                'Apertura de Caja',
                'POS / Caja',
                "Caja abierta con un fondo inicial de L. " . number_format($monto_apertura, 2) . " (Sesión ID: $session_id). Movimiento APERTURA registrado."
            );

            return ['success' => true, 'message' => 'Caja abierta correctamente.', 'id' => $session_id];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al abrir caja: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al abrir caja.'];
        }
    }

    /**
     * Calcula el estado actual (efectivo disponible) de un turno de caja.
     * Función ÚNICA de cálculo usada por la vista, los retiros y el cierre,
     * para garantizar coherencia absoluta.
     *
     * @param int $sesion_id
     * @param PDO|null $pdo Si se proporciona, usa la transacción activa.
     * @return array
     */
    public static function obtenerEstadoCaja($sesion_id, $pdo = null) {
        $db = $pdo ?? conectarBD();

        $sql_apertura = "SELECT monto_apertura FROM cajas_sesiones WHERE id = :id";
        $stmt = $db->prepare($sql_apertura);
        $stmt->execute([':id' => $sesion_id]);
        $monto_apertura = floatval($stmt->fetchColumn() ?: 0);

        $sumar = function ($tipo, $columna = 'tipo') use ($db, $sesion_id) {
            $sql = "SELECT COALESCE(SUM(monto), 0) FROM movimientos_caja 
                    WHERE caja_sesion_id = :caja AND $columna = :tipo";
            $stmt = $db->prepare($sql);
            $stmt->execute([':caja' => $sesion_id, ':tipo' => $tipo]);
            return floatval($stmt->fetchColumn());
        };

        // Ventas en efectivo se leen de `ventas` (no de movimientos) para no
        // duplicar: el movimiento INGRESO_VENTA es trazabilidad del historial.
        $sql_ventas = "SELECT COALESCE(SUM(total), 0) FROM ventas 
                       WHERE caja_sesion_id = :caja AND estado = 'Completada' AND metodo_pago = 'Efectivo'";
        $stmt = $db->prepare($sql_ventas);
        $stmt->execute([':caja' => $sesion_id]);
        $ventas_efectivo = floatval($stmt->fetchColumn());

        $ingresos = $sumar('INGRESO');
        $retiros = $sumar('RETIRO');
        $devoluciones = $sumar('EGRESO_DEVOLUCION');

        $saldo_efectivo = $monto_apertura + $ventas_efectivo + $ingresos - $retiros - $devoluciones;

        return [
            'monto_apertura'   => $monto_apertura,
            'ventas_efectivo'  => $ventas_efectivo,
            'ingresos'         => $ingresos,
            'retiros'          => $retiros,
            'devoluciones'     => $devoluciones,
            'saldo_efectivo'   => $saldo_efectivo
        ];
    }

    /**
     * Registra un ingreso o retiro de caja (Fase 7).
     * Transaccional: bloquea la sesión con FOR UPDATE; el retiro no puede
     * superar el efectivo disponible; caja cerrada no recibe movimientos.
     *
     * @param int $usuario_id
     * @param string $tipo 'INGRESO' | 'RETIRO'
     * @param float $monto
     * @param string $motivo Obligatorio (mínimo 3 caracteres).
     * @return array
     */
    public static function registrarIngresoRetiro($usuario_id, $tipo, $monto, $motivo) {
        $tipo = in_array($tipo, ['INGRESO', 'RETIRO']) ? $tipo : null;
        if (!$tipo) {
            return ['success' => false, 'message' => 'Tipo de movimiento no válido.'];
        }

        $monto = limpiarMonto($monto);
        if ($monto <= 0) {
            return ['success' => false, 'message' => 'El monto debe ser mayor a cero.'];
        }

        $motivo = trim((string)$motivo);
        if (mb_strlen($motivo) < 3) {
            return ['success' => false, 'message' => 'Debes indicar un motivo/observación (mínimo 3 caracteres).'];
        }

        $sesion = self::obtenerSesionActiva($usuario_id);
        if (!$sesion) {
            return ['success' => false, 'message' => 'No tienes un turno de caja abierto.'];
        }

        try {
            $pdo = conectarBD();
            $pdo->beginTransaction();

            // Regla 6: la caja debe estar Abierta para recibir movimientos.
            $sql = "SELECT * FROM cajas_sesiones WHERE id = :id AND estado = 'Abierta' FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $sesion['id']]);
            $sesion_bloqueada = $stmt->fetch();

            if (!$sesion_bloqueada) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La caja está cerrada y no puede recibir nuevos movimientos.'];
            }

            // IDOR (Fase 3): el cajero solo opera su propia caja.
            $es_admin = ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
            if (!$es_admin && intval($sesion_bloqueada['usuario_id']) !== intval($usuario_id)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'No tienes permisos para operar el turno de otro usuario.'];
            }

            // Saldo disponible calculado con la misma función que la vista.
            $estado = self::obtenerEstadoCaja($sesion_bloqueada['id'], $pdo);
            $saldo = $estado['saldo_efectivo'];

            // Regla 7: un retiro no puede superar el efectivo disponible.
            if ($tipo === 'RETIRO' && $monto > $saldo) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'El retiro (L. ' . number_format($monto, 2) . ') supera el efectivo disponible (L. ' . number_format($saldo, 2) . ').'
                ];
            }

            self::insertarMovimiento(
                $pdo,
                $sesion_bloqueada['id'],
                $tipo,
                $monto,
                'CAJA',
                $sesion_bloqueada['id'],
                $usuario_id,
                'Motivo: ' . $motivo
            );

            $pdo->commit();

            $nuevo_saldo = $tipo === 'INGRESO' ? $saldo + $monto : $saldo - $monto;

            $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Cajero';
            $usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';

            AuditoriaController::registrar(
                $usuario_id,
                $usuario_nombre,
                $usuario_rol,
                ($tipo === 'INGRESO' ? 'Ingreso de Caja' : 'Retiro de Caja'),
                'POS / Caja',
                ($tipo === 'INGRESO' ? 'Ingreso' : 'Retiro') . " de L. " . number_format($monto, 2)
                    . " (Motivo: $motivo; Caja ID: {$sesion_bloqueada['id']}). Nuevo saldo de efectivo: L. " . number_format($nuevo_saldo, 2)
            );

            return [
                'success' => true,
                'message' => ($tipo === 'INGRESO' ? 'Ingreso' : 'Retiro') . ' registrado correctamente.',
                'saldo_efectivo' => $nuevo_saldo
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al registrar movimiento de caja: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al registrar el movimiento.'];
        }
    }

    /**
     * Realiza los cálculos y cierra el turno de caja (Arqueo y Cierre) ampliado.
     * Transaccional: bloquea la sesión con FOR UPDATE, calcula las partidas
     * (apertura, ventas en efectivo, ingresos, retiros, devoluciones, esperado),
     * persiste el arqueo, registra el movimiento CIERRE y marca estado 'Cerrada'.
     *
     * @param int $sesion_id ID de la sesión de caja
     * @param float $monto_cierre_efectivo Efectivo real contado
     * @param float $monto_cierre_tarjeta Tarjeta real contada (comprobantes)
     * @param string $observaciones Comentarios adicionales
     * @return array
     */
    public static function cerrarCaja($sesion_id, $monto_cierre_efectivo, $monto_cierre_tarjeta, $observaciones = '') {
        $monto_cierre_efectivo = limpiarMonto($monto_cierre_efectivo);
        $monto_cierre_tarjeta = limpiarMonto($monto_cierre_tarjeta);
        $observaciones = trim($observaciones);

        try {
            $pdo = conectarBD();
            $pdo->beginTransaction();

            // 1. Obtener la sesión de caja BLOQUEADA. Cierre duplicado imposible:
            //    solo las sesiones en estado 'Abierta' pueden cerrarse.
            $sql_sesion = "SELECT * FROM cajas_sesiones WHERE id = :id AND estado = 'Abierta' FOR UPDATE";
            $stmt_sesion = $pdo->prepare($sql_sesion);
            $stmt_sesion->execute([':id' => $sesion_id]);
            $sesion = $stmt_sesion->fetch();

            if (!$sesion) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'La sesión de caja especificada no existe o ya está cerrada.'];
            }

            // IDOR (Fase 3): el cajero solo puede cerrar su propio turno;
            // el administrador puede cerrar cualquier turno.
            $es_admin = ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
            if (!$es_admin && intval($sesion['usuario_id']) !== intval($_SESSION['usuario_id'] ?? 0)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'No tienes permisos para cerrar el turno de otro usuario.'];
            }

            // 2. Calcular ventas registradas en esta sesión de caja agrupadas por método de pago
            $sql_ventas = "SELECT 
                            SUM(CASE WHEN metodo_pago = 'Efectivo' THEN total ELSE 0 END) as total_efectivo,
                            SUM(CASE WHEN metodo_pago = 'Tarjeta' THEN total ELSE 0 END) as total_tarjeta,
                            SUM(CASE WHEN metodo_pago = 'Transferencia' THEN total ELSE 0 END) as total_transferencia,
                            SUM(total) as total_vendido
                           FROM ventas 
                           WHERE caja_sesion_id = :caja_sesion_id AND estado = 'Completada'";
            
            $stmt_ventas = $pdo->prepare($sql_ventas);
            $stmt_ventas->execute([':caja_sesion_id' => $sesion_id]);
            $ventas_data = $stmt_ventas->fetch();

            $total_efectivo = floatval($ventas_data['total_efectivo'] ?? 0);
            $total_tarjeta = floatval($ventas_data['total_tarjeta'] ?? 0);
            $total_transferencia = floatval($ventas_data['total_transferencia'] ?? 0);
            $total_vendido = floatval($ventas_data['total_vendido'] ?? 0);

            // 3. Partidas del turno desde movimientos_caja (Fase 7)
            $sql_partidas = "SELECT 
                                COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto ELSE 0 END), 0) as ingresos,
                                COALESCE(SUM(CASE WHEN tipo = 'RETIRO' THEN monto ELSE 0 END), 0) as retiros,
                                COALESCE(SUM(CASE WHEN tipo = 'EGRESO_DEVOLUCION' THEN monto ELSE 0 END), 0) as devoluciones
                             FROM movimientos_caja 
                             WHERE caja_sesion_id = :caja_sesion_id";
            $stmt_partidas = $pdo->prepare($sql_partidas);
            $stmt_partidas->execute([':caja_sesion_id' => $sesion_id]);
            $partidas = $stmt_partidas->fetch();

            $monto_ingresos = floatval($partidas['ingresos'] ?? 0);
            $monto_retiros = floatval($partidas['retiros'] ?? 0);
            $monto_devoluciones = floatval($partidas['devoluciones'] ?? 0);

            // 4. Calcular montos esperados (misma fórmula que obtenerEstadoCaja)
            $monto_apertura = floatval($sesion['monto_apertura']);

            // Efectivo esperado = Apertura + Ventas Efectivo + Ingresos - Retiros - Devoluciones
            $efectivo_esperado = $monto_apertura + $total_efectivo + $monto_ingresos - $monto_retiros - $monto_devoluciones;
            $tarjeta_esperada = $total_tarjeta;

            // Diferencia en efectivo
            $dif_efectivo = $monto_cierre_efectivo - $efectivo_esperado;
            // Diferencia en tarjeta
            $dif_tarjeta = $monto_cierre_tarjeta - $tarjeta_esperada;

            // Diferencia total del arqueo de caja
            $diferencia_total = $dif_efectivo + $dif_tarjeta;

            $observaciones_final = $observaciones !== '' ? $observaciones
                : 'Sin observaciones adicionales.';

            // 5. Cerrar la sesión de caja (arqueo ampliado) dentro de la transacción
            $sql_update = "UPDATE cajas_sesiones SET 
                            monto_cierre_efectivo = :monto_cierre_efectivo,
                            monto_cierre_tarjeta = :monto_cierre_tarjeta,
                            monto_ventas_calculado = :monto_ventas_calculado,
                            monto_ventas_efectivo = :monto_ventas_efectivo,
                            monto_ingresos = :monto_ingresos,
                            monto_retiros = :monto_retiros,
                            monto_devoluciones = :monto_devoluciones,
                            efectivo_esperado = :efectivo_esperado,
                            diferencia = :diferencia,
                            fecha_cierre = NOW(),
                            estado = 'Cerrada',
                            observaciones = :observaciones 
                           WHERE id = :id";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':monto_cierre_efectivo' => $monto_cierre_efectivo,
                ':monto_cierre_tarjeta'  => $monto_cierre_tarjeta,
                ':monto_ventas_calculado' => $total_vendido,
                ':monto_ventas_efectivo'  => $total_efectivo,
                ':monto_ingresos'         => $monto_ingresos,
                ':monto_retiros'          => $monto_retiros,
                ':monto_devoluciones'     => $monto_devoluciones,
                ':efectivo_esperado'      => $efectivo_esperado,
                ':diferencia'             => $diferencia_total,
                ':observaciones'          => $observaciones_final,
                ':id'                     => $sesion_id
            ]);

            // Movimiento CIERRE (trazabilidad del arqueo; no suma al saldo).
            self::insertarMovimiento(
                $pdo,
                $sesion_id,
                'CIERRE',
                0.00,
                'CAJA',
                $sesion_id,
                intval($_SESSION['usuario_id'] ?? $sesion['usuario_id']),
                "Cierre registrado. Efectivo esperado L. " . number_format($efectivo_esperado, 2)
                    . ", contado L. " . number_format($monto_cierre_efectivo, 2)
                    . ", tarjeta esperada L. " . number_format($tarjeta_esperada, 2)
                    . ", contada L. " . number_format($monto_cierre_tarjeta, 2)
                    . ", diferencia L. " . number_format($diferencia_total, 2)
            );

            $pdo->commit();

            // 6. Registrar en Log de Auditoría
            $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Cajero';
            $usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';
            $usuario_id = $_SESSION['usuario_id'] ?? $sesion['usuario_id'];
            
            $detalle_log = "Cierre de caja Turno ID $sesion_id. Ventas sistema: L. " 
                . number_format($total_vendido, 2) . " (Efectivo: L. " 
                . number_format($total_efectivo, 2) . "). Fondo Inicial: L. " 
                . number_format($monto_apertura, 2) . ". Ingresos: L. " 
                . number_format($monto_ingresos, 2) . ". Retiros: L. " 
                . number_format($monto_retiros, 2) . ". Devoluciones: L. " 
                . number_format($monto_devoluciones, 2) . ". Efectivo esperado: L. " 
                . number_format($efectivo_esperado, 2) . ". Entregado Efectivo: L. " 
                . number_format($monto_cierre_efectivo, 2) . ". Entregado Tarjeta: L. " 
                . number_format($monto_cierre_tarjeta, 2) . " (Esp: L. " 
                . number_format($tarjeta_esperada, 2) . "). Diferencia final: L. " 
                . number_format($diferencia_total, 2);
            
            AuditoriaController::registrar(
                $usuario_id,
                $usuario_nombre,
                $usuario_rol,
                'Cierre de Caja',
                'POS / Caja',
                $detalle_log
            );

            // Liberar el turno activo de la sesión PHP: si el usuario que
            // cerró era el dueño de esa caja, ya no debe poder cobrar más
            // sin abrir un nuevo turno.
            if (($_SESSION['caja_sesion_id'] ?? null) == $sesion_id) {
                unset($_SESSION['caja_sesion_id']);
            }

            return [
                'success' => true,
                'message' => 'Caja cerrada y arqueada exitosamente.',
                'data' => [
                    'monto_apertura' => $monto_apertura,
                    'total_vendido' => $total_vendido,
                    'ventas_efectivo' => $total_efectivo,
                    'ingresos' => $monto_ingresos,
                    'retiros' => $monto_retiros,
                    'devoluciones' => $monto_devoluciones,
                    'efectivo_esperado' => $efectivo_esperado,
                    'tarjeta_esperada' => $tarjeta_esperada,
                    'monto_cierre_efectivo' => $monto_cierre_efectivo,
                    'monto_cierre_tarjeta' => $monto_cierre_tarjeta,
                    'diferencia_efectivo' => $dif_efectivo,
                    'diferencia_tarjeta' => $dif_tarjeta,
                    'diferencia' => $diferencia_total
                ]
            ];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al cerrar caja: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al guardar arqueo de caja.'];
        }
    }

    /**
     * Lista los movimientos de caja de un turno (historial).
     *
     * @param int $sesion_id
     * @param PDO|null $pdo
     * @return array
     */
    public static function listarMovimientosCaja($sesion_id, $pdo = null) {
        try {
            $db = $pdo ?? conectarBD();
            $sql = "SELECT m.*, u.nombre AS usuario_nombre 
                    FROM movimientos_caja m 
                    LEFT JOIN usuarios u ON u.id = m.usuario_id 
                    WHERE m.caja_sesion_id = :caja 
                    ORDER BY m.id ASC 
                    LIMIT 500";
            $stmt = $db->prepare($sql);
            $stmt->execute([':caja' => $sesion_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en listarMovimientosCaja: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el listado completo de todos los turnos de caja para auditoría de administrador.
     */
    public static function obtenerHistorialCajas() {
        try {
            $pdo = conectarBD();
            $sql = "SELECT c.*, u.nombre as nombre_usuario 
                    FROM cajas_sesiones c 
                    JOIN usuarios u ON c.usuario_id = u.id 
                    ORDER BY c.fecha_apertura DESC LIMIT 100";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerHistorialCajas: " . $e->getMessage());
            return [];
        }
    }
}