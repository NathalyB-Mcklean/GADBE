<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package GADBE\Tests
 */

// Cargar autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar configuración para tests
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';

// Iniciar sesión para tests
if (!isset($_SESSION)) {
    session_start();
}

// Funciones de ayuda para tests
function clearTestDatabase(): void {
    $pdo = getDatabase();

    $tables = ['auditoria', 'evaluaciones', 'citas', 'solicitudes', 'servicios', 'usuarios'];

    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM $table WHERE 1=1");
    }
}

function createTestUser(array $data = []): array {
    $pdo = getDatabase();

    $defaults = [
        'correo' => 'test.user@utp.ac.pa',
        'nombre' => 'Test User',
        'password' => 'Test1234',
        'rol' => 'Estudiante'
    ];

    $userData = array_merge($defaults, $data);

    $stmt = $pdo->prepare("
        INSERT INTO usuarios (correo, password_hash, nombre, rol, activo)
        VALUES (?, ?, ?, ?, 1)
    ");

    $hash = password_hash($userData['password'], PASSWORD_DEFAULT);
    $stmt->execute([
        $userData['correo'],
        $hash,
        $userData['nombre'],
        $userData['rol']
    ]);

    $userId = (int)$pdo->lastInsertId();

    return [
        'id' => $userId,
        'correo' => $userData['correo'],
        'nombre' => $userData['nombre'],
        'rol' => $userData['rol'],
        'password' => $userData['password'] // Password sin hashear para tests
    ];
}
