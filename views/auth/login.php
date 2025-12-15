<?php
/**
 * Página de Login - VERSIÓN DEBUG
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

// Definir la ruta base del proyecto
$base_path = dirname(dirname(__DIR__));

// Incluir archivos necesarios
require_once $base_path . '/config/config.php';
require_once $base_path . '/validaciones/validaciones.php';

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
$debug_info = ""; // Para mostrar información de debug

// Procesar formulario de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getDBConnection();
        
        // Obtener y limpiar datos del formulario
        $correo = trim($_POST["correo"]);
        $password = trim($_POST["password"]);
        
        $debug_info .= "📧 Correo ingresado: " . htmlspecialchars($correo) . "<br>";
        $debug_info .= "🔑 Contraseña ingresada: " . htmlspecialchars($password) . "<br><br>";
        
        // ===== VALIDACIONES =====
        validarNoVacio($correo, "correo electrónico");
        validarNoVacio($password, "contraseña");
        validarCorreoUTP($correo);
        
        // 3. Buscar usuario en la base de datos LOCAL
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
            $debug_info .= "🔐 Password en BD: " . htmlspecialchars(substr($usuario['password'], 0, 20)) . "...<br>";
            $debug_info .= "📊 Activo: " . ($usuario['activo'] ? 'Sí' : 'No') . "<br><br>";
        } else {
            $debug_info .= "❌ Usuario NO encontrado en tabla usuarios<br>";
            
            // Buscar en directorio UTP
            $stmt_utp = $conn->prepare("
                SELECT * FROM directorio_utp 
                WHERE correo_institucional = ? AND activo_en_utp = 1
            ");
            $stmt_utp->execute([strtolower(trim($correo))]);
            $usuario_utp = $stmt_utp->fetch();
            
            if ($usuario_utp) {
                $debug_info .= "📋 Usuario encontrado en directorio_utp<br>";
                $debug_info .= "👤 Nombre: " . htmlspecialchars($usuario_utp['nombre_completo']) . "<br>";
                throw new Exception("Usuario existe en directorio UTP pero no ha sido importado. Contacte al administrador.");
            } else {
                $debug_info .= "❌ Usuario NO encontrado en directorio_utp<br>";
                throw new Exception("Credenciales UTP inválidas. Verifique su correo institucional.");
            }
        }
        
        // 4. Verificar contraseña
        $debug_info .= "🔍 Verificando contraseña...<br>";
        
        $password_valida = false;
        
        if (!isset($usuario['password']) || $usuario['password'] === null || $usuario['password'] === '') {
            $debug_info .= "⚠️ El usuario NO tiene contraseña configurada en la BD<br>";
            throw new Exception("Usuario sin contraseña configurada. Contacte al administrador.");
        }
        
        // Intentar con hash primero
        if (password_verify($password, $usuario['password'])) {
            $debug_info .= "✅ Contraseña válida (verificada con hash)<br>";
            $password_valida = true;
        }
        // Si falla, intentar texto plano
        elseif ($password === $usuario['password']) {
            $debug_info .= "✅ Contraseña válida (texto plano - DESARROLLO)<br>";
            $password_valida = true;
        } else {
            $debug_info .= "❌ Contraseña NO coincide<br>";
            $debug_info .= "Comparación: '" . htmlspecialchars($password) . "' vs '" . htmlspecialchars($usuario['password']) . "'<br>";
        }
        
        if (!$password_valida) {
            throw new Exception("Credenciales UTP inválidas. Contraseña incorrecta.");
        }
        
        // ===== AUTENTICACIÓN EXITOSA =====
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
                header("Location: ../admin/dashboard.php");
                break;
            case 'Trabajadora Social':
                header("Location: ../tsocial/dashboard.php");
                break;
            case 'Coordinador':
                header("Location: ../tsocial/dashboard.php");
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
        
        .debug-badge {
            background: #ff6b6b;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
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
        
        .login-button:hover {
            background: linear-gradient(135deg, #1a5c3a, #2d8659);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 92, 58, 0.3);
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
        
        .test-credentials {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .test-credentials h4 {
            color: #1976D2;
            margin-bottom: 8px;
        }
        
        .test-credentials code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        
        <div class="logo-container">
            <img src="../../images/utp.png" alt="Logo UTP" class="logo-utp" 
                 onerror="this.style.display='none'">
        </div>

        <div class="login-header">
            <h1>Sistema de Bienestar Estudiantil</h1>
        </div>

        
        <?php 
        if (!empty($mensaje)) {
            echo "<div class='error'>" . htmlspecialchars($mensaje) . "</div>";
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
                >
            </div>
            
            <button type="submit" class="login-button">Ingresar</button>
        </form>
        
        <?php if (!empty($debug_info)): ?>
        <div class="debug-box">
            <h4>📊 Información de Debug:</h4>
            <?php echo $debug_info; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>