<?php
// ==========================================
// Controlador de Autenticación y Roles
// ==========================================

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/AuditoriaController.php';
require_once __DIR__ . '/../helpers/security.php';

// Iniciar sesión de forma segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    // Configuración recomendada para cookies de sesión (Fase 3):
    // HttpOnly + solo cookies + strict mode + SameSite Lax.
    // Secure SOLO cuando la conexión es HTTPS (no romper HTTP local).
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

class AuthController {
    
    /**
     * Intenta autenticar un usuario en el sistema.
     *
     * @param string $username
     * @param string $password
     * @return array Array indicando success (true/false) y mensaje.
     */
    public static function login($username, $password) {
        // Limpiar inputs contra XSS e inyecciones lógicas
        $username = trim($username);
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Por favor complete todos los campos.'];
        }
        
        try {
            $pdo = conectarBD();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // ==========================================
            // RATE LIMIT (Fase 3): protege contra fuerza bruta.
            // Tabla intentos_login (migración 001).
            // ==========================================
            $stmt_int = $pdo->prepare("SELECT bloqueado_hasta FROM intentos_login WHERE ip = :ip AND usuario = :usuario LIMIT 1");
            $stmt_int->execute([':ip' => $ip, ':usuario' => $username]);
            $intento_row = $stmt_int->fetch();

            if ($intento_row && $intento_row['bloqueado_hasta'] !== null) {
                $bloqueo = strtotime($intento_row['bloqueado_hasta']);
                if ($bloqueo > time()) {
                    $min_restantes = ceil(($bloqueo - time()) / 60);
                    return ['success' => false, 'message' => "Demasiados intentos fallidos. Intente nuevamente en aproximadamente $min_restantes minuto(s)."];
                }
            }

            $sql = "SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':usuario' => $username]);
            $user = $stmt->fetch();
            
            if ($user && $user['activo'] == 1) {
                // Verificar hash de contraseña
                if (password_verify($password, $user['password_hash'])) {
                    // Regenerar ID de sesión para evitar fijación de sesión
                    session_regenerate_id(true);

                    // Establecer variables de sesión
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nombre'] = $user['nombre'];
                    $_SESSION['usuario_username'] = $user['usuario'];
                    $_SESSION['usuario_rol'] = $user['rol'];
                    $_SESSION['logged_in'] = true;

                    // Limpiar intentos fallidos previos de este usuario+IP
                    $pdo->prepare("DELETE FROM intentos_login WHERE ip = :ip AND usuario = :usuario")
                        ->execute([':ip' => $ip, ':usuario' => $username]);

                    // Registrar login exitoso en logs de auditoría
                    AuditoriaController::registrar(
                        $user['id'],
                        $user['nombre'],
                        $user['rol'],
                        'Inicio de Sesión',
                        'Autenticación',
                        'El usuario accedió correctamente al sistema.'
                    );
                    
                    return ['success' => true, 'message' => 'Acceso autorizado.'];
                }
            }
            
            // ==========================================
            // FALLO DE AUTENTICACIÓN: registrar intento
            // Bloqueo por 15 minutos al acumular 5 fallos.
            // ==========================================
            $sql_upsert = "INSERT INTO intentos_login (ip, usuario, intentos, ultimo_intento, bloqueado_hasta)
                           VALUES (:ip, :usuario, 1, NOW(), NULL)
                           ON DUPLICATE KEY UPDATE
                             intentos = IF(ultimo_intento < NOW() - INTERVAL 15 MINUTE, 1, intentos + 1),
                             ultimo_intento = NOW(),
                             bloqueado_hasta = IF(intentos + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)";
            $stmt_upsert = $pdo->prepare($sql_upsert);
            $stmt_upsert->execute([':ip' => $ip, ':usuario' => $username]);

            // Si llega aquí, falló la autenticación
            AuditoriaController::registrar(
                null,
                $username,
                'Intruso / Desconocido',
                'Intento Fallido de Inicio de Sesión',
                'Autenticación',
                'Intento de acceso fallido con el usuario: ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
            );
            
            return ['success' => false, 'message' => 'Usuario o contraseña incorrectos.'];
            
        } catch (PDOException $e) {
            error_log("Error de login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ocurrió un error en el servidor. Intente nuevamente.'];
        }
    }

    /**
     * Cierra la sesión del usuario actual y destruye los datos de sesión.
     */
    public static function logout() {
        if (self::isLoggedIn()) {
            AuditoriaController::registrar(
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nombre'],
                $_SESSION['usuario_rol'],
                'Cierre de Sesión',
                'Autenticación',
                'El usuario cerró sesión voluntariamente.'
            );
        }
        
        // Limpiar array de sesión
        $_SESSION = [];
        
        // Destruir cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir sesión
        session_destroy();
        
        // Redirigir a login
        header("Location: index.php?route=login");
        exit();
    }

    /**
     * Comprueba si el usuario tiene una sesión activa.
     *
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Obliga a que haya una sesión activa. De lo contrario, redirige al login.
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: index.php?route=login");
            exit();
        }
    }

    /**
     * Enmienda la restricción de que solo usuarios de determinado rol accedan.
     *
     * @param array|string $roles
     */
    public static function requireRole($roles) {
        self::requireLogin();
        
        $roles = (array) $roles;
        if (!in_array($_SESSION['usuario_rol'], $roles)) {
            // Registrar intento de violación de permisos en auditoría
            AuditoriaController::registrar(
                $_SESSION['usuario_id'],
                $_SESSION['usuario_nombre'],
                $_SESSION['usuario_rol'],
                'Acceso Denegado',
                'Auditoría y Seguridad',
                'Intento no autorizado de acceder a un módulo restringido.'
            );
            
            // Mostrar página de error 403
            http_response_code(403);
            die("<div style='background-color:#111625; color:#f25858; padding:30px; font-family:system-ui, sans-serif; border-radius:12px; margin:50px auto; max-width:650px; border:1px solid #ff4444; box-shadow: 0 4px 20px rgba(0,0,0,0.3); text-align:center;'>
                    <h1 style='font-size: 48px; margin:0;'>403</h1>
                    <h3 style='margin-top:0; font-size: 22px; border-bottom: 2px solid #f25858; padding-bottom: 10px;'>Acceso Prohibido</h3>
                    <p style='color:#aeb8cc;'>Tu rol <strong>" . htmlspecialchars($_SESSION['usuario_rol']) . "</strong> no cuenta con los permisos necesarios para ver esta sección.</p>
                    <a href='index.php?route=pos' style='display:inline-block; margin-top:20px; background:#00cbc0; color:#111625; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold;'>Volver a Caja / POS</a>
                 </div>");
        }
    }
}
