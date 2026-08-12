<?php
// ==========================================
// Controlador de Reportes y Consultas Operativas (Fase 8)
// ==========================================
// SOLO LECTURA. No toca ninguna tabla transaccional.
// Toda consulta usa PDO + prepared statements + parámetros enlazados.
// Fechas en America/Tegucigalpa; el filtro final incluye 23:59:59.

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/CajaController.php';

// Zona horaria oficial del proyecto (requisito Fase 8: filtros de fecha).
date_default_timezone_set('America/Tegucigalpa');

class ReporteController {

    // ---------- Validaciones compartidas ----------

    /**
     * Valida una fecha en formato Y-m-d (inclusive checkdate).
     * @return string|null Fecha normalizada o null si es inválida.
     */
    private static function validarFecha($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }
        $partes = explode('-', $valor);
        $y = (int)$partes[0];
        $m = (int)$partes[1];
        $d = (int)$partes[2];
        return checkdate($m, $d, $y) ? $valor : null;
    }

    /**
     * Valida el par fecha_inicio / fecha_fin y devuelve los límites SQL
     * (inicio 00:00:00, fin 23:59:59). Ambas o ninguna.
     * @return array|false ['ini' => ..., 'fin' => ...] o false con $error
     */
    private static function validarRangoFechas(&$error) {
        $rawIni = trim((string)($_GET['fecha_inicio'] ?? ''));
        $rawFin = trim((string)($_GET['fecha_fin'] ?? ''));
        $inicio = self::validarFecha($rawIni);
        $fin = self::validarFecha($rawFin);

        if (($rawIni === '') !== ($rawFin === '')) {
            $error = 'Debe indicar ambas fechas (inicio y fin) para filtrar por período.';
            return false;
        }
        if ($rawIni === '') {
            return null; // sin filtro
        }
        if ($inicio === null || $fin === null) {
            $error = 'Formato de fecha inválido. Use el formato AAAA-MM-DD.';
            return false;
        }
        if ($inicio > $fin) {
            $error = 'La fecha inicial no puede ser posterior a la fecha final.';
            return false;
        }
        return [
            'ini' => $inicio . ' 00:00:00',
            'fin' => $fin . ' 23:59:59'
        ];
    }

    /**
     * Valida un ID entero no negativo.
     * @return int 0 = sin filtro.
     */
    private static function validarId($valor) {
        $v = filter_var($valor ?? 0, FILTER_VALIDATE_INT);
        return ($v === false || $v < 0) ? 0 : $v;
    }

    /**
     * Valida un valor contra una lista blanca.
     * @return string|null Valor si está en la lista, null si no.
     */
    private static function validarLista($valor, $opciones) {
        $v = trim((string)$valor);
        return in_array($v, $opciones, true) ? $v : null;
    }

    /**
     * Ejecuta una consulta preparada dinámica (WHERE construido con
     * parámetros enlazados, nombres de placeholder únicos).
     *
     * @param string $sql_base SELECT ... con %WHERE% y %ORDER%
     * @param array $wheres [sql, param] para cada condición (params ya bindeados)
     * @param array $orden
     * @param int $limite
     * @return array Filas (FETCH_ASSOC)
     */
    private static function consultar($sql_base, $wheres, $orden, $limite, $params_extra = []) {
        $pdo = conectarBD();
        $cond = count($wheres) ? implode(' AND ', array_column($wheres, 'sql')) : '';
        $sql = str_replace('%WHERE%', $cond !== '' ? 'WHERE ' . $cond : '', $sql_base)
            . ' ORDER BY ' . $orden
            . ' LIMIT :limite';

        $stmt = $pdo->prepare($sql);
        foreach ($wheres as $w) {
            foreach ($w['params'] as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        foreach ($params_extra as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ==========================================================
    // 1. REPORTE DE VENTAS
    // ==========================================================
    public static function reporteVentas() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $usuario_id = self::validarId($_GET['usuario_id'] ?? 0);
        $cliente_id = self::validarId($_GET['cliente_id'] ?? 0);
        $metodo = self::validarLista($_GET['metodo_pago'] ?? '', ['Efectivo', 'Tarjeta', 'Transferencia']);
        if (($_GET['metodo_pago'] ?? '') !== '' && $metodo === null) {
            return ['success' => false, 'message' => 'Método de pago no válido.'];
        }
        $estado = self::validarLista($_GET['estado'] ?? '', ['Completada', 'Anulada']);
        if (($_GET['estado'] ?? '') !== '' && $estado === null) {
            return ['success' => false, 'message' => 'Estado de venta no válido.'];
        }

        $wheres = [];
        $params = [];
        $i = 0;
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'v.fecha_venta >= :f_ini AND v.fecha_venta <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($usuario_id > 0) {
            $wheres[] = ['sql' => 'v.usuario_id = :u', 'params' => [':u' => $usuario_id]];
        }
        if ($cliente_id > 0) {
            $wheres[] = ['sql' => 'v.cliente_id = :cl', 'params' => [':cl' => $cliente_id]];
        }
        if ($metodo !== null) {
            $wheres[] = ['sql' => 'v.metodo_pago = :m', 'params' => [':m' => $metodo]];
        }
        if ($estado !== null) {
            $wheres[] = ['sql' => 'v.estado = :e', 'params' => [':e' => $estado]];
        }

        $sql_base = "SELECT v.id, v.fecha_venta, v.num_factura, u.nombre AS vendedor,
                            COALESCE(c.nombre, 'Público General') AS cliente,
                            v.metodo_pago, v.subtotal, v.impuesto, v.total, v.estado
                     FROM ventas v
                     JOIN usuarios u ON u.id = v.usuario_id
                     LEFT JOIN clientes c ON c.id = v.cliente_id
                     %WHERE%";
        $filas = self::consultar($sql_base, $wheres, 'v.fecha_venta DESC, v.id DESC', 5000);

        // Totales: monetarios SOLO de ventas Completadas (anuladas excluidas).
        $pdo = conectarBD();
        $sql_tot = "SELECT COUNT(*) AS cantidad,
                           COUNT(CASE WHEN v.estado = 'Anulada' THEN 1 END) AS anuladas,
                           CAST(COALESCE(SUM(CASE WHEN v.estado = 'Completada' THEN v.subtotal END), 0) AS DECIMAL(10,2)) AS subtotal,
                           CAST(COALESCE(SUM(CASE WHEN v.estado = 'Completada' THEN v.impuesto END), 0) AS DECIMAL(10,2)) AS impuesto,
                           CAST(COALESCE(SUM(CASE WHEN v.estado = 'Completada' THEN v.total END), 0) AS DECIMAL(10,2)) AS total,
                           CAST(COALESCE(SUM(CASE WHEN v.estado = 'Completada' AND v.metodo_pago = 'Efectivo' THEN v.total END), 0) AS DECIMAL(10,2)) AS total_efectivo,
                           CAST(COALESCE(SUM(CASE WHEN v.estado = 'Completada' AND v.metodo_pago = 'Tarjeta' THEN v.total END), 0) AS DECIMAL(10,2)) AS total_tarjeta
                    FROM ventas v";
        $cond = self::condiciones($wheres);
        $stmt = $pdo->prepare($sql_tot . ($cond !== '' ? ' WHERE ' . $cond : ''));
        self::enlazar($stmt, $wheres);
        $stmt->execute();
        $totales = $stmt->fetch();

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 2. REPORTE DE PRODUCTOS VENDIDOS
    // ==========================================================
    public static function reporteProductos() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $producto_id = self::validarId($_GET['producto_id'] ?? 0);
        $usuario_id = self::validarId($_GET['usuario_id'] ?? 0);

        $wheres = [];
        $params = [];
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'v.fecha_venta >= :f_ini AND v.fecha_venta <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($usuario_id > 0) {
            $wheres[] = ['sql' => 'v.usuario_id = :u', 'params' => [':u' => $usuario_id]];
        }
        // El filtro de producto va en el ON del JOIN (ver SQL).
        if ($producto_id > 0) {
            $wheres[] = ['sql' => 'dv.producto_id = :p', 'params' => [':p' => $producto_id]];
        }

        $sql_base = "SELECT p.id AS producto_id, p.nombre AS producto, p.precio_venta AS precio,
                            SUM(dv.cantidad) AS cantidad_vendida,
                            COALESCE(SUM(dev.cantidad), 0) AS cantidad_devuelta,
                            (SUM(dv.cantidad) - COALESCE(SUM(dev.cantidad), 0)) AS cantidad_neta,
                            CAST(SUM(dv.subtotal) AS DECIMAL(10,2)) AS subtotal,
                            CAST(COALESCE(SUM(dev.subtotal), 0) AS DECIMAL(10,2)) AS monto_devuelto,
                            CAST(SUM(dv.subtotal) - COALESCE(SUM(dev.subtotal), 0) AS DECIMAL(10,2)) AS total_generado
                     FROM productos p
                     JOIN detalle_ventas dv ON dv.producto_id = p.id
                     JOIN ventas v ON v.id = dv.venta_id AND v.estado = 'Completada'
                     LEFT JOIN (
                         SELECT d.venta_id, dd.producto_id,
                                SUM(dd.cantidad) AS cantidad, SUM(dd.subtotal) AS subtotal
                         FROM devoluciones d
                         JOIN detalle_devoluciones dd ON dd.devolucion_id = d.id
                         GROUP BY d.venta_id, dd.producto_id
                     ) dev ON dev.venta_id = v.id AND dev.producto_id = p.id
                     %WHERE%
                     GROUP BY p.id, p.nombre, p.precio_venta";
        $filas = self::consultar($sql_base, $wheres, 'SUM(dv.cantidad) DESC, p.nombre ASC', 5000);

        $totales = [
            'cantidad_vendida' => (int)array_sum(array_column($filas, 'cantidad_vendida')),
            'cantidad_devuelta' => (int)array_sum(array_column($filas, 'cantidad_devuelta')),
            'cantidad_neta' => (int)array_sum(array_column($filas, 'cantidad_neta')),
            'subtotal' => number_format(array_sum(array_column($filas, 'subtotal')), 2, '.', ''),
            'monto_devuelto' => number_format(array_sum(array_column($filas, 'monto_devuelto')), 2, '.', ''),
            'total_generado' => number_format(array_sum(array_column($filas, 'total_generado')), 2, '.', '')
        ];

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 3. REPORTE DE INVENTARIO / KARDEX
    // ==========================================================
    public static function reporteInventario() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $producto_id = self::validarId($_GET['producto_id'] ?? 0);
        $tipos = ['INVENTARIO_INICIAL', 'SALIDA_VENTA', 'AJUSTE_ENTRADA', 'AJUSTE_SALIDA',
                  'ENTRADA_COMPRA', 'DEVOLUCION_VENTA', 'DEVOLUCION_COMPRA', 'MERMA'];
        $tipo = self::validarLista($_GET['tipo_movimiento'] ?? '', $tipos);
        if (($_GET['tipo_movimiento'] ?? '') !== '' && $tipo === null) {
            return ['success' => false, 'message' => 'Tipo de movimiento no válido.'];
        }

        $wheres = [];
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'mi.fecha >= :f_ini AND mi.fecha <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($producto_id > 0) {
            $wheres[] = ['sql' => 'mi.producto_id = :p', 'params' => [':p' => $producto_id]];
        }
        if ($tipo !== null) {
            $wheres[] = ['sql' => 'mi.tipo_movimiento = :t', 'params' => [':t' => $tipo]];
        }

        $sql_base = "SELECT mi.id, mi.fecha, p.nombre AS producto, mi.tipo_movimiento,
                            mi.cantidad, mi.stock_anterior, mi.stock_nuevo,
                            mi.referencia_tipo AS referencia, mi.referencia_id,
                            COALESCE(u.nombre, 'Sistema') AS usuario, mi.observaciones
                     FROM movimientos_inventario mi
                     JOIN productos p ON p.id = mi.producto_id
                     LEFT JOIN usuarios u ON u.id = mi.usuario_id
                     %WHERE%";
        $filas = self::consultar($sql_base, $wheres, 'mi.id DESC', 2000);

        $totales = [
            'movimientos' => count($filas),
            'entradas' => count(array_filter($filas, function ($r) { return $r['cantidad'] > 0; })),
            'salidas' => count(array_filter($filas, function ($r) { return $r['cantidad'] < 0; }))
        ];

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 4. REPORTE DE COMPRAS
    // ==========================================================
    public static function reporteCompras() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $proveedor_id = self::validarId($_GET['proveedor_id'] ?? 0);
        $estado = self::validarLista($_GET['estado'] ?? '', ['BORRADOR', 'CONFIRMADA']);
        if (($_GET['estado'] ?? '') !== '' && $estado === null) {
            return ['success' => false, 'message' => 'Estado de compra no válido.'];
        }
        $num_doc = trim((string)($_GET['numero_documento'] ?? ''));
        if ($num_doc !== '' && mb_strlen($num_doc) > 100) {
            return ['success' => false, 'message' => 'Número de documento demasiado largo.'];
        }

        $wheres = [];
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'c.fecha_compra >= :f_ini AND c.fecha_compra <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($proveedor_id > 0) {
            $wheres[] = ['sql' => 'c.proveedor_id = :pr', 'params' => [':pr' => $proveedor_id]];
        }
        if ($estado !== null) {
            $wheres[] = ['sql' => 'c.estado = :e', 'params' => [':e' => $estado]];
        }
        if ($num_doc !== '') {
            // LIKE con parámetro enlazado: 'nunca' se concatena el valor crudo.
            $wheres[] = ['sql' => 'c.numero_documento LIKE :doc', 'params' => [':doc' => '%' . $num_doc . '%']];
        }

        $sql_base = "SELECT c.id, c.fecha_compra, pr.nombre AS proveedor,
                            c.numero_documento, c.estado, c.subtotal, c.impuesto, c.total
                     FROM compras c
                     JOIN proveedores pr ON pr.id = c.proveedor_id
                     %WHERE%";
        $filas = self::consultar($sql_base, $wheres, 'c.fecha_compra DESC, c.id DESC', 5000);

        // Totales monetarios SOLO de compras CONFIRMADAS.
        $pdo = conectarBD();
        $sql_tot = "SELECT COUNT(*) AS cantidad,
                           COUNT(CASE WHEN c.estado = 'BORRADOR' THEN 1 END) AS borradores,
                           CAST(COALESCE(SUM(CASE WHEN c.estado = 'CONFIRMADA' THEN c.subtotal END), 0) AS DECIMAL(10,2)) AS subtotal,
                           CAST(COALESCE(SUM(CASE WHEN c.estado = 'CONFIRMADA' THEN c.impuesto END), 0) AS DECIMAL(10,2)) AS impuesto,
                           CAST(COALESCE(SUM(CASE WHEN c.estado = 'CONFIRMADA' THEN c.total END), 0) AS DECIMAL(10,2)) AS total
                    FROM compras c";
        $cond = self::condiciones($wheres);
        $stmt = $pdo->prepare($sql_tot . ($cond !== '' ? ' WHERE ' . $cond : ''));
        self::enlazar($stmt, $wheres);
        $stmt->execute();
        $totales = $stmt->fetch();

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 5. REPORTE DE DEVOLUCIONES
    // ==========================================================
    public static function reporteDevoluciones() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $usuario_id = self::validarId($_GET['usuario_id'] ?? 0);
        $venta_id = self::validarId($_GET['venta_id'] ?? 0);
        $producto_id = self::validarId($_GET['producto_id'] ?? 0);

        $wheres = [];
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'd.fecha >= :f_ini AND d.fecha <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($usuario_id > 0) {
            $wheres[] = ['sql' => 'd.usuario_id = :u', 'params' => [':u' => $usuario_id]];
        }
        if ($venta_id > 0) {
            $wheres[] = ['sql' => 'd.venta_id = :v', 'params' => [':v' => $venta_id]];
        }
        if ($producto_id > 0) {
            $wheres[] = ['sql' => 'dd.producto_id = :p', 'params' => [':p' => $producto_id]];
        }

        $sql_base = "SELECT d.id AS devolucion_id, d.fecha, d.num_devolucion, d.venta_id,
                            COALESCE(u.nombre, 'Sistema') AS usuario, p.nombre AS producto,
                            p.id AS producto_id, dd.cantidad, dd.subtotal AS monto,
                            d.motivo, d.estado
                     FROM devoluciones d
                     JOIN detalle_devoluciones dd ON dd.devolucion_id = d.id
                     JOIN productos p ON p.id = dd.producto_id
                     LEFT JOIN usuarios u ON u.id = d.usuario_id
                     %WHERE%";
        // (ORDER fijo: d.id DESC, dd.id ASC, se pasa a consultar)
        $filas = self::consultar($sql_base, $wheres, 'd.id DESC, dd.id ASC', 5000);

        $totales = [
            'cantidad_devoluciones' => count(array_unique(array_column($filas, 'devolucion_id'))),
            'unidades_devueltas' => (int)array_sum(array_column($filas, 'cantidad')),
            'monto_total' => number_format(array_sum(array_column($filas, 'monto')), 2, '.', '')
        ];

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 6. REPORTE DE CAJA (usa los valores PERSISTIDOS del arqueo)
    // ==========================================================
    public static function reporteCaja() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $usuario_id = self::validarId($_GET['usuario_id'] ?? 0);
        $estado = self::validarLista($_GET['estado'] ?? '', ['Abierta', 'Cerrada']);
        if (($_GET['estado'] ?? '') !== '' && $estado === null) {
            return ['success' => false, 'message' => 'Estado de caja no válido.'];
        }

        $wheres = [];
        if ($fechas !== null) {
            $wheres[] = [
                'sql' => 'c.fecha_apertura >= :f_ini AND c.fecha_apertura <= :f_fin',
                'params' => [':f_ini' => $fechas['ini'], ':f_fin' => $fechas['fin']]
            ];
        }
        if ($usuario_id > 0) {
            $wheres[] = ['sql' => 'c.usuario_id = :u', 'params' => [':u' => $usuario_id]];
        }
        if ($estado !== null) {
            $wheres[] = ['sql' => 'c.estado = :e', 'params' => [':e' => $estado]];
        }

        $sql_base = "SELECT c.id AS turno_id, u.nombre AS usuario, c.fecha_apertura, c.fecha_cierre,
                            c.monto_apertura, c.monto_ventas_efectivo, c.monto_ingresos,
                            c.monto_retiros, c.monto_devoluciones, c.efectivo_esperado,
                            c.monto_cierre_efectivo, c.diferencia, c.estado
                     FROM cajas_sesiones c
                     JOIN usuarios u ON u.id = c.usuario_id
                     %WHERE%";
        $filas = self::consultar($sql_base, $wheres, 'c.fecha_apertura DESC, c.id DESC', 1000);

        // Resumen numérico SOLO de turnos CERRADOS (arqueo definitivo persistido;
        // NO se recalcula nada: se suman las columnas guardadas en el cierre).
        $pdo = conectarBD();
        $cond = self::condiciones($wheres);
        $cond = ($cond !== '' ? $cond . ' AND ' : '') . "c.estado = 'Cerrada'";
        $sql_tot = "SELECT COUNT(*) AS turnos,
                           CAST(COALESCE(SUM(c.monto_ventas_efectivo), 0) AS DECIMAL(10,2)) AS ventas_efectivo,
                           CAST(COALESCE(SUM(c.monto_ingresos), 0) AS DECIMAL(10,2)) AS ingresos,
                           CAST(COALESCE(SUM(c.monto_retiros), 0) AS DECIMAL(10,2)) AS retiros,
                           CAST(COALESCE(SUM(c.monto_devoluciones), 0) AS DECIMAL(10,2)) AS devoluciones,
                           CAST(COALESCE(SUM(c.efectivo_esperado), 0) AS DECIMAL(10,2)) AS efectivo_esperado,
                           CAST(COALESCE(SUM(c.monto_cierre_efectivo), 0) AS DECIMAL(10,2)) AS efectivo_contado,
                           CAST(COALESCE(SUM(CASE WHEN c.diferencia > 0 THEN c.diferencia END), 0) AS DECIMAL(10,2)) AS diferencias_positivas,
                           CAST(COALESCE(SUM(CASE WHEN c.diferencia < 0 THEN c.diferencia END), 0) AS DECIMAL(10,2)) AS diferencias_negativas
                    FROM cajas_sesiones c
                    WHERE " . $cond;
        $stmt = $pdo->prepare($sql_tot);
        self::enlazar($stmt, $wheres);
        $stmt->execute();
        $totales = $stmt->fetch();

        return ['success' => true, 'rows' => $filas, 'totales' => $totales];
    }

    // ==========================================================
    // 7. RESUMEN ADMINISTRATIVO (Dashboard de reportes)
    // ==========================================================
    public static function resumenDashboard() {
        $fechas = self::validarRangoFechas($error);
        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }
        // Sin fechas -> período de hoy (América/Tegucigalpa).
        if ($fechas === null) {
            $hoy = date('Y-m-d');
            $fechas = ['ini' => $hoy . ' 00:00:00', 'fin' => $hoy . ' 23:59:59'];
        }

        $pdo = conectarBD();

        $resumen = [
            'fecha_inicio' => substr($fechas['ini'], 0, 10),
            'fecha_fin' => substr($fechas['fin'], 0, 10)
        ];

        // Ventas del período (Completadas)
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cantidad,
                                      CAST(COALESCE(SUM(total), 0) AS DECIMAL(10,2)) AS total,
                                      CAST(COALESCE(SUM(CASE WHEN metodo_pago = 'Efectivo' THEN total END), 0) AS DECIMAL(10,2)) AS efectivo,
                                      CAST(COALESCE(SUM(CASE WHEN metodo_pago = 'Tarjeta' THEN total END), 0) AS DECIMAL(10,2)) AS tarjeta,
                                      CAST(COALESCE(SUM(CASE WHEN metodo_pago = 'Transferencia' THEN total END), 0) AS DECIMAL(10,2)) AS transferencia
                               FROM ventas WHERE estado = 'Completada' AND fecha_venta BETWEEN :ini AND :fin");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['ventas'] = $stmt->fetch();

        // Compras del período (CONFIRMADAS)
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cantidad, CAST(COALESCE(SUM(total), 0) AS DECIMAL(10,2)) AS total
                               FROM compras WHERE estado = 'CONFIRMADA' AND fecha_compra BETWEEN :ini AND :fin");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['compras'] = $stmt->fetch();

        // Devoluciones del período
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT d.id) AS cantidad,
                                      COALESCE(SUM(dd.cantidad), 0) AS unidades,
                                      CAST(COALESCE(SUM(d.monto_total), 0) AS DECIMAL(10,2)) AS monto
                               FROM devoluciones d
                               LEFT JOIN detalle_devoluciones dd ON dd.devolucion_id = d.id
                               WHERE d.fecha BETWEEN :ini AND :fin");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['devoluciones'] = $stmt->fetch();

        // Caja: ingresos y retiros del período (movimientos_caja)
        $stmt = $pdo->prepare("SELECT CAST(COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto END), 0) AS DECIMAL(10,2)) AS ingresos,
                                      CAST(COALESCE(SUM(CASE WHEN tipo = 'RETIRO' THEN monto END), 0) AS DECIMAL(10,2)) AS retiros
                               FROM movimientos_caja WHERE fecha BETWEEN :ini AND :fin");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['caja_movimientos'] = $stmt->fetch();

        // Diferencias de caja (turnos cerrados del período por fecha_apertura)
        $stmt = $pdo->prepare("SELECT COUNT(*) AS turnos,
                                      CAST(COALESCE(SUM(CASE WHEN diferencia > 0 THEN diferencia END), 0) AS DECIMAL(10,2)) AS positivas,
                                      CAST(COALESCE(SUM(CASE WHEN diferencia < 0 THEN diferencia END), 0) AS DECIMAL(10,2)) AS negativas
                               FROM cajas_sesiones WHERE estado = 'Cerrada' AND fecha_apertura BETWEEN :ini AND :fin");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['caja_diferencias'] = $stmt->fetch();

        // Productos más vendidos (top 5, Completadas del período)
        $stmt = $pdo->prepare("SELECT p.id AS producto_id, p.nombre AS producto, SUM(dv.cantidad) AS cantidad, CAST(SUM(dv.subtotal) AS DECIMAL(10,2)) AS subtotal
                               FROM detalle_ventas dv
                               JOIN ventas v ON v.id = dv.venta_id AND v.estado = 'Completada'
                               JOIN productos p ON p.id = dv.producto_id
                               WHERE v.fecha_venta BETWEEN :ini AND :fin
                               GROUP BY p.id, p.nombre
                               ORDER BY SUM(dv.cantidad) DESC
                               LIMIT 5");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['top_productos'] = $stmt->fetchAll();

        // Movimientos de inventario por tipo (período)
        $stmt = $pdo->prepare("SELECT tipo_movimiento, COUNT(*) AS cantidad, COALESCE(SUM(cantidad), 0) AS unidades
                               FROM movimientos_inventario WHERE fecha BETWEEN :ini AND :fin
                               GROUP BY tipo_movimiento ORDER BY tipo_movimiento");
        $stmt->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
        $resumen['movimientos_inventario'] = $stmt->fetchAll();

        // Stock actual global (referencia operativa)
        $stmt = $pdo->query("SELECT COUNT(*) AS productos, COALESCE(SUM(stock), 0) AS unidades_totales,
                                    COUNT(CASE WHEN stock <= stock_minimo THEN 1 END) AS bajo_stock
                             FROM productos WHERE activo = 1");
        $resumen['inventario_actual'] = $stmt->fetch();

        return ['success' => true, 'resumen' => $resumen];
    }

    // ==========================================================
    // 8. DASHBOARD ADMINISTRATIVO (Fase 9)
    // ==========================================================

    /**
     * Resuelve los límites de un período preseleccionado en
     * America/Tegucigalpa. Los presets (hoy/ayer/semana/mes/mes
     * anterior) se calculan SIEMPRE en el servidor. 'personalizado'
     * valida ambas fechas (checkdate) y el orden del rango.
     * @return array|null ['ini' => ..., 'fin' => ...] o null si inválido.
     */
    private static function periodoPreset($nombre, $fecha_inicio = '', $fecha_fin = '') {
        $tz = new DateTimeZone('America/Tegucigalpa');
        $hoy = new DateTime('now', $tz);
        switch ($nombre) {
            case 'hoy':
                $dr = [clone $hoy, clone $hoy];
                break;
            case 'ayer':
                $a = (clone $hoy)->modify('-1 day');
                $dr = [$a, clone $a];
                break;
            case 'semana':
                $ini = (clone $hoy)->modify('-' . ((int)$hoy->format('N') - 1) . ' days');
                $dr = [$ini, clone $hoy];
                break;
            case 'mes':
                $dr = [(clone $hoy)->modify('first day of this month'), clone $hoy];
                break;
            case 'mes_anterior':
                $dr = [
                    (clone $hoy)->modify('first day of last month'),
                    (clone $hoy)->modify('last day of last month')
                ];
                break;
            case 'personalizado':
                $ini = self::validarFecha($fecha_inicio);
                $fin = self::validarFecha($fecha_fin);
                if ($ini === null || $fin === null || $ini > $fin) {
                    return null;
                }
                return ['ini' => $ini . ' 00:00:00', 'fin' => $fin . ' 23:59:59'];
            default:
                return null;
        }
        return [
            'ini' => $dr[0]->format('Y-m-d') . ' 00:00:00',
            'fin' => $dr[1]->format('Y-m-d') . ' 23:59:59'
        ];
    }

    /**
     * Datos del dashboard administrativo (Fase 9).
     *
     * ADMIN: vista global (todos los indicadores, series, top y
     * actividad). CAJERO: SOLO su caja/turno y sus ventas; jamás
     * recibe indicadores globales. El usuario_id SIEMPRE proviene de
     * la sesión (nunca del cliente) — sin IDOR por parámetro.
     *
     * @param string $rol Rol de la sesión (Administrador/Cajero).
     * @param int $usuario_id Usuario autenticado.
     * @param string $periodo Preset o 'personalizado'.
     * @param string $fecha_inicio Para período personalizado.
     * @param string $fecha_fin Para período personalizado.
     * @return array
     */
    public static function dashboardDatos($rol, $usuario_id, $periodo, $fecha_inicio = '', $fecha_fin = '') {
        $fechas = self::periodoPreset($periodo, $fecha_inicio, $fecha_fin);
        if ($fechas === null) {
            return ['success' => false, 'message' => 'Período no válido o fechas incorrectas (formato AAAA-MM-DD).'];
        }

        $es_admin = ($rol === 'Administrador');
        $pdo = conectarBD();
        $d = [];
        $d['periodo'] = [
            'label' => $periodo,
            'ini' => substr($fechas['ini'], 0, 10),
            'fin' => substr($fechas['fin'], 0, 10)
        ];

        // ---------- Ventas del período (Completadas) ----------
        $sql = "SELECT COUNT(*) AS cantidad,
                       CAST(COALESCE(SUM(total),0) AS DECIMAL(10,2)) AS total,
                       CAST(COALESCE(SUM(CASE WHEN metodo_pago='Efectivo' THEN total END),0) AS DECIMAL(10,2)) AS efectivo,
                       CAST(COALESCE(SUM(CASE WHEN metodo_pago='Tarjeta' THEN total END),0) AS DECIMAL(10,2)) AS tarjeta
                FROM ventas
                WHERE estado='Completada' AND fecha_venta BETWEEN :ini AND :fin";
        $params = [':ini' => $fechas['ini'], ':fin' => $fechas['fin']];
        if (!$es_admin) {
            $sql .= ' AND usuario_id = :uid';
            $params[':uid'] = $usuario_id;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $d['indicadores']['ventas_periodo'] = $st->fetch();

        // ---------- Ventas de HOY (Tegucigalpa) ----------
        $hoy = date('Y-m-d');
        $sql = "SELECT COUNT(*) AS cantidad, CAST(COALESCE(SUM(total),0) AS DECIMAL(10,2)) AS total
                FROM ventas WHERE estado='Completada' AND fecha_venta BETWEEN :ini AND :fin";
        $params = [':ini' => $hoy . ' 00:00:00', ':fin' => $hoy . ' 23:59:59'];
        if (!$es_admin) {
            $sql .= ' AND usuario_id = :uid';
            $params[':uid'] = $usuario_id;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $d['indicadores']['ventas_hoy'] = $st->fetch();

        if ($es_admin) {
            // ---------- Compras del período (CONFIRMADAS) ----------
            $st = $pdo->prepare("SELECT COUNT(*) AS cantidad, CAST(COALESCE(SUM(total),0) AS DECIMAL(10,2)) AS total
                                 FROM compras WHERE estado='CONFIRMADA' AND fecha_compra BETWEEN :ini AND :fin");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['indicadores']['compras_periodo'] = $st->fetch();

            // Borradores pendientes de confirmar (indicador/alertas)
            $st = $pdo->prepare("SELECT COUNT(*) AS cantidad FROM compras WHERE estado='BORRADOR' AND fecha_compra BETWEEN :ini AND :fin");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['indicadores']['compras_borradores'] = (int)$st->fetchColumn();

            // ---------- Devoluciones del período ----------
            $st = $pdo->prepare("SELECT COUNT(*) AS cantidad, CAST(COALESCE(SUM(monto_total),0) AS DECIMAL(10,2)) AS monto
                                 FROM devoluciones WHERE fecha BETWEEN :ini AND :fin");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['indicadores']['devoluciones_periodo'] = $st->fetch();

            // ---------- Inventario (referencia actual) ----------
            $st = $pdo->prepare("SELECT COUNT(*) AS total_productos, COALESCE(SUM(stock),0) AS unidades_totales FROM productos WHERE activo=1");
            $st->execute();
            $d['indicadores']['inventario'] = $st->fetch();

            // ---------- Cajas abiertas (global) ----------
            $st = $pdo->prepare("SELECT COUNT(*) AS cantidad FROM cajas_sesiones WHERE estado='Abierta'");
            $st->execute();
            $d['indicadores']['cajas_abiertas'] = (int)$st->fetchColumn();

            // ---------- Caja: movimientos del período ----------
            $st = $pdo->prepare("SELECT CAST(COALESCE(SUM(CASE WHEN tipo='INGRESO_VENTA' THEN monto END),0) AS DECIMAL(10,2)) AS ventas_efectivo,
                                        CAST(COALESCE(SUM(CASE WHEN tipo='INGRESO' THEN monto END),0) AS DECIMAL(10,2)) AS ingresos,
                                        CAST(COALESCE(SUM(CASE WHEN tipo='RETIRO' THEN monto END),0) AS DECIMAL(10,2)) AS retiros,
                                        CAST(COALESCE(SUM(CASE WHEN tipo='EGRESO_DEVOLUCION' THEN monto END),0) AS DECIMAL(10,2)) AS devoluciones
                                 FROM movimientos_caja WHERE fecha BETWEEN :ini AND :fin");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['indicadores']['caja_periodo'] = $st->fetch();

            // ---------- Series: ventas por día ----------
            $st = $pdo->prepare("SELECT DATE(fecha_venta) AS fecha, COUNT(*) AS cantidad,
                                        CAST(SUM(total) AS DECIMAL(10,2)) AS total
                                 FROM ventas WHERE estado='Completada' AND fecha_venta BETWEEN :ini AND :fin
                                 GROUP BY DATE(fecha_venta) ORDER BY fecha ASC");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['ventas_por_dia'] = $st->fetchAll();

            // ---------- Métodos de pago ----------
            $st = $pdo->prepare("SELECT metodo_pago, COUNT(*) AS cantidad, CAST(SUM(total) AS DECIMAL(10,2)) AS total
                                 FROM ventas WHERE estado='Completada' AND fecha_venta BETWEEN :ini AND :fin
                                 GROUP BY metodo_pago ORDER BY total DESC");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['metodos_pago'] = $st->fetchAll();
            $suma_metodos = array_sum(array_column($d['metodos_pago'], 'total'));
            foreach ($d['metodos_pago'] as &$m) {
                $m['porcentaje'] = $suma_metodos > 0 ? round((float)$m['total'] / $suma_metodos * 100, 1) : 0.0;
            }
            unset($m);

            // ---------- Top productos (top 10, sin duplicar devoluciones:
            // la serie proviene SOLO de detalle_ventas, patrón Fase 8) ----------
            $st = $pdo->prepare("SELECT p.id AS producto_id, p.nombre AS producto,
                                        SUM(dv.cantidad) AS cantidad, CAST(SUM(dv.subtotal) AS DECIMAL(10,2)) AS total
                                 FROM detalle_ventas dv
                                 JOIN ventas v ON v.id = dv.venta_id AND v.estado = 'Completada'
                                 JOIN productos p ON p.id = dv.producto_id
                                 WHERE v.fecha_venta BETWEEN :ini AND :fin
                                 GROUP BY p.id, p.nombre
                                 ORDER BY cantidad DESC, total DESC
                                 LIMIT 10");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['top_productos'] = $st->fetchAll();

            // ---------- Inventario: agotados y bajo stock ----------
            $st = $pdo->prepare("SELECT id, nombre, stock, stock_minimo, disponibilidad
                                 FROM productos WHERE activo = 1 AND stock <= 0
                                 ORDER BY nombre");
            $st->execute();
            $d['inventario']['agotados'] = $st->fetchAll();

            $st = $pdo->prepare("SELECT id, nombre, stock, stock_minimo, disponibilidad
                                 FROM productos
                                 WHERE activo = 1 AND stock > 0 AND stock <= stock_minimo
                                   AND disponibilidad != 'Descontinuado'
                                 ORDER BY stock ASC, nombre");
            $st->execute();
            $d['inventario']['bajo_stock'] = $st->fetchAll();

            // ---------- Actividad reciente (mezclar 4 fuentes, límites) ----------
            $st = $pdo->prepare("SELECT id, num_factura AS ref, total AS monto, estado AS detalle, fecha_venta AS fecha, 'venta' AS tipo
                                 FROM ventas WHERE fecha_venta BETWEEN :ini AND :fin ORDER BY fecha_venta DESC LIMIT 8");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $act = $st->fetchAll();

            $st = $pdo->prepare("SELECT id, numero_documento AS ref, total AS monto, estado AS detalle, fecha_compra AS fecha, 'compra' AS tipo
                                 FROM compras WHERE fecha_compra BETWEEN :ini AND :fin ORDER BY fecha_compra DESC LIMIT 8");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $act = array_merge($act, $st->fetchAll());

            $st = $pdo->prepare("SELECT id, num_devolucion AS ref, monto_total AS monto, CONCAT('venta ', venta_id) AS detalle, fecha, 'devolucion' AS tipo
                                 FROM devoluciones WHERE fecha BETWEEN :ini AND :fin ORDER BY fecha DESC LIMIT 8");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $act = array_merge($act, $st->fetchAll());

            $st = $pdo->prepare("SELECT mi.id, CONCAT(p.nombre, ' (', mi.tipo_movimiento, ')') AS ref,
                                        mi.cantidad AS monto, CONCAT(mi.stock_anterior, ' -> ', mi.stock_nuevo) AS detalle,
                                        mi.fecha, 'inventario' AS tipo
                                 FROM movimientos_inventario mi
                                 JOIN productos p ON p.id = mi.producto_id
                                 WHERE mi.fecha BETWEEN :ini AND :fin ORDER BY mi.fecha DESC LIMIT 8");
            $st->execute([':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $act = array_merge($act, $st->fetchAll());

            // Ordenar por fecha DESC (string ISO comparable) y cortar a 12.
            usort($act, function ($a, $b) { return strcmp($b['fecha'], $a['fecha']); });
            $d['actividad'] = array_slice($act, 0, 12);

            // ---------- Alertas (solo las útiles, sin ruido) ----------
            $alertas = [];
            $st = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock <= 0");
            $st->execute();
            $nAgot = (int)$st->fetchColumn();
            if ($nAgot > 0) {
                $alertas[] = ['severidad' => 'danger', 'texto' => $nAgot . ' producto(s) AGOTADO(S).', 'link' => 'index.php?route=inventario'];
            }
            $st = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock > 0 AND stock <= stock_minimo AND disponibilidad != 'Descontinuado'");
            $st->execute();
            $nBajo = (int)$st->fetchColumn();
            if ($nBajo > 0) {
                $alertas[] = ['severidad' => 'warning', 'texto' => $nBajo . ' producto(s) con STOCK BAJO.', 'link' => 'index.php?route=inventario'];
            }
            if ($d['indicadores']['cajas_abiertas'] > 0) {
                $alertas[] = ['severidad' => 'info', 'texto' => $d['indicadores']['cajas_abiertas'] . ' caja(s) ABIERTA(S) en este momento.', 'link' => 'index.php?route=pos'];
            }
            if ($d['indicadores']['compras_borradores'] > 0) {
                $alertas[] = ['severidad' => 'info', 'texto' => $d['indicadores']['compras_borradores'] . ' compra(s) pendiente(s) de confirmar.', 'link' => 'index.php?route=compras'];
            }
            $d['alertas'] = $alertas;
        } else {
            // ---------- CAJERO: solo su turno de caja ----------
            $turno = null;
            $sesion = CajaController::obtenerSesionActiva($usuario_id);
            if ($sesion) {
                $turno = [
                    'turno_id' => (int)$sesion['id'],
                    'monto_apertura' => $sesion['monto_apertura']
                ];
                $estado = CajaController::obtenerEstadoCaja($sesion['id']);
                $turno['estado'] = $estado;
            }
            $d['caja_turno'] = $turno;
            $d['indicadores']['cajas_abiertas'] = $turno !== null ? 1 : 0;

            // Sus últimas ventas (actividad propia)
            $st = $pdo->prepare("SELECT id, num_factura AS ref, total AS monto, estado AS detalle, fecha_venta AS fecha, 'venta' AS tipo
                                 FROM ventas WHERE usuario_id = :uid AND fecha_venta BETWEEN :ini AND :fin
                                 ORDER BY fecha_venta DESC LIMIT 12");
            $st->execute([':uid' => $usuario_id, ':ini' => $fechas['ini'], ':fin' => $fechas['fin']]);
            $d['actividad'] = $st->fetchAll();
            $d['inventario'] = ['agotados' => [], 'bajo_stock' => []];
            $d['top_productos'] = [];
            $d['metodos_pago'] = [];
            $d['alertas'] = [];
        }

        return ['success' => true] + $d;
    }

    // ---------- Helpers internos ----------

    /**
     * Ejecuta un SELECT con condiciones comunes (totales): reutiliza
     * el array $wheres para enlazar parámetros con los mismos nombres.
     */
    private static function condiciones($wheres) {
        return implode(' AND ', array_column($wheres, 'sql'));
    }

    private static function enlazar($stmt, $wheres) {
        foreach ($wheres as $w) {
            foreach ($w['params'] as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
    }
}