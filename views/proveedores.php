<?php
// ==========================================
// Vista del Módulo de Proveedores (Fase 5)
// CRUD con baja lógica — solo Administrador
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/ProveedorController.php';

$usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';

$mensaje_exito = "";
$mensaje_error = "";

// ------------------------------------------
// PROCESAMIENTO DE ACCIONES POR POST (Admin)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario_rol === 'Administrador') {

    // Validación CSRF centralizada (Fase 3)
    if (!verify_csrf_token()) {
        $mensaje_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {

        // 1. Crear proveedor
        if (isset($_POST['accion']) && $_POST['accion'] == 'crear_proveedor') {
            $res = ProveedorController::crearProveedor(
                $_POST['nombre'],
                $_POST['rtn'],
                $_POST['telefono'],
                $_POST['correo'],
                $_POST['direccion'],
                $_POST['contacto']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 2. Editar proveedor
        if (isset($_POST['accion']) && $_POST['accion'] == 'editar_proveedor') {
            $res = ProveedorController::editarProveedor(
                $_POST['id'],
                $_POST['nombre'],
                $_POST['rtn'],
                $_POST['telefono'],
                $_POST['correo'],
                $_POST['direccion'],
                $_POST['contacto']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 3. Desactivar proveedor (Baja lógica)
        if (isset($_POST['accion']) && $_POST['accion'] == 'desactivar_proveedor') {
            $res = ProveedorController::desactivarProveedor($_POST['id']);
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
    }
}

// Búsqueda opcional por GET (q)
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $proveedores = ProveedorController::buscarProveedores($q);
} else {
    // Fase 10: se listan también los inactivos para mostrar su estado con badge.
    $proveedores = ProveedorController::obtenerProveedores(false);
}
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
            <i class="bi bi-truck text-cyan me-2"></i>Proveedores
        </div>
        <div>
            <form action="" method="GET" class="d-inline-block me-2">
                <input type="hidden" name="route" value="proveedores">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-custom" name="q" placeholder="Buscar por nombre, RTN, teléfono o correo..."
                           value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-outline-cyan" type="submit"><i class="bi bi-search"></i></button>
                    <?php if ($q !== ''): ?>
                        <a class="btn btn-outline-secondary" href="index.php?route=proveedores"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Proveedor
            </button>
        </div>
    </div>

    <div class="p-3 table-responsive">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">RTN</th>
                    <th scope="col">Teléfono</th>
                    <th scope="col">Correo</th>
                    <th scope="col">Contacto</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proveedores)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            <i class="bi bi-truck d-block fs-2 mb-2"></i> No hay proveedores registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proveedores as $prov): ?>
                        <tr class="<?php echo empty($prov['activo']) ? 'table-light text-secondary' : ''; ?>">
                            <td>
                                <span class="fw-bold"><?php echo htmlspecialchars($prov['nombre']); ?></span>
                                <?php if (!empty($prov['direccion'])): ?>
                                    <small class="text-secondary d-block"><?php echo htmlspecialchars($prov['direccion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo $prov['rtn'] ? htmlspecialchars($prov['rtn']) : '-'; ?></code></td>
                            <td><?php echo $prov['telefono'] ? htmlspecialchars($prov['telefono']) : '-'; ?></td>
                            <td><?php echo $prov['correo'] ? htmlspecialchars($prov['correo']) : '-'; ?></td>
                            <td><?php echo $prov['contacto'] ? htmlspecialchars($prov['contacto']) : '-'; ?></td>
                            <td>
                                <?php if (!empty($prov['activo'])): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-cyan me-1 py-1 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditarProveedor"
                                        onclick='cargarDatosModal(<?php echo json_encode($prov); ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger py-1 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDesactivarProveedor"
                                        onclick="configurarDesactivacion(<?php echo $prov['id']; ?>, '<?php echo htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8'); ?>')">
                                    <i class="bi bi-trash"></i>
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
     MODALES ADMINISTRATIVOS (EXCLUSIVOS ROL ADMINISTRADOR)
     ========================================================== -->

<!-- MODAL 1: NUEVO PROVEEDOR -->
<div class="modal fade" id="modalNuevoProveedor" tabindex="-1" aria-labelledby="modalNuevoProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-plus-circle-fill text-cyan me-2"></i> Nuevo Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="crear_proveedor">
                <?php echo csrf_field(); ?>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary fw-semibold">Nombre del Proveedor *</label>
                            <input type="text" class="form-control form-control-custom" name="nombre" placeholder="Ej: Textiles de Honduras S.A." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">RTN</label>
                            <input type="text" class="form-control form-control-custom" name="rtn" placeholder="Ej: 08019001234567" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Teléfono</label>
                            <input type="text" class="form-control form-control-custom" name="telefono" placeholder="Ej: 2230-0000" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Correo</label>
                            <input type="email" class="form-control form-control-custom" name="correo" placeholder="Ej: ventas@textileshn.com" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Persona de Contacto</label>
                            <input type="text" class="form-control form-control-custom" name="contacto" placeholder="Ej: Juan Pérez" maxlength="150">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-secondary fw-semibold">Dirección</label>
                            <textarea class="form-control form-control-custom" name="direccion" placeholder="Dirección física del proveedor..." rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cyan px-4">Registrar Proveedor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 2: EDITAR PROVEEDOR -->
<div class="modal fade" id="modalEditarProveedor" tabindex="-1" aria-labelledby="modalEditarProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square text-cyan me-2"></i> Editar Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="editar_proveedor">
                <input type="hidden" name="id" id="edit_id">
                <?php echo csrf_field(); ?>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary fw-semibold">Nombre del Proveedor *</label>
                            <input type="text" class="form-control form-control-custom" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">RTN</label>
                            <input type="text" class="form-control form-control-custom" id="edit_rtn" name="rtn" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Teléfono</label>
                            <input type="text" class="form-control form-control-custom" id="edit_telefono" name="telefono" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Correo</label>
                            <input type="email" class="form-control form-control-custom" id="edit_correo" name="correo" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary fw-semibold">Persona de Contacto</label>
                            <input type="text" class="form-control form-control-custom" id="edit_contacto" name="contacto" maxlength="150">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-secondary fw-semibold">Dirección</label>
                            <textarea class="form-control form-control-custom" id="edit_direccion" name="direccion" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cyan px-4">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 3: DESACTIVAR PROVEEDOR (Baja lógica) -->
<div class="modal fade" id="modalDesactivarProveedor" tabindex="-1" aria-labelledby="modalDesactivarProveedorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Desactivar Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="accion" value="desactivar_proveedor">
                <input type="hidden" name="id" id="delete_id">
                <?php echo csrf_field(); ?>

                <div class="modal-body p-4">
                    <p class="text-secondary mb-1">
                        ¿Estás seguro de desactivar al proveedor
                        <span class="fw-bold" id="delete_nombre"></span>?
                    </p>
                    <small class="text-secondary">
                        El proveedor no se eliminará físicamente para conservar el historial de compras;
                        quedará desactivado (<code>activo = 0</code>) y no podrá usarse en compras nuevas.
                    </small>
                </div>

                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4">Desactivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarDatosModal(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_nombre').value = p.nombre;
    document.getElementById('edit_rtn').value = p.rtn ? p.rtn : '';
    document.getElementById('edit_telefono').value = p.telefono ? p.telefono : '';
    document.getElementById('edit_correo').value = p.correo ? p.correo : '';
    document.getElementById('edit_contacto').value = p.contacto ? p.contacto : '';
    document.getElementById('edit_direccion').value = p.direccion ? p.direccion : '';
}

function configurarDesactivacion(id, nombre) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_nombre').innerText = nombre;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
