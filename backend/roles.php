<?php
/**
 * roles.php
 * Gestión de roles y permisos de usuario
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ LISTAR ROLES Y PERMISOS ============
if ($action === 'listar_roles_permisos') {
    verificarPermiso(['Administrador']);
    
    $stmt = $pdo->query(
        "SELECT rp.*, p.nombre as permiso_nombre, p.modulo, p.descripcion as permiso_descripcion
         FROM roles_permisos rp 
         JOIN permisos p ON rp.permiso_id = p.id 
         ORDER BY rp.rol, p.modulo, p.nombre"
    );
    $roles_permisos = $stmt->fetchAll();
    
    responder(['success' => true, 'roles_permisos' => $roles_permisos]);
}

// ============ ASIGNAR ROL A USUARIO ============
if ($action === 'asignar_rol') {
    verificarPermiso(['Administrador']);
    validarCSRF();
    
    $usuario_id = (int)($data['usuario_id'] ?? 0);
    $rol = $data['rol'] ?? '';
    
    if (!$usuario_id || !$rol) {
        responder(['success' => false, 'message' => 'Usuario y rol son obligatorios'], 400);
    }
    
    $roles_validos = ['Estudiante', 'Trabajadora Social', 'Administrador'];
    if (!in_array($rol, $roles_validos)) {
        responder(['success' => false, 'message' => 'Rol inválido'], 400);
    }
    
    // Verificar que el usuario existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        responder(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }
    
    // Prevenir auto-eliminación de privilegios de administrador
    if ($usuario_id == $_SESSION['user_id'] && 
        $usuario['rol'] === 'Administrador' && 
        $rol !== 'Administrador') {
        responder([
            'success' => false,
            'message' => 'No puede modificar sus propios privilegios administrativos'
        ], 403);
    }
    
    // Actualizar rol
    $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
    $stmt->execute([$rol, $usuario_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'asignar_rol', 'usuarios', $usuario_id, [
        'rol_anterior' => $usuario['rol'],
        'rol_nuevo' => $rol
    ]);
    
    responder([
        'success' => true,
        'message' => 'Rol asignado exitosamente',
        'usuario' => [
            'id' => $usuario_id,
            'nombre' => $usuario['nombre'],
            'rol_anterior' => $usuario['rol'],
            'rol_nuevo' => $rol
        ]
    ]);
}

// ============ LISTAR USUARIOS CON ROLES ============
if ($action === 'listar_usuarios') {
    verificarPermiso(['Administrador']);
    
    $stmt = $pdo->query(
        "SELECT id, nombre, correo, rol, activo, fecha_registro, ultima_sesion
         FROM usuarios 
         ORDER BY rol, nombre"
    );
    $usuarios = $stmt->fetchAll();
    
    responder(['success' => true, 'usuarios' => $usuarios]);
}

// ============ ACTIVAR/DESACTIVAR USUARIO ============
if ($action === 'toggle_usuario') {
    verificarPermiso(['Administrador']);
    validarCSRF();
    
    $usuario_id = (int)($data['usuario_id'] ?? 0);
    
    if (!$usuario_id) {
        responder(['success' => false, 'message' => 'ID de usuario requerido'], 400);
    }
    
    // Prevenir auto-desactivación
    if ($usuario_id == $_SESSION['user_id']) {
        responder(['success' => false, 'message' => 'No puede desactivar su propia cuenta'], 403);
    }
    
    $stmt = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        responder(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }
    
    $nuevo_estado = $usuario['activo'] ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $usuario_id]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 
        $nuevo_estado ? 'activar_usuario' : 'desactivar_usuario', 
        'usuarios', $usuario_id
    );
    
    responder([
        'success' => true,
        'message' => 'Usuario ' . ($nuevo_estado ? 'activado' : 'desactivado'),
        'activo' => $nuevo_estado
    ]);
}

responder(['success' => false, 'message' => 'Acción no válida'], 400);