<?php
// ==========================================
// Vista del Módulo POS (Venta Interactiva)
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/CajaController.php';
require_once __DIR__ . '/../controllers/InventarioController.php';
require_once __DIR__ . '/../controllers/ClienteController.php';

$usuario_id = $_SESSION['usuario_id'];
$caja_activa = CajaController::obtenerSesionActiva($usuario_id);

// Procesar Apertura de Caja por formulario normal (Tradicional POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_caja']) && $_POST['accion_caja'] == 'apertura') {
    if (!verify_csrf_token()) {
        $caja_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {
        $fondos = limpiarMonto($_POST['monto_apertura'] ?? 0);
        $res_ap = CajaController::abrirCaja($usuario_id, $fondos);
        if ($res_ap['success']) {
            echo "<script>window.location.href='index.php?route=pos';</script>";
            exit();
        } else {
            $caja_error = $res_ap['message'];
        }
    }
}

// Procesar Cierre de Caja por formulario normal (Tradicional POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_caja']) && $_POST['accion_caja'] == 'cierre') {
    if (!verify_csrf_token()) {
        $caja_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {
        $cierre_efectivo = limpiarMonto($_POST['cierre_efectivo'] ?? 0);
        $cierre_tarjeta = limpiarMonto($_POST['cierre_tarjeta'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';
        
        if ($caja_activa) {
            $res_cr = CajaController::cerrarCaja($caja_activa['id'], $cierre_efectivo, $cierre_tarjeta, $observaciones);
            if ($res_cr['success']) {
                echo "<script>window.location.href='index.php?route=pos';</script>";
                exit();
            } else {
                $caja_error = $res_cr['message'];
            }
        }
    }
}

$productos_catalogo = InventarioController::obtenerProductos(true);
$clientes_lista = ClienteController::obtenerClientes();
?>

<!-- ALERTA DE ERROR EN TURNO DE CAJA -->
<?php if (isset($caja_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 text-white py-3 mb-4" style="background-color: var(--danger-red);" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($caja_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- CASO 1: CAJA CERRADA (REQUERIR APERTURA ANTES DE ACCEDER AL POS) -->
<?php if (!$caja_activa): ?>
    <div class="row justify-content-center align-items-center" style="min-height: calc(80vh - 100px);">
        <div class="col-md-5">
            <div class="card-premium p-4 text-center">
                <div class="p-3 bg-dark border border-color rounded-circle d-inline-block mb-3">
                    <i class="bi bi-lock-fill text-cyan fs-1"></i>
                </div>
                <h3 class="fw-bold">Apertura de Caja Obligatoria</h3>
                <p class="text-secondary mb-4">
                    Para comenzar a registrar ventas del catálogo de sublimación, debes aperturar un turno de caja especificando el fondo de dinero físico inicial en la gaveta.
                </p>

                <form action="" method="POST" autocomplete="off" class="text-start">
                    <input type="hidden" name="accion_caja" value="apertura">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-4">
                        <label for="monto_apertura" class="form-label text-secondary fw-semibold">Monto Inicial en Efectivo (L.)</label>
                        <div class="input-group">
                            <span class="input-group-text form-control-custom bg-dark border-end-0 text-cyan">L.</span>
                            <input type="number" step="0.01" min="0" class="form-control form-control-custom border-start-0 ps-0" id="monto_apertura" name="monto_apertura" value="500.00" required>
                        </div>
                        <small class="text-secondary mt-1 d-block">Suma total de monedas y billetes para dar cambio al cliente.</small>
                    </div>

                    <button type="submit" class="btn btn-cyan w-100 py-3 text-uppercase fw-bold">
                        <i class="bi bi-unlock-fill me-1"></i> Abrir Turno de Ventas
                    </button>
                </form>
            </div>
        </div>
    </div>

<!-- CASO 2: CAJA ABIERTA -> RENDERIZAR ENTORNO POS COMPLETO -->
<?php else: ?>
    <div class="row">
        <!-- Panel Izquierdo: Buscador & Catálogo de Productos para selección rápida -->
        <div class="col-lg-8 mb-4">
            <div class="card-premium p-3 mb-3">
                <div class="row g-3">
                    <!-- Escaneo rápido (Código de Barras) -->
                    <div class="col-md-5">
                        <label for="barcode-search" class="form-label text-secondary fw-semibold">
                            <i class="bi bi-upc-scan text-cyan me-1"></i> Lector Código Barras
                        </label>
                        <input type="text" class="form-control form-control-custom" id="barcode-search" placeholder="Escanea o escribe el código + ENTER">
                    </div>
                    
                    <!-- Búsqueda manual por Nombre -->
                    <div class="col-md-7 position-relative">
                        <label for="product-name-search" class="form-label text-secondary fw-semibold">
                            <i class="bi bi-search text-cyan me-1"></i> Buscar por Nombre
                        </label>
                        <input type="text" class="form-control form-control-custom" id="product-name-search" placeholder="Escribe el nombre del artículo de sublimación (mínimo 2 letras)">
                        
                        <!-- Panel flotante de autocompletar -->
                        <ul id="product-search-results" class="dropdown-menu w-100 modal-content-premium border-color mt-1" style="max-height: 250px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>

            <!-- Grilla de Acceso Rápido del Catálogo -->
            <div class="card-premium p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="m-0 fw-bold"><i class="bi bi-grid-fill text-cyan me-2"></i> Acceso Rápido al Inventario</h5>
                    <span class="text-secondary small">Haz clic para agregar al carrito</span>
                </div>
                
                <div class="row row-cols-2 row-cols-md-4 g-2 pos-productos-scroll">
                    <?php if (empty($productos_catalogo)): ?>
                        <div class="col-12 py-5 text-center text-muted">
                            No hay productos registrados en el catálogo de inventarios.
                        </div>
                    <?php else: ?>
                        <?php foreach ($productos_catalogo as $prod): ?>
                            <?php 
                            $esBajoStock = $prod['stock'] <= $prod['stock_minimo'];
                            $esAgotado = $prod['stock'] == 0 || $prod['disponibilidad'] == 'Agotado'; 
                            ?>
                            <div class="col">
                                <div class="card pos-producto p-2 text-center h-100 d-flex flex-column justify-content-between position-relative" 
                                     onclick='addToCart(<?php echo json_encode($prod); ?>)'
                                     role="button" tabindex="0"
                                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();addToCart(<?php echo json_encode($prod); ?>)}"
                                     aria-label="Agregar <?php echo htmlspecialchars($prod['nombre']); ?> al carrito">
                                    
                                    <!-- Stock Badge Flotante -->
                                    <span class="position-absolute top-0 start-50 translate-middle-y badge rounded-pill <?php echo $esAgotado ? 'bg-danger' : ($esBajoStock ? 'bg-warning text-dark' : 'bg-secondary'); ?>" style="font-size:0.75rem;">
                                        Stock: <?php echo $prod['stock']; ?>
                                    </span>
                                    
                                    <div class="mt-2 mb-2">
                                        <div class="fw-bold text-primary text-truncate small" title="<?php echo htmlspecialchars($prod['nombre']); ?>">
                                            <?php echo htmlspecialchars($prod['nombre']); ?>
                                        </div>
                                        <small class="text-secondary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($prod['nombre_categoria']); ?></small>
                                    </div>
                                    <div class="fw-bold text-cyan precio mt-auto">
                                        L. <?php echo number_format($prod['precio_venta'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Carrito de Compras en Curso -->
        <div class="col-lg-4">
            <div class="card-premium d-flex flex-column" style="height: calc(100vh - 150px);">
                <div class="card-header-premium d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <span class="fw-bold"><i class="bi bi-cart3 text-cyan me-2"></i> Carrito Ventas</span>
                    <span class="d-flex gap-2 align-items-center">
                        <span class="badge pos-caja-badge px-3 py-2" title="Efectivo disponible en gaveta">
                            <i class="bi bi-cash-stack me-1"></i> Saldo: <span id="caja-saldo-text">L. 0.00</span>
                        </span>
                        <button class="btn btn-outline-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#movimientoCajaModal">
                            <i class="bi bi-arrow-left-right"></i> Movimientos
                        </button>
                        <button class="btn btn-outline-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#cierreCajaModal">
                            <i class="bi bi-lock-fill"></i> Arqueo & Cierre
                        </button>
                    </span>
                </div>
                
                <!-- Cliente Asignado -->
                <div class="p-3 border-bottom border-color bg-dark d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-secondary d-block" style="font-size: 0.75rem;">Cliente:</small>
                        <div id="customer-selected-badge">
                            <span class="badge bg-dark p-2 text-secondary">Público General</span>
                        </div>
                    </div>
                    
                    <!-- Selector de cliente dropdown clickeable -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-cyan dropdown-toggle" type="button" id="customerPosDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people-fill"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end modal-content-premium border-color p-3" aria-labelledby="customerPosDropdown" style="width: 280px;">
                            <label for="customer-search" class="form-label small text-secondary">Busca un Cliente</label>
                            <input type="text" class="form-control form-control-custom form-control-sm mb-2" id="customer-search" placeholder="Nombre o Teléfono">
                            <ul id="customer-search-results" class="list-unstyled mb-0" style="max-height: 150px; overflow-y:auto;"></ul>
                        </div>
                    </div>
                </div>

                <!-- Detalle Carrito Físico -->
                <div class="cart-items" id="cart-items-container">
                    <div class="text-center text-muted p-5">
                        <i class="bi bi-cart3 fs-1 d-block mb-3" style="color: var(--border-color);"></i>
                        El carrito está vacío
                    </div>
                </div>

                <!-- Resumen de Totales y Botón Cobrar -->
                <div class="cart-total-section mt-auto">
                    <table class="table table-borderless text-white table-sm m-0">
                        <tr>
                            <td class="text-secondary ps-0">Subtotal (Neto):</td>
                            <td class="text-end pe-0" id="subtotal-display">$0.00</td>
                        </tr>
                        <tr>
                            <td class="text-secondary ps-0">Impuestos (IVA 16%):</td>
                            <td class="text-end pe-0" id="tax-display">$0.00</td>
                        </tr>
                        <tr class="border-top border-color">
                            <td class="fs-5 fw-bold ps-0 text-cyan">TOTAL:</td>
                            <td class="fs-5 fw-bold text-end pe-0 text-cyan" id="total-display">$0.00</td>
                        </tr>
                    </table>
                    
                    <button class="btn btn-cyan w-100 py-3 mt-3 fw-bold text-uppercase" id="pos-checkout-btn" data-bs-toggle="modal" data-bs-target="#checkoutModal" disabled>
                        <i class="bi bi-cash-coin me-1"></i> Cobrar Compra
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         MODAL 1: COBRO DE LA VENTA
         ========================================================== -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white" id="checkoutModalLabel"><i class="bi bi-cash-coin text-cyan me-2"></i> Procesar Pago</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <div class="text-center mb-4">
                        <small class="text-secondary text-uppercase d-block fw-semibold mb-1">Total a Pagar</small>
                        <h1 class="text-cyan fw-bold" id="checkout-modal-total-text" style="font-size: 3rem;">$0.00</h1>
                    </div>

                    <div class="mb-3">
                        <label for="payment-method" class="form-label text-secondary fw-semibold">Método de Pago</label>
                        <select class="form-select form-control-custom" id="payment-method" required>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta de Crédito/Débito</option>
                            <option value="Transferencia">Transferencia Bancaria</option>
                        </select>
                    </div>

                    <!-- Detalles para pago por Efectivo -->
                    <div id="cash-payment-details">
                        <div class="mb-3">
                            <label for="cash-received" class="form-label text-secondary fw-semibold">Efectivo Recibido (L.)</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-custom text-cyan fw-bold fs-4" id="cash-received" value="0.00">
                        </div>

                        <div class="mb-3">
                            <label for="cash-change" class="form-label text-secondary fw-semibold">Cambio a Entregar (L.)</label>
                            <input type="text" class="form-control form-control-custom fw-bold fs-4" id="cash-change" value="L. 0.00" readonly>
                        </div>
                        
                        <div class="text-danger small mb-3 fw-semibold text-center" id="change-warning-message" style="display:none;">
                            <i class="bi bi-exclamation-circle-fill"></i> El monto recibido es menor al total de la factura.
                        </div>
                    </div>

                </div>
                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-cyan px-4" id="btn-confirm-checkout">Confirmar Cobro</button>
                </div>
            </div>
        </div>
    </div>


    <!-- ==========================================================
         MODAL 3: INGRESO / RETIRO DE CAJA (Fase 7)
         ========================================================== -->
    <div class="modal fade" id="movimientoCajaModal" tabindex="-1" aria-labelledby="movimientoCajaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white" id="movimientoCajaModalLabel"><i class="bi bi-arrow-left-right text-cyan me-2"></i> Movimiento de Caja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="mov-tipo" class="form-label text-secondary fw-semibold">Tipo de Movimiento</label>
                        <select class="form-select form-control-custom" id="mov-tipo" required>
                            <option value="INGRESO">Ingreso (dinero que entra a la gaveta)</option>
                            <option value="RETIRO">Retiro (dinero que sale de la gaveta)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="mov-monto" class="form-label text-secondary fw-semibold">Monto (L.)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-custom fw-bold text-cyan" id="mov-monto" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label for="mov-motivo" class="form-label text-secondary fw-semibold">Motivo / Observación <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-custom" id="mov-motivo" rows="2" placeholder="Ej: Cambio adicional para la gaveta / Compra de agua para el personal" required></textarea>
                        <small class="text-secondary" style="font-size:0.75rem;">Obligatorio para todo ingreso o retiro (mínimo 3 caracteres).</small>
                    </div>
                    <div class="alert alert-danger border-0 text-white d-none" id="mov-error" style="background-color: var(--danger-red);"></div>
                </div>
                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-cyan px-4 fw-bold" id="btn-movimiento-caja">Registrar Movimiento</button>
                </div>
            </div>
        </div>
    </div>


    <!-- ==========================================================
         MODAL 4: CIERRE / ARQUEO DE CAJA AMPLIADO (Fase 7)
         ========================================================== -->
    <div class="modal fade" id="cierreCajaModal" tabindex="-1" aria-labelledby="cierreCajaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-danger">
                    <h5 class="modal-title fw-bold" id="cierreCajaModalLabel"><i class="bi bi-shield-lock-fill me-2"></i> Cerrar Turno & Arqueo Contable</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="" method="POST" autocomplete="off">
                    <input type="hidden" name="accion_caja" value="cierre">
                    <?php echo csrf_field(); ?>
                    
                    <div class="modal-body p-4">
                        <p class="text-secondary small mb-3">
                            El sistema calcula en vivo el efectivo esperado del turno. Ingresa los montos del arqueo físico y valida la diferencia.
                        </p>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 border border-color rounded bg-dark h-100">
                                    <span class="text-secondary small d-block">Usuario del Turno:</span>
                                    <span class="fw-bold"><?php echo htmlspecialchars($usuario_nombre); ?></span>
                                    <br>
                                    <span class="text-secondary small d-block mt-2">Fondo de Apertura:</span>
                                    <span class="fw-bold text-cyan" id="res-apertura">L. <?php echo number_format($caja_activa['monto_apertura'], 2); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border border-color rounded bg-dark h-100">
                                    <table class="table table-borderless table-sm text-white m-0">
                                        <tr>
                                            <td class="text-secondary ps-0 py-0">Ventas en Efectivo:</td>
                                            <td class="text-end pe-0 py-0 fw-semibold" id="res-ventas-ef">L. 0.00</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0 py-0">Ingresos:</td>
                                            <td class="text-end pe-0 py-0 fw-semibold" id="res-ingresos">L. 0.00</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0 py-0">Retiros:</td>
                                            <td class="text-end pe-0 py-0 fw-semibold" id="res-retiros">L. 0.00</td>
                                        </tr>
                                        <tr>
                                            <td class="text-secondary ps-0 py-0">Devoluciones en efectivo:</td>
                                            <td class="text-end pe-0 py-0 fw-semibold" id="res-devoluciones">L. 0.00</td>
                                        </tr>
                                        <tr class="border-top border-color">
                                            <td class="text-cyan fw-bold ps-0 py-0">Efectivo ESPERADO:</td>
                                            <td class="text-end pe-0 py-0 text-cyan fw-bold" id="res-esperado" data-valor="0">L. 0.00</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Moneda Física Contada -->
                        <div class="mb-3">
                            <label for="cierre_efectivo" class="form-label text-secondary fw-semibold">Total Efectivo Físico Contado (L.)</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-custom fw-bold text-cyan" id="cierre_efectivo" name="cierre_efectivo" placeholder="Cuenta de billetes y monedas" required>
                            <small class="text-secondary" style="font-size:0.75rem;">Debe incluir el fondo inicial + ventas en efectivo + ingresos - retiros - devoluciones.</small>
                            <div id="dif-efectivo-preview" class="mt-1 fw-semibold"></div>
                        </div>

                        <!-- Vouchers de Tarjeta Contados -->
                        <div class="mb-3">
                            <label for="cierre_tarjeta" class="form-label text-secondary fw-semibold">Total Vouchers/Tarjeta Contado (L.)</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-custom fw-bold text-cyan" id="cierre_tarjeta" name="cierre_tarjeta" value="0.00" required>
                        </div>

                        <!-- Observaciones -->
                        <div class="mb-3">
                            <label for="observaciones" class="form-label text-secondary fw-semibold">Observaciones (Faltantes/Sobrantes)</label>
                            <textarea class="form-control form-control-custom" id="observaciones" name="observaciones" rows="2" placeholder="Escribe detalles del arqueo si hay desfases."></textarea>
                        </div>

                        <!-- Historial de movimientos del turno (Fase 7) -->
                        <div class="mt-2">
                            <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-list-ol text-cyan me-1"></i> Movimientos del Turno</h6>
                            <div style="max-height: 180px; overflow-y: auto;">
                                <table class="table table-sm table-dark table-striped m-0">
                                    <thead>
                                        <tr class="text-secondary" style="font-size:0.75rem;">
                                            <th>Tipo</th>
                                            <th class="text-end">Monto</th>
                                            <th>Detalle</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody id="caja-movimientos-tabla">
                                        <tr><td colspan="4" class="text-secondary text-center">Cargando...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continuar Vendiendo</button>
                        <button type="submit" class="btn btn-danger text-white px-4 fw-bold">Efectuar Arqueo & Cerrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($caja_activa): ?>
<script>
(function () {
    // =====================================================
    // Fase 7: Caja Ampliada en el POS (saldo, movimientos, arqueo)
    // =====================================================
    var fmt = function (v) { return 'L. ' + Number(v || 0).toFixed(2); };

    function cargarEstadoCaja() {
        fetch('index.php?route=caja_estado_ajax')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) return;
                var e = d.estado;

                var saldoEl = document.getElementById('caja-saldo-text');
                if (saldoEl) saldoEl.textContent = fmt(e.saldo_efectivo);

                var pinta = function (id, texto) {
                    var el = document.getElementById(id);
                    if (el) el.textContent = texto;
                };
                pinta('res-apertura', fmt(e.monto_apertura));
                pinta('res-ventas-ef', fmt(e.ventas_efectivo));
                pinta('res-ingresos', fmt(e.ingresos));
                pinta('res-retiros', fmt(e.retiros));
                pinta('res-devoluciones', fmt(e.devoluciones));
                var esperadoEl = document.getElementById('res-esperado');
                if (esperadoEl) {
                    esperadoEl.textContent = fmt(e.saldo_efectivo);
                    esperadoEl.setAttribute('data-valor', String(e.saldo_efectivo));
                }
                recalcularDiferencia();

                var tb = document.getElementById('caja-movimientos-tabla');
                if (tb) {
                    if (d.movimientos.length === 0) {
                        tb.innerHTML = '<tr><td colspan="4" class="text-secondary text-center">Sin movimientos registrados.</td></tr>';
                        return;
                    }
                    var filas = '';
                    d.movimientos.forEach(function (m) {
                        filas += '<tr>'
                            + '<td><span class="badge ' + (m.tipo === 'RETIRO' || m.tipo === 'EGRESO_DEVOLUCION' ? 'text-bg-danger' : 'text-bg-success') + '">' + m.tipo + '</span></td>'
                            + '<td class="text-end">' + fmt(m.monto) + '</td>'
                            + '<td style="font-size:0.75rem;">' + (m.observaciones || '') + '</td>'
                            + '<td class="text-secondary" style="font-size:0.75rem;">' + m.fecha + '</td>'
                            + '</tr>';
                    });
                    tb.innerHTML = filas;
                }
            });
    }

    function recalcularDiferencia() {
        var esperadoEl = document.getElementById('res-esperado');
        var contadoEl = document.getElementById('cierre_efectivo');
        var difEl = document.getElementById('dif-efectivo-preview');
        if (!esperadoEl || !contadoEl || !difEl) return;
        var esperado = parseFloat(esperadoEl.getAttribute('data-valor') || '0');
        var contado = parseFloat(contadoEl.value || '0');
        var dif = contado - esperado;
        var exacto = Math.abs(dif) < 0.005;
        var cls = exacto ? 'text-secondary' : (dif > 0 ? 'text-success' : 'text-danger');
        var icono = exacto ? 'bi-check-circle-fill' : (dif > 0 ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill');
        var etiqueta = exacto ? 'EXACTO' : (dif > 0 ? 'SOBRANTE' : 'FALTANTE');
        difEl.className = 'dif-efectivo-preview mt-2 fw-bold ' + cls;
        difEl.innerHTML = '<i class="bi ' + icono + ' me-2"></i>Diferencia en efectivo: '
            + (dif >= 0 ? '+' : '') + dif.toFixed(2)
            + ' <span class="badge ' + (dif >= 0 ? 'bg-success' : 'bg-danger') + ' ms-1">' + etiqueta + '</span>';
    }

    var btnMov = document.getElementById('btn-movimiento-caja');
    if (btnMov) {
        btnMov.addEventListener('click', function () {
            var errorEl = document.getElementById('mov-error');
            errorEl.classList.add('d-none');
            var tipo = document.getElementById('mov-tipo').value;
            var monto = parseFloat(document.getElementById('mov-monto').value);
            var motivo = document.getElementById('mov-motivo').value.trim();
            if (!monto || monto <= 0) {
                errorEl.textContent = 'Indica un monto mayor a cero.';
                errorEl.classList.remove('d-none');
                return;
            }
            if (motivo.length < 3) {
                errorEl.textContent = 'El motivo es obligatorio (mínimo 3 caracteres).';
                errorEl.classList.remove('d-none');
                return;
            }
            fetch('index.php?route=caja_movimiento_ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                body: JSON.stringify({ tipo: tipo, monto: monto, motivo: motivo })
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        var modalEl = document.getElementById('movimientoCajaModal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                        document.getElementById('mov-monto').value = '';
                        document.getElementById('mov-motivo').value = '';
                        cargarEstadoCaja();
                    } else {
                        errorEl.textContent = d.message || 'No se pudo registrar el movimiento.';
                        errorEl.classList.remove('d-none');
                    }
                });
        });
    }

    var contadoEl = document.getElementById('cierre_efectivo');
    if (contadoEl) {
        contadoEl.addEventListener('input', recalcularDiferencia);
        document.getElementById('cierreCajaModal').addEventListener('shown.bs.modal', cargarEstadoCaja);
        document.getElementById('movimientoCajaModal').addEventListener('shown.bs.modal', cargarEstadoCaja);
    }

    cargarEstadoCaja();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
