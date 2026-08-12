<?php
// ==========================================
// Enrutador Central y Despachador API AJAX
// ==========================================

require_once __DIR__ . '/conexion/db.php';
require_once __DIR__ . '/helpers/security.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/InventarioController.php';
require_once __DIR__ . '/controllers/ClienteController.php';
require_once __DIR__ . '/controllers/VentaController.php';
require_once __DIR__ . '/controllers/CajaController.php';
require_once __DIR__ . '/controllers/UsuarioController.php';
require_once __DIR__ . '/controllers/ProveedorController.php';
require_once __DIR__ . '/controllers/CompraController.php';
require_once __DIR__ . '/controllers/DevolucionController.php';
require_once __DIR__ . '/helpers/InventarioService.php';
require_once __DIR__ . '/helpers/CompraService.php';
require_once __DIR__ . '/controllers/ReporteController.php';

// Sanitizar ruta solicitada
$route = $_GET['route'] ?? '';

// ==========================================================
// RUTAS AJAX / API ENDPOINTS (RÁPIDAS Y SIN LAYOUT)
// ==========================================================

if ($route === 'buscar_productos_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET['q'] ?? '';
    echo json_encode(InventarioController::buscarProductos($q));
    exit();
}

if ($route === 'buscar_codigo_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $code = $_GET['code'] ?? '';
    $product = InventarioController::obtenerProductoPorCodigo($code);
    echo json_encode($product ?: new stdClass());
    exit();
}

if ($route === 'buscar_clientes_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET['q'] ?? '';
    echo json_encode(ClienteController::buscarClientes($q));
    exit();
}

if ($route === 'ver_productos_venta_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $venta_id = intval($_GET['venta_id'] ?? 0);
    $venta = VentaController::obtenerVentaPorId($venta_id);
    if (!$venta) {
        http_response_code(404);
        echo json_encode(['error' => 'Venta no encontrada']);
        exit();
    }
    // IDOR (Fase 3): el cajero solo puede ver ventas propias;
    // el administrador puede ver todas.
    $es_admin = ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
    if (!$es_admin && intval($venta['usuario_id']) !== intval($_SESSION['usuario_id'] ?? 0)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado a esta venta.']);
        exit();
    }
    echo json_encode(VentaController::obtenerDetallesVenta($venta_id));
    exit();
}

if ($route === 'cobrar_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    
    // Obtener JSON crudo enviado por POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Cargamento de datos corrupto o vacío.']);
        exit();
    }

    // CSRF (Fase 3): el token viaja en el cuerpo JSON
    if (!verify_csrf_token($data['csrf_token'] ?? '')) {
        http_response_code(403); // 403 estándar: Apache reescribe códigos no estándar (419) como 500. (Fase 11)
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.']);
        exit();
    }

    $usuario_id = $_SESSION['usuario_id'];
    $cliente_id = intval($data['cliente_id'] ?? 1);
    $metodo_pago = $data['metodo_pago'] ?? 'Efectivo';
    $monto_pagado = limpiarMonto($data['monto_pagado'] ?? 0);
    $carrito = $data['carrito'] ?? [];

    $res = VentaController::procesarVenta($usuario_id, $cliente_id, $carrito, $metodo_pago, $monto_pagado);
    echo json_encode($res);
    exit();
}

if ($route === 'caja_estado_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');

    $sesion = CajaController::obtenerSesionActiva($_SESSION['usuario_id']);
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'No hay un turno de caja abierto.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'estado' => CajaController::obtenerEstadoCaja($sesion['id']),
        'movimientos' => CajaController::listarMovimientosCaja($sesion['id'])
    ]);
    exit();
}

if ($route === 'caja_movimiento_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];

    $res = CajaController::registrarIngresoRetiro(
        $_SESSION['usuario_id'],
        $data['tipo'] ?? '',
        limpiarMonto($data['monto'] ?? 0),
        trim($data['motivo'] ?? '')
    );

    if (!$res['success']) {
        http_response_code($res['code'] ?? 400);
    }
    echo json_encode($res);
    exit();
}

// ==========================================================
// FASE 8: REPORTES (solo lectura, solo Administrador)
// Los endpoints son GET autenticados; toda validación de
// fechas/IDs/estados vive en ReporteController (prepared).
// ==========================================================

if ($route === 'reporte_ventas_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteVentas();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_productos_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteProductos();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_inventario_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteInventario();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_compras_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteCompras();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_devoluciones_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteDevoluciones();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_caja_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::reporteCaja();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'reporte_dashboard_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::resumenDashboard();
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

// ==========================================================
// FASE 9 — DASHBOARD ADMINISTRATIVO (GET, JSON)
// El rol decide la vista: ADMIN = global; CAJERO = solo su
// caja/turno y sus ventas (nunca datos administrativos).
// usuario_id siempre de la SESIÓN (sin IDOR por parámetro).
// ==========================================================

if ($route === 'dashboard_datos_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $res = ReporteController::dashboardDatos(
        $_SESSION['usuario_rol'] ?? 'Cajero',
        (int)($_SESSION['usuario_id'] ?? 0),
        (string)($_GET['periodo'] ?? 'hoy'),
        (string)($_GET['fecha_inicio'] ?? ''),
        (string)($_GET['fecha_fin'] ?? '')
    );
    if (!$res['success']) {
        http_response_code(400);
    }
    echo json_encode($res);
    exit();
}

