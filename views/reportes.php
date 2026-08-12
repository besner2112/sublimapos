<?php
// ==========================================
// Vista del MÃ³dulo de Reportes (Fase 8)
// Solo Administrador (garantizado en index.php).
// Solo lectura: consume los endpoints AJAX del mÃ³dulo.
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/ReporteController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/ClienteController.php';
require_once __DIR__ . '/../controllers/InventarioController.php';
require_once __DIR__ . '/../controllers/ProveedorController.php';

$usuarios_lista = UsuarioController::obtenerUsuarios();
$clientes_lista = ClienteController::obtenerClientes();
$productos_lista = InventarioController::obtenerProductos(true);
$proveedores_lista = ProveedorController::obtenerProveedores(false);

$tipos_kardex = ['INVENTARIO_INICIAL', 'SALIDA_VENTA', 'AJUSTE_ENTRADA', 'AJUSTE_SALIDA',
                 'ENTRADA_COMPRA', 'DEVOLUCION_VENTA', 'DEVOLUCION_COMPRA', 'MERMA'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0 fw-bold"><i class="bi bi-file-earmark-bar-graph text-cyan me-2"></i> Reportes y Consultas Operativas</h4>
    <span class="badge text-bg-secondary">Solo lectura</span>
</div>

<div class="card-premium p-4 mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="reporte-tipo" class="form-label text-secondary fw-semibold">Tipo de Reporte</label>
            <select class="form-select form-control-custom" id="reporte-tipo">
                <option value="resumen">Resumen del PerÃ­odo (Dashboard)</option>
                <option value="ventas">1. Ventas</option>
                <option value="productos">2. Productos Vendidos</option>
                <option value="inventario">3. Inventario / Kardex</option>
                <option value="compras">4. Compras</option>
                <option value="devoluciones">5. Devoluciones</option>
                <option value="caja">6. Caja (arqueos)</option>
            </select>
        </div>

        <!-- Filtro de fechas (comÃºn a todos) -->
        <div class="col-md-3">
            <label for="f-fecha-inicio" class="form-label text-secondary fw-semibold">Fecha Inicial</label>
            <input type="date" class="form-control form-control-custom" id="f-fecha-inicio">
        </div>
        <div class="col-md-3">
            <label for="f-fecha-fin" class="form-label text-secondary fw-semibold">Fecha Final</label>
            <input type="date" class="form-control form-control-custom" id="f-fecha-fin">
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-cyan w-100 fw-bold" id="btn-reporte-buscar">
                <i class="bi bi-search me-1"></i> Buscar
            </button>
        </div>
    </div>

    <!-- Filtros especÃ­ficos (se habilitan segÃºn el reporte) -->
    <div class="row g-3 mt-1" id="filtros-especificos">
        <div class="col-md-3 d-none" data-filtro="usuario">
            <label for="f-usuario" class="form-label text-secondary fw-semibold">Usuario / Vendedor</label>
            <select class="form-select form-control-custom" id="f-usuario">
                <option value="">Todos</option>
                <?php foreach ($usuarios_lista as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="cliente">
            <label for="f-cliente" class="form-label text-secondary fw-semibold">Cliente</label>
            <select class="form-select form-control-custom" id="f-cliente">
                <option value="">Todos</option>
                <?php foreach ($clientes_lista as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="producto">
            <label for="f-producto" class="form-label text-secondary fw-semibold">Producto</label>
            <select class="form-select form-control-custom" id="f-producto">
                <option value="">Todos</option>
                <?php foreach ($productos_lista as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="metodo">
            <label for="f-metodo" class="form-label text-secondary fw-semibold">MÃ©todo de Pago</label>
            <select class="form-select form-control-custom" id="f-metodo">
                <option value="">Todos</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Tarjeta">Tarjeta</option>
                <option value="Transferencia">Transferencia</option>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="estado-venta">
            <label for="f-estado-venta" class="form-label text-secondary fw-semibold">Estado de Venta</label>
            <select class="form-select form-control-custom" id="f-estado-venta">
                <option value="">Todos</option>
                <option value="Completada">Completada</option>
                <option value="Anulada">Anulada</option>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="tipo-mov">
            <label for="f-tipo-mov" class="form-label text-secondary fw-semibold">Tipo de Movimiento</label>
            <select class="form-select form-control-custom" id="f-tipo-mov">
                <option value="">Todos</option>
                <?php foreach ($tipos_kardex as $t): ?>
                    <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="proveedor">
            <label for="f-proveedor" class="form-label text-secondary fw-semibold">Proveedor</label>
            <select class="form-select form-control-custom" id="f-proveedor">
                <option value="">Todos</option>
                <?php foreach ($proveedores_lista as $pr): ?>
                    <option value="<?php echo (int)$pr['id']; ?>"><?php echo htmlspecialchars($pr['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="estado-compra">
            <label for="f-estado-compra" class="form-label text-secondary fw-semibold">Estado de Compra</label>
            <select class="form-select form-control-custom" id="f-estado-compra">
                <option value="">Todos</option>
                <option value="BORRADOR">BORRADOR</option>
                <option value="CONFIRMADA">CONFIRMADA</option>
            </select>
        </div>
        <div class="col-md-3 d-none" data-filtro="doc">
            <label for="f-doc" class="form-label text-secondary fw-semibold">NÃºmero de Documento</label>
            <input type="text" class="form-control form-control-custom" id="f-doc" placeholder="Ej: FAC-001 (parcial)">
        </div>
        <div class="col-md-3 d-none" data-filtro="venta">
            <label for="f-venta" class="form-label text-secondary fw-semibold">Venta (ID)</label>
            <input type="number" min="1" class="form-control form-control-custom" id="f-venta" placeholder="ID de venta">
        </div>
        <div class="col-md-3 d-none" data-filtro="estado-caja">
            <label for="f-estado-caja" class="form-label text-secondary fw-semibold">Estado de Caja</label>
            <select class="form-select form-control-custom" id="f-estado-caja">
                <option value="">Todos</option>
                <option value="Abierta">Abierta</option>
                <option value="Cerrada">Cerrada</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" id="btn-reporte-limpiar">
                <i class="bi bi-eraser me-1"></i> Limpiar
            </button>
        </div>
    </div>
</div>

<!-- ============ RESULTADOS ============ -->
<div id="reporte-resultados" class="d-none">
    <div class="card-premium p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0 fw-bold" id="reporte-titulo"></h5>
            <button class="btn btn-outline-cyan btn-sm" id="btn-reporte-csv">
                <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
            </button>
        </div>
        <div id="reporte-error" class="alert alert-danger border-0 text-white d-none" style="background-color: var(--danger-red);"></div>
        <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
            <table class="table table-custom table-striped table-hover align-middle m-0" style="font-size: 0.85rem;">
                <thead class="text-secondary text-uppercase" style="font-size: 0.7rem;" id="reporte-thead"></thead>
                <tbody id="reporte-tbody"></tbody>
            </table>
        </div>
        <div class="mt-3 p-3 border border-color rounded bg-dark" id="reporte-totales"></div>
    </div>
</div>

<!-- ============ RESUMEN ADMINISTRATIVO (Dashboard F8) ============ -->
<div id="resumen-panel" class="d-none">
    <div class="row g-3 mb-3" id="resumen-cards"></div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card-premium p-3 h-100">
                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-trophy text-cyan me-1"></i> Productos MÃ¡s Vendidos (perÃ­odo)</h6>
                <table class="table table-custom table-striped m-0" style="font-size: 0.85rem;">
                    <thead><tr class="text-secondary" style="font-size:0.7rem;"><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody id="resumen-top"></tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card-premium p-3 h-100">
                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-boxes me-1 text-cyan"></i> Movimientos de Inventario (perÃ­odo)</h6>
                <table class="table table-custom table-striped m-0" style="font-size: 0.85rem;">
                    <thead><tr class="text-secondary" style="font-size:0.7rem;"><th>Tipo</th><th class="text-end">Movimientos</th><th class="text-end">Unidades</th></tr></thead>
                    <tbody id="resumen-movimientos"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var fmt = function (v, dec) {
        return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: dec === undefined ? 2 : dec, maximumFractionDigits: dec === undefined ? 2 : dec });
    };

    // ConfiguraciÃ³n de cada reporte: endpoint, columnas y filtros visibles
    var REPORTES = {
        ventas: {
            endpoint: 'reporte_ventas_ajax',
            titulo: 'Reporte de Ventas',
            filtros: ['usuario', 'cliente', 'metodo', 'estado-venta'],
            columnas: [
                { k: 'id', t: 'ID' }, { k: 'fecha_venta', t: 'Fecha' }, { k: 'num_factura', t: 'Factura' },
                { k: 'vendedor', t: 'Vendedor' }, { k: 'cliente', t: 'Cliente' }, { k: 'metodo_pago', t: 'MÃ©todo' },
                { k: 'subtotal', t: 'Subtotal', moneda: true }, { k: 'impuesto', t: 'ISV', moneda: true },
                { k: 'total', t: 'Total', moneda: true }, { k: 'estado', t: 'Estado' }
            ]
        },
        productos: {
            endpoint: 'reporte_productos_ajax',
            titulo: 'Reporte de Productos Vendidos',
            filtros: ['usuario', 'producto'],
            columnas: [
                { k: 'producto', t: 'Producto' }, { k: 'precio', t: 'Precio', moneda: true },
                { k: 'cantidad_vendida', t: 'Cant. Vendida' }, { k: 'cantidad_devuelta', t: 'Cant. Devuelta' },
                { k: 'cantidad_neta', t: 'Cant. Neta' }, { k: 'subtotal', t: 'Ventas (L.)', moneda: true },
                { k: 'monto_devuelto', t: 'Devuelto (L.)', moneda: true }, { k: 'total_generado', t: 'Total Generado (L.)', moneda: true }
            ]
        },
        inventario: {
            endpoint: 'reporte_inventario_ajax',
            titulo: 'Reporte de Inventario / Kardex',
            filtros: ['producto', 'tipo-mov'],
            columnas: [
                { k: 'fecha', t: 'Fecha' }, { k: 'producto', t: 'Producto' }, { k: 'tipo_movimiento', t: 'Tipo' },
                { k: 'cantidad', t: 'Cantidad' }, { k: 'stock_anterior', t: 'Stock Anterior' }, { k: 'stock_nuevo', t: 'Stock Nuevo' },
                { k: 'referencia', t: 'Referencia' }, { k: 'referencia_id', t: 'ID Ref' },
                { k: 'usuario', t: 'Usuario' }, { k: 'observaciones', t: 'Observaciones' }
            ]
        },
        compras: {
            endpoint: 'reporte_compras_ajax',
            titulo: 'Reporte de Compras',
            filtros: ['proveedor', 'estado-compra', 'doc'],
            columnas: [
                { k: 'id', t: 'ID' }, { k: 'fecha_compra', t: 'Fecha' }, { k: 'proveedor', t: 'Proveedor' },
                { k: 'numero_documento', t: 'Documento' }, { k: 'estado', t: 'Estado' },
                { k: 'subtotal', t: 'Subtotal', moneda: true }, { k: 'impuesto', t: 'Impuesto', moneda: true },
                { k: 'total', t: 'Total', moneda: true }
            ]
        },
        devoluciones: {
            endpoint: 'reporte_devoluciones_ajax',
            titulo: 'Reporte de Devoluciones',
            filtros: ['usuario', 'venta', 'producto'],
            columnas: [
                { k: 'devolucion_id', t: 'ID Dev.' }, { k: 'fecha', t: 'Fecha' }, { k: 'venta_id', t: 'Venta' },
                { k: 'usuario', t: 'Usuario' }, { k: 'producto', t: 'Producto' },
                { k: 'cantidad', t: 'Cantidad' }, { k: 'monto', t: 'Monto', moneda: true },
                { k: 'motivo', t: 'Motivo' }, { k: 'estado', t: 'Estado' }
            ]
        },
        caja: {
            endpoint: 'reporte_caja_ajax',
            titulo: 'Reporte de Caja (arqueos)',
            filtros: ['usuario', 'estado-caja'],
            columnas: [
                { k: 'turno_id', t: 'Turno' }, { k: 'usuario', t: 'Usuario' }, { k: 'fecha_apertura', t: 'Apertura' },
                { k: 'fecha_cierre', t: 'Cierre' }, { k: 'monto_apertura', t: 'Inicial', moneda: true },
                { k: 'monto_ventas_efectivo', t: 'Ventas Ef.', moneda: true }, { k: 'monto_ingresos', t: 'Ingresos', moneda: true },
                { k: 'monto_retiros', t: 'Retiros', moneda: true }, { k: 'monto_devoluciones', t: 'Devol.', moneda: true },
                { k: 'efectivo_esperado', t: 'Esperado', moneda: true }, { k: 'monto_cierre_efectivo', t: 'Contado', moneda: true },
                { k: 'diferencia', t: 'Diferencia', moneda: true, signo: true }, { k: 'estado', t: 'Estado' }
            ]
        }
    };

    var reporteActual = 'ventas';
    var ultimaRespuesta = null;

    function activarReporte(tipo) {
        reporteActual = tipo;
        var esResumen = tipo === 'resumen';
        document.getElementById('reporte-resultados').classList.toggle('d-none', esResumen);
        document.getElementById('resumen-panel').classList.toggle('d-none', !esResumen);
        document.getElementById('filtros-especificos').classList.toggle('d-none', esResumen);
        if (esResumen) {
            cargarResumen();
            return;
        }
        // Mostrar/ocultar filtros especÃ­ficos del reporte
        document.querySelectorAll('#filtros-especificos [data-filtro]').forEach(function (el) {
            el.classList.toggle('d-none', REPORTES[tipo].filtros.indexOf(el.dataset.filtro) === -1);
        });
        if (tipo !== 'ventas') { buscarReporte(); }
    }

    function params() {
        var tipo = reporteActual;
        var q = new URLSearchParams();
        var fi = document.getElementById('f-fecha-inicio').value;
        var ff = document.getElementById('f-fecha-fin').value;
        if (fi) q.set('fecha_inicio', fi);
        if (ff) q.set('fecha_fin', ff);
        var mapa = {
            usuario: 'usuario_id', cliente: 'cliente_id', producto: 'producto_id',
            metodo: 'metodo_pago', 'estado-venta': 'estado', 'tipo-mov': 'tipo_movimiento',
            proveedor: 'proveedor_id', 'estado-compra': 'estado', doc: 'numero_documento',
            venta: 'venta_id', 'estado-caja': 'estado'
        };
        REPORTES[tipo].filtros.forEach(function (f) {
            var el = document.getElementById('f-' + f);
            var v = el ? el.value.trim() : '';
            if (v !== '') q.set(mapa[f], v);
        });
        return q;
    }

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function buscarReporte() {
        var cfg = REPORTES[reporteActual];
        document.getElementById('reporte-titulo').textContent = cfg.titulo;
        var errorEl = document.getElementById('reporte-error');
        errorEl.classList.add('d-none');
        document.getElementById('reporte-resultados').classList.remove('d-none');

        fetch('index.php?route=' + cfg.endpoint + '&' + params().toString())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                ultimaRespuesta = d;
                if (!d.success) {
                    errorEl.textContent = d.message || 'Error al consultar el reporte.';
                    errorEl.classList.remove('d-none');
                    document.getElementById('reporte-tbody').innerHTML = '';
                    document.getElementById('reporte-totales').innerHTML = '';
                    return;
                }
                var thead = '<tr>' + cfg.columnas.map(function (c) { return '<th>' + esc(c.t) + '</th>'; }).join('') + '</tr>';
                document.getElementById('reporte-thead').innerHTML = thead;

                var rows = d.rows || [];
                var tbody = document.getElementById('reporte-tbody');
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="' + cfg.columnas.length + '" class="text-center text-secondary py-4">Sin resultados para los filtros indicados.</td></tr>';
                } else {
                    tbody.innerHTML = rows.map(function (r) {
                        return '<tr>' + cfg.columnas.map(function (c) {
                            var v = r[c.k];
                            if (c.moneda) { v = 'L. ' + fmt(v); }
                            else if (c.k === 'cantidad' || c.k === 'cantidad_vendida' || c.k === 'cantidad_devuelta' || c.k === 'cantidad_neta' || c.k === 'unidades' || c.k === 'stock_anterior' || c.k === 'stock_nuevo' || c.k === 'referencia_id') { v = Number(v || 0); }
                            var cls = c.signo ? (Number(r[c.k]) < 0 ? 'text-danger fw-bold' : (Number(r[c.k]) > 0 ? 'text-success fw-bold' : '')) : '';
                            return '<td class="' + cls + '">' + esc(v) + '</td>';
                        }).join('') + '</tr>';
                    }).join('');
                }
                renderTotales(d.totales || {});
            })
            .catch(function () {
                errorEl.textContent = 'No se pudo consultar el reporte.';
                errorEl.classList.remove('d-none');
            });
    }

    function renderTotales(t) {
        var el = document.getElementById('reporte-totales');
        var chips = [];
        Object.keys(t).forEach(function (k) {
            var v = t[k];
            var texto = /(subtotal|impuesto|total|monto|diferencia|efectivo|ingresos|retiros|devoluciones|contado|esperado)/i.test(k)
                ? 'L. ' + fmt(v) : fmt(v, 0);
            chips.push('<span class="badge bg-dark border border-color p-2 me-2 mb-1" style="font-size:0.8rem;">' + esc(k.replace(/_/g, ' ')) + ': <span class="text-cyan">' + texto + '</span></span>');
        });
        el.innerHTML = '<span class="text-secondary small d-block mb-1 fw-semibold">TOTALES DEL REPORTE</span>' + chips.join('');
    }

    function exportarCSV() {
        if (!ultimaRespuesta || !ultimaRespuesta.success) return;
        var cfg = REPORTES[reporteActual];
        var filas = [cfg.columnas.map(function (c) { return c.t; })];
        (ultimaRespuesta.rows || []).forEach(function (r) {
            filas.push(cfg.columnas.map(function (c) {
                var v = r[c.k];
                if (c.moneda) v = 'L. ' + fmt(v);
                v = String(v === null || v === undefined ? '' : v);
                return /[;,\n"]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
            }));
        });
        var csv = '\uFEFF' + filas.map(function (f) { return f.join(','); }).join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'reporte_' + reporteActual + '_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    function cargarResumen() {
        fetch('index.php?route=reporte_dashboard_ajax&' + params().toString())
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.resumen) return;
                var r = d.resumen;
                var periodo = 'PerÃ­odo: ' + r.fecha_inicio + ' a ' + r.fecha_fin;
                var ventas = r.ventas, compras = r.compras, dev = r.devoluciones,
                    cm = r.caja_movimientos, cd = r.caja_diferencias, ia = r.inventario_actual || {};
                var card = function (titulo, valor, icono, color) {
                    return '<div class="col-md-3 col-sm-6">'
                        + '<div class="card-premium p-3 h-100">'
                        + '<small class="text-secondary d-block mb-1">' + esc(titulo) + '</small>'
                        + '<div class="fw-bold ' + (color || 'text-primary') + '">' + esc(valor) + '</div>'
                        + '</div></div>';
                };
                document.getElementById('resumen-cards').innerHTML =
                    card('Ventas del perÃ­odo (' + ventas.cantidad + ')', 'L. ' + fmt(ventas.total), 0, 'text-cyan') +
                    card('Compras confirmadas (' + compras.cantidad + ')', 'L. ' + fmt(compras.total), 0) +
                    card('Devoluciones (' + dev.cantidad + ', ' + dev.unidades + ' u.)', 'L. ' + fmt(dev.monto), 0) +
                    card('Ingresos de caja', 'L. ' + fmt(cm.ingresos), 0) +
                    card('Retiros de caja', 'L. ' + fmt(cm.retiros), 0, 'text-warning') +
                    card('Ventas en efectivo', 'L. ' + fmt(ventas.efectivo), 0) +
                    card('Ventas con tarjeta', 'L. ' + fmt(ventas.tarjeta), 0) +
                    card('Dif. caja (+/-)', '+' + fmt(cd.positivas, 0) + ' / ' + fmt(cd.negativas, 0), 0) +
                    card('Turnos cerrados', cd.turnos + ' Â· Stock total: ' + fmt(ia.unidades_totales, 0) + ' u.', 0) +
                    card('Productos con bajo stock', fmt(ia.bajo_stock, 0), 0, 'text-danger');

                var top = d.resumen.top_productos || [];
                document.getElementById('resumen-top').innerHTML = top.length
                    ? top.map(function (p) {
                        return '<tr><td>' + esc(p.producto) + '</td><td class="text-end">' + Number(p.cantidad) + '</td><td class="text-end">L. ' + fmt(p.subtotal) + '</td></tr>';
                    }).join('')
                    : '<tr><td colspan="3" class="text-center text-secondary py-3">Sin ventas en el perÃ­odo.</td></tr>';

                var movs = d.resumen.movimientos_inventario || [];
                document.getElementById('resumen-movimientos').innerHTML = movs.length
                    ? movs.map(function (m) {
                        return '<tr><td>' + esc(m.tipo_movimiento) + '</td><td class="text-end">' + Number(m.cantidad) + '</td><td class="text-end">' + Number(m.unidades) + '</td></tr>';
                    }).join('')
                    : '<tr><td colspan="3" class="text-center text-secondary py-3">Sin movimientos en el perÃ­odo.</td></tr>';

                var msg = document.getElementById('reporte-titulo');
                if (msg) msg.textContent = 'Resumen del PerÃ­odo';
            });
    }

    document.getElementById('reporte-tipo').addEventListener('change', function (e) { activarReporte(e.target.value); });
    document.getElementById('btn-reporte-buscar').addEventListener('click', function () { activarReporte(reporteActual); });
    document.getElementById('btn-reporte-limpiar').addEventListener('click', function () {
        ['f-fecha-inicio', 'f-fecha-fin', 'f-doc', 'f-venta'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        ['f-usuario', 'f-cliente', 'f-producto', 'f-metodo', 'f-estado-venta', 'f-tipo-mov',
         'f-proveedor', 'f-estado-compra', 'f-estado-caja'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        activarReporte(reporteActual);
    });
    document.getElementById('btn-reporte-csv').addEventListener('click', exportarCSV);

    activarReporte('resumen');
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>