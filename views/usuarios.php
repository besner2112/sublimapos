<?php
// ==========================================
// Vista de Gestión de Usuarios (Solo Administrador)
// ==========================================
// Esta ruta ya está protegida en index.php con
// AuthController::requireRole('Administrador'), pero se
// revalida aquí también como segunda capa de defensa.

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';

AuthController::requireRole('Administrador');

$mensaje_exito = "";
$mensaje_error = "";

// ------------------------------------------
// PROCESAMIENTO DE ACCIONES POR POST (Admin)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validación CSRF centralizada (Fase 3)
    if (!verify_csrf_token()) {
        $mensaje_error = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {

        // 1. Crear nuevo usuario
        if (isset($_POST['accion']) && $_POST['accion'] == 'crear_usuario') {
            $res = UsuarioController::crearUsuario(
                $_POST['nombre'] ?? '',
                $_POST['usuario'] ?? '',
                $_POST['email'] ?? '',
                $_POST['password'] ?? '',
                $_POST['rol'] ?? 'Cajero'
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 2. Activar / Desactivar usuario
        if (isset($_POST['accion']) && $_POST['accion'] == 'cambiar_estado') {
            $res = UsuarioController::cambiarEstado(
                $_POST['usuario_id'] ?? 0,
                $_POST['activo'] ?? 0
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
    }
}

$usuarios_lista = UsuarioController::obtenerUsuarios();
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

<div class="card-premium">
    <div class="card-header-premium">
        <div>
            <i class="bi bi-person-badge text-cyan me-2"></i>Gestión de Usuarios y Roles
        </div>
        <div>
            <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
                <i class="bi bi-person-plus-fill me-1"></i> Nuevo Usuario
            </button>
        </div>
    </div>

    <div class="p-3 table-responsive">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Correo</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Creado</th>
                    <th scope="col" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios_lista)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            <i class="bi bi-person-x d-block fs-2 mb-2"></i> No hay usuarios registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios_lista as $u): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($u['nombre']); ?></td>
                            <td><code><?php echo htmlspecialchars($u['usuario']); ?></code></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['rol'] === 'Administrador' ? 'bg-cyan text-dark' : 'bg-secondary'; ?> fw-semibold">
                                    <?php echo htmlspecialchars($u['rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary" style="font-size:0.85rem;">
                                <?php echo date('d/m/Y', strtotime($u['fecha_creacion'])); ?>
                            </td>
                            <td class="text-end">
                                <?php if (intval($u['id']) === intval($_SESSION['usuario_id'])): ?>
                                    <span class="text-secondary" style="font-size:0.8rem;">Tu cuenta</span>
                                <?php else: ?>
                                    <form action="" method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas <?php echo $u['activo'] ? 'desactivar' : 'activar'; ?> a <?php echo htmlspecialchars($u['usuario']); ?>?');">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="usuario_id" value="<?php echo intval($u['id']); ?>">
                                        <input type="hidden" name="activo" value="<?php echo $u['activo'] ? 0 : 1; ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php if ($u['activo']): ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-slash-circle me-1"></i> Desactivar
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-check-circle me-1"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVO USUARIO -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-labelledby="modalNuevoUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-premium">
            <div class="modal-header modal-header-premium">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-person-plus text-cyan me-2"></i> Crear Cuenta de Personal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="crear_usuario">
                <?php echo csrf_field(); ?>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Nombre Completo *</label>
                        <input type="text" class="form-control form-control-custom" name="nombre" placeholder="Ej: María López" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Nombre de Usuario (login) *</label>
                        <input type="text" class="form-control form-control-custom" name="usuario" placeholder="Ej: mlopez" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Correo Electrónico *</label>
                        <input type="email" class="form-control form-control-custom" name="email" placeholder="maria@sublimacion.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Contraseña Temporal (mín. 8 caracteres) *</label>
                        <input type="password" class="form-control form-control-custom" name="password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold">Rol del Sistema *</label>
                        <select class="form-select form-control-custom" name="rol" required>
                            <option value="Cajero" selected>Cajero (solo módulo POS)</option>
                            <option value="Administrador">Administrador (acceso total)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cyan px-4">Crear Cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
