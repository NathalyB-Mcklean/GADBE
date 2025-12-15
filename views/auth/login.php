<?php
/**
 * Página de Login con Bloqueo Temporal
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 * 
 * CARACTERÍSTICAS DE SEGURIDAD:
 * - Máximo 5 intentos fallidos permitidos
 * - Bloqueo temporal de 30 minutos después del 5to intento
 * - Reset automático de intentos después del bloqueo
 * - Reset de intentos con login exitoso
 */

// Definir la ruta base del proyecto
$base_path = dirname(dirname(__DIR__));

// Incluir archivos necesarios
require_once $base_path . '/config/config.php';
require_once $base_path . '/validaciones/validaciones.php';

// Configuración de seguridad
define('MAX_INTENTOS_FALLIDOS', 5);
define('TIEMPO_BLOQUEO_MINUTOS', 30);

// Iniciar sesión
session_start();

// Si ya hay sesión activa, redirigir según rol
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'Administrador':
            header("Location: ../admin/dashboard_admin.php");
            break;
        case 'Trabajadora Social':
            header("Location: ../tsocial/dashboard_trabajadora.php");
            break;
        case 'Estudiante':
            header("Location: ../estudiante/dashboard.php");
            break;
        default:
            header("Location: ../estudiante/dashboard.php");
    }
    exit();
}

$mensaje = "";
$tipo_mensaje = "";
$debug_info = "";
$tiempo_bloqueo_restante = 0;

/**
 * Verificar si una cuenta está bloqueada
 */
