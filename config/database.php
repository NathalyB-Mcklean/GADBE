<?php
/**
 * database.php
 * Configuración de base de datos
 */

// Cargar variables de entorno (en producción usar .env)
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'db' => getenv('DB_NAME') ?: 'bienestar_estudiantil',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4'
];

// Crear conexión PDO
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['db']};charset={$db_config['charset']}",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']));
}

return $pdo;