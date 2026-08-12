<?php
// ==========================================
// Controlador de Usuarios (Gestión de Personal)
// Acceso exclusivo del rol Administrador.
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';

class UsuarioController {

    /**
     * Crea una nueva cuenta de usuario (Administrador o Cajero).
     * Nunca acepta contraseñas en texto plano hacia la base de datos:
     * siempre se hashea con password_hash() antes de insertar.
     *
     * @param string $nombre
     * @param string $usuario
     * @param string $email
     * @param string $password
     * @param string $rol 'Administrador' | 'Cajero'
     * @return array
     */
    public static function crearUsuario($nombre, $usuario, $email, $password, $rol) {
        $nombre  = trim($nombre);
        $usuario = trim($usuario);
        $email   = trim($email);
        $rol     = in_array($rol, ['Administrador', 'Cajero'], true) ? $rol : 'Cajero';

        // Validaciones básicas de entrada
        if (empty($nombre) || empty($usuario) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Todos los campos son obligatorios.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'El correo electrónico no es válido.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'];
        }

        try {
            $pdo = getDB();

            // Validar duplicados de usuario o correo antes de insertar
            $sql_check = "SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email LIMIT 1";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':usuario' => $usuario, ':email' => $email]);

            if ($stmt_check->fetch()) {
                return ['success' => false, 'message' => 'Ya existe un usuario con ese nombre de usuario o correo electrónico.'];
            }

            // Hash seguro obligatorio - jamás se guarda contraseña en texto plano
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, activo)
                    VALUES (:nombre, :usuario, :email, :password_hash, :rol, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre'        => $nombre,
                ':usuario'       => $usuario,
                ':email'         => $email,
                ':password_hash' => $password_hash,
                ':rol'           => $rol
            ]);

            $nuevo_id = $pdo->lastInsertId();

            // Auditoría: registrar qué administrador creó la cuenta
            $admin_id     = $_SESSION['usuario_id'] ?? null;
            $admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
            $admin_rol    = $_SESSION['usuario_rol'] ?? 'Administrador';

            AuditoriaController::registrar(
                $admin_id,
                $admin_nombre,
                $admin_rol,
                'Creación de Usuario',
                'Usuarios',
                "El administrador '{$admin_nombre}' creó la cuenta '{$usuario}' (ID $nuevo_id) con rol '{$rol}'."
            );

            return ['success' => true, 'message' => 'Usuario creado correctamente.', 'id' => $nuevo_id];

        } catch (PDOException $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al crear el usuario.'];
        }
    }

    /**
     * Obtiene el listado completo de usuarios (sin exponer el hash de contraseña).
     */
    public static function obtenerUsuarios() {
        try {
            $pdo = getDB();
            $sql = "SELECT id, nombre, usuario, email, rol, activo, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC";
            return $pdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en obtenerUsuarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Activa o desactiva una cuenta de usuario. Un administrador no puede
     * desactivarse a sí mismo (evita bloqueos accidentales del sistema).
     *
     * @param int $usuario_id
     * @param int $activo 1 | 0
     * @return array
     */
    public static function cambiarEstado($usuario_id, $activo) {
        $usuario_id = intval($usuario_id);
        $activo = $activo ? 1 : 0;

        if ($usuario_id === intval($_SESSION['usuario_id'] ?? 0)) {
            return ['success' => false, 'message' => 'No puedes activar o desactivar tu propia cuenta.'];
        }

        try {
            $pdo = getDB();
            $sql = "UPDATE usuarios SET activo = :activo WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':activo' => $activo, ':id' => $usuario_id]);

            $admin_id     = $_SESSION['usuario_id'] ?? null;
            $admin_nombre = $_SESSION['usuario_nombre'] ?? 'Administrador';
            $admin_rol    = $_SESSION['usuario_rol'] ?? 'Administrador';

            AuditoriaController::registrar(
                $admin_id,
                $admin_nombre,
                $admin_rol,
                $activo ? 'Activación de Usuario' : 'Desactivación de Usuario',
                'Usuarios',
                "El administrador '{$admin_nombre}' " . ($activo ? 'activó' : 'desactivó') . " la cuenta con ID {$usuario_id}."
            );

            return ['success' => true, 'message' => 'Estado del usuario actualizado correctamente.'];
        } catch (PDOException $e) {
            error_log("Error en cambiarEstado: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de base de datos al actualizar el usuario.'];
        }
    }
}
