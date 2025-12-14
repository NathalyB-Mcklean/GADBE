<?php
/**
 * auth.php
 * Manejo de autenticación (login, registro, logout, recuperación)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============ LOGIN ============
if ($action === 'login') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';
    
    // Validar campos requeridos
    if (empty($correo) || empty($password)) {
        responder(['success' => false, 'message' => 'Correo y contraseña son obligatorios'], 400);
    }
    
    // Validar dominio UTP
    if (!esCorreoUTP($correo)) {
        responder(['success' => false, 'message' => 'Solo correos institucionales UTP (@utp.ac.pa)'], 403);
    }
    
    // Buscar usuario
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        registrarAuditoria($pdo, null, 'login_failed', 'usuarios', null, ['correo' => $correo, 'razon' => 'usuario_no_existe']);
        responder(['success' => false, 'message' => 'Credenciales UTP inválidas'], 401);
    }
    
    // Verificar si cuenta está bloqueada
    if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
        $minutos = ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
        registrarAuditoria($pdo, $usuario['id'], 'login_blocked', 'usuarios', $usuario['id']);
        responder(['success' => false, 'message' => "Cuenta bloqueada. Intente en {$minutos} minutos"], 403);
    }
    
    // Verificar contraseña
    if (!password_verify($password, $usuario['password_hash'])) {
        $intentos = $usuario['intentos_fallidos'] + 1;
        
        // Bloquear después de MAX_LOGIN_ATTEMPTS intentos
        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
            $bloqueado_hasta = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            $stmt = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?");
            $stmt->execute([$intentos, $bloqueado_hasta, $usuario['id']]);
            
            registrarAuditoria($pdo, $usuario['id'], 'account_locked', 'usuarios', $usuario['id']);
            responder(['success' => false, 'message' => 'Cuenta bloqueada por 30 minutos por seguridad'], 403);
        }
        
        // Incrementar intentos fallidos
        $stmt = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?");
        $stmt->execute([$intentos, $usuario['id']]);
        
        $restantes = MAX_LOGIN_ATTEMPTS - $intentos;
        registrarAuditoria($pdo, $usuario['id'], 'login_failed', 'usuarios', $usuario['id'], ['intentos' => $intentos]);
        responder(['success' => false, 'message' => "Credenciales incorrectas. Intentos restantes: {$restantes}"], 401);
    }
    
    // Login exitoso
    // Resetear intentos y actualizar última sesión
    $stmt = $pdo->prepare(
        "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultima_sesion = NOW() WHERE id = ?"
    );
    $stmt->execute([$usuario['id']]);
    
    // Regenerar session ID para prevenir session fixation
    session_regenerate_id(true);
    
    // Establecer variables de sesión
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_rol'] = $usuario['rol'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['user_correo'] = $usuario['correo'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = time();
    
    registrarAuditoria($pdo, $usuario['id'], 'login', 'usuarios', $usuario['id']);
    
    responder([
        'success' => true,
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'correo' => $usuario['correo'],
            'rol' => $usuario['rol']
        ],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

// ============ LOGOUT ============
if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        registrarAuditoria($pdo, $_SESSION['user_id'], 'logout', 'usuarios', $_SESSION['user_id']);
    }
    
    session_destroy();
    responder(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
}

// ============ REGISTRAR ============
if ($action === 'registrar') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';
    $password_confirmation = $data['password_confirmation'] ?? '';
    $nombre = sanitizar($data['nombre'] ?? '');
    
    // Validar campos requeridos
    if (empty($correo) || empty($password) || empty($nombre)) {
        responder(['success' => false, 'message' => 'Todos los campos son obligatorios'], 400);
    }
    
    // Validar dominio UTP
    if (!esCorreoUTP($correo)) {
        responder(['success' => false, 'message' => 'Solo correos institucionales UTP (@utp.ac.pa)'], 403);
    }
    
    // Validar coincidencia de contraseñas
    if ($password !== $password_confirmation) {
        responder(['success' => false, 'message' => 'Las contraseñas no coinciden'], 400);
    }
    
    // Validar fuerza de contraseña
    $errores_password = validarPassword($password);
    if (!empty($errores_password)) {
        responder(['success' => false, 'message' => implode('. ', $errores_password)], 400);
    }
    
    // Verificar si el correo ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    if ($stmt->fetch()) {
        responder(['success' => false, 'message' => 'Este correo ya está registrado'], 409);
    }
    
    // Crear usuario
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (correo, password_hash, nombre, rol, activo, fecha_registro) 
         VALUES (?, ?, ?, 'Estudiante', 1, NOW())"
    );
    $stmt->execute([$correo, $password_hash, $nombre]);
    
    $usuario_id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $usuario_id, 'registro', 'usuarios', $usuario_id);
    
    responder(['success' => true, 'message' => 'Cuenta creada exitosamente', 'usuario_id' => $usuario_id], 201);
}

// ============ VERIFICAR SESIÓN ============
if ($action === 'verificar_sesion') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'authenticated' => false], 401);
    }
    
    // Verificar timeout
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            responder(['success' => false, 'authenticated' => false, 'message' => 'Sesión expirada'], 401);
        }
    }
    
    $_SESSION['last_activity'] = time();
    
    responder([
        'success' => true,
        'authenticated' => true,
        'usuario' => [
            'id' => $_SESSION['user_id'],
            'nombre' => $_SESSION['user_nombre'],
            'correo' => $_SESSION['user_correo'],
            'rol' => $_SESSION['user_rol']
        ],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

// ============ SOLICITAR RECUPERACIÓN DE CONTRASEÑA ============
if ($action === 'recuperar_password') {
    $correo = $data['correo'] ?? '';
    
    if (empty($correo)) {
        responder(['success' => false, 'message' => 'Correo requerido'], 400);
    }
    
    if (!esCorreoUTP($correo)) {
        responder(['success' => false, 'message' => 'Solo correos institucionales UTP'], 403);
    }
    
    // Buscar usuario (no revelar si existe o no)
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE correo = ? AND activo = 1");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + 3600); // 1 hora
        
        // Guardar token
        $stmt = $pdo->prepare(
            "UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?"
        );
        $stmt->execute([$token, $expira, $usuario['id']]);
        
        // Enviar email
        $url_reset = "https://bienestar.utp.ac.pa/reset-password.html?token={$token}";
        $mensaje = "Hola {$usuario['nombre']},<br><br>" .
                   "Has solicitado restablecer tu contraseña.<br>" .
                   "Haz clic en el siguiente enlace para continuar:<br><br>" .
                   "<a href='{$url_reset}'>{$url_reset}</a><br><br>" .
                   "Este enlace expira en 1 hora.<br><br>" .
                   "Si no solicitaste este cambio, ignora este mensaje.";
        
        enviarEmail($correo, 'Recuperación de contraseña - Bienestar UTP', $mensaje);
        
        registrarAuditoria($pdo, $usuario['id'], 'password_reset_requested', 'usuarios', $usuario['id']);
    }
    
    // Siempre responder igual para no revelar si el usuario existe
    responder([
        'success' => true,
        'message' => 'Si el correo existe en nuestro sistema, recibirás instrucciones de recuperación'
    ]);
}

// ============ RESTABLECER CONTRASEÑA ============
if ($action === 'reset_password') {
    $token = $data['token'] ?? '';
    $nueva_password = $data['password'] ?? '';
    $password_confirmation = $data['password_confirmation'] ?? '';
    
    if (empty($token) || empty($nueva_password)) {
        responder(['success' => false, 'message' => 'Datos incompletos'], 400);
    }
    
    if ($nueva_password !== $password_confirmation) {
        responder(['success' => false, 'message' => 'Las contraseñas no coinciden'], 400);
    }
    
    // Validar fuerza de contraseña
    $errores = validarPassword($nueva_password);
    if (!empty($errores)) {
        responder(['success' => false, 'message' => implode('. ', $errores)], 400);
    }
    
    // Buscar token válido
    $stmt = $pdo->prepare(
        "SELECT id FROM usuarios 
         WHERE reset_token = ? 
         AND reset_token_expira > NOW() 
         AND activo = 1"
    );
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        responder(['success' => false, 'message' => 'Token inválido o expirado'], 400);
    }
    
    // Actualizar contraseña
    $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "UPDATE usuarios 
         SET password_hash = ?, reset_token = NULL, reset_token_expira = NULL 
         WHERE id = ?"
    );
    $stmt->execute([$password_hash, $usuario['id']]);
    
    registrarAuditoria($pdo, $usuario['id'], 'password_reset_completed', 'usuarios', $usuario['id']);
    
    responder(['success' => true, 'message' => 'Contraseña actualizada exitosamente']);
}

// Acción no válida
responder(['success' => false, 'message' => 'Acción no válida'], 400);