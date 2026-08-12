<?php
// ==========================================
// Vista del Módulo de Compras (Fase 5)
// Nueva compra (BORRADOR) + Confirmación
// con aplicación de stock y Kardex.
// Solo Administrador.
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/CompraController.php';
require_once __DIR__ . '/../controllers/ProveedorController.php';
require_once __DIR__ . '/../controllers/InventarioController.php';

$usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';

$mensaje_exito = "";
$mensaje_error = "";

// ------------------------------------------
// PROCESAMIENTO DE ACCIONES POR POST (Admin)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_rol === 'Administrador') {

    if (!verify_csrf_token()) {
        $mensaje_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {

        // Confirmar compra (BORRADOR -> CONFIRMADA con stock + Kardex)
        if (isset($_POST['accion']) && $_POST['accion'] == 'confirmar_compra') {
            $res = CompraController::confirmarCompra($_POST['compra_id']);
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
    }
}

$compras = CompraController::listarCompras();
$proveedores = ProveedorController::obtenerProveedores(true);
$productos = InventarioController::obtenerProductos(true);
?>

<!-- BANNER MENSAJES -->
<?php if (!empty($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 text-white py-3" style="background-color: var(--success-green);" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 text-white py-3" style="background-color: var(--danger-red);" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card-premium">
    <div class="card-header-premium">
        <div>
            <i class="bi bi-bag-check text-cyan me-2"></i>Compras a Proveedores
        </div>
        <div>
            <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaCompra">
                <i class="bi bi-plus-lg me-1"></i> Nueva Compra
            </button>
        </div>
    </div>

    <div class="p-3 table-responsive">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Proveedor</th>
                    <th scope="col">Documento</th>
                    <th scope="col">Subtotal</th>
                    <th scope="col">Impuesto</th>
                    <th scope="col">Total</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
                            <i class="bi bi-bag-check d-block fs-2 mb-2"></i> No hay compras registradas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($compras as $c): ?>
                        <?php
                        $estadoBadge = match ($c['estado']) {
                            'BORRADOR'   => '<span class="badge badge-borrador">BORRADOR</span>',
                            'CONFIRMADA' => '<span class="badge badge-confirmada">CONFIRMADA</span>',
                            'ANULADA'    => '<span class="badge badge-anulada">ANULADA</span>',
                            default      => '<span class="badge bg-secondary">' . htmlspecialchars($c['estado']) . '</span>',
                        };
                        ?>
                        <tr>
                            <td><code>#<?php echo $c['id']; ?></code></td>
                            <td><span class="fw-bold"><?php echo htmlspecialchars($c['proveedor_nombre']); ?></span></td>
                            <td><?php echo $c['numero_documento'] ? htmlspecialchars($c['numero_documento']) : '-'; ?></td>
                            <td>L. <?php echo number_format($c['subtotal'], 2); ?></td>
                            <td>L. <?php echo number_format($c['impuesto'], 2); ?></td>
                            <td class="text-cyan fw-semibold">L. <?php echo number_format($c['total'], 2); ?></td>
                            <td><?php echo $estadoBadge; ?></td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($c['fecha_compra'])); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1 py-1 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDetalleCompra"
                                        onclick="cargarDetalle(<?php echo $c['id']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($c['estado'] === 'BORRADOR'): ?>
                                    <button class="btn btn-sm btn-outline-success py-1 px-2"
                                            onclick="confirmarCompra(<?php echo $c['id']; ?>)"
                                            title="Confirmar compra (aplica stock)">
                                        <i class="bi bi-check2-circle"></i>
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

<!-- ==========================================================
     MODAL: NUEVA COMPRA (BORRADOR)
     ========================================================== -->
<div class="modal fade" id="modalNuevaCompra" tabindex="-1" aria-labelledby="modalNuevaCompraLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-plus-circle-fill text-cyan me-2"></i> Nueva Compra (BORRADOR)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-semibold">Proveedor *</label>
                        <select class="form-select form-control-custom" id="compra_proveedor_id" required>
                            <option value="">-- Seleccionar proveedor --</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-semibold">Número de Documento</label>
                        <input type="text" class="form-control form-control-custom" id="compra_documento" placeholder="Ej: FAC-001-2026" maxlength="100">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-secondary fw-semibold">Observaciones</label>
                        <textarea class="form-control form-control-custom" id="compra_observaciones" rows="1" maxlength="500" placeholder="Notas de la compra..."></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="text-secondary fw-semibold m-0">Productos de la Compra</label>
                    <button type="button" class="btn btn-outline-cyan btn-sm" onclick="agregarFilaProducto()">
                        <i class="bi bi-plus-lg me-1"></i> Agregar Producto
                    </button>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-custom m-0" id="tablaDetalles">
                        <thead>
                            <tr>
                                <th scope="col" style="min-width: 280px;">Producto *</th>
                                <th scope="col" style="width: 120px;">Cantidad *</th>
                                <th scope="col" style="width: 140px;">Costo Unit. (L.) *</th>
                                <th scope="col" style="width: 140px;">Subtotal</th>
                                <th scope="col" style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyDetalles">
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">
                                    <i class="bi bi-cart-plus d-block fs-2 mb-2"></i> Agrega al menos un producto para guardar la compra.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row justify-content-end g-2">
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between text-secondary">
                            <span>Subtotal:</span>
                            <span id="tot_subtotal" class="fw-bold">L. 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-secondary">
                            <span>Impuesto (16%):</span>
                            <span id="tot_impuesto" class="fw-bold">L. 0.00</span>
                        </div>
                        <hr class="border-secondary my-2">
                        <div class="d-flex justify-content-between fs-5">
                            <span class="text-secondary fw-semibold">Total:</span>
                            <span id="tot_total" class="fw-bold text-cyan">L. 0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer modal-footer-premium">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-cyan px-4" onclick="guardarBorrador()">
                    <i class="bi bi-save me-1"></i> Guardar Borrador
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DE COMPRA -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-labelledby="modalDetalleCompraLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-receipt text-cyan me-2"></i> Detalle de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detalle_compra_body">
                Cargando detalles...
            </div>
            <div class="modal-footer modal-footer-premium">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
var PRODUCTOS_CATALOGO = <?php echo json_encode(array_map(function ($p) {
    return ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'precio_compra' => (float)$p['precio_compra']];
}, $productos)); ?>;

function productoPorId(id) {
    for (var i = 0; i < PRODUCTOS_CATALOGO.length; i++) {
        if (PRODUCTOS_CATALOGO[i].id === id) return PRODUCTOS_CATALOGO[i];
    }
    return null;
}

function agregarFilaProducto() {
    var tbody = document.getElementById('tbodyDetalles');
    var vacia = tbody.querySelector('td[colspan]');
    if (vacia) tbody.innerHTML = '';

    var opts = '<option value="">-- Seleccionar producto --</option>';
    for (var i = 0; i < PRODUCTOS_CATALOGO.length; i++) {
        opts += '<option value="' + PRODUCTOS_CATALOGO[i].id + '">' + PRODUCTOS_CATALOGO[i].nombre + '</option>';
    }

    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select class="form-select form-select-sm form-control-custom fila-producto">' + opts + '</select></td>' +
        '<td><input type="number" min="1" step="1" class="form-control form-control-custom form-control-sm fila-cantidad" value="1"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-custom form-control-sm fila-costo" placeholder="0.00"></td>' +
        '<td class="fila-subtotal text-secondary fw-semibold">L. 0.00</td>' +
        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="quitarFila(this)"><i class="bi bi-x-lg"></i></button></td>';
    tbody.appendChild(tr);

    var sel = tr.querySelector('.fila-producto');
    sel.addEventListener('change', function () {
        var prod = productoPorId(parseInt(this.value, 10));
        if (prod) {
            tr.querySelector('.fila-costo').value = prod.precio_compra.toFixed(2);
        }
        recalcularTotales();
    });
    tr.querySelector('.fila-cantidad').addEventListener('input', recalcularTotales);
    tr.querySelector('.fila-costo').addEventListener('input', recalcularTotales);
}

function quitarFila(btn) {
    var tbody = document.getElementById('tbodyDetalles');
    tbody.removeChild(btn.closest('tr'));
    if (!tbody.querySelector('tr')) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-4"><i class="bi bi-cart-plus d-block fs-2 mb-2"></i> Agrega al menos un producto para guardar la compra.</td></tr>';
    }
    recalcularTotales();
}

