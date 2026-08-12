<?php
// ==========================================================
// Conexión Centralizada a Base de Datos (AWS RDS) - PDO
// ==========================================================
// Las credenciales se leen PRIMERO de variables de entorno del
// servidor (recomendado en producción / AWS) y solo caen en los
// valores por defecto de abajo si la variable de entorno no existe.
// Esto evita tener contraseñas reales escritas en el código fuente.
//
// Configuración recomendada en el servidor (Apache/Nginx/PHP-FPM o
// archivo .env cargado antes de este script):
//   DB_HOST, DB_NAME, DB_USER, DB_PASS
// ==========================================================

// Configuración local opcional (solo desarrollo). Si existe
// config.local.php en la raíz, se cargan sus variables de entorno.
// En producción ese archivo no existe y se usan las variables del
// servidor o los valores por defecto. (Fase 2)
$configLocal = __DIR__ . '/../config.local.php';
if (is_file($configLocal)) {
    require_once $configLocal;
}

/**
 * Lee una variable de entorno distinguiendo "no definida" de "vacía".
 * Así una DB_PASS vacía (MySQL local sin contraseña) es válida y no
 * cae en el valor por defecto. (Fase 2)
 */
function envDb($nombre, $default) {
    $valor = getenv($nombre);
    return $valor === false ? $default : $valor;
}

// Los valores por defecto son placeholders: las credenciales reales de
// infraestructura (host, nombre de BD, usuario) se definen SIEMPRE via
// variables de entorno del servidor (config.local.php en desarrollo o
// /etc/apache2/envvars en producción). (Fase 11 / Repositorio público)
define('DB_HOST', envDb('DB_HOST', 'DB_HOST_ENV'));
define('DB_NAME', envDb('DB_NAME', 'DB_NAME_ENV'));
define('DB_USER', envDb('DB_USER', 'DB_USER_ENV'));
define('DB_PASS', envDb('DB_PASS', '')); // En produccion la contrasena viene de variables de entorno del servidor (DB_PASS en /etc/apache2/envvars). Sin fallback en codigo. (Fase 11)

/**
 * Devuelve una única instancia PDO por petición (patrón singleton),
 * evitando abrir conexiones repetidas a AWS RDS en un mismo request.
 * Oculta el detalle real del error SQL al usuario final, pero lo
 * registra en el log del servidor para diagnóstico.
 *
 * @return PDO
 */
function conectarBD() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES  => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Nunca mostrar la excepción real (host, credenciales, etc.) al usuario final.
        error_log("Error crítico de conexión a AWS RDS: " . $e->getMessage());
        http_response_code(500);
        die("No fue posible conectar con el servidor de base de datos. Contacte al administrador del sistema.");
    }
}

/**
 * Alias de compatibilidad. Varios controladores del sistema (Ventas,
 * Inventario) llaman a getDB() en lugar de conectarBD(); sin este
 * alias esas llamadas provocaban un error fatal "Call to undefined
 * function getDB()" y por eso el cobro y el inventario fallaban.
 *
 * @return PDO
 */
function getDB() {
    return conectarBD();
}

/**
 * Sanitiza un monto monetario que puede venir del front-end como texto
 * formateado (ej. "1,500.00", " L. 1500.00 ", "1500,50"). Elimina
 * separadores de miles, símbolos de moneda y espacios antes de
 * convertir a float, para que nunca se inserte basura en columnas
 * DECIMAL(10,2).
 *
 * @param mixed $valor
 * @return float
 */
function limpiarMonto($valor) {
    if (is_null($valor)) {
        return 0.0;
    }
    $valor = (string) $valor;
    // Quitar símbolo de moneda (L. / L / $), espacios y comas de miles.
    $valor = str_replace(['L.', 'L', '$', ' ', ','], '', $valor);
    $valor = trim($valor);
    return $valor === '' ? 0.0 : floatval($valor);
}
