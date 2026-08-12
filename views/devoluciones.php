<?php
// ==========================================
// Vista del Módulo de Devoluciones (Fase 6)
// Devolución completa o parcial de una venta:
// reintegra stock (Kardex DEVOLUCION_VENTA) y
// registra el dinero devuelto en la caja
// (EGRESO_DEVOLUCION). Cajero y Administrador.
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/DevolucionController.php';

$devoluciones = DevolucionController::listarDevoluciones(100);
?>

<div class="card-premium">
    <div class="card-header-premium">
        <div>
            <i class="bi bi-arrow-return-left text-cyan me-2"></i>Devoluciones
        </div>
        <div>
            <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaDevolucion">
                <i class="bi bi-plus-lg me-1"></i> Nueva Devolución
            </button>
        </div>
    </div>

    <div class="p-3 table-responsive">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Documento</th>
                    <th scope="col">Factura Original</th>
                    <th scope="col">Cajero</th>
                    <th scope="col">Monto Devuelto</th>
                    <th scope="col">Método (Venta)</th>
                    <th scope="col">Motivo</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" class="text-end">Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devoluciones)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
                            <i class="bi bi-arrow-return-left d-block fs-2 mb-2"></i> No hay devoluciones registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devoluciones as $d): ?>
                        <tr>
                            <td><code>#<?php echo $d['id']; ?></code></td>
                            <td><code><?php echo htmlspecialchars($d['num_devolucion']); ?></code></td>
                            <td>
                                <span class="fw-bold"><?php echo htmlspecialchars($d['num_factura']); ?></span>
                                <?php if ($d['estado_venta'] !== 'Completada'): ?>
                                    <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($d['estado_venta']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $d['nombre_usuario'] ? htmlspecialchars($d['nombre_usuario']) : '-'; ?></td>
                            <td class="text-cyan fw-semibold">L. <?php echo number_format($d['monto_total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($d['metodo_pago_venta']); ?></td>
                            <td class="text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($d['motivo'] ?? ''); ?>">
                                <?php echo $d['motivo'] ? htmlspecialchars($d['motivo']) : '-'; ?>
                            </td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($d['fecha'])); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary py-1 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDetalleDevolucion"
                                        onclick="cargarDetalleDevolucion(<?php echo $d['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================
     MODAL: NUEVA DEVOLUCIÓN
     ========================================================== -->
<div class="modal fade" id="modalNuevaDevolucion" tabindex="-1" aria-labelledby="modalNuevaDevolucionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-arrow-return-left text-cyan me-2"></i> Nueva Devolución</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3 mb-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-secondary fw-semibold">ID de la Venta *</label>
                        <input type="number" min="1" class="form-control form-control-custom" id="dev_venta_id" placeholder="Ej: 1">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-cyan w-100" onclick="cargarVentaDevolucion()">
                            <i class="bi bi-search me-1"></i> Buscar Venta
                        </button>
                    </div>
                    <div class="col-md-5 text-end text-secondary small" id="dev_venta_info"></div>
                </div>

                <div id="dev_venta_cargada" style="display:none;">
                    <div class="table-responsive mb-3">
                        <table class="table table-custom m-0">
                            <thead>
                                <tr>
                                    <th scope="col">Producto</th>
                                    <th scope="col" class="text-center">Vendido</th>
                                    <th scope="col" class="text-center">Devuelto</th>
                                    <th scope="col" class="text-center">Disponible</th>
                                    <th scope="col" class="text-center" style="width: 120px;">A Devolver *</th>
                                    <th scope="col" class="text-end">Subtotal Dev.</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDevDetalles"></tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-secondary fw-semibold">Motivo de la Devolución</label>
                            <textarea class="form-control form-control-custom" id="dev_motivo" rows="2" maxlength="500" placeholder="Ej: cliente devolvió la taza por defecto de fábrica..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between text-secondary">
                                <span>Total a Devolver:</span>
                                <span id="dev_total" class="fw-bold text-cyan fs-5">L. 0.00</span>
                            </div>
                            <small class="text-secondary d-block mt-2" style="font-size:0.75rem;">
                                El dinero se registra como EGRESO del turno de caja actual y el stock se reintegra automáticamente.
                            </small>
                        </div>
                    </div>
                </div>

                <div id="dev_venta_error" class="text-danger fw-semibold small d-none mt-2"></div>
            </div>

            <div class="modal-footer modal-footer-premium">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-cyan px-4" id="btnRegistrarDevolucion" onclick="registrarDevolucion()" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Registrar Devolución
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
     MODAL: DETALLE DE DEVOLUCIÓN
     ========================================================== -->
<div class="modal fade" id="modalDetalleDevolucion" tabindex="-1" aria-labelledby="modalDetalleDevolucionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-arrow-return-left text-cyan me-2"></i> Detalle de Devolución</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detalle_devolucion_body">
                Cargando detalles...
            </div>
            <div class="modal-footer modal-footer-premium">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
var DEV_VENTA_DETALLES = [];

function esc(s) {
    return (s === null || s === undefined) ? '-' : String(s).replace(/[&"']/g, function (c) {
        return { '&': '&amp;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function cargarVentaDevolucion() {
    var venta_id = document.getElementById('dev_venta_id').value;
    var errorBox = document.getElementById('dev_venta_error');

    if (!venta_id || parseInt(venta_id, 10) <= 0) {
        errorBox.textContent = 'Indica el ID de la venta a devolver.';
        errorBox.classList.remove('d-none');
        return;
    }

    fetch('index.php?route=venta_devolucion_datos_ajax&venta_id=' + encodeURIComponent(venta_id))
    .then(function (r) {
        if (r.status === 404) throw new Error('Venta no encontrada.');
        if (r.status === 403) throw new Error('Acceso denegado: un cajero solo puede devolver sus propias ventas.');
        if (!r.ok) throw new Error('Error al consultar la venta.');
        return r.json();
    })
    .then(function (data) {
        if (data.venta.estado !== 'Completada') {
            throw new Error('La venta ya fue ' + esc(data.venta.estado) + ' y no se puede devolver.');
        }
        errorBox.classList.add('d-none');

        var v = data.venta;
        document.getElementById('dev_venta_info').innerHTML =
            '<small class="d-block text-secondary">Factura: <strong>' + esc(v.num_factura) +
            '</strong></small>' +
            '<small class="d-block text-secondary">Total: <strong>L. ' + parseFloat(v.total).toFixed(2) +
            '</strong> (' + esc(v.metodo_pago) + ') | Cajero: <strong>' + esc(v.nombre_usuario) + '</strong></small>';

        var tbody = document.getElementById('tbodyDevDetalles');
        tbody.innerHTML = '';
        DEV_VENTA_DETALLES = data.detalles;

        if (data.detalles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">La venta no tiene productos.</td></tr>';
        }

        data.detalles.forEach(function (d) {
            var tr = document.createElement('tr');
            tr.dataset.productoId = d.producto_id;
            tr.innerHTML =
                '<td><span class="fw-semibold">' + esc(d.nombre_producto) + '</span></td>' +
                '<td class="text-center">' + d.vendido + '</td>' +
                '<td class="text-center text-warning">' + d.devuelto + '</td>' +
                '<td class="text-center text-cyan fw-semibold" class="td-disponible">' + d.disponible + '</td>' +
                '<td class="text-center"><input type="number" min="0" step="1" class="form-control form-control-custom form-control-sm text-center fila-devolver" value="0" max="' + d.disponible + '" ' + (d.disponible <= 0 ? 'disabled' : '') + '></td>' +
                '<td class="text-end subtotal-dev text-secondary fw-semibold">L. 0.00</td>';
            tbody.appendChild(tr);
            tr.querySelector('.fila-devolver').addEventListener('input', recalcularDevolucion);
        });

        document.getElementById('dev_venta_cargada').style.display = 'block';
        recalcularDevolucion();
    })
    .catch(function (err) {
        document.getElementById('dev_venta_cargada').style.display = 'none';
        document.getElementById('dev_total').textContent = 'L. 0.00';
        errorBox.textContent = err.message || 'Error al consultar la venta.';
        errorBox.classList.remove('d-none');
    });
}

function recalcularDevolucion() {
    var total = 0;
    var alguna = false;
    document.querySelectorAll('#tbodyDevDetalles tr').forEach(function (tr) {
        var pid = parseInt(tr.dataset.productoId, 10);
        var input = tr.querySelector('.fila-devolver');
        var cant = parseInt(input.value, 10) || 0;
        var det = null;
        for (var i = 0; i < DEV_VENTA_DETALLES.length; i++) {
            if (DEV_VENTA_DETALLES[i].producto_id === pid) { det = DEV_VENTA_DETALLES[i]; break; }
        }
        var max = det ? det.disponible : 0;
        if (cant > max) { cant = max; input.value = cant; }
        if (cant > 0) alguna = true;
        var sub = det ? cant * parseFloat(det.precio_unitario) : 0;
        tr.querySelector('.subtotal-dev').textContent = 'L. ' + sub.toFixed(2);
        total += sub;
    });
    document.getElementById('dev_total').textContent = 'L. ' + total.toFixed(2);
    document.getElementById('btnRegistrarDevolucion').disabled = !alguna;
}

function registrarDevolucion() {
    var venta_id = parseInt(document.getElementById('dev_venta_id').value, 10);
    var items = [];

    document.querySelectorAll('#tbodyDevDetalles tr').forEach(function (tr) {
        var cant = parseInt(tr.querySelector('.fila-devolver').value, 10) || 0;
        if (cant > 0) {
            items.push({ producto_id: parseInt(tr.dataset.productoId, 10), cantidad: cant });
        }
    });

    if (!venta_id) { showToast('Devolución incompleta', 'Indica el ID de la venta.', 'warning'); return; }
    if (items.length === 0) { showToast('Devolución incompleta', 'Indica al menos un producto con cantidad a devolver.', 'warning'); return; }

    var payload = {
        venta_id: venta_id,
        motivo: document.getElementById('dev_motivo').value,
        items: items
    };

    fetch('index.php?route=crear_devolucion_ajax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN
        },
        body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (j) {
        if (j.success) {
            showToast('Devolución registrada', j.message, 'success');
            location.reload();
        } else {
            showToast('No se pudo registrar', j.message, 'danger');
        }
    })
    .catch(function () { showToast('Error de red', 'No se pudo registrar la devolución.', 'danger'); });
}

function cargarDetalleDevolucion(devolucionId) {
    var body = document.getElementById('detalle_devolucion_body');
    body.innerHTML = 'Cargando detalles...';
    fetch('index.php?route=detalle_devolucion_ajax&devolucion_id=' + devolucionId)
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (!data || !data.devolucion) {
            body.innerHTML = '<p class="text-secondary m-0">No se pudo cargar la devolución.</p>';
            return;
        }
        var d = data.devolucion;
        var h = '<div class="row g-3 mb-3">' +
            '<div class="col-md-4"><small class="text-secondary d-block">Documento</small><strong>' + esc(d.num_devolucion) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Factura Original</small><strong>' + esc(d.num_factura) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Usuario</small><strong>' + (d.nombre_usuario ? esc(d.nombre_usuario) : '-') + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Fecha</small><strong>' + esc(d.fecha) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Método (Venta)</small><strong>' + esc(d.metodo_pago_venta) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Total Devuelto</small><strong class="text-danger">L. ' + parseFloat(d.monto_total).toFixed(2) + '</strong></div>' +
            (d.motivo ? '<div class="col-md-12"><small class="text-secondary d-block">Motivo</small><strong>' + esc(d.motivo) + '</strong></div>' : '') +
            '</div>' +
            '<table class="table table-custom table-sm m-0">' +
            '<thead><tr><th>Producto</th><th class="text-center">Cantidad</th><th class="text-end">Precio Unit.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
        data.detalles.forEach(function (dd) {
            h += '<tr><td>' + esc(dd.nombre_producto) + '</td>' +
                 '<td class="text-center">' + dd.cantidad + '</td>' +
                 '<td class="text-end">L. ' + parseFloat(dd.precio_unitario).toFixed(2) + '</td>' +
                 '<td class="text-end fw-semibold">L. ' + parseFloat(dd.subtotal).toFixed(2) + '</td></tr>';
        });
        h += '</tbody></table>';
        body.innerHTML = h;
    })
    .catch(function () { body.innerHTML = '<p class="text-secondary m-0">Error al cargar la devolución.</p>'; });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>