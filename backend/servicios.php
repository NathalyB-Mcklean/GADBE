<?php
/**
 * servicios.php
 * Gestión de servicios y ofertas de bienestar estudiantil
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ LISTAR SERVICIOS ============
if ($action === 'listar_servicios') {
    $where = ["1=1"];
    $params = [];
    
    // Filtros opcionales
    if (!empty($data['tipo'])) {
        $where[] = "s.tipo = ?";
        $params[] = $data['tipo'];
    }
    
    if (!empty($data['categoria'])) {
        $where[] = "c.nombre = ?";
        $params[] = $data['categoria'];
    }
    
    if (!empty($data['estado'])) {
        $where[] = "s.estado = ?";
        $params[] = $data['estado'];
    }
    
    if (!empty($data['busqueda'])) {
        $where[] = "(s.nombre LIKE ? OR s.descripcion LIKE ?)";
        $busqueda = "%{$data['busqueda']}%";
        $params[] = $busqueda;
        $params[] = $busqueda;
    }
    
    $sql = "SELECT s.*, c.nombre as categoria, u.nombre as trabajador 
            FROM servicios s 
            JOIN categorias c ON s.categoria_id = c.id 
            JOIN usuarios u ON s.trabajador_social_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY s.fecha_publicacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $servicios = $stmt->fetchAll();
    
    responder(['success' => true, 'servicios' => $servicios]);
}

// ============ CREAR SERVICIO ============
if ($action === 'crear_servicio') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    validarCSRF();
    
    // Validar campos requeridos
    $campos_requeridos = ['nombre', 'categoria_id', 'tipo'];
    foreach ($campos_requeridos as $campo) {
        if (empty($data[$campo])) {
            responder(['success' => false, 'message' => "Campo '{$campo}' es obligatorio"], 400);
        }
    }
    
    $nombre = sanitizar($data['nombre']);
    $descripcion = sanitizar($data['descripcion'] ?? '');
    $categoria_id = (int)$data['categoria_id'];
    $tipo = $data['tipo'];
    $fecha_limite = $data['fecha_limite'] ?? null;
    $trabajador_social_id = $_SESSION['user_id'];
    
    // Validar que no exista servicio con mismo nombre
    $stmt = $pdo->prepare("SELECT id FROM servicios WHERE nombre = ?");
    $stmt->execute([$nombre]);
    if ($stmt->fetch()) {
        responder(['success' => false, 'message' => 'Ya existe un servicio con ese nombre'], 409);
    }
    
    // Validar fecha límite si se proporciona
    if ($fecha_limite && !validarFecha($fecha_limite)) {
        responder(['success' => false, 'message' => 'Fecha límite inválida'], 400);
    }
    
    // Insertar servicio
    $stmt = $pdo->prepare(
        "INSERT INTO servicios (nombre, descripcion, categoria_id, tipo, trabajador_social_id, fecha_limite, estado, fecha_publicacion) 
         VALUES (?, ?, ?, ?, ?, ?, 'activo', NOW())"
    );
    
    $stmt->execute([
        $nombre,
        $descripcion,
        $categoria_id,
        $tipo,
        $trabajador_social_id,
        $fecha_limite
    ]);
    
    $servicio_id = $pdo->lastInsertId();
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_servicio', 'servicios', $servicio_id, [
        'nombre' => $nombre,
        'tipo' => $tipo
    ]);
    
    responder([
        'success' => true,
        'message' => 'Servicio creado exitosamente',
        'id' => $servicio_id
    ], 201);
}

// ============ MODIFICAR SERVICIO ============
if ($action === 'modificar_servicio') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    validarCSRF();
    
    $servicio_id = (int)($data['id'] ?? 0);
    
    if (!$servicio_id) {
        responder(['success' => false, 'message' => 'ID de servicio requerido'], 400);
    }
    
    // Verificar que el servicio existe y el usuario tiene permiso
    $stmt = $pdo->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmt->execute([$servicio_id]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        responder(['success' => false, 'message' => 'Servicio no encontrado'], 404);
    }
    
    // Solo el trabajador social asignado o un administrador pueden modificar
    if ($_SESSION['user_rol'] !== 'Administrador' && $servicio['trabajador_social_id'] != $_SESSION['user_id']) {
        responder(['success' => false, 'message' => 'Sin permiso para modificar este servicio'], 403);
    }
    
    // Actualizar campos proporcionados
    $campos_actualizar = [];
    $params = [];
    
    if (isset($data['nombre'])) {
        $campos_actualizar[] = "nombre = ?";
        $params[] = sanitizar($data['nombre']);
    }
    
    if (isset($data['descripcion'])) {
        $campos_actualizar[] = "descripcion = ?";
        $params[] = sanitizar($data['descripcion']);
    }
    
    if (isset($data['categoria_id'])) {
        $campos_actualizar[] = "categoria_id = ?";
        $params[] = (int)$data['categoria_id'];
    }
    
    if (isset($data['estado'])) {
        $campos_actualizar[] = "estado = ?";
        $params[] = $data['estado'];
    }
    
    if (isset($data['fecha_limite'])) {
        if ($data['fecha_limite'] && !validarFecha($data['fecha_limite'])) {
            responder(['success' => false, 'message' => 'Fecha límite inválida'], 400);
        }
        $campos_actualizar[] = "fecha_limite = ?";
        $params[] = $data['fecha_limite'];
    }
    
    if (empty($campos_actualizar)) {
        responder(['success' => false, 'message' => 'No hay campos para actualizar'], 400);
    }
    
    $params[] = $servicio_id;
    
    $sql = "UPDATE servicios SET " . implode(', ', $campos_actualizar) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'modificar_servicio', 'servicios', $servicio_id, [
        'campos_modificados' => array_keys($data)
    ]);
    
    responder(['success' => true, 'message' => 'Servicio actualizado exitosamente']);
}

// ============ ELIMINAR SERVICIO ============
if ($action === 'eliminar_servicio') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    validarCSRF();
    
    $servicio_id = (int)($data['id'] ?? 0);
    
    if (!$servicio_id) {
        responder(['success' => false, 'message' => 'ID de servicio requerido'], 400);
    }
    
    // Verificar que el servicio existe
    $stmt = $pdo->prepare("SELECT * FROM servicios WHERE id = ?");
    $stmt->execute([$servicio_id]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        responder(['success' => false, 'message' => 'Servicio no encontrado'], 404);
    }
    
    // Verificar permiso
    if ($_SESSION['user_rol'] !== 'Administrador' && $servicio['trabajador_social_id'] != $_SESSION['user_id']) {
        responder(['success' => false, 'message' => 'Sin permiso para eliminar este servicio'], 403);
    }
    
    // Verificar que no tenga citas activas
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total FROM citas 
         WHERE servicio_id = ? 
         AND estado IN ('pendiente', 'confirmada')"
    );
    $stmt->execute([$servicio_id]);
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        responder([
            'success' => false,
            'message' => "No se puede eliminar. El servicio tiene {$result['total']} citas activas"
        ], 409);
    }
    
    // Eliminar servicio
    $stmt = $pdo->prepare("DELETE FROM servicios WHERE id = ?");
    $stmt->execute([$servicio_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'eliminar_servicio', 'servicios', $servicio_id, [
        'nombre' => $servicio['nombre']
    ]);
    
    responder(['success' => true, 'message' => 'Servicio eliminado exitosamente']);
}

// ============ LISTAR CATEGORÍAS ============
if ($action === 'listar_categorias') {
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nombre");
    $categorias = $stmt->fetchAll();
    
    responder(['success' => true, 'categorias' => $categorias]);
}

// ============ LISTAR TRABAJADORES SOCIALES ============
if ($action === 'listar_trabajadores') {
    verificarPermiso(['Trabajadora Social', 'Administrador']);
    
    $stmt = $pdo->prepare(
        "SELECT id, nombre, correo FROM usuarios 
         WHERE rol IN ('Trabajadora Social', 'Administrador') 
         AND activo = 1 
         ORDER BY nombre"
    );
    $stmt->execute();
    $trabajadores = $stmt->fetchAll();
    
    responder(['success' => true, 'trabajadores' => $trabajadores]);
}

// ============ OBTENER DETALLES DE SERVICIO ============
if ($action === 'detalle_servicio') {
    $servicio_id = (int)($_GET['id'] ?? 0);
    
    if (!$servicio_id) {
        responder(['success' => false, 'message' => 'ID de servicio requerido'], 400);
    }
    
    $stmt = $pdo->prepare(
        "SELECT s.*, c.nombre as categoria, u.nombre as trabajador, u.correo as trabajador_correo
         FROM servicios s 
         JOIN categorias c ON s.categoria_id = c.id 
         JOIN usuarios u ON s.trabajador_social_id = u.id 
         WHERE s.id = ?"
    );
    $stmt->execute([$servicio_id]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        responder(['success' => false, 'message' => 'Servicio no encontrado'], 404);
    }
    
    responder(['success' => true, 'servicio' => $servicio]);
}

// Acción no válida
responder(['success' => false, 'message' => 'Acción no válida'], 400);