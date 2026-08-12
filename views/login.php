<?php
// ==========================================
// Formulario de Inicio de Sesión
// ==========================================

require_once __DIR__ . '/../controllers/AuthController.php';

// Si el usuario ya está autenticado, enviarlo directo al POS
if (AuthController::isLoggedIn()) {
    header("Location: index.php?route=pos");
    exit();
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_msg = "Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.";
    } else {
        $usuario = $_POST['usuario'] ?? '';
        $password = $_POST['password'] ?? '';

        $res = AuthController::login($usuario, $password);
        if ($res['success']) {
            header("Location: index.php?route=pos");
            exit();
        } else {
            $error_msg = $res['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - Sublimart POS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-wrapper">

    <div class="login-card p-4">
        <div class="card-premium p-4">
            
            <div class="login-logo text-center mt-3">
                <i class="bi bi-layers-half text-cyan fs-1 d-block mb-2"></i>
                Sublima<span>POS</span>
                <span class="d-block text-secondary fs-6 fw-normal mt-1">Control de Caja & Inventarios</span>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger border-0 text-white py-3" style="background-color: rgba(255, 71, 126, 0.15);" role="alert">
                    <i class="bi bi-exclamation-octagon me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label for="usuario" class="form-label text-secondary fw-semibold">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text form-control-custom bg-dark border-end-0 text-secondary"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control form-control-custom border-start-0 ps-0" id="usuario" name="usuario" placeholder="Ingresa tu usuario" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-secondary fw-semibold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text form-control-custom bg-dark border-end-0 text-secondary"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control form-control-custom border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-cyan w-100 py-3 text-uppercase fw-bold mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Acceder al Sistema
                </button>
            </form>

            <div class="mt-4 p-3 border border-color rounded" style="background-color: rgba(16, 22, 38, 0.4);">
                <div style="font-size:0.8rem; line-height: 1.5;" class="text-secondary">
                    <i class="bi bi-shield-lock me-1 text-cyan"></i>
                    Las credenciales de acceso las proporciona el administrador del sistema.
                </div>
            </div>
            
        </div>
        <p class="text-center text-secondary mt-4" style="font-size: 0.8rem;">
            &copy; 2026 - Sublimart POS. Todos los derechos reservados.
        </p>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
