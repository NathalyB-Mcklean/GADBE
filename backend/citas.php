<?php
/**
 * citas.php
 * Gestión de citas para servicios de bienestar
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ PROGRAMAR CITA ============
if ($action === 'programar_cita') {
    validarSesion();
    validarCSRF();
    
    // Validar campos requeridos
    $campos_requeridos = ['servicio_id', 'fecha', 'hora'];
    foreach ($campos_requeridos as $campo) {
        if (empty($data[$campo])) {
            responder(['success' => false, 'message' => "Campo '{$campo}' es obligatorio"], 400);
        }
    }
    
    $servicio_id = (int)$data['servicio_id'];
    $fecha = $data['fecha'];
    $hora = $data['hora'];
    $estudiante_id = $_SESSION['user_id'];
    $motivo = sanitizar($data['motivo'] ?? '');
    
    // Validar formato de fecha y hora
    if (!validarFecha($fecha)) {
        responder(['success' => false, 'message' => 'Formato de fecha inválido'], 400);
    }
    
    if (!validarHora($hora)) {
        responder(['success' => false, 'message' => 'Formato de hora inválido'], 400);
    }
    
    // Validar que la fecha no sea pasada
    if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
        responder(['success' => false, 'message' => 'No es posible programar citas en fechas pasadas'], 400);
    }
    
    // Verificar que el servicio existe y está activo
    $stmt = $pdo->prepare("SELECT * FROM servicios WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$servicio_id]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        responder(['success' => false, 'message' => 'Servicio no encontrado o inactivo'], 404);
    }
    
    // Usar transacción para evitar race conditions
    $pdo->beginTransaction();
    
    try {
        // Verificar disponibilidad con lock
        $stmt = $pdo->prepare(
            "SELECT id FROM citas 
             WHERE servicio_id = ? 
             AND fecha = ? 
             AND hora = ? 
             AND estado IN ('pendiente', 'confirmada') 
             FOR UPDATE"
        );
        $stmt->execute([$servicio_id, $fecha, $hora]);
        
        if ($stmt->fetch()) {
            $pdo->rollBack();
            responder([
                'success' => false,
                'message' => 'El horario seleccionado ya no está disponible. Por favor, elija otro horario'
            ], 409);
        }
        
        // Crear cita
        $stmt = $pdo->prepare(
            "INSERT INTO citas (estudiante_id, servicio_id, fecha, hora, motivo, estado, fecha_creacion) 
             VALUES (?, ?, ?, ?, ?, 'confirmada', NOW())"
        );
        $stmt->execute([$estudiante_id, $servicio_id, $fecha, $hora, $motivo]);
        
        $cita_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        registrarAuditoria($pdo, $estudiante_id, 'programar_cita', 'citas', $cita_id, [
            'servicio_id' => $servicio_id,
            'fecha' => $fecha,
            'hora' => $hora
        ]);
        
        // Enviar confirmación por email
        $mensaje = "Tu cita ha sido programada:<br><br>" .
                   "Servicio: {$servicio['nombre']}<br>" .
                   "Fecha: {$fecha}<br>" .
                   "Hora: {$hora}<br><br>" .
                   "Comprobante: #CITA-{$cita_id}";
        
        enviarEmail($_SESSION['user_correo'], 'Cita confirmada - Bienestar UTP', $mensaje);
        
        responder([
            'success' => true,
            'message' => 'Cita programada exitosamente',
            'cita_id' => $cita_id,
            'comprobante' => "CITA-{$cita_id}"
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al programar cita: " . $e->getMessage());
        responder(['success' => false, 'message' => 'Error al programar cita'], 500);
    }
}

// ============ LISTAR CITAS ============
if ($action === 'listar_citas') {
    validarSesion();
    
    $where = ["1=1"];
    $params = [];
    
    // Filtrar por rol
    if ($_SESSION['user_rol'] === 'Estudiante') {
        $where[] = "c.estudiante_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['user_rol'] === 'Trabajadora Social') {
        $where[] = "s.trabajador_social_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    // Administrador ve todas las citas
    
    // Filtros adicionales
    if (!empty($data['estado'])) {
        $where[] = "c.estado = ?";
        $params[] = $data['estado'];
    }
    
    if (!empty($data['fecha'])) {
        $where[] = "c.fecha = ?";
        $params[] = $data['fecha'];
    }
    
    $sql = "SELECT c.*, s.nombre as servicio, u.nombre as estudiante, u.correo as estudiante_correo
            FROM citas c 
            JOIN servicios s ON c.servicio_id = s.id 
            JOIN usuarios u ON c.estudiante_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY c.fecha DESC, c.hora DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll();
    
    responder(['success' => true, 'citas' => $citas]);
}

// ============ MODIFICAR CITA ============
if ($action === 'modificar_cita') {
    validarSesion();
    validarCSRF();
    
    $cita_id = (int)($data['id'] ?? 0);
    
    if (!$cita_id) {
        responder(['success' => false, 'message' => 'ID de cita requerido'], 400);
    }
    
    // Obtener cita actual
    $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
    $stmt->execute([$cita_id]);
    $cita = $stmt->fetch();
    
    if (!$cita) {
        responder(['success' => false, 'message' => 'Cita no encontrada'], 404);
    }
    
    // Verificar permisos
    if ($_SESSION['user_rol'] === 'Estudiante' && $cita['estudiante_id'] != $_SESSION['user_id']) {
        responder(['success' => false, 'message' => 'Sin permiso para modificar esta cita'], 403);
    }
    
    $nueva_fecha = $data['fecha'] ?? $cita['fecha'];
    $nueva_hora = $data['hora'] ?? $cita['hora'];
    
    // Validar formatos
    if (!validarFecha($nueva_fecha)) {
        responder(['success' => false, 'message' => 'Formato de fecha inválido'], 400);
    }
    
    if (!validarHora($nueva_hora)) {
        responder(['success' => false, 'message' => 'Formato de hora inválido'], 400);
    }
    
    // Validar que no sea fecha pasada
    if (strtotime($nueva_fecha) < strtotime(date('Y-m-d'))) {
        responder(['success' => false, 'message' => 'No es posible modificar a fechas pasadas'], 400);
    }
    
    // Si cambió fecha u hora, verificar disponibilidad
    if ($nueva_fecha != $cita['fecha'] || $nueva_hora != $cita['hora']) {
        $stmt = $pdo->prepare(
            "SELECT id FROM citas 
             WHERE servicio_id = ? 
             AND fecha = ? 
             AND hora = ? 
             AND estado IN ('pendiente', 'confirmada')
             AND id != ?"
        );
        $stmt->execute([$cita['servicio_id'], $nueva_fecha, $nueva_hora, $cita_id]);
        
        if ($stmt->fetch()) {
            responder(['success' => false, 'message' => 'El nuevo horario no está disponible'], 409);
        }
    }
    
    // Actualizar cita
    $stmt = $pdo->prepare(
        "UPDATE citas SET fecha = ?, hora = ? WHERE id = ?"
    );
    $stmt->execute([$nueva_fecha, $nueva_hora, $cita_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'modificar_cita', 'citas', $cita_id, [
        'fecha_anterior' => $cita['fecha'],
        'hora_anterior' => $cita['hora'],
        'fecha_nueva' => $nueva_fecha,
        'hora_nueva' => $nueva_hora
    ]);
    
    // Enviar notificación
    $mensaje = "Tu cita ha sido modificada:<br><br>" .
               "Nueva fecha: {$nueva_fecha}<br>" .
               "Nueva hora: {$nueva_hora}<br>";
    
    enviarEmail($_SESSION['user_correo'], 'Cita modificada - Bienestar UTP', $mensaje);
    
    responder(['success' => true, 'message' => 'Cita modificada exitosamente']);
}

// ============ CANCELAR CITA ============
if ($action === 'cancelar_cita') {
    validarSesion();
    validarCSRF();
    
    $cita_id = (int)($data['id'] ?? 0);
    
    if (!$cita_id) {
        responder(['success' => false, 'message' => 'ID de cita requerido'], 400);
    }
    
    // Obtener cita
    $stmt = $pdo->prepare("SELECT * FROM citas WHERE id = ?");
    $stmt->execute([$cita_id]);
    $cita = $stmt->fetch();
    
    if (!$cita) {
        responder(['success' => false, 'message' => 'Cita no encontrada'], 404);
    }
    
    // Verificar permisos
    if ($_SESSION['user_rol'] === 'Estudiante' && $cita['estudiante_id'] != $_SESSION['user_id']) {
        responder(['success' => false, 'message' => 'Sin permiso para cancelar esta cita'], 403);
    }
    
    // Actualizar estado
    $motivo_cancelacion = sanitizar($data['motivo_cancelacion'] ?? 'No especificado');
    
    $stmt = $pdo->prepare(
        "UPDATE citas 
         SET estado = 'cancelada', 
             motivo_cancelacion = ?, 
             fecha_cancelacion = NOW(),
             cancelado_por = ?
         WHERE id = ?"
    );
    $stmt->execute([$motivo_cancelacion, $_SESSION['user_id'], $cita_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'cancelar_cita', 'citas', $cita_id, [
        'motivo' => $motivo_cancelacion
    ]);
    
    // Enviar notificación
    $mensaje = "Tu cita ha sido cancelada:<br><br>" .
               "Comprobante: #CITA-{$cita_id}<br>" .
               "Motivo: {$motivo_cancelacion}";
    
    enviarEmail($_SESSION['user_correo'], 'Cita cancelada - Bienestar UTP', $mensaje);
    
    responder(['success' => true, 'message' => 'Cita cancelada exitosamente']);
}

// ============ COMPLETAR CITA (Solo trabajadora social) ============
if ($action === 'completar_cita') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    validarCSRF();
    
    $cita_id = (int)($data['id'] ?? 0);
    
    if (!$cita_id) {
        responder(['success' => false, 'message' => 'ID de cita requerido'], 400);
    }
    
    $notas = sanitizar($data['notas'] ?? '');
    
    $stmt = $pdo->prepare(
        "UPDATE citas 
         SET estado = 'completada', 
             notas_trabajador = ?, 
             fecha_completada = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$notas, $cita_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'completar_cita', 'citas', $cita_id);
    
    responder(['success' => true, 'message' => 'Cita marcada como completada']);
}

// ============ OBTENER HORARIOS DISPONIBLES ============
if ($action === 'horarios_disponibles') {
    $servicio_id = (int)($_GET['servicio_id'] ?? 0);
    $fecha = $_GET['fecha'] ?? '';
    
    if (!$servicio_id || !$fecha) {
        responder(['success' => false, 'message' => 'Servicio y fecha requeridos'], 400);
    }
    
    if (!validarFecha($fecha)) {
        responder(['success' => false, 'message' => 'Formato de fecha inválido'], 400);
    }
    
    // Obtener horarios ocupados
    $stmt = $pdo->prepare(
        "SELECT hora FROM citas 
         WHERE servicio_id = ? 
         AND fecha = ? 
         AND estado IN ('pendiente', 'confirmada')"
    );
    $stmt->execute([$servicio_id, $fecha]);
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generar horarios disponibles (8:00 AM - 4:00 PM, cada hora)
    $horarios = [];
    for ($h = 8; $h <= 16; $h++) {
        $hora = sprintf('%02d:00:00', $h);
        if (!in_array($hora, $ocupados)) {
            $horarios[] = [
                'hora' => $hora,
                'hora_display' => sprintf('%02d:00', $h),
                'disponible' => true
            ];
        }
    }
    
    responder(['success' => true, 'horarios' => $horarios]);
}

// Acción no válida
responder(['success' => false, 'message' => 'Acción no válida'], 400);