if ($route === 'crear_usuario_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $nombre   = $_POST['nombre'] ?? '';
    $usuario  = $_POST['usuario'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $rol      = $_POST['rol'] ?? 'Cajero';

    $res = UsuarioController::crearUsuario($nombre, $usuario, $email, $password, $rol);
    echo json_encode($res);
    exit();
}

if ($route === 'cambiar_estado_usuario_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    $activo = intval($_POST['activo'] ?? 0);

    $res = UsuarioController::cambiarEstado($usuario_id, $activo);
    echo json_encode($res);
    exit();
}

if ($route === 'ajuste_stock_ajax') {
    // Fase 4: ajuste de inventario SOLO administrador (backend: 403 para cajeros).
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $res = InventarioController::ajustarStock(
        $_POST['producto_id'] ?? 0,
        $_POST['tipo'] ?? '',
        $_POST['cantidad'] ?? 0,
        $_POST['motivo'] ?? ''
    );
    echo json_encode($res);
    exit();
}

if ($route === 'movimientos_kardex_ajax') {
    // Fase 4: lectura del Kardex por producto (usuarios autenticados).
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $producto_id = intval($_GET['producto_id'] ?? 0);
    echo json_encode(InventarioService::obtenerMovimientos($producto_id, 100));
    exit();
}

// ==========================================================
// FASE 5 — PROVEEDORES Y COMPRAS (solo Administrador)
// ==========================================================

if ($route === 'buscar_proveedores_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET['q'] ?? '';
    echo json_encode(ProveedorController::buscarProveedores($q));
    exit();
}

if ($route === 'crear_compra_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Cargamento de datos corrupto o vacío.']);
        exit();
    }

    $res = CompraController::crearCompra(
        $data['proveedor_id'] ?? 0,
        $data['numero_documento'] ?? '',
        $data['observaciones'] ?? '',
        $data['detalles'] ?? []
    );
    echo json_encode($res);
    exit();
}

if ($route === 'confirmar_compra_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $res = CompraController::confirmarCompra($_POST['compra_id'] ?? 0);
    echo json_encode($res);
    exit();
}

if ($route === 'detalle_compra_ajax') {
    AuthController::requireRole('Administrador');
    header('Content-Type: application/json; charset=utf-8');
    $compra_id = intval($_GET['compra_id'] ?? 0);
    $data = CompraController::obtenerCompraConDetalles($compra_id);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Compra no encontrada']);
        exit();
    }
    echo json_encode($data);
    exit();
}

// ==========================================================
// FASE 6 — DEVOLUCIONES (Cajero y Administrador, con caja abierta)
// ==========================================================

if ($route === 'venta_devolucion_datos_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $venta_id = intval($_GET['venta_id'] ?? 0);
    $data = DevolucionController::obtenerVentaParaDevolucion($venta_id);
    if ($data === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Venta no encontrada.']);
        exit();
    }
    if ($data === 'FORBIDDEN') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado: un cajero solo puede devolver sus propias ventas.']);
        exit();
    }
    echo json_encode($data);
    exit();
}

if ($route === 'crear_devolucion_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    require_csrf();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Cargamento de datos corrupto o vacío.']);
        exit();
    }

    $res = DevolucionController::procesarDevolucion(
        intval($data['venta_id'] ?? 0),
        $data['motivo'] ?? '',
        $data['items'] ?? []
    );
    echo json_encode($res);
    exit();
}

if ($route === 'detalle_devolucion_ajax') {
    AuthController::requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $devolucion_id = intval($_GET['devolucion_id'] ?? 0);
    $data = DevolucionController::obtenerDevolucionConDetalles($devolucion_id);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['error' => 'Devolución no encontrada']);
        exit();
    }
    echo json_encode($data);
    exit();
}

// ==========================================================
// DESPACHADOR DE VISTAS (MVC)
// ==========================================================

switch ($route) {
    case 'login':
        require_once __DIR__ . '/views/login.php';
        break;

    case 'logout':
        // Logout seguro (Fase 3): solo POST + token CSRF.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit();
        }
        require_csrf();
        AuthController::logout();
        break;

    case 'pos':
        AuthController::requireLogin();
        require_once __DIR__ . '/views/pos.php';
        break;

    case 'devoluciones':
        // Fase 6: operación de POS — disponible para Cajero y Administrador.
        AuthController::requireLogin();
        require_once __DIR__ . '/views/devoluciones.php';
        break;

    case 'inventario':
        AuthController::requireLogin();
        require_once __DIR__ . '/views/inventario.php';
        break;

    case 'clientes':
        AuthController::requireLogin();
        require_once __DIR__ . '/views/clientes.php';
        break;

    case 'dashboard':
        // Fase 9: disponible para Administrador (vista global) y
        // Cajero (solo su caja/turno y sus ventas). La vista y el
        // endpoint AJAX filtran los datos por rol en el backend.
        AuthController::requireLogin();
        require_once __DIR__ . '/views/dashboard.php';
        break;

    case 'reportes':
        // Fase 8: reportes administrativos (solo lectura).
        AuthController::requireRole('Administrador');
        require_once __DIR__ . '/views/reportes.php';
        break;

    case 'auditoria':
        AuthController::requireRole('Administrador');
        require_once __DIR__ . '/views/auditoria.php';
        break;

    case 'usuarios':
        AuthController::requireRole('Administrador');
        require_once __DIR__ . '/views/usuarios.php';
        break;

    case 'proveedores':
        // Fase 5: solo administradores administran proveedores.
        AuthController::requireRole('Administrador');
        require_once __DIR__ . '/views/proveedores.php';
        break;

    case 'compras':
        // Fase 5: solo administradores crean/confirman compras.
        AuthController::requireRole('Administrador');
        require_once __DIR__ . '/views/compras.php';
        break;

    // Ruta inicial / por defecto
    default:
        if (AuthController::isLoggedIn()) {
            header("Location: index.php?route=pos");
        } else {
            header("Location: index.php?route=login");
        }
        exit();
}
