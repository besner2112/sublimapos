<?php
// ==========================================
// Vista del Módulo de Clientes (CRUD y Historial)
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/ClienteController.php';
require_once __DIR__ . '/../controllers/VentaController.php';

$mensaje_exito = "";
$mensaje_error = "";

// ------------------------------------------
// PROCESAMIENTO DE ACCIONES POR POST
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validación CSRF centralizada (Fase 3)
    if (!verify_csrf_token()) {
        $mensaje_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {
    
        // 1. Registrar Cliente Nuevo
        if (isset($_POST['accion']) && $_POST['accion'] == 'crear_cliente') {
            $res = ClienteController::crearCliente(
                $_POST['identificacion'],
                $_POST['nombre'],
                $_POST['telefono'],
                $_POST['email'],
                $_POST['direccion']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 2. Editar Cliente Existente
        if (isset($_POST['accion']) && $_POST['accion'] == 'editar_cliente') {
            $res = ClienteController::editarCliente(
                $_POST['id'],
                $_POST['identificacion'],
                $_POST['nombre'],
                $_POST['telefono'],
                $_POST['email'],
                $_POST['direccion']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
    }
}

// ------------------------------------------
// DETECTAR PARÁMETRO DE HISTORIAL POR CLIENTE
// ------------------------------------------
$ver_historial = false;
$cliente_info = null;
$compras = [];

if (isset($_GET['cliente_id']) && intval($_GET['cliente_id']) > 0) {
    $cliente_id_sel = intval($_GET['cliente_id']);
    $cliente_info = ClienteController::obtenerClientePorId($cliente_id_sel);
    if ($cliente_info) {
        $ver_historial = true;
        $compras = ClienteController::obtenerHistorialCompras($cliente_id_sel);
    }
}

// Cargar todos los clientes para la tabla principal
$clientes = ClienteController::obtenerClientes();
?>

<!-- BANNER MENSAJES -->
<?php if (!empty($mensaje_exito)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 text-white py-3 mb-3" style="background-color: var(--success-green);" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 text-white py-3 mb-3" style="background-color: var(--danger-red);" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($mensaje_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- DETALLE A: PANTALLA HISTORIAL COMPRAS DE CLIENTE -->
<?php if ($ver_historial && $cliente_info): ?>
    <div class="row">
        <!-- Ficha de Datos del Cliente -->
        <div class="col-md-4 mb-4">
            <div class="card-premium p-3">
                <div class="card-header-premium mb-3 bg-dark border-color">
                    <span class="fw-bold"><i class="bi bi-person-badge-fill text-cyan me-2"></i> Ficha Cliente</span>
                </div>
                
                <h4 class="fw-bold mb-3"><?php echo htmlspecialchars($cliente_info['nombre']); ?></h4>
                
                <div class="p-3 border border-color rounded" style="background-color: rgba(16, 22, 38, 0.4);">
                    <div class="mb-2">
                        <small class="text-secondary d-block">Identificación (RFC/DNI):</small>
                        <span class="fw-semibold"><?php echo htmlspecialchars($cliente_info['identificacion'] ?? 'Sin Registro'); ?></span>
                    </div>
                    <div class="mb-2">
                        <small class="text-secondary d-block">Teléfono:</small>
                        <span class="fw-semibold"><?php echo htmlspecialchars($cliente_info['telefono'] ?? 'Sin Registro'); ?></span>
                    </div>
                    <div class="mb-2">
                        <small class="text-secondary d-block">Correo Electrónico:</small>
                        <span class="fw-semibold text-cyan"><?php echo htmlspecialchars($cliente_info['email'] ?? 'Sin Registro'); ?></span>
                    </div>
                    <div>
                        <small class="text-secondary d-block">Dirección Postal:</small>
                        <span class="fw-semibold text-secondary" style="font-size:0.9rem;"><?php echo htmlspecialchars($cliente_info['direccion'] ?? 'Sin Registro'); ?></span>
                    </div>
                </div>
                
                <a href="index.php?route=clientes" class="btn btn-outline-cyan w-100 mt-4 fw-bold text-uppercase">
                    <i class="bi bi-arrow-left me-1"></i> Regresar al Listado
                </a>
            </div>
        </div>

        <!-- Tabla de Facturas Recientes de este Cliente -->
        <div class="col-md-8 mb-4">
            <div class="card-premium p-3">
                <div class="card-header-premium mb-3">
                    <span class="fw-bold"><i class="bi bi-clock-history text-cyan me-2"></i> Historial de Compras</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom table-hover m-0">
                        <thead>
                            <tr>
                                <th scope="col">Num. Factura</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Método Pago</th>
                                <th scope="col">IVA Isól.</th>
                                <th scope="col">Total Cobrado</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($compras)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-5">
                                        Este cliente no ha registrado compras en el sistema POS.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($compras as $v): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($v['num_factura']); ?></code></td>
                                        <td style="font-size: 0.85rem;"><?php echo date("d/m/Y H:i", strtotime($v['fecha_venta'])); ?></td>
                                        <td><?php echo htmlspecialchars($v['metodo_pago']); ?></td>
                                        <td>L. <?php echo number_format($v['impuesto'], 2); ?></td>
                                        <td class="text-cyan fw-bold">L. <?php echo number_format($v['total'], 2); ?></td>
                                        <td>
                                            <?php if ($v['estado'] === 'Anulada'): ?>
                                                <span class="badge bg-danger">Anulada</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Completada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <!-- Botón desplegador de productos -->
                                            <button class="btn btn-sm btn-outline-cyan py-1 px-2"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalFacturaProductos"
                                                    onclick="cargarProductosFactura(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars($v['num_factura']); ?>')">
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
        </div>
    </div>

<!-- DETALLE B: TABLA PRINCIPAL DE TODOS LOS CLIENTES -->
<?php else: ?>
    <div class="card-premium">
        <div class="card-header-premium">
            <div>
                <i class="bi bi-people-fill text-cyan me-2"></i>Cartera de Clientes Registrados
            </div>
            
            <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
                <i class="bi bi-plus-lg me-1"></i> Registrar Cliente
            </button>
        </div>

        <div class="p-3 table-responsive">
            <table class="table table-custom table-hover m-0">
                <thead>
                    <tr>
                        <th scope="col">Identificación</th>
                        <th scope="col">Nombre Comercial / Cliente</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Email</th>
                        <th scope="col">Dirección</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">
                                Ningún cliente registrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($c['identificacion'] ?? 'N/D'); ?></code></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($c['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($c['telefono'] ?? 'Sin Registro'); ?></td>
                                <td class="text-cyan"><?php echo htmlspecialchars($c['email'] ?? 'Sin Registro'); ?></td>
                                <td class="text-secondary text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($c['direccion'] ?? 'Sin Registro'); ?></td>
                                <td class="text-end">
                                    <?php if ($c['id'] != 1): ?>
                                        <button class="btn btn-sm btn-outline-cyan me-1 py-1 px-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditarCliente"
                                                onclick='cargarDatosCliente(<?php echo json_encode($c); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="index.php?route=clientes&cliente_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-cyan py-1 px-2">
                                        <i class="bi bi-clock-history"></i> Historial
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ==========================================================
     MODALES CLIENTES
     ========================================================== -->

<!-- MODAL 1: REGISTRAR NUEVO CLIENTE -->
<div class="modal fade" id="modalNuevoCliente" tabindex="-1" aria-labelledby="modalNuevoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-person-plus text-cyan me-2"></i> Registrar Cliente de Sublimación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="crear_cliente">
                <?php echo csrf_field(); ?>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Nombre del Cliente / Empresa *</label>
                        <input type="text" class="form-control form-control-custom" name="nombre" placeholder="Ej: Juan Perez / Diseños Pro" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Identificación fiscal / personal (DNI, RFC, RUT)</label>
                        <input type="text" class="form-control form-control-custom" name="identificacion" placeholder="Ej: RFC, Cedula o Pasaporte">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Teléfono Comercial</label>
                        <input type="text" class="form-control form-control-custom" name="telefono" placeholder="Ej: 554321098">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Correo Electrónico</label>
                        <input type="email" class="form-control form-control-custom" name="email" placeholder="Juan@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Dirección</label>
                        <textarea class="form-control form-control-custom" name="direccion" placeholder="Calle, Número, Colonia, Ciudad..." rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cyan px-4">Guardar Perfil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 2: EDITAR CLIENTE EXISTENTE -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-labelledby="modalEditarClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square text-cyan me-2"></i> Editar Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="editar_cliente">
                <input type="hidden" name="id" id="edit_cliente_id">
                <?php echo csrf_field(); ?>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Nombre del Cliente / Empresa *</label>
                        <input type="text" class="form-control form-control-custom" id="edit_cliente_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Identificación fiscal / personal (DNI, RFC, RUT)</label>
                        <input type="text" class="form-control form-control-custom" id="edit_cliente_ident" name="identificacion">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Teléfono Comercial</label>
                        <input type="text" class="form-control form-control-custom" id="edit_cliente_telefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Correo Electrónico</label>
                        <input type="email" class="form-control form-control-custom" id="edit_cliente_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Dirección</label>
                        <textarea class="form-control form-control-custom" id="edit_cliente_direccion" name="direccion" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cyan px-4">Actualizar Datos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================================
     MODAL COMPLEMENTARIO: VER COMPRAS CON CRUCE DE ARTÍCULOS
     ========================================================== -->
<div class="modal fade" id="modalFacturaProductos" tabindex="-1" aria-labelledby="modalFacturaProductosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-journal-text text-cyan me-2"></i> Detalle de Artículos Comprados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-3 p-3 border border-color rounded bg-dark">
                    <span class="text-secondary small d-block">Número Factura / Folio:</span>
                    <span class="fw-bold text-white" id="factura-modal-folio">Cargando...</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-custom text-white fs-6">
                        <thead>
                            <tr>
                                <th>Artículo</th>
                                <th class="text-center">Cant.</th>
                                <th>Precio U.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="factura_productos_table_body">
                            <!-- Inyección dinámica JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function cargarDatosCliente(c) {
        document.getElementById('edit_cliente_id').value = c.id;
        document.getElementById('edit_cliente_nombre').value = c.nombre;
        document.getElementById('edit_cliente_ident').value = c.identificacion ? c.identificacion : '';
        document.getElementById('edit_cliente_telefono').value = c.telefono ? c.telefono : '';
        document.getElementById('edit_cliente_email').value = c.email ? c.email : '';
        document.getElementById('edit_cliente_direccion').value = c.direccion ? c.direccion : '';
    }

    /**
     * Consulta por AJAX los productos correspondientes a la venta y los dibuja en el modal
     */
    function cargarProductosFactura(ventaId, folio) {
        document.getElementById('factura-modal-folio').innerText = folio;
        const tbody = document.getElementById('factura_productos_table_body');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">Obteniendo cargamento...</td></tr>';

        fetch(`index.php?route=ver_productos_venta_ajax&venta_id=${ventaId}`)
            .then(res => res.json())
            .then(productos => {
                tbody.innerHTML = '';
                if(productos.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No se cargaron artículos para esta venta.</td></tr>';
                    return;
                }
                productos.forEach(pr => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${pr.nombre_producto}</strong></td>
                        <td class="text-center font-monospace">${pr.cantidad}</td>
                        <td>$${parseFloat(pr.precio_unitario).toFixed(2)}</td>
                        <td class="text-end text-cyan">$${parseFloat(pr.subtotal).toFixed(2)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(err => {
                console.error("Error al cargar productos de factura:", err);
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Ocurrió un error al cargar los artículos.</td></tr>';
            });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
