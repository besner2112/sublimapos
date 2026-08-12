<?php
// ==========================================
// Panel de Control Administrativo (Fase 9)
// ADMIN: vista global (indicadores, grÃ¡ficas, top, actividad).
// CAJERO: SOLO su caja/turno y sus ventas (nunca datos globales).
// Los datos se cargan vÃ­a AJAX (dashboard_datos_ajax) con un
// selector de perÃ­odo; las grÃ¡ficas usan Chart.js (CDN) con
// respaldo en tablas si la librerÃ­a no carga.
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CajaController.php';
require_once __DIR__ . '/../controllers/VentaController.php';
require_once __DIR__ . '/../conexion/db.php';

AuthController::requireLogin();

$es_admin = ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
$usuario_id = intval($_SESSION['usuario_id'] ?? 0);
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';

$mensaje_exito = "";
$mensaje_error = "";

// ------------------------------------------
// PROCESAR ANULACIÃ“N DE VENTA POR POST (SOLO ADMIN)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'anular_venta') {
    if (!$es_admin) {
        $mensaje_error = "No autorizado: solo el administrador puede anular ventas.";
    } elseif (!verify_csrf_token()) {
        $mensaje_error = "Token de seguridad invÃ¡lido o expirado. Recargue la pÃ¡gina e intente nuevamente.";
    } else {
        $v_id = intval($_POST['venta_id'] ?? 0);
        if ($v_id > 0) {
            $res_an = VentaController::anularVenta($v_id);
            if ($res_an['success']) {
                $mensaje_exito = $res_an['message'];
            } else {
                $mensaje_error = $res_an['message'];
            }
        }
    }
}

// Paneles servidor-side heredados (F7/F8): Ãºltimas ventas + arqueos (solo admin)
$ultimas_ventas = [];
$cajas_historial = [];
if ($es_admin) {
    $ultimas_ventas = VentaController::obtenerUltimasVentas(20);
    $cajas_historial = CajaController::obtenerHistorialCajas();
}

