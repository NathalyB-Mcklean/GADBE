<?php
/**
 * estadisticas.php
 * Generación de estadísticas y reportes
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ GENERAR ESTADÍSTICAS ============
if ($action === 'generar_estadisticas') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    
    $fecha_inicio = $data['fecha_inicio'] ?? null;
    $fecha_fin = $data['fecha_fin'] ?? null;
    $categoria_id = !empty($data['categoria_id']) ? (int)$data['categoria_id'] : null;
    
    if (!$fecha_inicio || !$fecha_fin) {
        responder(['success' => false, 'message' => 'Rango de fechas es obligatorio'], 400);
    }
    
    if (!validarFecha($fecha_inicio) || !validarFecha($fecha_fin)) {
        responder(['success' => false, 'message' => 'Formato de fecha inválido'], 400);
    }
    
    $where = ["c.fecha BETWEEN ? AND ?"];
    $params = [$fecha_inicio, $fecha_fin];
    
    if ($categoria_id) {
        $where[] = "s.categoria_id = ?";
        $params[] = $categoria_id;
    }
    
    // Estadísticas de citas
    $sql_citas = "SELECT 
                    COUNT(*) as total_citas,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN c.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN c.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    AVG(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) * 100 as tasa_exito
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE " . implode(' AND ', $where);
    
    $stmt = $pdo->prepare($sql_citas);
    $stmt->execute($params);
    $stats_citas = $stmt->fetch();
    
    // Estadísticas de evaluaciones
    $sql_eval = "SELECT 
                   COUNT(*) as total_evaluaciones,
                   SUM(CASE WHEN calificacion = 'Excelente' THEN 1 ELSE 0 END) as excelente,
                   SUM(CASE WHEN calificacion = 'Bueno' THEN 1 ELSE 0 END) as bueno,
                   SUM(CASE WHEN calificacion = 'Regular' THEN 1 ELSE 0 END) as regular,
                   SUM(CASE WHEN calificacion = 'Malo' THEN 1 ELSE 0 END) as malo,
                   SUM(CASE WHEN calificacion = 'Muy Malo' THEN 1 ELSE 0 END) as muy_malo,
                   AVG(CASE 
                     WHEN calificacion = 'Excelente' THEN 5
                     WHEN calificacion = 'Bueno' THEN 4
                     WHEN calificacion = 'Regular' THEN 3
                     WHEN calificacion = 'Malo' THEN 2
                     WHEN calificacion = 'Muy Malo' THEN 1
                   END) as promedio_general
                 FROM evaluaciones e
                 JOIN servicios s ON e.servicio_id = s.id
                 JOIN citas c ON (e.servicio_id = c.servicio_id AND e.estudiante_id = c.estudiante_id)
                 WHERE " . implode(' AND ', $where);
    
    $stmt = $pdo->prepare($sql_eval);
    $stmt->execute($params);
    $stats_eval = $stmt->fetch();
    
    // Estadísticas de solicitudes
    $sql_sol = "SELECT 
                  COUNT(*) as total_solicitudes,
                  SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                  SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                  SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM solicitudes sol
                JOIN servicios s ON sol.servicio_id = s.id
                WHERE sol.fecha_solicitud BETWEEN ? AND ?" .
                ($categoria_id ? " AND s.categoria_id = ?" : "");
    
    $stmt = $pdo->prepare($sql_sol);
    $stmt->execute($params);
    $stats_sol = $stmt->fetch();
    
    responder([
        'success' => true,
        'citas' => $stats_citas,
        'evaluaciones' => $stats_eval,
        'solicitudes' => $stats_sol,
        'periodo' => [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ]
    ]);
}

// ============ EXPORTAR PDF (Requiere librería) ============
if ($action === 'exportar_pdf') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    
    // TODO: Implementar con TCPDF o Dompdf
    $filename = "reporte_" . date('Y-m-d_H-i-s') . ".pdf";
    
    responder([
        'success' => true,
        'message' => 'PDF generado',
        'filename' => $filename,
        'url' => '/descargas/' . $filename
    ]);
}

// ============ EXPORTAR EXCEL (Requiere librería) ============
if ($action === 'exportar_excel') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    
    // TODO: Implementar con PhpSpreadsheet
    $filename = "reporte_" . date('Y-m-d_H-i-s') . ".xlsx";
    
    responder([
        'success' => true,
        'message' => 'Excel generado',
        'filename' => $filename,
        'url' => '/descargas/' . $filename
    ]);
}

responder(['success' => false, 'message' => 'Acción no válida'], 400);