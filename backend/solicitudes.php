<?php
/**
 * solicitudes.php
 * Gestión de solicitudes de servicios
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ CREAR SOLICITUD ============
if ($action === 'crear_solicitud') {
    validarSesion();
    validarCSRF();
    
    // Validar campos requeridos
    if (empty($data['servicio_id']) || empty($data['motivo'])) {
        responder(['success' => false, 'message' => 'Servicio y motivo son obligatorios'], 400);
    }
    
    $servicio_id = (int)$data['servicio_id'];
    $motivo = sanitizar($data['motivo']);
    $estudiante_id = $_SESSION['user_id'];
    
    // Verificar límite de solicitudes activas
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total FROM solicitudes 
         WHERE estudiante_id = ? 
         AND estado IN ('pendiente', 'en_revision')"
    );
    $stmt->execute([$estudiante_id]);
    $result = $stmt->fetch();
    
    if ($result['total'] >= MAX_SOLICITUDES_ACTIVAS) {
        responder([
            'success' => false,
            'message' => 'Límite de ' . MAX_SOLICITUDES_ACTIVAS . ' solicitudes activas alcanzado'
        ], 409);
    }
    
    // Verificar que el servicio existe
    $stmt = $pdo->prepare("SELECT id FROM servicios WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$servicio_id]);
    if (!$stmt->fetch()) {
        responder(['success' => false, 'message' => 'Servicio no encontrado o inactivo'], 404);
    }
    
    // Crear solicitud
    $stmt = $pdo->prepare(
        "INSERT INTO solicitudes (estudiante_id, servicio_id, motivo, estado, fecha_solicitud) 
         VALUES (?, ?, ?, 'pendiente', NOW())"
    );
    $stmt->execute([$estudiante_id, $servicio_id, $motivo]);
    
    $solicitud_id = $pdo->lastInsertId();
    
    registrarAuditoria($pdo, $estudiante_id, 'crear_solicitud', 'solicitudes', $solicitud_id, [
        'servicio_id' => $servicio_id
    ]);
    
    // Enviar notificación
    $stmt = $pdo->prepare("SELECT nombre FROM servicios WHERE id = ?");
    $stmt->execute([$servicio_id]);
    $servicio = $stmt->fetch();
    
    $mensaje = "Tu solicitud ha sido enviada:<br><br>" .
               "Servicio: {$servicio['nombre']}<br>" .
               "Comprobante: #SOL-{$solicitud_id}<br><br>" .
               "Será revisada por el personal de bienestar estudiantil.";
    
    enviarEmail($_SESSION['user_correo'], 'Solicitud recibida - Bienestar UTP', $mensaje);
    
    responder([
        'success' => true,
        'message' => 'Solicitud creada exitosamente',
        'id' => $solicitud_id,
        'comprobante' => "SOL-{$solicitud_id}"
    ], 201);
}

// ============ LISTAR SOLICITUDES ============
if ($action === 'listar_solicitudes') {
    validarSesion();
    
    $where = ["1=1"];
    $params = [];
    
    // Filtrar por rol
    if ($_SESSION['user_rol'] === 'Estudiante') {
        $where[] = "s.estudiante_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['user_rol'] === 'Trabajadora Social') {
        // Ver solicitudes de servicios que maneja
        $where[] = "ser.trabajador_social_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    // Administrador ve todas
    
    // Filtros adicionales
    if (!empty($data['estado'])) {
        $where[] = "s.estado = ?";
        $params[] = $data['estado'];
    }
    
    if (!empty($data['servicio_id'])) {
        $where[] = "s.servicio_id = ?";
        $params[] = (int)$data['servicio_id'];
    }
    
    $sql = "SELECT s.*, ser.nombre as servicio, u.nombre as estudiante, u.correo as estudiante_correo,
                   rev.nombre as revisado_por_nombre
            FROM solicitudes s 
            JOIN servicios ser ON s.servicio_id = ser.id 
            JOIN usuarios u ON s.estudiante_id = u.id 
            LEFT JOIN usuarios rev ON s.revisado_por = rev.id
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY s.fecha_solicitud DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    responder(['success' => true, 'solicitudes' => $solicitudes]);
}

// ============ GESTIONAR SOLICITUD (Aprobar/Rechazar/Solicitar info) ============
if ($action === 'gestionar_solicitud') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    validarCSRF();
    
    $solicitud_id = (int)($data['id'] ?? 0);
    $accion = $data['accion'] ?? ''; // 'aprobar', 'rechazar', 'solicitar_info'
    $comentario = sanitizar($data['comentario'] ?? '');
    
    if (!$solicitud_id) {
        responder(['success' => false, 'message' => 'ID de solicitud requerido'], 400);
    }
    
    if (!in_array($accion, ['aprobar', 'rechazar', 'solicitar_info'])) {
        responder(['success' => false, 'message' => 'Acción inválida'], 400);
    }
    
    // Obtener solicitud
    $stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ?");
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        responder(['success' => false, 'message' => 'Solicitud no encontrada'], 404);
    }
    
    // Mapear acción a estado
    $estados = [
        'aprobar' => 'aprobada',
        'rechazar' => 'rechazada',
        'solicitar_info' => 'en_revision'
    ];
    $nuevo_estado = $estados[$accion];
    
    // Actualizar solicitud
    $stmt = $pdo->prepare(
        "UPDATE solicitudes 
         SET estado = ?, 
             comentarios_trabajador = ?, 
             revisado_por = ?, 
             fecha_revision = NOW() 
         WHERE id = ?"
    );
    $stmt->execute([$nuevo_estado, $comentario, $_SESSION['user_id'], $solicitud_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'gestionar_solicitud', 'solicitudes', $solicitud_id, [
        'accion' => $accion,
        'estado_nuevo' => $nuevo_estado
    ]);
    
    // Enviar notificación al estudiante
    $stmt = $pdo->prepare("SELECT correo FROM usuarios WHERE id = ?");
    $stmt->execute([$solicitud['estudiante_id']]);
    $estudiante = $stmt->fetch();
    
    $mensajes = [
        'aprobar' => 'Tu solicitud ha sido APROBADA',
        'rechazar' => 'Tu solicitud ha sido RECHAZADA',
        'solicitar_info' => 'Se requiere información adicional para tu solicitud'
    ];
    
    $mensaje = "{$mensajes[$accion]}<br><br>" .
               "Comprobante: #SOL-{$solicitud_id}<br>" .
               "Comentarios: {$comentario}";
    
    enviarEmail($estudiante['correo'], 'Actualización de solicitud - Bienestar UTP', $mensaje);
    
    responder([
        'success' => true,
        'message' => "Solicitud {$accion} exitosamente",
        'nuevo_estado' => $nuevo_estado
    ]);
}

// ============ GUARDAR BORRADOR ============
if ($action === 'guardar_borrador') {
    validarSesion();
    
    $servicio_id = (int)($data['servicio_id'] ?? 0);
    $motivo = sanitizar($data['motivo'] ?? '');
    $estudiante_id = $_SESSION['user_id'];
    
    // Buscar si ya existe un borrador para este servicio
    $stmt = $pdo->prepare(
        "SELECT id FROM solicitudes 
         WHERE estudiante_id = ? 
         AND servicio_id = ? 
         AND estado = 'borrador'"
    );
    $stmt->execute([$estudiante_id, $servicio_id]);
    $borrador = $stmt->fetch();
    
    if ($borrador) {
        // Actualizar borrador existente
        $stmt = $pdo->prepare(
            "UPDATE solicitudes SET motivo = ? WHERE id = ?"
        );
        $stmt->execute([$motivo, $borrador['id']]);
        $solicitud_id = $borrador['id'];
    } else {
        // Crear nuevo borrador
        $stmt = $pdo->prepare(
            "INSERT INTO solicitudes (estudiante_id, servicio_id, motivo, estado, fecha_solicitud) 
             VALUES (?, ?, ?, 'borrador', NOW())"
        );
        $stmt->execute([$estudiante_id, $servicio_id, $motivo]);
        $solicitud_id = $pdo->lastInsertId();
    }
    
    responder([
        'success' => true,
        'message' => 'Borrador guardado exitosamente',
        'id' => $solicitud_id
    ]);
}

// ============ SUBIR DOCUMENTO ============
if ($action === 'subir_documento') {
    validarSesion();
    
    if (empty($_FILES['documento'])) {
        responder(['success' => false, 'message' => 'No se recibió ningún archivo'], 400);
    }
    
    $solicitud_id = (int)($_POST['solicitud_id'] ?? 0);
    
    if (!$solicitud_id) {
        responder(['success' => false, 'message' => 'ID de solicitud requerido'], 400);
    }
    
    // Verificar que la solicitud pertenece al usuario
    $stmt = $pdo->prepare("SELECT * FROM solicitudes WHERE id = ? AND estudiante_id = ?");
    $stmt->execute([$solicitud_id, $_SESSION['user_id']]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        responder(['success' => false, 'message' => 'Solicitud no encontrada o sin permiso'], 403);
    }
    
    // Validar archivo
    $archivo = $_FILES['documento'];
    $errores = validarArchivo($archivo, ['pdf', 'jpg', 'jpeg', 'png'], 5242880); // 5MB
    
    if (!empty($errores)) {
        responder(['success' => false, 'message' => implode('. ', $errores)], 400);
    }
    
    // Crear directorio si no existe
    $upload_dir = __DIR__ . '/../uploads/solicitudes/' . $solicitud_id;
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = uniqid() . '.' . $extension;
    $ruta_destino = $upload_dir . '/' . $nombre_archivo;
    
    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        responder(['success' => false, 'message' => 'Error al guardar archivo'], 500);
    }
    
    // Guardar referencia en BD
    $stmt = $pdo->prepare(
        "INSERT INTO documentos_solicitud (solicitud_id, nombre_original, nombre_archivo, ruta, tipo_mime, tamano, fecha_subida) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $solicitud_id,
        $archivo['name'],
        $nombre_archivo,
        $ruta_destino,
        $archivo['type'],
        $archivo['size']
    ]);
    
    $documento_id = $pdo->lastInsertId();
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'subir_documento', 'documentos_solicitud', $documento_id, [
        'solicitud_id' => $solicitud_id,
        'nombre_archivo' => $archivo['name']
    ]);
    
    responder([
        'success' => true,
        'message' => 'Documento subido exitosamente',
        'documento_id' => $documento_id,
        'nombre_archivo' => $nombre_archivo
    ], 201);
}

// ============ LISTAR DOCUMENTOS DE SOLICITUD ============
if ($action === 'listar_documentos') {
    validarSesion();
    
    $solicitud_id = (int)($_GET['solicitud_id'] ?? 0);
    
    if (!$solicitud_id) {
        responder(['success' => false, 'message' => 'ID de solicitud requerido'], 400);
    }
    
    // Verificar permiso
    $stmt = $pdo->prepare(
        "SELECT estudiante_id, servicio_id FROM solicitudes WHERE id = ?"
    );
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        responder(['success' => false, 'message' => 'Solicitud no encontrada'], 404);
    }
    
    // Solo el estudiante dueño, trabajadora social asignada o admin pueden ver documentos
    $tiene_permiso = false;
    if ($_SESSION['user_rol'] === 'Administrador') {
        $tiene_permiso = true;
    } elseif ($_SESSION['user_rol'] === 'Estudiante' && $solicitud['estudiante_id'] == $_SESSION['user_id']) {
        $tiene_permiso = true;
    } elseif ($_SESSION['user_rol'] === 'Trabajadora Social') {
        $stmt = $pdo->prepare("SELECT trabajador_social_id FROM servicios WHERE id = ?");
        $stmt->execute([$solicitud['servicio_id']]);
        $servicio = $stmt->fetch();
        if ($servicio['trabajador_social_id'] == $_SESSION['user_id']) {
            $tiene_permiso = true;
        }
    }
    
    if (!$tiene_permiso) {
        responder(['success' => false, 'message' => 'Sin permiso para ver documentos'], 403);
    }
    
    // Listar documentos
    $stmt = $pdo->prepare(
        "SELECT id, nombre_original, nombre_archivo, tipo_mime, tamano, fecha_subida 
         FROM documentos_solicitud 
         WHERE solicitud_id = ? 
         ORDER BY fecha_subida DESC"
    );
    $stmt->execute([$solicitud_id]);
    $documentos = $stmt->fetchAll();
    
    responder(['success' => true, 'documentos' => $documentos]);
}

// Acción no válida
responder(['success' => false, 'message' => 'Acción no válida'], 400);