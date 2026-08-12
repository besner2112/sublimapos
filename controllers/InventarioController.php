<?php
// ==========================================
// Controlador de Inventario (Productos-Categorías)
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';
require_once __DIR__ . '/../helpers/InventarioService.php';

class InventarioController {

    // ==========================================
    // SECCIÓN CATEGORÍAS
    // ==========================================

    public static function obtenerCategorias() {
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerCategorias: " . $e->getMessage());
            return [];
        }
    }

    public static function crearCategoria($nombre, $descripcion) {
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $descripcion = trim(htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'));

        if (empty($nombre)) {
            return ['success' => false, 'message' => 'El nombre de la categoría es requerido.'];
        }

        try {
            $pdo = getDB();
            $sql = "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Crear Categoría',
                'Inventario',
                "Se creó la categoría de producto: $nombre"
            );

            return ['success' => true, 'message' => 'Categoría creada con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al crear categoría: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar categoría (posible nombre duplicado).'];
        }
    }

    public static function editarCategoria($id, $nombre, $descripcion) {
        $id = intval($id);
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $descripcion = trim(htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'));

        if ($id <= 0 || empty($nombre)) {
            return ['success' => false, 'message' => 'Datos inválidos para actualizar categoría.'];
        }

        try {
            $pdo = getDB();
            $sql = "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Editar Categoría',
                'Inventario',
                "Se editó la categoría ID $id: $nombre"
            );

            return ['success' => true, 'message' => 'Categoría actualizada con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al editar categoría: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar la categoría.'];
        }
    }

    public static function eliminarCategoria($id) {
        $id = intval($id);
        
        try {
            $pdo = getDB();
            
            // Comprobar si hay productos activos vinculados a esta categoría antes de dar de baja
            $chk = $pdo->prepare("SELECT COUNT(*) as cuenta FROM productos WHERE categoria_id = :id AND activo = 1");
            $chk->execute([':id' => $id]);
            if ($chk->fetch()['cuenta'] > 0) {
                return ['success' => false, 'message' => 'No puedes desactivar esta categoría porque tiene productos activos asociados.'];
            }

            $sql = "UPDATE categorias SET activo = 0 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Eliminar Categoría (Baja Lógica)',
                'Inventario',
                "Se dio de baja la categoría ID $id"
            );

            return ['success' => true, 'message' => 'Categoría desactivada con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al eliminar categoría: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al desactivar la categoría.'];
        }
    }


    // ==========================================
    // SECCIÓN PRODUCTOS (INVENTARIO)
    // ==========================================

    public static function obtenerProductos($soloActivos = true) {
        try {
            $pdo = conectarBD();
            $where = $soloActivos ? "WHERE p.activo = 1" : "";
            $sql = "SELECT p.*, c.nombre as nombre_categoria 
                    FROM productos p
                    JOIN categorias c ON p.categoria_id = c.id
                    $where
                    ORDER BY p.nombre ASC";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerProductos: " . $e->getMessage());
            return [];
        }
    }

    public static function obtenerProductoPorId($id) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT p.*, c.nombre as nombre_categoria 
                    FROM productos p
                    JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerProductoPorId: " . $e->getMessage());
            return false;
        }
    }

    public static function obtenerProductoPorCodigo($codigo) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT p.*, c.nombre as nombre_categoria 
                    FROM productos p
                    JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.codigo_barras = :codigo AND p.activo = 1 LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':codigo' => $codigo]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerProductoPorCodigo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca productos por coincidencia en nombre o código de barras (Buscador dinámico POS).
     */
    public static function buscarProductos($query) {
        $query = trim(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'));
        if (empty($query)) return [];

        try {
            $pdo = getDB();
            $sql = "SELECT p.*, c.nombre as nombre_categoria 
                    FROM productos p 
                    JOIN categorias c ON p.categoria_id = c.id 
                    WHERE p.activo = 1 AND p.disponibilidad != 'Descontinuado' 
                      AND (p.nombre LIKE :query OR p.codigo_barras LIKE :query) 
                    ORDER BY p.nombre ASC LIMIT 15";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':query' => "%$query%"]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al buscar productos: " . $e->getMessage());
            return [];
        }
    }

    public static function crearProducto($categoria_id, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta, $stock, $stock_minimo, $disponibilidad) {
        $categoria_id = intval($categoria_id);
        $codigo_barras = trim(htmlspecialchars($codigo_barras, ENT_QUOTES, 'UTF-8'));
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $descripcion = trim(htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'));
        $precio_compra = floatval($precio_compra);
        $precio_venta = floatval($precio_venta);
        $stock = intval($stock);
        $stock_minimo = intval($stock_minimo);
        $disponibilidad = in_array($disponibilidad, ['Disponible', 'Agotado', 'Descontinuado']) ? $disponibilidad : 'Disponible';

        if (empty($codigo_barras) || empty($nombre) || $precio_venta <= 0 || $stock < 0) {
            return ['success' => false, 'message' => 'Complete los campos obligatorios con valores positivos.'];
        }

        try {
            $pdo = getDB();
            
            // Verificar unicidad de código de barras
            $chk = $pdo->prepare("SELECT id FROM productos WHERE codigo_barras = :codigo AND activo = 1");
            $chk->execute([':codigo' => $codigo_barras]);
            if ($chk->fetch()) {
                return ['success' => false, 'message' => 'El código de barras ya se encuentra registrado.'];
            }

            // Ajustar estado de disponibilidad automáticamente por reglas de negocio
            if ($stock == 0 && $disponibilidad == 'Disponible') {
                $disponibilidad = 'Agotado';
            } elseif ($stock > 0 && $disponibilidad == 'Agotado') {
                $disponibilidad = 'Disponible';
            }

            // Transacción: el alta del producto y su Kardex de stock inicial
            // deben ser atómicos (Fase 4).
            $pdo->beginTransaction();

            $sql = "INSERT INTO productos (categoria_id, codigo_barras, nombre, descripcion, precio_compra, precio_venta, stock, stock_minimo, disponibilidad) 
                    VALUES (:categoria, :codigo, :nombre, :descripcion, :compra, :venta, 0, :minimo, :disp)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':categoria' => $categoria_id,
                ':codigo'    => $codigo_barras,
                ':nombre'    => $nombre,
                ':descripcion'=> $descripcion,
                ':compra'    => $precio_compra,
                ':venta'     => $precio_venta,
                ':minimo'    => $stock_minimo,
                ':disp'      => $disponibilidad
            ]);

            $nuevo_id = $pdo->lastInsertId();

            // Stock inicial del alta => movimiento INVENTARIO_INICIAL (Kardex).
            // El producto se inserta con stock 0 y el servicio aplica el stock
            // inicial en la misma transacción (evita doble aplicación).
            if ($stock > 0) {
                InventarioService::aplicarMovimiento(
                    $pdo,
                    $nuevo_id,
                    $_SESSION['usuario_id'] ?? null,
                    'INVENTARIO_INICIAL',
                    $stock,
                    $nuevo_id,
                    'PRODUCTO',
                    'Stock inicial de alta de producto.'
                );
            }

            $pdo->commit();

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Crear Producto',
                'Inventario',
                "Se creó el producto '$nombre' con stock inicial: $stock"
            );

            return ['success' => true, 'message' => 'Producto registrado con éxito.'];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al crear producto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el producto.'];
        }
    }

    public static function editarProducto($id, $categoria_id, $codigo_barras, $nombre, $descripcion, $precio_compra, $precio_venta, $stock, $stock_minimo, $disponibilidad) {
        $id = intval($id);
        $categoria_id = intval($categoria_id);
        $codigo_barras = trim(htmlspecialchars($codigo_barras, ENT_QUOTES, 'UTF-8'));
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $descripcion = trim(htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'));
        $precio_compra = floatval($precio_compra);
        $precio_venta = floatval($precio_venta);
        $stock = intval($stock);
        $stock_minimo = intval($stock_minimo);
        $disponibilidad = in_array($disponibilidad, ['Disponible', 'Agotado', 'Descontinuado']) ? $disponibilidad : 'Disponible';

        if ($id <= 0 || empty($codigo_barras) || empty($nombre) || $precio_venta <= 0) {
            return ['success' => false, 'message' => 'Datos requeridos inválidos.'];
        }

        try {
            $pdo = getDB();
            
            // Verificar unicidad de código de barras (excepto él mismo)
            $chk = $pdo->prepare("SELECT id FROM productos WHERE codigo_barras = :codigo AND id != :id AND activo = 1");
            $chk->execute([':codigo' => $codigo_barras, ':id' => $id]);
            if ($chk->fetch()) {
                return ['success' => false, 'message' => 'El código de barras ya lo tiene asignado otro producto activo.'];
            }

            // Obtener stock actual para auditoría y detección de intento de
            // modificación manual de stock (Regla Fase 4).
            $sql_ant = "SELECT stock, nombre FROM productos WHERE id = :id";
            $stmt_ant = $pdo->prepare($sql_ant);
            $stmt_ant->execute([':id' => $id]);
            $prod_anterior = $stmt_ant->fetch();
            $stock_actual = intval($prod_anterior['stock'] ?? 0);

            // REGLA FUNDAMENTAL (Fase 4): la edición normal del producto NO
            // modifica el stock. El campo enviado se ignora; si difiere del
            // actual se avisa que el cambio debe hacerse por Ajuste de Inventario.
            $warning = null;
            if ($stock !== $stock_actual) {
                $warning = "El stock NO fue modificado. Solo puede cambiarse mediante Ajuste de Inventario (stock actual: $stock_actual unidades).";
            }

            $sql = "UPDATE productos SET 
                        categoria_id = :categoria, 
                        codigo_barras = :codigo, 
                        nombre = :nombre, 
                        descripcion = :descripcion, 
                        precio_compra = :compra, 
                        precio_venta = :venta, 
                        stock_minimo = :minimo, 
                        disponibilidad = :disp 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':categoria' => $categoria_id,
                ':codigo'    => $codigo_barras,
                ':nombre'    => $nombre,
                ':descripcion'=> $descripcion,
                ':compra'    => $precio_compra,
                ':venta'     => $precio_venta,
                ':minimo'    => $stock_minimo,
                ':disp'      => $disponibilidad,
                ':id'        => $id
            ]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Editar Producto',
                'Inventario',
                "Edición de producto ID $id. Nombre: '$nombre'." . ($warning ? " " . $warning : "")
            );

            return ['success' => true, 'message' => 'Producto actualizado con éxito.', 'warning' => $warning];

        } catch (PDOException $e) {
            error_log("Error al editar producto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar el producto.'];
        }
    }

    public static function eliminarProducto($id) {
        $id = intval($id);
        
        try {
            $pdo = getDB();
            
            // Obtenemos el nombre para el registro de auditoría
            $p = self::obtenerProductoPorId($id);
            if (!$p) {
                return ['success' => false, 'message' => 'El producto no existe.'];
            }

            // Baja lógica: Conservar la integridad referencial en detalle_ventas
            $sql = "UPDATE productos SET activo = 0, disponibilidad = 'Descontinuado' WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Eliminar Producto (Baja Lógica)',
                'Inventario',
                "Se dio de baja permanente al producto '{$p['nombre']}' (ID: $id)"
            );

            return ['success' => true, 'message' => 'Producto dado de baja lógica correctamente.'];
        } catch (PDOException $e) {
            error_log("Error al eliminar producto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al desactivar el producto del catálogo.'];
        }
    }

    /**
     * Ajuste de inventario (solo Administrador — validado además en la ruta).
     * Genera el movimiento Kardex AJUSTE_ENTRADA o AJUSTE_SALIDA en la misma
     * transacción que el cambio de stock. No permite stock negativo.
     *
     * @param int    $producto_id
     * @param string $tipo      'AJUSTE_ENTRADA' | 'AJUSTE_SALIDA'
     * @param int    $cantidad  Cantidad positiva (el servicio normaliza el signo)
     * @param string $motivo    Obligatorio (min 3 caracteres)
     * @return array
     */
    public static function ajustarStock($producto_id, $tipo, $cantidad, $motivo) {
        $producto_id = intval($producto_id);
        $tipo = in_array($tipo, ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'], true) ? $tipo : null;
        $cantidad = intval($cantidad);
        $motivo = trim((string)$motivo);

        if ($producto_id <= 0 || $tipo === null || $cantidad <= 0) {
            return ['success' => false, 'message' => 'Datos de ajuste inválidos: producto, tipo y cantidad positiva son requeridos.'];
        }
        if (mb_strlen($motivo) < 3) {
            return ['success' => false, 'message' => 'El motivo del ajuste es obligatorio (mínimo 3 caracteres).'];
        }

        $pdo = getDB();
        try {
            $pdo->beginTransaction();

            $res = InventarioService::aplicarMovimiento(
                $pdo,
                $producto_id,
                $_SESSION['usuario_id'] ?? null,
                $tipo,
                $cantidad,
                null,
                'AJUSTE',
                "Ajuste de inventario: $motivo"
            );

            $nom = $pdo->prepare("SELECT nombre FROM productos WHERE id = :id");
            $nom->execute([':id' => $producto_id]);
            $nombre_prod = $nom->fetchColumn();

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Ajuste de Inventario',
                'Inventario',
                "Ajuste $tipo de $cantidad unidades al producto '$nombre_prod' (ID $producto_id). Stock: {$res['stock_anterior']} -> {$res['stock_nuevo']}. Motivo: $motivo"
            );

            $pdo->commit();

            return [
                'success' => true,
                'message' => "Ajuste aplicado: stock de '$nombre_prod' pasó de {$res['stock_anterior']} a {$res['stock_nuevo']} unidades.",
                'stock_anterior' => $res['stock_anterior'],
                'stock_nuevo'    => $res['stock_nuevo']
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en ajuste de inventario: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtiene el total de productos que tienen un stock inferior al stock mínimo parametrizado.
     */
    public static function obtenerProductosBajoStock() {
        try {
            $pdo = conectarBD();
            $sql = "SELECT p.*, c.nombre as nombre_categoria 
                    FROM productos p
                    JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.activo = 1 AND p.stock <= p.stock_minimo AND p.disponibilidad != 'Descontinuado'
                    ORDER BY p.stock ASC";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerProductosBajoStock: " . $e->getMessage());
            return [];
        }
    }
}
