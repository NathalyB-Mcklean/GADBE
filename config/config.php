<?php
/**
 * config.php
 * Configuración general del sistema
 */

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Solo en HTTPS
ini_set('session.cookie_samesite', 'Strict');

session_start();

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ocultar errores en producción
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS - Ajustar según necesidad
$allowed_origins = ['http://localhost', 'https://bienestar.utp.ac.pa'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Constantes del sistema
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutos en segundos
define('MIN_PASSWORD_LENGTH', 8);
define('SESSION_TIMEOUT', 3600); // 1 hora
define('MAX_SOLICITUDES_ACTIVAS', 3);
define('UTP_EMAIL_DOMAIN', '@utp.ac.pa');

// Timezone
date_default_timezone_set('America/Panama');