<?php
// ==========================================
// Vista del Módulo de Inventario (CRUD)
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/InventarioController.php';

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
    
        // 1. Crear nuevo producto
        if (isset($_POST['accion']) && $_POST['accion'] == 'crear_producto') {
            $res = InventarioController::crearProducto(
                $_POST['categoria_id'],
                $_POST['codigo_barras'],
                $_POST['nombre'],
                $_POST['descripcion'],
                $_POST['precio_compra'],
                $_POST['precio_venta'],
                $_POST['stock'],
                $_POST['stock_minimo'],
                $_POST['disponibilidad']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
        
        // 2. Editar producto existente
        if (isset($_POST['accion']) && $_POST['accion'] == 'editar_producto') {
            $res = InventarioController::editarProducto(
                $_POST['id'],
                $_POST['categoria_id'],
                $_POST['codigo_barras'],
                $_POST['nombre'],
                $_POST['descripcion'],
                $_POST['precio_compra'],
                $_POST['precio_venta'],
                $_POST['stock'],
                $_POST['stock_minimo'],
                $_POST['disponibilidad']
            );
            if ($res['success']) {
                $mensaje_exito = $res['message'];
                // Aviso Fase 4: el stock no se modifica en la edición normal
                if (!empty($res['warning'])) {
                    $mensaje_exito .= " " . $res['warning'];
                }
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 3. Eliminar producto (Baja lógica)
        if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar_producto') {
            $res = InventarioController::eliminarProducto($_POST['id']);
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }

        // 4. Crear nueva categoría
        if (isset($_POST['accion']) && $_POST['accion'] == 'crear_categoria') {
            $res = InventarioController::crearCategoria($_POST['nombre_cat'], $_POST['descripcion_cat']);
            if ($res['success']) {
                $mensaje_exito = $res['message'];
            } else {
                $mensaje_error = $res['message'];
            }
        }
    }
}

// Obtener datos frescos del inventario
$productos = InventarioController::obtenerProductos(true);
$categorias = InventarioController::obtenerCategorias();
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
            <i class="bi bi-box-seam text-cyan me-2"></i>Catálogo de Insumos para Sublimación
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form action="" method="GET" class="d-inline-block">
                <input type="hidden" name="route" value="inventario">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-custom" id="inv-filtro" name="q" placeholder="Filtrar productos..." value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           oninput="filtrarInventario()" aria-label="Filtrar productos del catálogo">
                    <button class="btn btn-outline-cyan" type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
                </div>
            </form>
            <?php if ($usuario_rol === 'Administrador'): ?>
                <!-- Botón Categorías -->
                <button class="btn btn-outline-cyan btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                    <i class="bi bi-tags-fill me-1"></i> Nueva Categoría
                </button>
                <!-- Botón Ajuste de Stock (Fase 4) -->
                <button class="btn btn-outline-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalAjusteStock">
                    <i class="bi bi-sliders me-1"></i> Ajuste de Stock
                </button>
                <!-- Botón Nuevo Producto -->
                <button class="btn btn-cyan btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoProducto">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo Producto
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-3 table-responsive">
        <table class="table table-custom table-hover m-0">
            <thead>
                <tr>
                    <th scope="col">Cód. Barras</th>
                    <th scope="col">Nombre de Producto</th>
                    <th scope="col">Categoría</th>
                    <?php if ($usuario_rol === 'Administrador'): ?>
                        <th scope="col">P. Compra</th>
                    <?php endif; ?>
                    <th scope="col">P. Venta</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Estatus</th>
                    <?php if ($usuario_rol === 'Administrador'): ?>
                        <th scope="col" class="text-end">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="inv-tbody">
                <?php if (empty($productos)): ?>
                    <tr id="inv-fila-vacia" class="inv-fila">
                        <td colspan="<?php echo ($usuario_rol === 'Administrador') ? 8 : 6; ?>" class="text-center text-secondary py-5">
                            <i class="bi bi-box-seam d-block fs-2 mb-2"></i> El inventario está vacío.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p): ?>
                        <?php 
                        $esBajoStock = $p['stock'] <= $p['stock_minimo'];
                        $esAgotado = $p['stock'] == 0 || $p['disponibilidad'] == 'Agotado';
                        ?>
                        <tr class="inv-fila">
                            <td><code><?php echo htmlspecialchars($p['codigo_barras']); ?></code></td>
                            <td>
                                <span class="fw-bold"><?php echo htmlspecialchars($p['nombre']); ?></span>
                                <?php if (!empty($p['descripcion'])): ?>
                                    <small class="text-secondary d-block"><?php echo htmlspecialchars($p['descripcion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['nombre_categoria']); ?></td>
                            <?php if ($usuario_rol === 'Administrador'): ?>
                                <td>L. <?php echo number_format($p['precio_compra'], 2); ?></td>
                            <?php endif; ?>
                            <td class="text-cyan fw-semibold">L. <?php echo number_format($p['precio_venta'], 2); ?></td>
                            <td>
                                <span class="badge badge-stock <?php echo $esAgotado ? 'stock-danger' : ($esBajoStock ? 'stock-warning' : 'stock-ok'); ?>">
                                    <?php echo $p['stock']; ?> unidades
                                </span>
                            </td>
                            <td>
                                <?php if ($p['disponibilidad'] === 'Descontinuado'): ?>
                                    <span class="badge bg-secondary text-dark">Descontinuado</span>
                                <?php elseif ($esAgotado): ?>
                                    <span class="badge bg-danger">Agotado</span>
                                <?php elseif ($esBajoStock): ?>
                                    <span class="badge bg-warning text-dark">Stock Bajo</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Disponible</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($usuario_rol === 'Administrador'): ?>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary me-1 py-1 px-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalKardex"
                                            onclick="cargarKardex(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="bi bi-journal-text"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-cyan me-1 py-1 px-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarProducto"
                                            onclick='cargarDatosModal(<?php echo json_encode($p); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-1 px-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEliminarProducto"
                                            onclick="configurarEliminacion(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="inv-filtro-aviso" class="d-none text-center text-secondary py-4">
    <i class="bi bi-search d-block fs-2 mb-2"></i> Ningún producto coincide con el filtro.
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function filtrarInventario() {
        var q = (document.getElementById('inv-filtro').value || '').toLowerCase().trim();
        var filas = document.querySelectorAll('#inv-tbody tr.inv-fila');
        var visibles = 0;
        filas.forEach(function (tr) {
            var coincide = !q || (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
            tr.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        var aviso = document.getElementById('inv-filtro-aviso');
        if (aviso) aviso.style.display = visibles === 0 ? '' : 'none';
    }
    window.filtrarInventario = filtrarInventario;
    if (document.getElementById('inv-filtro') && document.getElementById('inv-filtro').value) {
        filtrarInventario();
    }
});
</script>

<!-- ==========================================================
     MODALES ADMINISTRATIVOS (EXCLUSIVOS ROL ADMINISTRADOR)
     ========================================================== -->
<?php if ($usuario_rol === 'Administrador'): ?>

    <!-- MODAL 1: NUEVO PRODUCTO -->
    <div class="modal fade" id="modalNuevoProducto" tabindex="-1" aria-labelledby="modalNuevoProductoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-plus-circle-fill text-cyan me-2"></i> Alta de Artículo de Sublimación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="crear_producto">
                    <?php echo csrf_field(); ?>
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Código de Barras *</label>
                                <input type="text" class="form-control form-control-custom" name="codigo_barras" placeholder="Ej: 7501001" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Categoría *</label>
                                <select class="form-select form-control-custom" name="categoria_id" required>
                                    <?php foreach ($categorias as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-secondary fw-semibold">Nombre de Producto *</label>
                                <input type="text" class="form-control form-control-custom" name="nombre" placeholder="Ej: Taza de Cerámica Blanca 11oz" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-secondary fw-semibold">Descripción</label>
                                <textarea class="form-control form-control-custom" name="descripcion" placeholder="Añade especificaciones del material sublimable..." rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Costo Compra (L.) *</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom" name="precio_compra" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Precio Venta (L.) *</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom" name="precio_venta" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Estatus Catálogo</label>
                                <select class="form-select form-control-custom" name="disponibilidad">
                                    <option value="Disponible">Disponible</option>
                                    <option value="Agotado">Agotado</option>
                                    <option value="Descontinuado">Descontinuado</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Cantidad en Inventario (Stock inicial) *</label>
                                <input type="number" min="0" class="form-control form-control-custom text-cyan fw-bold" name="stock" value="0" required>
                                <small class="text-secondary">Se registrará como INVENTARIO_INICIAL en el Kardex.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Mínimo para Alerta *</label>
                                <input type="number" min="1" class="form-control form-control-custom text-warning fw-bold" name="stock_minimo" value="5" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cyan px-4">Registrar Insumo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: EDITAR PRODUCTO -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="modalEditarProductoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square text-cyan me-2"></i> Editar Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="editar_producto">
                    <input type="hidden" name="id" id="edit_id">
                    <?php echo csrf_field(); ?>
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Código de Barras *</label>
                                <input type="text" class="form-control form-control-custom" id="edit_codigo_barras" name="codigo_barras" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Categoría *</label>
                                <select class="form-select form-control-custom" id="edit_categoria_id" name="categoria_id" required>
                                    <?php foreach ($categorias as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-secondary fw-semibold">Nombre de Producto *</label>
                                <input type="text" class="form-control form-control-custom" id="edit_nombre" name="nombre" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-secondary fw-semibold">Descripción</label>
                                <textarea class="form-control form-control-custom" id="edit_descripcion" name="descripcion" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Costo Compra (L.) *</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom" id="edit_precio_compra" name="precio_compra" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Precio Venta (L.) *</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom" id="edit_precio_venta" name="precio_venta" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary fw-semibold">Estatus Catálogo</label>
                                <select class="form-select form-control-custom" id="edit_disponibilidad" name="disponibilidad">
                                    <option value="Disponible">Disponible</option>
                                    <option value="Agotado">Agotado</option>
                                    <option value="Descontinuado">Descontinuado</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Stock (controlado por Kardex)</label>
                                <input type="hidden" id="edit_stock" name="stock">
                                <div class="form-control form-control-custom text-cyan fw-bold d-flex justify-content-between align-items-center">
                                    <span id="edit_stock_label">-</span>
                                    <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" onclick="abrirAjusteDesdeEdicion()">
                                        <i class="bi bi-sliders me-1"></i> Ajustar
                                    </button>
                                </div>
                                <small class="text-secondary">El stock solo se modifica mediante Ajuste de Inventario o ventas.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Mínimo para Alerta *</label>
                                <input type="number" min="1" class="form-control form-control-custom text-warning fw-bold" id="edit_stock_minimo" name="stock_minimo" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cyan px-4">Guardar Modificaciones</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: ELIMINAR/BAJA LÓGICA -->
    <div class="modal fade" id="modalEliminarProducto" tabindex="-1" aria-labelledby="modalEliminarProductoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-danger">
                    <h5 class="modal-title fw-bold"><i class="bi bi-trash-fill me-2"></i> Desactivar Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="" method="POST">
                    <input type="hidden" name="accion" value="eliminar_producto">
                    <input type="hidden" name="id" id="delete_id">
                    <?php echo csrf_field(); ?>
                    
                    <div class="modal-body p-4 text-center">
                        <i class="bi bi-exclamation-circle text-danger fs-1 d-block mb-3"></i>
                        <p class="mb-2">¿Estás completamente seguro de retirar el insumo de sublimación?</p>
                        <p class="fw-bold text-primary fs-5" id="delete_nombre"></p>
                        <p class="text-secondary small">
                            El producto no se eliminará físicamente para conservar el historial en boletas o facturas anteriores; pasará a estatus de baja lógica y no se mostrará más en el catálogo activo del POS.
                        </p>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger text-white px-4">Dar de Baja Insumo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4: CREAR CATEGORÍA -->
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-tag-fill text-cyan me-2"></i> Nueva Categoría del Catálogo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form action="" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="crear_categoria">
                    <?php echo csrf_field(); ?>
                    
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-semibold">Nombre de Categoría *</label>
                            <input type="text" class="form-control form-control-custom" name="nombre_cat" placeholder="Ej: Camisetas Dry-Fit" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-semibold">Descripción</label>
                            <textarea class="form-control form-control-custom" name="descripcion_cat" placeholder="Características rápidas de la familia de productos..." rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cyan px-4">Guardar Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 5: AJUSTE DE INVENTARIO (Fase 4 — solo admin) -->
    <div class="modal fade" id="modalAjusteStock" tabindex="-1" aria-labelledby="modalAjusteStockLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-sliders text-warning me-2"></i> Ajuste de Inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formAjusteStock" autocomplete="off">
                    <div class="modal-body p-4">
                        <div id="resultadoAjuste"></div>
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-semibold">Producto *</label>
                            <select class="form-select form-control-custom" name="producto_id" id="ajuste_producto_id" required>
                                <option value="">-- Seleccionar producto --</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?> (Stock: <?php echo $p['stock']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Tipo de Ajuste *</label>
                                <select class="form-select form-control-custom" name="tipo" required>
                                    <option value="AJUSTE_ENTRADA">Entrada (+)</option>
                                    <option value="AJUSTE_SALIDA">Salida (-)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary fw-semibold">Cantidad *</label>
                                <input type="number" min="1" step="1" class="form-control form-control-custom text-cyan fw-bold" name="cantidad" placeholder="Ej: 5" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label text-secondary fw-semibold">Motivo / Observación *</label>
                            <textarea class="form-control form-control-custom" name="motivo" rows="2" placeholder="Ej: Conteo físico, ajuste por merma, entrada por préstamo devuelto..." required></textarea>
                            <small class="text-secondary">El motivo queda registrado en el Kardex y en auditoría.</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer modal-footer-premium">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning text-dark px-4">Aplicar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 6: KARDEX DEL PRODUCTO (Fase 4) -->
    <div class="modal fade" id="modalKardex" tabindex="-1" aria-labelledby="modalKardexLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold text-white"><i class="bi bi-journal-text text-cyan me-2"></i> Kardex de <span id="kardex_nombre"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3" id="kardex_body">
                    <div class="text-center text-secondary py-4">Cargando movimientos...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS helper para cargar datos del modal dinámicamente -->
    <script>
        function cargarDatosModal(p) {
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_codigo_barras').value = p.codigo_barras;
            document.getElementById('edit_categoria_id').value = p.categoria_id;
            document.getElementById('edit_nombre').value = p.nombre;
            document.getElementById('edit_descripcion').value = p.descripcion ? p.descripcion : '';
            document.getElementById('edit_precio_compra').value = p.precio_compra;
            document.getElementById('edit_precio_venta').value = p.precio_venta;
            document.getElementById('edit_stock').value = p.stock;
            document.getElementById('edit_stock_label').innerText = p.stock + ' unidades';
            document.getElementById('edit_stock_minimo').value = p.stock_minimo;
            document.getElementById('edit_disponibilidad').value = p.disponibilidad;
        }

        function configurarEliminacion(id, nombre) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nombre').innerText = nombre;
        }

        function esc(s) {
            return (s === null || s === undefined) ? '-' : String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function abrirAjusteDesdeEdicion() {
            var pid = document.getElementById('edit_id').value;
            if (pid) { document.getElementById('ajuste_producto_id').value = pid; }
            new bootstrap.Modal(document.getElementById('modalAjusteStock')).show();
        }

        document.getElementById('formAjusteStock').addEventListener('submit', function (e) {
            e.preventDefault();
            var res = document.getElementById('resultadoAjuste');
            res.innerHTML = '';
            fetch('index.php?route=ajuste_stock_ajax', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                body: new URLSearchParams(new FormData(this))
            })
            .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
            .then(function (r) {
                if (r.status === 403) { res.innerHTML = '<div class="alert alert-danger py-2 mb-2">Acceso denegado.</div>'; return; }
                if (r.status === 419) { res.innerHTML = '<div class="alert alert-danger py-2 mb-2">Token de seguridad expirado. Recargue la página.</div>'; return; }
                var cls = r.j.success ? 'alert-success' : 'alert-danger';
                res.innerHTML = '<div class="alert ' + cls + ' py-2 mb-2">' + esc(r.j.message) + '</div>';
                if (r.j.success) { setTimeout(function () { location.reload(); }, 900); }
            })
            .catch(function () { res.innerHTML = '<div class="alert alert-danger py-2 mb-2">Error de red al aplicar el ajuste.</div>'; });
        });

        function cargarKardex(id, nombre) {
            document.getElementById('kardex_nombre').innerText = nombre;
            var body = document.getElementById('kardex_body');
            body.innerHTML = '<div class="text-center text-secondary py-4">Cargando movimientos...</div>';
            fetch('index.php?route=movimientos_kardex_ajax&producto_id=' + id)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.length) {
                    body.innerHTML = '<div class="text-center text-secondary py-4">Sin movimientos registrados para este producto.</div>';
                    return;
                }
                var ENTRADAS = ['INVENTARIO_INICIAL', 'ENTRADA_COMPRA', 'DEVOLUCION_VENTA', 'AJUSTE_ENTRADA'];
                var badgeTipo = function (tipo) {
                    return '<span class="' + (ENTRADAS.indexOf(tipo) !== -1 ? 'kardex-badge-in' : 'kardex-badge-out') + '">'
                        + (ENTRADAS.indexOf(tipo) !== -1 ? '<i class="bi bi-arrow-down-left me-1"></i>' : '<i class="bi bi-arrow-up-right me-1"></i>')
                        + esc(tipo) + '</span>';
                };
                var h = '<div class="table-responsive"><table class="table table-custom table-sm m-0">' +
                    '<thead><tr><th>Fecha</th><th>Tipo</th><th>Cant.</th><th>Stock Ant.</th><th>Stock Nuevo</th><th>Referencia</th><th>Usuario</th><th>Observaciones</th></tr></thead><tbody>';
                data.forEach(function (m) {
                    var esEntrada = parseInt(m.cantidad) > 0;
                    var cls = esEntrada ? 'text-success' : 'text-danger';
                    var signo = esEntrada ? '+' : '';
                    h += '<tr><td>' + esc(m.fecha) + '</td><td>' + badgeTipo(m.tipo_movimiento) + '</td>' +
                         '<td class="' + cls + ' fw-bold">' + signo + esc(m.cantidad) + '</td>' +
                         '<td>' + esc(m.stock_anterior) + '</td><td>' + esc(m.stock_nuevo) + '</td>' +
                         '<td>' + esc(m.referencia_tipo) + (m.referencia_id ? ' #' + esc(m.referencia_id) : '') + '</td>' +
                         '<td>' + esc(m.nombre_usuario) + '</td><td class="small">' + esc(m.observaciones) + '</td></tr>';
                });
                body.innerHTML = h + '</tbody></table></div>';
            })
            .catch(function () { body.innerHTML = '<div class="text-center text-danger py-4">Error al cargar el Kardex.</div>'; });
        }
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
