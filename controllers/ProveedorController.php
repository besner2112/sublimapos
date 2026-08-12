<?php
// ==========================================
// Controlador de Proveedores (Fase 5)
// CRUD con baja lógica. Solo Administrador
// (validado también en la ruta/AJAX).
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';

class ProveedorController {

    public static function obtenerProveedores($soloActivos = true) {
        try {
            $pdo = conectarBD();
            $where = $soloActivos ? "WHERE activo = 1" : "";
            $sql = "SELECT * FROM proveedores $where ORDER BY nombre ASC";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerProveedores: " . $e->getMessage());
            return [];
        }
    }

    public static function buscarProveedores($q) {
        $q = trim(htmlspecialchars($q, ENT_QUOTES, 'UTF-8'));
        if ($q === '') return [];
        try {
            $pdo = getDB();
            // PDO con EMULATE_PREPARES=false: cada placeholder con nombre
            // debe ser único (HY093 si se repite el mismo nombre).
            $sql = "SELECT * FROM proveedores
                    WHERE activo = 1
                      AND (nombre LIKE :q1 OR rtn LIKE :q2 OR telefono LIKE :q3 OR correo LIKE :q4)
                    ORDER BY nombre ASC LIMIT 15";
            $stmt = $pdo->prepare($sql);
            $like = "%$q%";
            $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al buscar proveedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crea un proveedor. El nombre es obligatorio; el resto opcional.
     */
    public static function crearProveedor($nombre, $rtn, $telefono, $correo, $direccion, $contacto) {
        $nombre    = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $rtn       = trim(htmlspecialchars($rtn, ENT_QUOTES, 'UTF-8'));
        $telefono  = trim(htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'));
        $correo    = trim(htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'));
        $direccion = trim(htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8'));
        $contacto  = trim(htmlspecialchars($contacto, ENT_QUOTES, 'UTF-8'));

        if ($nombre === '') {
            return ['success' => false, 'message' => 'El nombre del proveedor es obligatorio.'];
        }
        if (mb_strlen($nombre) < 2) {
            return ['success' => false, 'message' => 'El nombre del proveedor debe tener al menos 2 caracteres.'];
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'El correo electrónico no es válido.'];
        }
        if ($rtn !== '' && !preg_match('/^[A-Za-z0-9\-]+$/', $rtn)) {
            return ['success' => false, 'message' => 'El RTN solo puede contener letras, números y guiones.'];
        }

        try {
            $pdo = getDB();
            $sql = "INSERT INTO proveedores (nombre, rtn, telefono, correo, direccion, contacto)
                    VALUES (:nombre, :rtn, :telefono, :correo, :direccion, :contacto)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre'    => mb_substr($nombre, 0, 150),
                ':rtn'       => $rtn !== '' ? mb_substr($rtn, 0, 20) : null,
                ':telefono'  => $telefono !== '' ? mb_substr($telefono, 0, 30) : null,
                ':correo'    => $correo !== '' ? mb_substr($correo, 0, 150) : null,
                ':direccion' => $direccion !== '' ? mb_substr($direccion, 0, 255) : null,
                ':contacto'  => $contacto !== '' ? mb_substr($contacto, 0, 150) : null,
            ]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Crear Proveedor',
                'Proveedores',
                "Se creó el proveedor: $nombre"
            );

            return ['success' => true, 'message' => 'Proveedor creado con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al crear proveedor: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar el proveedor.'];
        }
    }

    public static function editarProveedor($id, $nombre, $rtn, $telefono, $correo, $direccion, $contacto) {
        $id = intval($id);
        $nombre    = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        $rtn       = trim(htmlspecialchars($rtn, ENT_QUOTES, 'UTF-8'));
        $telefono  = trim(htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'));
        $correo    = trim(htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'));
        $direccion = trim(htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8'));
        $contacto  = trim(htmlspecialchars($contacto, ENT_QUOTES, 'UTF-8'));

        if ($id <= 0 || $nombre === '') {
            return ['success' => false, 'message' => 'Datos inválidos para actualizar el proveedor.'];
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'El correo electrónico no es válido.'];
        }
        if ($rtn !== '' && !preg_match('/^[A-Za-z0-9\-]+$/', $rtn)) {
            return ['success' => false, 'message' => 'El RTN solo puede contener letras, números y guiones.'];
        }

        try {
            $pdo = getDB();
            $chk = $pdo->prepare("SELECT nombre FROM proveedores WHERE id = :id");
            $chk->execute([':id' => $id]);
            if (!$chk->fetch()) {
                return ['success' => false, 'message' => 'El proveedor no existe.'];
            }

            $sql = "UPDATE proveedores SET nombre = :nombre, rtn = :rtn, telefono = :telefono,
                    correo = :correo, direccion = :direccion, contacto = :contacto WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre'    => mb_substr($nombre, 0, 150),
                ':rtn'       => $rtn !== '' ? mb_substr($rtn, 0, 20) : null,
                ':telefono'  => $telefono !== '' ? mb_substr($telefono, 0, 30) : null,
                ':correo'    => $correo !== '' ? mb_substr($correo, 0, 150) : null,
                ':direccion' => $direccion !== '' ? mb_substr($direccion, 0, 255) : null,
                ':contacto'  => $contacto !== '' ? mb_substr($contacto, 0, 150) : null,
                ':id'        => $id,
            ]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Editar Proveedor',
                'Proveedores',
                "Se editó el proveedor ID $id: $nombre"
            );

            return ['success' => true, 'message' => 'Proveedor actualizado con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al editar proveedor: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar el proveedor.'];
        }
    }

    /**
     * Baja lógica (activo = 0). Nunca se elimina físicamente un
     * proveedor con compras relacionadas: la baja lógica preserva
     * la integridad del historial.
     */
    public static function desactivarProveedor($id) {
        $id = intval($id);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Proveedor inválido.'];
        }

        try {
            $pdo = getDB();
            $chk = $pdo->prepare("SELECT nombre FROM proveedores WHERE id = :id");
            $chk->execute([':id' => $id]);
            $prov = $chk->fetch();
            if (!$prov) {
                return ['success' => false, 'message' => 'El proveedor no existe.'];
            }

            $sql = "UPDATE proveedores SET activo = 0 WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            AuditoriaController::registrar(
                $_SESSION['usuario_id'] ?? null,
                $_SESSION['usuario_nombre'] ?? 'Administrador',
                $_SESSION['usuario_rol'] ?? 'Administrador',
                'Desactivar Proveedor (Baja Lógica)',
                'Proveedores',
                "Se dio de baja al proveedor '{$prov['nombre']}' (ID: $id)"
            );

            return ['success' => true, 'message' => 'Proveedor desactivado con éxito.'];
        } catch (PDOException $e) {
            error_log("Error al desactivar proveedor: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al desactivar el proveedor.'];
        }
    }
}
