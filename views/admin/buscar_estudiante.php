<?php
/**
 * Buscar Estudiantes - AJAX
 * Búsqueda de estudiantes para asignar citas
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Solo administrador y trabajadoras pueden buscar estudiantes
if ($_SESSION['user_role'] !== 'Administrador' && $_SESSION['user_role'] !== 'Trabajadora Social') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 3) {
    echo json_encode([]);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Buscar estudiantes por nombre, correo o cédula
    $stmt = $conn->prepare("
        SELECT 
            id_usuario,
            nombre_completo,
            correo_institucional,
            cedula,
            facultad
        FROM usuarios
        WHERE tipo_usuario = 'Estudiante'
        AND activo = 1
        AND (
            nombre_completo LIKE ? 
            OR correo_institucional LIKE ?
            OR cedula LIKE ?
        )
        ORDER BY nombre_completo
        LIMIT 10
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($estudiantes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la búsqueda: ' . $e->getMessage()]);
}
?>