function recalcularTotales() {
    var filas = document.querySelectorAll('#tbodyDetalles tr');
    var sub = 0;
    filas.forEach(function (tr) {
        var cant = parseFloat(tr.querySelector('.fila-cantidad').value) || 0;
        var costo = parseFloat(tr.querySelector('.fila-costo').value) || 0;
        var s = cant * costo;
        tr.querySelector('.fila-subtotal').textContent = 'L. ' + s.toFixed(2);
        sub += s;
    });
    var imp = sub * 0.16;
    document.getElementById('tot_subtotal').textContent = 'L. ' + sub.toFixed(2);
    document.getElementById('tot_impuesto').textContent = 'L. ' + imp.toFixed(2);
    document.getElementById('tot_total').textContent = 'L. ' + (sub + imp).toFixed(2);
}

function guardarBorrador() {
    var proveedor_id = document.getElementById('compra_proveedor_id').value;
    var detalles = [];

    document.querySelectorAll('#tbodyDetalles tr').forEach(function (tr) {
        var pid = tr.querySelector('.fila-producto').value;
        var cant = tr.querySelector('.fila-cantidad').value;
        var costo = tr.querySelector('.fila-costo').value;
        if (pid) {
            detalles.push({ producto_id: parseInt(pid, 10), cantidad: parseInt(cant, 10), costo_unitario: parseFloat(costo) });
        }
    });

    if (!proveedor_id) { showToast('Compra incompleta', 'Selecciona un proveedor.', 'warning'); return; }
    if (detalles.length === 0) { showToast('Compra incompleta', 'Agrega al menos un producto.', 'warning'); return; }

    var payload = {
        proveedor_id: parseInt(proveedor_id, 10),
        numero_documento: document.getElementById('compra_documento').value,
        observaciones: document.getElementById('compra_observaciones').value,
        detalles: detalles
    };

    fetch('index.php?route=crear_compra_ajax', {
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
            showToast('Borrador guardado', j.message, 'success');
            location.reload();
        } else {
            showToast('No se pudo guardar', j.message, 'danger');
        }
    })
    .catch(function () { showToast('Error de red', 'No se pudo guardar la compra.', 'danger'); });
}

