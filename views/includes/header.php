<?php
// ==========================================
// Cabecera / Layout Principal (navbar y alertas)
// ==========================================

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/InventarioController.php';
require_once __DIR__ . '/../../controllers/CajaController.php';
require_once __DIR__ . '/../../helpers/security.php';

// Validar inicio de sesión general
AuthController::requireLogin();

$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_rol = $_SESSION['usuario_rol'] ?? 'Cajero';
$usuario_id = $_SESSION['usuario_id'];

// Obtener alertas de stock
$bajo_stock_items = InventarioController::obtenerProductosBajoStock();
$count_bajo_stock = count($bajo_stock_items);

// Verificar estado de caja
$caja_activa = CajaController::obtenerSesionActiva($usuario_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sublimart POS - Gestión de Artículos de Sublimación</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Hojas de Estilo Personalizadas -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <a class="skip-link" href="#app-main">Saltar al contenido principal</a>

    <!-- Barra de Navegación Principal -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top" aria-label="Navegación principal">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php?route=pos" aria-label="SublimaPOS - Inicio">
                <i class="bi bi-layers-half text-cyan me-2"></i>Sublima<span>POS</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Alternar menú de navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarText">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- ============ OPERACIÓN ============ -->
                    <li class="nav-section-label d-none d-lg-block">Operación</li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'pos') ? 'active' : ''; ?>" href="index.php?route=pos" <?php echo (isset($_GET['route']) && $_GET['route'] == 'pos') ? 'aria-current="page"' : ''; ?>>
                            <i class="bi bi-cart3 me-1"></i> Caja / POS
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'inventario') ? 'active' : ''; ?>" href="index.php?route=inventario" <?php echo (isset($_GET['route']) && $_GET['route'] == 'inventario') ? 'aria-current="page"' : ''; ?>>
                            <i class="bi bi-box-seam me-1"></i> Inventario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'clientes') ? 'active' : ''; ?>" href="index.php?route=clientes" <?php echo (isset($_GET['route']) && $_GET['route'] == 'clientes') ? 'aria-current="page"' : ''; ?>>
                            <i class="bi bi-people me-1"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'devoluciones') ? 'active' : ''; ?>" href="index.php?route=devoluciones" <?php echo (isset($_GET['route']) && $_GET['route'] == 'devoluciones') ? 'aria-current="page"' : ''; ?>>
                            <i class="bi bi-arrow-return-left me-1"></i> Devoluciones
                        </a>
                    </li>
                    <?php if ($usuario_rol !== 'Administrador'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'dashboard') ? 'active' : ''; ?>" href="index.php?route=dashboard" <?php echo (isset($_GET['route']) && $_GET['route'] == 'dashboard') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-speedometer2 me-1"></i> Mi panel
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($usuario_rol === 'Administrador'): ?>
                        <!-- ============ ADMINISTRACIÓN ============ -->
                        <li class="nav-section-label d-none d-lg-block mt-1">Administración</li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'dashboard') ? 'active' : ''; ?>" href="index.php?route=dashboard" <?php echo (isset($_GET['route']) && $_GET['route'] == 'dashboard') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'reportes') ? 'active' : ''; ?>" href="index.php?route=reportes" <?php echo (isset($_GET['route']) && $_GET['route'] == 'reportes') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reportes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'compras') ? 'active' : ''; ?>" href="index.php?route=compras" <?php echo (isset($_GET['route']) && $_GET['route'] == 'compras') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-bag-check me-1"></i> Compras
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'proveedores') ? 'active' : ''; ?>" href="index.php?route=proveedores" <?php echo (isset($_GET['route']) && $_GET['route'] == 'proveedores') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-truck me-1"></i> Proveedores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'auditoria') ? 'active' : ''; ?>" href="index.php?route=auditoria" <?php echo (isset($_GET['route']) && $_GET['route'] == 'auditoria') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-shield-check me-1"></i> Auditoría
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['route']) && $_GET['route'] == 'usuarios') ? 'active' : ''; ?>" href="index.php?route=usuarios" <?php echo (isset($_GET['route']) && $_GET['route'] == 'usuarios') ? 'aria-current="page"' : ''; ?>>
                                <i class="bi bi-person-badge me-1"></i> Usuarios
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Indicadores de Caja y Usuario -->
                <div class="d-flex align-items-center">
                    
                    <!-- Alerta de Stock Mínimo -->
                    <?php if ($count_bajo_stock > 0): ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-outline-danger btn-sm dropdown-toggle position-relative" type="button" id="stockAlertDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Alertas de stock bajo (<?php echo $count_bajo_stock; ?>)">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $count_bajo_stock; ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end modal-content-premium p-2 border-color" aria-labelledby="stockAlertDropdown" style="width: 280px; max-height:300px; overflow-y:auto;">
                                <li><h6 class="dropdown-header text-danger">Stock Crítico o Agotado</h6></li>
                                <li><hr class="dropdown-divider bg-secondary"></li>
                                <?php foreach ($bajo_stock_items as $item): ?>
                                    <li class="p-2 border-bottom border-color">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span style="font-size:0.85rem; font-weight:500;" class="text-truncate col-9"><?php echo htmlspecialchars($item['nombre']); ?></span>
                                            <span class="badge bg-danger rounded-pill"><?php echo $item['stock']; ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <li><a class="dropdown-item text-center text-cyan py-2" style="font-size: 0.8rem; font-weight:600;" href="index.php?route=inventario">Administrar Insumos</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Estatus Turno de Caja -->
                    <span class="me-3">
                        <?php if ($caja_activa): ?>
                            <span class="badge text-dark bg-cyan fw-bold py-2 px-3 border border-dark rounded-pill">
                                <i class="bi bi-unlock-fill me-1"></i> Caja Abierta (L. <?php echo number_format($caja_activa['monto_apertura'], 2); ?>)
                            </span>
                        <?php else: ?>
                            <span class="badge text-secondary bg-dark border border-secondary fw-bold py-2 px-3 rounded-pill">
                                <i class="bi bi-lock-fill me-1"></i> Caja Cerrada
                            </span>
                        <?php endif; ?>
                    </span>

                    <!-- Info Usuario -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle user-chip py-2 px-3 rounded-pill fw-bold" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                            <i class="bi bi-person-circle me-1 text-cyan"></i> <?php echo htmlspecialchars($usuario_nombre); ?> 
                            <span class="rol-badge text-secondary">(<?php echo htmlspecialchars($usuario_rol); ?>)</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end modal-content-premium border-color mt-2" aria-labelledby="userMenu">
                            <li><h6 class="dropdown-header text-secondary">Turno: <?php echo htmlspecialchars($_SESSION['usuario_username']); ?></h6></li>
                            <li><hr class="dropdown-divider bg-secondary"></li>
                            <li>
                                <form action="index.php?route=logout" method="POST" onsubmit="return confirm('¿Deseas cerrar sesión?');">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="dropdown-item text-danger fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    
                </div>
            </div>
        </div>
    </nav>
    <main id="app-main" class="container-fluid py-4">
