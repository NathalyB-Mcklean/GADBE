<?php
/**
 * Configuración de Base de Datos
 *
 * Lee configuración desde variables de entorno para mayor seguridad
 *
 * @package GADBE\Config
 */

// Cargar variables de entorno desde archivo .env si existe
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

/**
 * Obtiene configuración de base de datos
 *
 * @return array Configuración de conexión PDO
 */
function getDatabaseConfig(): array {
    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'db' => getenv('DB_NAME') ?: 'bienestar_estudiantil',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ];
}

/**
 * Crea conexión PDO a la base de datos
 *
 * @return PDO Instancia de conexión
 * @throws PDOException Si falla la conexión
 */
function getDatabase(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $config = getDatabaseConfig();

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['user'], $config['pass'], $config['options']);
        } catch (PDOException $e) {
            error_log("Error de conexión a base de datos: " . $e->getMessage());
            throw new PDOException("Error de conexión a la base de datos");
        }
    }

    return $pdo;
}