function confirmarCompra(compraId) {
    showConfirm(
        '<p class="mb-1 fw-semibold">¿Confirmar la compra <code>#' + compraId + '</code>?</p>' +
        '<p class="text-secondary small mb-0"><i class="bi bi-info-circle me-1"></i>Se aplicará el stock y se registrará el Kardex ' +
        '<span class="kardex-badge-in">ENTRADA_COMPRA</span> de cada producto. Esta acción no se puede deshacer.</p>',
        function () {
            var body = new URLSearchParams();
            body.append('compra_id', compraId);

            fetch('index.php?route=confirmar_compra_ajax', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.success) {
                    showToast('Compra confirmada', j.message, 'success');
                    location.reload();
                } else {
                    showToast('No se pudo confirmar', j.message, 'danger');
                }
            })
            .catch(function () { showToast('Error de red', 'No se pudo confirmar la compra.', 'danger'); });
        },
        { titulo: 'Confirmar compra', boton: '<i class="bi bi-check2-circle me-1"></i> Confirmar compra' }
    );
}

function cargarDetalle(compraId) {
    var body = document.getElementById('detalle_compra_body');
    body.innerHTML = 'Cargando detalles...';
    fetch('index.php?route=detalle_compra_ajax&compra_id=' + compraId)
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (!data || !data.compra) {
            body.innerHTML = '<p class="text-secondary m-0">No se pudo cargar la compra.</p>';
            return;
        }
        var c = data.compra;
        var h = '<div class="row g-3 mb-3">' +
            '<div class="col-md-4"><small class="text-secondary d-block">Proveedor</small><strong>' + esc(c.proveedor_nombre) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Documento</small><strong>' + (c.numero_documento ? esc(c.numero_documento) : '-') + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Usuario</small><strong>' + (c.usuario_nombre ? esc(c.usuario_nombre) : '-') + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Fecha</small><strong>' + esc(c.fecha_compra) + '</strong></div>' +
            '<div class="col-md-4"><small class="text-secondary d-block">Estado</small><strong>' + esc(c.estado) + '</strong></div>' +
            (c.observaciones ? '<div class="col-md-12"><small class="text-secondary d-block">Observaciones</small><strong>' + esc(c.observaciones) + '</strong></div>' : '') +
            '</div>' +
            '<table class="table table-custom table-sm m-0">' +
            '<thead><tr><th>Producto</th><th class="text-center">Cantidad</th><th class="text-end">Costo Unit.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
        data.detalles.forEach(function (d) {
            h += '<tr><td>' + esc(d.producto_nombre) + '</td>' +
                 '<td class="text-center">' + esc(d.cantidad) + '</td>' +
                 '<td class="text-end">L. ' + parseFloat(d.costo_unitario).toFixed(2) + '</td>' +
                 '<td class="text-end fw-semibold">L. ' + parseFloat(d.subtotal).toFixed(2) + '</td></tr>';
        });
        h += '</tbody></table>' +
             '<div class="row justify-content-end mt-3 g-1"><div class="col-md-4">' +
             '<div class="d-flex justify-content-between text-secondary"><span>Subtotal:</span><span class="fw-bold">L. ' + parseFloat(c.subtotal).toFixed(2) + '</span></div>' +
             '<div class="d-flex justify-content-between text-secondary"><span>Impuesto (16%):</span><span class="fw-bold">L. ' + parseFloat(c.impuesto).toFixed(2) + '</span></div>' +
             '<hr class="border-secondary my-1"><div class="d-flex justify-content-between"><span class="fw-semibold text-secondary">Total:</span><span class="fw-bold text-cyan">L. ' + parseFloat(c.total).toFixed(2) + '</span></div>' +
             '</div></div>';
        body.innerHTML = h;
    })
    .catch(function () { body.innerHTML = '<p class="text-secondary m-0">Error al cargar la compra.</p>'; });
}

function esc(s) {
    return (s === null || s === undefined) ? '-' : String(s).replace(/[&"']/g, function (c) {
        return { '&': '&amp;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
