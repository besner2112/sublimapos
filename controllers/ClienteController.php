<?php
// ==========================================
// Controlador de Clientes e Historial de Ventas
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';

class ClienteController {

    public static function obtenerClientes() {
        try {
            $pdo = conectarBD();
            $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerClientes: " . $e->getMessage());
            return [];
        }
    }

    public static function obtenerClientePorId($id) {
        try {
            $pdo = conectarBD();
            $sql = "SELECT * FROM clientes WHERE id = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en obtenerClientePorId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca clientes por coincidencia de identificacion, nombre, email o telefono (Autocompletar POS y busquedas).
     */
    public static function buscarClientes($query) {
        $query = trim(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'));
        if (empty($query)) return [];

        try {
            $pdo = conectarBD();
            $sql = "SELECT * FROM clientes 
                    WHERE nombre LIKE :query OR identificacion LIKE :query OR telefono LIKE :query 
                    ORDER BY nombre ASC LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':query' => "%$query%"]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en buscarClientes: " . $e->getMessage());
            return [];
        }
    }

    public static function crearCliente($identificacion, $nombre, $telefono, $email, $direccion) {
        $identificacion = trim(htmlspecialchars($identificacion, ENT_QUOTES, 'UTF-8'));
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $telefono = trim(htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'));
        $email = trim(htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
        $direccion = trim(htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8'));

        if (empty($nombre)) {
            return ['success' => false, 'message' => 'El nombre del cliente es obligatorio.'];
        }

        try {
            $pdo = conectarBD();
            
            if (!empty($identificacion)) {
                $chk = $pdo->prepare("SELECT id FROM clientes WHERE identificacion = :ident");
                $chk->execute([':ident' => $identificacion]);
                if ($chk->fetch()) {
                    return ['success' => false, 'message' => 'La identificación (RFC, DNI, etc.) ya está registrada.'];
                }
            } else {
                $identificacion = null;
            }

            $sql = "INSERT INTO clientes (identificacion, nombre, telefono, email, direccion) 
                    VALUES (:ident, :nombre, :telefono, :email, :direccion)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ident'     => $identificacion,
                ':nombre'    => $nombre,
                ':telefono'  => $telefono,
                ':email'     => $email,
                ':direccion' => $direccion
            ]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Crear Cliente',
                'Clientes',
                "Se registró al cliente: '$nombre' (Identificación: " . ($identificacion ?? 'N/D') . ")"
            );

            return ['success' => true, 'message' => 'Cliente registrado adecuadamente.'];
        } catch (PDOException $e) {
            error_log("Error al crear cliente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el cliente en base de datos.'];
        }
    }

    public static function editarCliente($id, $identificacion, $nombre, $telefono, $email, $direccion) {
        $id = intval($id);
        $identificacion = trim(htmlspecialchars($identificacion, ENT_QUOTES, 'UTF-8'));
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $telefono = trim(htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'));
        $email = trim(htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
        $direccion = trim(htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8'));

        if ($id <= 0 || empty($nombre)) {
            return ['success' => false, 'message' => 'Campos obligatorios incompletos o datos corruptos.'];
        }

        if ($id === 1) {
            return ['success' => false, 'message' => 'No se permite editar el perfil del cliente genérico (Público General).'];
        }

        try {
            $pdo = conectarBD();
            
            if (!empty($identificacion)) {
                $chk = $pdo->prepare("SELECT id FROM clientes WHERE identificacion = :ident AND id != :id");
                $chk->execute([':ident' => $identificacion, ':id' => $id]);
                if ($chk->fetch()) {
                    return ['success' => false, 'message' => 'La identificación ya está en uso por otro cliente.'];
                }
            } else {
                $identificacion = null;
            }

            $sql = "UPDATE clientes SET 
                        identificacion = :ident, 
                        nombre = :nombre, 
                        telefono = :telefono, 
                        email = :email, 
                        direccion = :direccion 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ident'     => $identificacion,
                ':nombre'    => $nombre,
                ':telefono'  => $telefono,
                ':email'     => $email,
                ':direccion' => $direccion,
                ':id'        => $id
            ]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Editar Cliente',
                'Clientes',
                "Se actualizaron los datos del cliente ID $id: $nombre"
            );

            return ['success' => true, 'message' => 'Datos de cliente modificados con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al editar cliente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar los cambios del cliente.'];
        }
    }

    public static function obtenerHistorialCompras($cliente_id) {
        $cliente_id = intval($cliente_id);
        try {
            $pdo = conectarBD();
            $sql = "SELECT v.*, u.nombre as nombre_cajero 
                    FROM ventas v
                    JOIN usuarios u ON v.usuario_id = u.id
                    WHERE v.cliente_id = :cliente_id 
                    ORDER BY v.fecha_venta DESC LIMIT 100";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':cliente_id' => $cliente_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerHistorialCompras: " . $e->getMessage());
            return [];
        }
    }
}