// Turno propio del cajero (servidor-side, sin datos globales)
$mi_turno = null;
if (!$es_admin) {
    $sesion = CajaController::obtenerSesionActiva($usuario_id);
    if ($sesion) {
        $mi_turno = [
            'monto_apertura' => $sesion['monto_apertura'],
            'estado' => CajaController::obtenerEstadoCaja($sesion['id'])
        ];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="m-0 fw-bold"><i class="bi bi-speedometer2 text-cyan me-2"></i> Panel de Control
        <?php if (!$es_admin): ?><span class="badge text-bg-secondary ms-1">Mi actividad</span><?php endif; ?>
    </h4>
    <span class="text-secondary small" id="dash-periodo-etiqueta">Cargandoâ€¦</span>
</div>

<!-- Selector de perÃ­odo (los presets se resuelven en el servidor) -->
<div class="card-premium p-3 mb-3">
    <div class="row g-2 align-items-center">
        <div class="col-auto">
            <span class="text-secondary small fw-semibold me-1">PERÃODO:</span>
        </div>
        <div class="col-auto">
            <div class="btn-group btn-group-sm d-flex flex-wrap" role="group" id="dash-periodos">
                <button type="button" class="btn btn-outline-cyan fw-bold active" data-periodo="hoy">Hoy</button>
                <button type="button" class="btn btn-outline-cyan fw-bold" data-periodo="ayer">Ayer</button>
                <button type="button" class="btn btn-outline-cyan fw-bold" data-periodo="semana">Semana</button>
                <button type="button" class="btn btn-outline-cyan fw-bold" data-periodo="mes">Mes</button>
                <button type="button" class="btn btn-outline-cyan fw-bold" data-periodo="mes_anterior">Mes anterior</button>
                <button type="button" class="btn btn-outline-cyan fw-bold" data-periodo="personalizado" id="btn-periodo-pers">Personalizado</button>
            </div>
        </div>
        <div class="col-auto d-none" id="dash-fechas-pers">
            <input type="date" id="dash-fecha-inicio" class="form-control form-control-custom form-control-sm" style="display:inline-block;width:auto;">
            <span class="text-secondary mx-1">a</span>
            <input type="date" id="dash-fecha-fin" class="form-control form-control-custom form-control-sm" style="display:inline-block;width:auto;">
            <button type="button" class="btn btn-cyan btn-sm fw-bold" id="btn-aplicar-pers">Aplicar</button>
        </div>
    </div>
</div>

<?php if (!empty($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 text-white py-3 mb-4" style="background-color: var(--success-green);" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 text-white py-3 mb-4" style="background-color: var(--danger-red);" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($es_admin): ?>

<!-- ============ KPIs (poblados por AJAX) ============ -->
<div class="row g-3 mb-3" id="dash-kpis"></div>

<!-- ============ GRÃFICAS ============ -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-graph-up text-cyan me-1"></i> EvoluciÃ³n de Ventas (perÃ­odo)</h6>
            <canvas id="chart-ventas" style="max-height: 280px;"></canvas>
            <div id="serie-ventas-fallback" class="d-none mt-3"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-pie-chart text-cyan me-1"></i> MÃ©todos de Pago</h6>
            <canvas id="chart-metodos" style="max-height: 180px;"></canvas>
            <table class="table table-custom m-0 mt-3" style="font-size:0.82rem;">
                <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>MÃ©todo</th><th class="text-end">Monto</th><th class="text-end">Cant.</th><th class="text-end">%</th></tr></thead>
                <tbody id="tabla-metodos"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ TOP PRODUCTOS + ACTIVIDAD ============ -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-trophy text-cyan me-1"></i> Productos MÃ¡s Vendidos (Top 10, perÃ­odo)</h6>
            <div class="table-responsive" style="max-height: 320px; overflow-y:auto;">
                <table class="table table-custom m-0" style="font-size:0.85rem;">
                    <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>#</th><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Total</th></tr></thead>
                    <tbody id="tabla-top"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-clock-history text-cyan me-1"></i> Actividad Reciente</h6>
            <div class="table-responsive" style="max-height: 320px; overflow-y:auto;">
                <table class="table table-custom m-0" style="font-size:0.82rem;">
                    <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>Tipo</th><th>Referencia</th><th>Detalle</th><th class="text-end">Monto</th><th>Fecha</th></tr></thead>
                    <tbody id="tabla-actividad"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============ INVENTARIO (alertas) + RESUMEN CAJA ============ -->
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-danger mb-3"><i class="bi bi-box-seam me-1"></i> Agotados</h6>
            <div class="table-responsive" style="max-height: 240px; overflow-y:auto;">
                <table class="table table-custom m-0" style="font-size:0.82rem;">
                    <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>Producto</th><th class="text-end">Stock</th><th>Estado</th></tr></thead>
                    <tbody id="tabla-agotados"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-warning mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Stock Bajo (â‰¤ mÃ­nimo)</h6>
            <div class="table-responsive" style="max-height: 240px; overflow-y:auto;">
                <table class="table table-custom m-0" style="font-size:0.82rem;">
                    <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>Producto</th><th class="text-end">Stock</th><th>Estado</th></tr></thead>
                    <tbody id="tabla-bajo-stock"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-bell text-cyan me-1"></i> Alertas</h6>
            <div id="dash-alertas"></div>
        </div>
    </div>
</div>

<!-- ============ DIARIO DE VENTAS (heredado, con anulaciÃ³n) ============ -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card-premium h-100">
            <div class="card-header-premium">
                <span><i class="bi bi-receipt-cutoff text-cyan me-2"></i> Diario de Ventas Recientes</span>
                <span class="text-secondary small">Ãšltimas 20 facturas</span>
            </div>
            <div class="p-3 table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-custom table-hover m-0">
                    <thead>
                        <tr>
                            <th scope="col">Folio</th>
                            <th scope="col">Cajero (Shift)</th>
                            <th scope="col">Total</th>
                            <th scope="col">Estatus</th>
                            <th scope="col" class="text-end">AcciÃ³n</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimas_ventas)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-5">No se registran ventas para auditar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimas_ventas as $v): ?>
                                <tr>
                                    <td>
                                        <code style="font-size:0.78rem;" class="text-cyan"><?php echo htmlspecialchars($v['num_factura']); ?></code>
                                        <small class="d-block text-secondary mt-1" style="font-size:0.75rem;"><i class="bi bi-clock"></i> <?php echo date("d/m H:i", strtotime($v['fecha_venta'])); ?></small>
                                    </td>
                                    <td><span class="fw-semibold text-white"><?php echo htmlspecialchars($v['nombre_cajero']); ?></span></td>
                                    <td class="fw-bold text-white">L. <?php echo number_format($v['total'], 2); ?></td>
                                    <td>
                                        <?php if ($v['estado'] === 'Anulada'): ?>
                                            <span class="badge bg-danger">Anulada</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Completada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($v['estado'] === 'Completada'): ?>
                                            <form action="" method="POST" style="display:inline-block;" onsubmit="return confirm('Â¿Seguro que deseas anular esta venta? Esto reintegrarÃ¡ inmediatamente los productos al stock del inventario y no se puede deshacer.');">
                                                <input type="hidden" name="accion" value="anular_venta">
                                                <input type="hidden" name="venta_id" value="<?php echo $v['id']; ?>">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Anular Factura y Devolver Stock">
                                                    <i class="bi bi-x-circle"></i> Anular
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary py-1 px-2" disabled>
                                                <i class="bi bi-slash-circle"></i> N/D
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Panel de Historial de Caja (Oversight Contable) -->
    <div class="col-lg-6 mb-4">
        <div class="card-premium h-100">
            <div class="card-header-premium">
                <span><i class="bi bi-safe-fill text-cyan me-2"></i> Reporte y Arqueo Contable de Turnos</span>
                <span class="text-secondary small">Ãšltimos cierras</span>
            </div>
            <div class="p-3 table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-custom table-hover m-0">
                    <thead>
                        <tr>
                            <th scope="col">Turno (Cajero)</th>
                            <th scope="col">Saldo Apertura</th>
                            <th scope="col">Venta Turno</th>
                            <th scope="col">FÃ­sico Contado</th>
                            <th scope="col">Diferencia</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cajas_historial)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-5">No se registran turnos de apertura contables en el historial.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cajas_historial as $cj): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-white"><?php echo htmlspecialchars($cj['nombre_usuario']); ?></span>
                                        <small class="d-block text-secondary mt-1" style="font-size:0.7rem; font-family:monospace;">
                                            Ape: <?php echo date("d/m H:i", strtotime($cj['fecha_apertura'])); ?>
                                            <?php if ($cj['fecha_cierre']): ?><br>Cie: <?php echo date("d/m H:i", strtotime($cj['fecha_cierre'])); ?><?php endif; ?>
                                        </small>
                                    </td>
                                    <td>L. <?php echo number_format($cj['monto_apertura'], 2); ?></td>
                                    <td>
                                        <?php if ($cj['estado'] === 'Cerrada'): ?>
                                            L. <?php echo number_format($cj['monto_ventas_calculado'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-secondary small">En curso</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cj['estado'] === 'Cerrada'): ?>
                                            L. <?php echo number_format(floatval($cj['monto_cierre_efectivo'] ?? 0) + floatval($cj['monto_cierre_tarjeta'] ?? 0), 2); ?>
                                        <?php else: ?>
                                            <span class="text-secondary small">En curso</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cj['estado'] === 'Cerrada'): ?>
                                            <?php
                                            $dif = floatval($cj['diferencia'] ?? 0);
                                            if ($dif == 0) {
                                                echo '<span class="text-success fw-bold">$0.00</span>';
                                            } elseif ($dif > 0) {
                                                echo '<span class="text-success fw-bold">+L. ' . number_format($dif, 2) . '</span>';
                                            } else {
                                                echo '<span class="text-danger fw-bold">-L. ' . number_format(abs($dif), 2) . '</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-secondary small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cj['estado'] === 'Abierta'): ?>
                                            <span class="badge bg-success">Abierta</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-dark">Cerrada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ============ CAJERO: SOLO SU TURNO Y SUS VENTAS ============ -->
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card-premium p-4 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-safe text-cyan me-1"></i> Mi Turno de Caja</h6>
<?php if ($mi_turno === null): ?>
                <div class="alert alert-secondary border-0 mb-0" role="alert">
                    <i class="bi bi-lock-fill me-2"></i> No tienes un turno de caja abierto. Ábrelo desde <strong>Caja / POS</strong>.
                </div>
            <?php else: ?>
                <?php $est = $mi_turno['estado']; ?>
                <div class="row g-2 text-center mb-2">
                    <div class="col-6">
                        <div class="p-3 stat-box">
                            <small class="text-secondary d-block">Apertura</small>
                            <span class="fw-bold">L. <?php echo number_format((float)$est['monto_apertura'], 2); ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 stat-box">
                            <small class="text-secondary d-block">Saldo disponible</small>
                            <span class="fw-bold text-cyan">L. <?php echo number_format((float)$est['saldo_efectivo'], 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 stat-box">
                            <small class="text-secondary d-block">Ventas efectivo</small>
                            <span class="fw-bold">L. <?php echo number_format((float)$est['ventas_efectivo'], 2); ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 stat-box">
                            <small class="text-secondary d-block">Ingresos − Retiros</small>
                            <span class="fw-bold">L. <?php echo number_format((float)($est['ingresos'] ?? 0) - (float)($est['retiros'] ?? 0), 2); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-premium p-3 h-100">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-clock-history text-cyan me-1"></i> Mis Ãšltimas Ventas</h6>
            <div class="table-responsive" style="max-height: 320px; overflow-y:auto;">
                <table class="table table-custom m-0" style="font-size:0.82rem;">
                    <thead><tr class="text-secondary text-uppercase" style="font-size:0.68rem;"><th>Factura</th><th class="text-end">Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody id="tabla-mis-ventas"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;
    var periodo = 'hoy';
    var charts = {};

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmt(v, dec) {
        return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: dec === undefined ? 2 : dec, maximumFractionDigits: dec === undefined ? 2 : dec });
    }
    function $id(id) { return document.getElementById(id); }

    function badgeTipo(tipo) {
        var mapa = { venta: 'bg-success', compra: 'bg-primary', devolucion: 'bg-warning text-dark', inventario: 'bg-secondary' };
        return '<span class="badge ' + (mapa[tipo] || 'bg-secondary') + '">' + esc(tipo) + '</span>';
    }

    function kpi(titulo, valor, sub, color, icono) {
        return '<div class="col-md-6 col-xl-3 col-sm-6">'
            + '<div class="card-premium p-3 h-100">'
            + '<div class="d-flex justify-content-between align-items-start">'
            + '<div><span class="text-secondary small d-block mb-1">' + esc(titulo) + '</span>'
            + '<span class="fw-bold fs-5 ' + (color || 'text-primary') + '">' + valor + '</span>'
            + (sub ? '<div class="text-secondary small mt-1">' + sub + '</div>' : '')
            + '</div>'
            + '<div class="p-2 bg-dark border border-color rounded-circle"><i class="bi ' + (icono || 'bi-circle') + ' text-cyan fs-5 px-1"></i></div>'
            + '</div></div></div>';
    }

    // ============ RENDER ============
    function renderKpis(d) {
        var i = d.indicadores;
        var vp = i.ventas_periodo, vh = i.ventas_hoy;
        var el = $id('dash-kpis');
        if (!el) return;

        var html = '';
        html += kpi('Ventas HOY', vh.cantidad + ' Â· L. ' + fmt(vh.total), 'PerÃ­odo actual: ' + d.periodo.ini + ' a ' + d.periodo.fin, null, 'bi-receipt');
        html += kpi('Ventas del perÃ­odo', 'L. ' + fmt(vp.total), vp.cantidad + ' venta(s) Â· Ef. L. ' + fmt(vp.efectivo) + ' Â· Tar. L. ' + fmt(vp.tarjeta), 'text-cyan', 'bi-wallet2');
        if (i.compras_periodo) {
            var cp = i.compras_periodo;
            html += kpi('Compras del perÃ­odo', cp.cantidad + ' Â· L. ' + fmt(cp.total), (i.compras_borradores > 0 ? i.compras_borradores + ' borrador(es) pendiente(s)' : 'Sin borradores pendientes'), null, 'bi-bag-check');
        }
        if (i.devoluciones_periodo) {
            var dp = i.devoluciones_periodo;
            html += kpi('Devoluciones del perÃ­odo', dp.cantidad + ' Â· L. ' + fmt(dp.monto), 'Reintegros realizados', 'text-warning', 'bi-arrow-return-left');
        }
        if (i.inventario) {
            var inv = i.inventario;
            html += kpi('Productos activos', inv.total_productos, fmt(inv.unidades_totales, 0) + ' unidades en stock', null, 'bi-box-seam');
        }
        var caja = i.caja_periodo || { ventas_efectivo: 0, ingresos: 0, retiros: 0, devoluciones: 0 };
        html += kpi('Caja (mov. del perÃ­odo)', 'Ef. L. ' + fmt(caja.ventas_efectivo), 'Ing. L. ' + fmt(caja.ingresos) + ' Â· Ret. L. ' + fmt(caja.retiros) + ' Â· Dev. L. ' + fmt(caja.devoluciones), 'text-success', 'bi-safe');
        html += kpi('Cajas abiertas', i.cajas_abiertas, 'Turnos activos en este momento', i.cajas_abiertas > 0 ? 'text-success' : null, 'bi-unlock');

        // Inventario alertas (agotados / bajo)
        var ag = (d.inventario.agotados || []).length;
        var ba = (d.inventario.bajo_stock || []).length;
        html += kpi('Inventario: agotados / bajo', ag + ' / ' + ba, 'Umbral = stock_minimo (Stock > 0 y â‰¤ mÃ­nimo)', ag > 0 ? 'text-danger' : null, 'bi-exclamation-triangle-fill');

        el.innerHTML = html;
        $id('dash-periodo-etiqueta').textContent = 'Mostrando: ' + d.periodo.ini + ' a ' + d.periodo.fin;
    }

    function renderSeries(d) {
        var serie = d.ventas_por_dia || [];
        var etiquetas = serie.map(function (r) { return r.fecha; });
        var valores = serie.map(function (r) { return Number(r.total); });

        // Respaldo en tabla si Chart.js no estÃ¡ disponible
        var fb = $id('serie-ventas-fallback');
        if (window.Chart === undefined) {
            if ($id('chart-ventas')) $id('chart-ventas').classList.add('d-none');
            if (fb) {
                fb.classList.remove('d-none');
                fb.innerHTML = serie.length
                    ? '<table class="table table-custom m-0" style="font-size:0.82rem;"><tbody>'
                        + serie.map(function (r) { return '<tr><td>' + esc(r.fecha) + '</td><td class="text-end">' + Number(r.cantidad) + '</td><td class="text-end">L. ' + fmt(r.total) + '</td></tr>'; }).join('')
                        + '</tbody></table>'
                    : '<div class="text-secondary text-center py-3">Sin ventas en el perÃ­odo.</div>';
            }
        } else {
            if (charts.ventas) charts.ventas.destroy();
            var ctx = $id('chart-ventas');
            if (ctx) {
                charts.ventas = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: etiquetas,
                        datasets: [{
                            label: 'Ventas (L.)',
                            data: valores,
                            backgroundColor: 'rgba(23, 162, 184, 0.65)',
                            borderColor: 'rgba(23, 162, 184, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#59524c' } } },
                        scales: {
                            x: { ticks: { color: '#59524c' } },
                            y: { ticks: { color: '#59524c' }, beginAtZero: true }
                        }
                    }
                });
            }
        }

        // MÃ©todos de pago
        var metodos = d.metodos_pago || [];
        var tmet = $id('tabla-metodos');
        if (tmet) {
            tmet.innerHTML = metodos.length
                ? metodos.map(function (m) {
                    return '<tr><td>' + esc(m.metodo_pago) + '</td><td class="text-end">L. ' + fmt(m.total) + '</td><td class="text-end">' + Number(m.cantidad) + '</td><td class="text-end">' + fmt(m.porcentaje, 1) + '%</td></tr>';
                }).join('')
                : '<tr><td colspan="4" class="text-center text-secondary py-3">Sin ventas en el perÃ­odo.</td></tr>';
        }
        if (window.Chart !== undefined && $id('chart-metodos')) {
            if (charts.metodos) charts.metodos.destroy();
            var ctx2 = $id('chart-metodos');
            charts.metodos = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: metodos.map(function (m) { return m.metodo_pago; }),
                    datasets: [{
                        data: metodos.map(function (m) { return Number(m.total); }),
                        backgroundColor: ['rgba(23,162,184,0.8)', 'rgba(220,53,69,0.8)', 'rgba(255,193,7,0.8)']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: '#59524c' } } }
                }
            });
        }
    }

    function renderTop(d) {
        var el = $id('tabla-top');
        if (!el) return;
        var top = d.top_productos || [];
        if (!top.length) {
            el.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-3">Sin ventas en el perÃ­odo.</td></tr>';
            return;
        }
        el.innerHTML = top.map(function (p, idx) {
            return '<tr><td class="text-secondary">' + (idx + 1) + '</td><td>' + esc(p.producto) + ' <span class="badge text-bg-secondary">#' + Number(p.producto_id) + '</span></td>'
                + '<td class="text-end">' + Number(p.cantidad) + '</td><td class="text-end fw-bold text-white">L. ' + fmt(p.total) + '</td></tr>';
        }).join('');
    }

    function renderInventario(d) {
        var ag = $id('tabla-agotados');
        if (!ag) return;
        var agotados = d.inventario.agotados || [];
        var bajo = d.inventario.bajo_stock || [];
        ag.innerHTML = agotados.length
            ? agotados.map(function (p) {
                return '<tr><td>' + esc(p.nombre) + '</td><td class="text-end text-danger fw-bold">' + Number(p.stock) + '</td><td><span class="badge bg-danger">Agotado</span></td></tr>';
            }).join('')
            : '<tr><td colspan="3" class="text-center text-secondary py-3">Sin productos agotados.</td></tr>';
        var ba = $id('tabla-bajo-stock');
        ba.innerHTML = bajo.length
            ? bajo.map(function (p) {
                return '<tr><td>' + esc(p.nombre) + '</td><td class="text-end text-warning fw-bold">' + Number(p.stock) + ' / ' + Number(p.stock_minimo) + '</td><td><span class="badge bg-warning text-dark">Bajo</span></td></tr>';
            }).join('')
            : '<tr><td colspan="3" class="text-center text-secondary py-3">Sin productos con stock bajo.</td></tr>';
    }

    function renderActividad(d) {
        var el = esAdmin ? $id('tabla-actividad') : $id('tabla-mis-ventas');
        if (!el) return;
        var act = d.actividad || [];
        if (!act.length) {
            el.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3">Sin actividad en el perÃ­odo.</td></tr>';
            return;
        }
        if (esAdmin) {
            el.innerHTML = act.map(function (r) {
                return '<tr><td>' + badgeTipo(r.tipo) + '</td><td>' + esc(r.ref) + '</td><td>' + esc(r.detalle) + '</td>'
                    + '<td class="text-end">' + (r.tipo === 'inventario' ? Number(r.monto) : 'L. ' + fmt(r.monto)) + '</td>'
                    + '<td class="text-secondary small">' + esc(String(r.fecha).slice(0, 16).replace('T', ' ')) + '</td></tr>';
            }).join('');
        } else {
            var tcols = 4;
            el.innerHTML = act.map(function (r) {
                return '<tr><td>' + esc(r.ref) + '</td><td class="text-end">L. ' + fmt(r.monto) + '</td>'
                    + '<td>' + (r.estado === 'Anulada' ? '<span class="badge bg-danger">Anulada</span>' : '<span class="badge bg-success">Completada</span>') + '</td>'
                    + '<td class="text-secondary small">' + esc(String(r.fecha).slice(0, 16).replace('T', ' ')) + '</td></tr>';
            }).join('') || '<tr><td colspan="' + tcols + '" class="text-center text-secondary py-3">Sin ventas en el perÃ­odo.</td></tr>';
        }
    }

    function renderAlertas(d) {
        var el = $id('dash-alertas');
        if (!el) return;
        var alertas = d.alertas || [];
        var mapSeveridad = { danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' };
        if (!alertas.length) {
            el.innerHTML = '<div class="text-secondary small py-3">Sin alertas en este momento.</div>';
            return;
        }
        el.innerHTML = alertas.map(function (a) {
            return '<div class="alert border-0 text-white py-2 mb-2 d-flex justify-content-between align-items-center ' + (mapSeveridad[a.severidad] || 'bg-secondary') + '" role="alert" style="font-size:0.85rem;">'
                + '<span><i class="bi bi-exclamation-circle-fill me-2"></i>' + esc(a.texto) + '</span>'
                + (a.link ? '<a class="btn btn-sm btn-cyan text-decoration-none" href="' + esc(a.link) + '">Ir</a>' : '')
                + '</div>';
        }).join('');
    }

    // ============ CARGA ============
    function cargar() {
        var params = new URLSearchParams();
        params.set('periodo', periodo);
        if (periodo === 'personalizado') {
            params.set('fecha_inicio', $id('dash-fecha-inicio').value);
            params.set('fecha_fin', $id('dash-fecha-fin').value);
        }
        fetch('index.php?route=dashboard_datos_ajax&' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    $id('dash-periodo-etiqueta').textContent = 'Error: ' + (d.message || 'Consulta rechazada.');
                    return;
                }
                renderKpis(d);
                renderSeries(d);
                renderTop(d);
                renderInventario(d);
                renderActividad(d);
                renderAlertas(d);
            })
            .catch(function () {
                $id('dash-periodo-etiqueta').textContent = 'No se pudo cargar el panel.';
            });
    }

    // ============ EVENTOS ============
    var botones = document.querySelectorAll('#dash-periodos [data-periodo]');
    botones.forEach(function (b) {
        b.addEventListener('click', function () {
            botones.forEach(function (x) { x.classList.remove('active', 'btn-cyan'); x.classList.add('btn-outline-cyan'); });
            b.classList.add('active', 'btn-cyan');
            b.classList.remove('btn-outline-cyan');
            periodo = b.dataset.periodo;
            var pers = $id('dash-fechas-pers');
            if (periodo === 'personalizado') {
                pers.classList.remove('d-none');
                if (!$id('dash-fecha-inicio').value) {
                    var hoy = new Date().toISOString().slice(0, 10);
                    $id('dash-fecha-inicio').value = hoy;
                    $id('dash-fecha-fin').value = hoy;
                }
            } else {
                pers.classList.add('d-none');
            }
            cargar();
        });
    });
    $id('btn-aplicar-pers').addEventListener('click', cargar);

    cargar();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>