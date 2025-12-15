<?php
/**
 * Perfil de Administrador
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header("Location: ../../auth/login.php");
    exit();
}

// Variables
$admin_info = null;
$error = null;
$mensaje = null;

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            UPDATE usuarios 
            SET nombre_completo = ?, telefono = ?
            WHERE id_usuario = ?
        ");
        $stmt->execute([$nombre, $telefono, $_SESSION['user_id']]);
        $mensaje = ['tipo' => 'success', 'texto' => 'Perfil actualizado correctamente.'];
    } catch (Exception $e) {
        $error = "Error al actualizar el perfil: " . $e->getMessage();
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_password') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    if ($password_nueva !== $password_confirmar) {
        $error = "Las contraseñas nuevas no coinciden.";
    } else {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $usuario = $stmt->fetch();
            
            if (password_verify($password_actual, $usuario['password'])) {
                $hashed_password = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $mensaje = ['tipo' => 'success', 'texto' => 'Contraseña actualizada correctamente.'];
            } else {
                $error = "La contraseña actual es incorrecta.";
            }
        } catch (Exception $e) {
            $error = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_info = $stmt->fetch();
} catch (Exception $e) {
    $error = "Error al cargar la información del perfil: " . $e->getMessage();
}

$page_title = "Mi Perfil";
$page_subtitle = "Administrar mi cuenta";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($mensaje['texto']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Información del Perfil -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Información del Perfil</h2>
            
            <?php if ($admin_info): ?>
            <div class="row mb-4">
                <div class="col-md-4 text-center">
                    <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #6B2C91 0%, #4A1D6B 100%); 
                                border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <span style="color: white; font-weight: bold; font-size: 36px;">
                            <?php 
                            $iniciales = '';
                            $nombres = explode(' ', $admin_info['nombre_completo']);
                            if (count($nombres) >= 2) {
                                $iniciales = substr($nombres[0], 0, 1) . substr($nombres[1], 0, 1);
                            } elseif (count($nombres) == 1) {
                                $iniciales = substr($nombres[0], 0, 2);
                            }
                            echo strtoupper($iniciales);
                            ?>
                        </span>
                    </div>
                    <button class="btn btn-outline-admin btn-sm">Cambiar Foto</button>
                </div>
                <div class="col-md-8">
                    <table class="table table-sm">
                        <tr>
                            <th>Nombre:</th>
                            <td><?php echo htmlspecialchars($admin_info['nombre_completo']); ?></td>
                        </tr>
                        <tr>
                            <th>Correo:</th>
                            <td><?php echo htmlspecialchars($admin_info['correo_institucional']); ?></td>
                        </tr>
                        <tr>
                            <th>Rol:</th>
                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($admin_info['nombre_rol']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Teléfono:</th>
                            <td><?php echo htmlspecialchars($admin_info['telefono'] ?? 'No registrado'); ?></td>
                        </tr>
                        <tr>
                            <th>Departamento:</th>
                            <td><?php echo htmlspecialchars($admin_info['departamento'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Último acceso:</th>
                            <td>
                                <?php if ($admin_info['ultimo_acceso']): ?>
                                    <?php echo date('d/m/Y H:i:s', strtotime($admin_info['ultimo_acceso'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Fecha registro:</th>
                            <td><?php echo date('d/m/Y', strtotime($admin_info['fecha_registro'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Formulario de actualización -->
            <h4 class="mt-4">Actualizar Información</h4>
            <form method="post">
                <input type="hidden" name="action" value="actualizar">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre Completo</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" 
                           value="<?php echo htmlspecialchars($admin_info['nombre_completo'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" 
                           value="<?php echo htmlspecialchars($admin_info['telefono'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-admin">Guardar Cambios</button>
            </form>
        </div>
    </div>
    
    <!-- Cambio de Contraseña -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Seguridad</h2>
            
            <h4 class="mt-4">Cambiar Contraseña</h4>
            <form method="post">
                <input type="hidden" name="action" value="cambiar_password">
                <div class="mb-3">
                    <label for="password_actual" class="form-label">Contraseña Actual</label>
                    <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                </div>
                <div class="mb-3">
                    <label for="password_nueva" class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" id="password_nueva" name="password_nueva" required>
                </div>
                <div class="mb-3">
                    <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required>
                </div>
                <button type="submit" class="btn btn-admin">Cambiar Contraseña</button>
            </form>
            
            <!-- Configuraciones adicionales -->
            <h4 class="mt-5">Configuraciones Adicionales</h4>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="notificaciones_email">
                <label class="form-check-label" for="notificaciones_email">Recibir notificaciones por correo</label>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="autenticacion_dos_factores">
                <label class="form-check-label" for="autenticacion_dos_factores">Autenticación de dos factores</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="sesiones_activas">
                <label class="form-check-label" for="sesiones_activas">Mostrar sesiones activas</label>
            </div>
            
            <div class="mt-4">
                <a href="#" class="btn btn-outline-danger">
                    <i class="bi bi-shield-exclamation"></i> Ver registros de seguridad
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>