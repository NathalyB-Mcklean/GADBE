<?php
/**
 * helpers.php
 * Funciones auxiliares reutilizables
 */

/**
 * Responder con JSON
 */
function responder($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Validar token CSRF
 */
function validarCSRF() {
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
    
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        responder(['success' => false, 'message' => 'Token CSRF inválido'], 403);
    }
}

/**
 * Generar token CSRF
 */
function generarCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar correo institucional UTP
 */
function esCorreoUTP($correo) {
    return preg_match('/@utp\.ac\.pa$/', $correo) === 1;
}

/**
 * Validar fuerza de contraseña
 */
function validarPassword($password) {
    $errores = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errores[] = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos una mayúscula';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos una minúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos un número';
    }
    
    if (!preg_match('/[@#$%^&*()_+\-=\[\]{};:"|,.<>\/?]/', $password)) {
        $errores[] = 'La contraseña debe contener al menos un carácter especial';
    }
    
    return $errores;
}

/**
 * Sanitizar entrada de texto
 */
function sanitizar($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar sesión activa
 */
function validarSesion() {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'message' => 'Sesión no válida'], 401);
    }
    
    // Validar timeout de sesión
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            responder(['success' => false, 'message' => 'Sesión expirada'], 401);
        }
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Verificar permisos de rol
 */
function verificarPermiso($roles_permitidos) {
    validarSesion();
    
    if (!in_array($_SESSION['user_rol'], $roles_permitidos)) {
        responder(['success' => false, 'message' => 'Sin permisos para esta acción'], 403);
    }
}

/**
 * Registrar en auditoría
 */
function registrarAuditoria($pdo, $usuario_id, $accion, $tabla = null, $registro_id = null, $detalles = null) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $usuario_id,
            $accion,
            $tabla,
            $registro_id,
            $detalles ? json_encode($detalles) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch(PDOException $e) {
        error_log("Error en auditoría: " . $e->getMessage());
    }
}

/**
 * Validar formato de fecha
 */
function validarFecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

/**
 * Validar formato de hora
 */
function validarHora($hora) {
    $h = DateTime::createFromFormat('H:i:s', $hora);
    return $h && $h->format('H:i:s') === $hora;
}

/**
 * Enviar email (implementación básica)
 */
function enviarEmail($destinatario, $asunto, $mensaje) {
    // TODO: Implementar con librería como PHPMailer o servicio SMTP
    $headers = 'From: bienestar@utp.ac.pa' . "\r\n" .
               'Reply-To: bienestar@utp.ac.pa' . "\r\n" .
               'X-Mailer: PHP/' . phpversion() . "\r\n" .
               'Content-Type: text/html; charset=UTF-8';
    
    return mail($destinatario, $asunto, $mensaje, $headers);
}

/**
 * Obtener IP real del cliente
 */
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Validar archivo subido
 */
function validarArchivo($archivo, $tipos_permitidos = ['pdf', 'jpg', 'png'], $tamano_max = 5242880) {
    $errores = [];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir el archivo';
        return $errores;
    }
    
    // Validar tamaño (5MB por defecto)
    if ($archivo['size'] > $tamano_max) {
        $errores[] = 'El archivo excede el tamaño máximo de ' . ($tamano_max / 1048576) . 'MB';
    }
    
    // Validar tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $tipos_permitidos)) {
        $errores[] = 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $tipos_permitidos);
    }
    
    if (!in_array($mime, array_values($mimes_permitidos))) {
        $errores[] = 'El contenido del archivo no es válido';
    }
    
    return $errores;
}