function verificarBloqueo($conn, $correo) {
    $stmt = $conn->prepare("
        SELECT 
            intentos_fallidos,
            cuenta_bloqueada_hasta,
            TIMESTAMPDIFF(MINUTE, NOW(), cuenta_bloqueada_hasta) as minutos_restantes
        FROM usuarios 
        WHERE correo_institucional = ?
    ");
    $stmt->execute([strtolower(trim($correo))]);
    $resultado = $stmt->fetch();
    
    if (!$resultado) {
        return ['bloqueada' => false, 'minutos_restantes' => 0, 'intentos' => 0];
    }
    
    // Si hay una fecha de bloqueo
    if ($resultado['cuenta_bloqueada_hasta']) {
        // Verificar si el bloqueo ya expiró
        if (strtotime($resultado['cuenta_bloqueada_hasta']) > time()) {
            return [
                'bloqueada' => true, 
                'minutos_restantes' => $resultado['minutos_restantes'],
                'intentos' => $resultado['intentos_fallidos']
            ];
        } else {
            // El bloqueo expiró, resetear contador
            $stmt_reset = $conn->prepare("
                UPDATE usuarios 
                SET intentos_fallidos = 0,
                    cuenta_bloqueada_hasta = NULL,
                    fecha_ultimo_intento_fallido = NULL
                WHERE correo_institucional = ?
            ");
            $stmt_reset->execute([strtolower(trim($correo))]);
            return ['bloqueada' => false, 'minutos_restantes' => 0, 'intentos' => 0];
        }
    }
    
    return [
        'bloqueada' => false, 
        'minutos_restantes' => 0, 
        'intentos' => $resultado['intentos_fallidos']
    ];
}

/**
 * Registrar intento fallido
 */
function registrarIntentoFallido($conn, $correo) {
    // Obtener intentos actuales
    $stmt = $conn->prepare("
        SELECT intentos_fallidos 
        FROM usuarios 
        WHERE correo_institucional = ?
    ");
    $stmt->execute([strtolower(trim($correo))]);
    $resultado = $stmt->fetch();
    
    if (!$resultado) {
        return ['bloqueada' => false, 'intentos' => 0];
    }
    
    $nuevos_intentos = $resultado['intentos_fallidos'] + 1;
    
    // Si alcanzó el máximo de intentos, bloquear cuenta
    if ($nuevos_intentos >= MAX_INTENTOS_FALLIDOS) {
        $fecha_desbloqueo = date('Y-m-d H:i:s', strtotime('+' . TIEMPO_BLOQUEO_MINUTOS . ' minutes'));
        
        $stmt_update = $conn->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = ?,
                fecha_ultimo_intento_fallido = NOW(),
                cuenta_bloqueada_hasta = ?
            WHERE correo_institucional = ?
        ");
        $stmt_update->execute([$nuevos_intentos, $fecha_desbloqueo, strtolower(trim($correo))]);
        
        return ['bloqueada' => true, 'intentos' => $nuevos_intentos];
    } else {
        // Solo incrementar contador
        $stmt_update = $conn->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = ?,
                fecha_ultimo_intento_fallido = NOW()
            WHERE correo_institucional = ?
        ");
        $stmt_update->execute([$nuevos_intentos, strtolower(trim($correo))]);
        
        return ['bloqueada' => false, 'intentos' => $nuevos_intentos];
    }
}

/**
 * Resetear intentos fallidos (después de login exitoso)
 */
function resetearIntentos($conn, $correo) {
    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET intentos_fallidos = 0,
            cuenta_bloqueada_hasta = NULL,
            fecha_ultimo_intento_fallido = NULL
        WHERE correo_institucional = ?
    ");
    $stmt->execute([strtolower(trim($correo))]);
}

// Procesar formulario de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getDBConnection();
        
        // Obtener y limpiar datos del formulario
        $correo = trim($_POST["correo"]);
        $password = trim($_POST["password"]);
        
        $debug_info .= "📧 Correo ingresado: " . htmlspecialchars($correo) . "<br>";
        
        // ===== VALIDACIONES BÁSICAS =====
        validarNoVacio($correo, "correo electrónico");
        validarNoVacio($password, "contraseña");
        validarCorreoUTP($correo);
        
        // ===== VERIFICAR BLOQUEO DE CUENTA =====
        $estado_bloqueo = verificarBloqueo($conn, $correo);
        
        if ($estado_bloqueo['bloqueada']) {
            $tiempo_bloqueo_restante = $estado_bloqueo['minutos_restantes'];
            $debug_info .= "🔒 <strong>CUENTA BLOQUEADA TEMPORALMENTE</strong><br>";
            $debug_info .= "⏱️ Tiempo restante: " . $tiempo_bloqueo_restante . " minutos<br>";
            $debug_info .= "❌ Intentos fallidos: " . $estado_bloqueo['intentos'] . "<br>";
            
            throw new Exception("Cuenta bloqueada temporalmente por seguridad. Intente nuevamente en " . $tiempo_bloqueo_restante . " minutos.");
        }
        
        $debug_info .= "✅ Cuenta no está bloqueada<br>";
        $debug_info .= "📊 Intentos fallidos previos: " . $estado_bloqueo['intentos'] . "<br><br>";
        
        // ===== BUSCAR USUARIO EN LA BASE DE DATOS =====
        $stmt = $conn->prepare("
            SELECT u.*, r.nombre_rol 
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.correo_institucional = ? AND u.activo = 1
        ");
        $stmt->execute([strtolower(trim($correo))]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            $debug_info .= "✅ Usuario encontrado en BD<br>";
            $debug_info .= "👤 Nombre: " . htmlspecialchars($usuario['nombre_completo']) . "<br>";
            $debug_info .= "🎭 Rol: " . htmlspecialchars($usuario['nombre_rol']) . "<br>";
            $debug_info .= "📊 Activo: " . ($usuario['activo'] ? 'Sí' : 'No') . "<br><br>";
        } else {
            $debug_info .= "❌ Usuario NO encontrado en tabla usuarios<br>";
            
            // Registrar intento fallido
            $resultado_intento = registrarIntentoFallido($conn, $correo);
            $intentos_restantes = MAX_INTENTOS_FALLIDOS - $resultado_intento['intentos'];
            
            if ($resultado_intento['bloqueada']) {
                throw new Exception("Cuenta bloqueada temporalmente por seguridad. Intente nuevamente en " . TIEMPO_BLOQUEO_MINUTOS . " minutos.");
            } else {
                throw new Exception("Credenciales UTP inválidas. Le quedan " . $intentos_restantes . " intentos antes del bloqueo temporal.");
            }
        }
        
        // ===== VERIFICAR CONTRASEÑA =====
        $debug_info .= "🔐 Verificando contraseña...<br>";
        
        $password_valida = false;
        
        if (!isset($usuario['password']) || $usuario['password'] === null || $usuario['password'] === '') {
            $debug_info .= "⚠️ El usuario NO tiene contraseña configurada en la BD<br>";
            
            // Registrar intento fallido
            $resultado_intento = registrarIntentoFallido($conn, $correo);
            throw new Exception("Usuario sin contraseña configurada. Contacte al administrador.");
        }
        
        // Intentar con hash primero
        if (password_verify($password, $usuario['password'])) {
            $debug_info .= "✅ Contraseña válida (verificada con hash)<br>";
            $password_valida = true;
        }
        // Si falla, intentar texto plano (solo para desarrollo)
        elseif ($password === $usuario['password']) {
            $debug_info .= "✅ Contraseña válida (texto plano - DESARROLLO)<br>";
            $password_valida = true;
        } else {
            $debug_info .= "❌ Contraseña NO coincide<br>";
        }
        
        // Si la contraseña no es válida, registrar intento fallido
        if (!$password_valida) {
            $resultado_intento = registrarIntentoFallido($conn, $correo);
            $intentos_restantes = MAX_INTENTOS_FALLIDOS - $resultado_intento['intentos'];
            
            $debug_info .= "⚠️ Intento fallido registrado<br>";
            $debug_info .= "📊 Total de intentos fallidos: " . $resultado_intento['intentos'] . "/" . MAX_INTENTOS_FALLIDOS . "<br>";
            $debug_info .= "⏳ Intentos restantes: " . $intentos_restantes . "<br>";
            
            if ($resultado_intento['bloqueada']) {
                throw new Exception("Cuenta bloqueada temporalmente por seguridad. Intente nuevamente en " . TIEMPO_BLOQUEO_MINUTOS . " minutos.");
            } else {
                throw new Exception("Contraseña incorrecta. Le quedan " . $intentos_restantes . " intentos antes del bloqueo temporal.");
            }
        }
        
        // ===== AUTENTICACIÓN EXITOSA =====
        $debug_info .= "<br>🎉 <strong>AUTENTICACIÓN EXITOSA</strong><br>";
        
        // Resetear intentos fallidos
        resetearIntentos($conn, $correo);
        $debug_info .= "✅ Intentos fallidos reseteados<br>";
        
        // Crear sesión
        $_SESSION['user_id'] = $usuario['id_usuario'];
        $_SESSION['user_name'] = $usuario['nombre_completo'];
        $_SESSION['user_email'] = $usuario['correo_institucional'];
        $_SESSION['user_role'] = $usuario['nombre_rol'];
        $_SESSION['user_role_id'] = $usuario['id_rol'];
        
        // Registrar el acceso en logs
        $stmt_log = $conn->prepare("
            INSERT INTO logs_acceso (id_usuario, accion, ip_address, user_agent, exitoso)
            VALUES (?, 'login', ?, ?, 1)
        ");
        $stmt_log->execute([
            $usuario['id_usuario'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Actualizar último acceso
        $stmt_update = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
        $stmt_update->execute([$usuario['id_usuario']]);
        
        // Redirigir según rol
        switch ($usuario['nombre_rol']) {
            case 'Administrador':
                header("Location: ../admin/dashboard_admin.php");
                break;
            case 'Trabajadora Social':
                header("Location: ../tsocial/dashboard_trabajadora.php");
                break;
            case 'Coordinador':
                header("Location: ../tsocial/dashboard_trabajadora.php");
                break;
            case 'Estudiante':
            default:
                header("Location: ../estudiante/dashboard.php");
                break;
        }
        exit();
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Bienestar Estudiantil UTP</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: 
                linear-gradient(135deg, rgba(107, 44, 145, 0.85) 0%, rgba(26, 71, 42, 0.85) 100%),
                url('../../images/campus.jpg') center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 600px;
        }
        
        .security-badge {
            background: linear-gradient(135deg, #2d8659, #1a5c3a);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .security-badge i {
            margin-right: 5px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .logo-utp {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .login-header h1 {
            color: #2d8659;
            font-size: 26px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2d8659;
            box-shadow: 0 0 0 3px rgba(45, 134, 89, 0.1);
        }
        
        .form-group input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2d8659, #1a5c3a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-button:hover:not(:disabled) {
            background: linear-gradient(135deg, #1a5c3a, #2d8659);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 92, 58, 0.3);
        }
        
        .login-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .error {
            background-color: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-size: 14px;
        }
        
        .error.blocked {
            background-color: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        
        .error strong {
            display: block;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            font-size: 14px;
        }
        
        .debug-box {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .debug-box h4 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .security-info {
            background: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #0c5460;
        }
        
        .security-info h4 {
            color: #0c5460;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .security-info ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .security-info li {
            margin-bottom: 5px;
        }
        
        .countdown {
            font-size: 18px;
            font-weight: bold;
            color: #856404;
            text-align: center;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="security-badge">
            🔒 Sistema Seguro con Protección Anti-Intrusos
        </div>
        
        <div class="logo-container">
            <img src="../../images/utp.png" alt="Logo UTP" class="logo-utp" 
                 onerror="this.style.display='none'">
        </div>

        <div class="login-header">
            <h1>Sistema de Bienestar Estudiantil</h1>
            <p>Universidad Tecnológica de Panamá</p>
        </div>

        <?php 
        if (!empty($mensaje)) {
            $clase_error = (strpos($mensaje, 'bloqueada') !== false) ? 'error blocked' : 'error';
            echo "<div class='" . $clase_error . "'>";
            
            if (strpos($mensaje, 'bloqueada') !== false) {
                echo "🔒 <strong>Cuenta Bloqueada Temporalmente</strong><br>";
                echo $mensaje;
                if ($tiempo_bloqueo_restante > 0) {
                    echo "<div class='countdown' id='countdown'></div>";
                }
            } else {
                echo $mensaje;
            }
            
            echo "</div>";
        }
        ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="correo">Correo Institucional</label>
                <input 
                    type="email" 
                    id="correo"
                    name="correo" 
                    placeholder="usuario@utp.ac.pa" 
                    required
                    value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>"
                    <?php echo ($tiempo_bloqueo_restante > 0) ? 'disabled' : ''; ?>
                >
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    placeholder="Tu contraseña" 
                    required
                    <?php echo ($tiempo_bloqueo_restante > 0) ? 'disabled' : ''; ?>
                >
            </div>
            
            <button 
                type="submit" 
                class="login-button"
                <?php echo ($tiempo_bloqueo_restante > 0) ? 'disabled' : ''; ?>
            >
                <?php echo ($tiempo_bloqueo_restante > 0) ? 'Cuenta Bloqueada' : 'Ingresar'; ?>
            </button>
        </form>
        
        <div class="security-info">
            <h4>🛡️ Información de Seguridad</h4>
            <ul>
                <li>✅ Tienes un máximo de <strong>5 intentos</strong> para ingresar tu contraseña</li>
                <li>⏱️ Después de 5 intentos fallidos, tu cuenta será bloqueada por <strong>30 minutos</strong></li>
                <li>🔄 Los intentos se resetean automáticamente después del bloqueo</li>
                <li>✨ Un login exitoso limpia todos los intentos fallidos previos</li>
            </ul>
        </div>
        
        <?php if (!empty($debug_info)): ?>
        <div class="debug-box">
            <h4>📊 Información de Debug:</h4>
            <?php echo $debug_info; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($tiempo_bloqueo_restante > 0): ?>
    <script>
        // Countdown timer
        let segundosRestantes = <?php echo $tiempo_bloqueo_restante * 60; ?>;
        
        function actualizarContador() {
            const minutos = Math.floor(segundosRestantes / 60);
            const segundos = segundosRestantes % 60;
            
            document.getElementById('countdown').innerHTML = 
                `Tiempo restante: ${minutos}:${segundos.toString().padStart(2, '0')}`;
            
            if (segundosRestantes > 0) {
                segundosRestantes--;
                setTimeout(actualizarContador, 1000);
            } else {
                location.reload();
            }
        }
        
        actualizarContador();
    </script>
    <?php endif; ?>
</body>
</html>