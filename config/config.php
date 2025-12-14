<?php
/**
 * Configuración General de la Aplicación
 *
 * @package GADBE\Config
 */

// Configuración de errores según entorno
$appEnv = getenv('APP_ENV') ?: 'production';
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'development' || $appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuración de zona horaria
date_default_timezone_set('America/Panama');

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', getenv('SESSION_SECURE') ?: 0);
ini_set('session.gc_maxlifetime', getenv('SESSION_LIFETIME') ?: 7200);

/**
 * Constantes de configuración
 */
define('APP_NAME', 'Sistema de Bienestar Estudiantil UTP');
define('APP_VERSION', '1.0.0');
define('APP_ENV', $appEnv);
define('APP_DEBUG', $appDebug);
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');

// Configuración de archivos
define('MAX_UPLOAD_SIZE', (int)(getenv('MAX_UPLOAD_SIZE') ?: 5242880)); // 5MB default
define('ALLOWED_EXTENSIONS', explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'pdf,jpg,jpeg,png'));
define('UPLOAD_PATH', __DIR__ . '/../' . (getenv('UPLOAD_PATH') ?: 'uploads/documentos'));

// Configuración de seguridad
define('BCRYPT_COST', (int)(getenv('BCRYPT_COST') ?: 12));
define('MAX_LOGIN_ATTEMPTS', (int)(getenv('MAX_LOGIN_ATTEMPTS') ?: 5));
define('LOCKOUT_TIME', (int)(getenv('LOCKOUT_TIME') ?: 1800)); // 30 minutos
define('PASSWORD_MIN_LENGTH', (int)(getenv('PASSWORD_MIN_LENGTH') ?: 8));

// Configuración de email
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@utp.ac.pa');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// Dominios permitidos para CORS (más restrictivo que '*')
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8000',
    'http://localhost:3000',
    'https://utp.ac.pa'
]);

/**
 * Configuración de headers HTTP
 */
function setSecurityHeaders(): void {
    // CORS configurado de forma segura
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content type
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * Obtiene valor de configuración
 *
 * @param string $key Clave de configuración
 * @param mixed $default Valor por defecto
 * @return mixed
 */
function config(string $key, $default = null) {
    return getenv($key) ?: $default;
}

/**
 * Verifica si la aplicación está en modo debug
 *
 * @return bool
 */
function isDebugMode(): bool {
    return APP_DEBUG;
}

/**
 * Registra un error en el log
 *
 * @param string $message Mensaje de error
 * @param array $context Contexto adicional
 * @return void
 */
function logError(string $message, array $context = []): void {
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log(date('[Y-m-d H:i:s]') . " ERROR: $message" . $contextStr);
}

/**
 * Registra un mensaje informativo en el log
 *
 * @param string $message Mensaje
 * @param array $context Contexto adicional
 * @return void
 */
function logInfo(string $message, array $context = []): void {
    if (APP_DEBUG) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log(date('[Y-m-d H:i:s]') . " INFO: $message" . $contextStr);
    }
}
