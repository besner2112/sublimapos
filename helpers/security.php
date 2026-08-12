<?php
// ==========================================================
// Helper Central de Seguridad (Fase 3)
// CSRF reutilizable para formularios y AJAX. No duplicar lógica.
// ==========================================================

/**
 * Devuelve (y crea si es necesario) el token CSRF de la sesión actual.
 *
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto HTML listo para insertar en formularios.
 *
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica un token CSRF (comparación en tiempo constante).
 * Fuentes aceptadas: parámetro $token (JSON/JS), $_POST['csrf_token'],
 * header X-CSRF-Token.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
        if ($token === '') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
    }
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Exige un token CSRF válido; en caso contrario responde 403 y termina.
 * (403 estándar: Apache reescribe códigos no estándar como 419 a 500. Fase 11)
 * Mensaje amigable, sin exponer detalles internos.
 */
function require_csrf() {
    if (!verify_csrf_token()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o expirado. Recargue la página e intente nuevamente.']);
        exit();
    }
}
