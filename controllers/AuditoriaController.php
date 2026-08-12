<?php
// ==========================================
// Controlador de Auditoría y Seguridad
// ==========================================

require_once __DIR__ . '/../conexion/db.php';

class AuditoriaController {
    
    /**
     * Registra un evento en la tabla de auditoría.
     *
     * @param int|null $usuario_id ID del usuario
     * @param string $nombre Nombre del usuario
     * @param string $rol Rol del usuario (Administrador / Cajero / Sistema)
     * @param string $accion Nombre de la acción realizada (ej: "Crear Producto")
     * @param string $modulo Nombre del módulo afectado (ej: "Inventario")
     * @param string $detalles Descripción e información adicional
     */
    public static function registrar($usuario_id, $nombre, $rol, $accion, $modulo, $detalles) {
        try {
            $pdo = conectarBD();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            // XSS y Sanitización preventiva antes de insertar en base de datos
            $nombre_clean = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $rol_clean = htmlspecialchars($rol, ENT_QUOTES, 'UTF-8');
            $accion_clean = htmlspecialchars($accion, ENT_QUOTES, 'UTF-8');
            $modulo_clean = htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8');
            $detalles_clean = htmlspecialchars($detalles, ENT_QUOTES, 'UTF-8');
            
            $sql = "INSERT INTO auditoria_logs 
                    (usuario_id, nombre_usuario, rol_usuario, accion, modulo, detalles, ip_address) 
                    VALUES (:usuario_id, :nombre, :rol, :accion, :modulo, :detalles, :ip)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':nombre'     => $nombre_clean,
                ':rol'        => $rol_clean,
                ':accion'     => $accion_clean,
                ':modulo'     => $modulo_clean,
                ':detalles'   => $detalles_clean,
                ':ip'         => $ip_address
            ]);
        } catch (PDOException $e) {
            // Manejo silencioso en log del servidor para no interrumpir la experiencia de usuario
            error_log("Error de Auditoría: " . $e->getMessage());
        }
    }

    /**
     * Obtiene todos los logs de auditoría ordenados por fecha descendente.
     * Exclusivo para administradores.
     *
     * @return array
     */
    public static function obtenerLogs() {
        try {
            $pdo = conectarBD();
            $stmt = $pdo->query("SELECT * FROM auditoria_logs ORDER BY fecha DESC LIMIT 500");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error al consultar logs: " . $e->getMessage());
            return [];
        }
    }
}
