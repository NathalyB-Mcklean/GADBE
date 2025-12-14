<?php
/**
 * evaluaciones.php
 * Gestión de evaluaciones de satisfacción
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ CREAR EVALUACIÓN ============
if ($action === 'crear_evaluacion') {
    validarSesion();
    validarCSRF();
    
    $servicio_id = (int)($data['servicio_id'] ?? 0);
    $calificacion = $data['calificacion'] ?? '';
    $comentarios = sanitizar($data['comentarios'] ?? '');
    
    if (!$servicio_id || !$calificacion) {
        responder(['success' => false, 'message' => 'Servicio y calificación son obligatorios'], 400);
    }
    
    $calificaciones_validas = ['Muy Malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];
    if (!in_array($calificacion, $calificaciones_validas)) {
        responder(['success' => false, 'message' => 'Calificación inválida'], 400);
    }
    
    // Verificar que el estudiante haya usado el servicio
    $stmt = $pdo->prepare(
        "SELECT id FROM citas 
         WHERE estudiante_id = ? AND servicio_id = ? AND estado = 'completada'"
    );
    $stmt->execute([$_SESSION['user_id'], $servicio_id]);
    if (!$stmt->fetch()) {
        responder(['success' => false, 'message' => 'Solo puede evaluar servicios que haya utilizado'], 403);
    }
    
    // Crear evaluación
    $stmt = $pdo->prepare(
        "INSERT INTO evaluaciones (estudiante_id, servicio_id, calificacion, comentarios, fecha_evaluacion) 
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$_SESSION['user_id'], $servicio_id, $calificacion, $comentarios]);
    
    $evaluacion_id = $pdo->lastInsertId();
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_evaluacion', 'evaluaciones', $evaluacion_id);
    
    responder(['success' => true, 'message' => 'Evaluación enviada exitosamente', 'id' => $evaluacion_id], 201);
}

// ============ LISTAR EVALUACIONES ============
if ($action === 'listar_evaluaciones') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($data['servicio_id'])) {
        $where[] = "e.servicio_id = ?";
        $params[] = (int)$data['servicio_id'];
    }
    
    if ($_SESSION['user_rol'] === 'Trabajadora Social') {
        $where[] = "s.trabajador_social_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $sql = "SELECT e.*, s.nombre as servicio, u.nombre as estudiante
            FROM evaluaciones e 
            JOIN servicios s ON e.servicio_id = s.id 
            JOIN usuarios u ON e.estudiante_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY e.fecha_evaluacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $evaluaciones = $stmt->fetchAll();
    
    responder(['success' => true, 'evaluaciones' => $evaluaciones]);
}

// ============ SERVICIOS PENDIENTES DE EVALUAR ============
if ($action === 'servicios_pendientes_evaluar') {
    validarSesion();
    
    $sql = "SELECT DISTINCT s.*, c.fecha as fecha_servicio
            FROM servicios s
            JOIN citas c ON s.id = c.servicio_id
            LEFT JOIN evaluaciones e ON (s.id = e.servicio_id AND e.estudiante_id = ?)
            WHERE c.estudiante_id = ? 
            AND c.estado = 'completada'
            AND e.id IS NULL
            ORDER BY c.fecha DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $servicios = $stmt->fetchAll();
    
    responder(['success' => true, 'servicios' => $servicios]);
}

responder(['success' => false, 'message' => 'Acción no válida'], 400);