<?php
/**
 * Mi Perfil - Trabajadora Social
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    header("Location: ../auth/login.php");
    exit();
}

// Variables
$message = '';
$error = '';
$perfil = [];
$actividad = [];

try {
    $conn = getDBConnection();
    
    // Cargar perfil
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol, d.*
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        LEFT JOIN directorio_utp d ON u.id_directorio = d.id_directorio
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $perfil = $stmt->fetch();
    
    // Cargar actividad reciente
    $stmt = $conn->prepare("
        (SELECT 
            'solicitud' as tipo,
            CONCAT('Solicitud ', codigo_solicitud) as descripcion,
            fecha_solicitud as fecha,
            estado,
            NULL as color
        FROM solicitudes 
        WHERE id_trabajadora_asignada = ?
        ORDER BY fecha_solicitud DESC
        LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'cita' as tipo,
            CONCAT('Cita con estudiante') as descripcion,
            CONCAT(fecha_cita, ' ', hora_inicio) as fecha,
            estado,
            NULL as color
        FROM citas 
        WHERE id_trabajadora_social = ?
        ORDER BY fecha_cita DESC
        LIMIT 5)
        
        ORDER BY fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $actividad = $stmt->fetchAll();
    
    // Actualizar perfil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
        $telefono = $_POST['telefono'];
        $departamento = $_POST['departamento'];
        
        $stmt = $conn->prepare("
            UPDATE usuarios 
            SET telefono = ?, departamento = ?
            WHERE id_usuario = ?
        ");
        $stmt->execute([$telefono, $departamento, $_SESSION['user_id']]);
        
        $message = "Perfil actualizado exitosamente";
        
        // Recargar perfil
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $perfil = $stmt->fetch();
    }
    
    // Cambiar contraseña
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
        $password_actual = $_POST['password_actual'];
        $password_nueva = $_POST['password_nueva'];
        $password_confirmar = $_POST['password_confirmar'];
        
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch();
        
        if ($password_nueva !== $password_confirmar) {
            $error = "Las contraseñas nuevas no coinciden";
        } elseif ($usuario['password'] !== $password_actual) {
            $error = "La contraseña actual es incorrecta";
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
            $stmt->execute([$password_nueva, $_SESSION['user_id']]);
            $message = "Contraseña actualizada exitosamente";
        }
    }
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$page_title = "Mi Perfil";
$page_subtitle = "Información personal y configuración";

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Columna izquierda: Información del perfil -->
    <div class="col-lg-8">
        <div class="content-card mb-4">
            <h2 class="card-title">Información Personal</h2>
            
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['nombre_completo'] ?? ''); ?>" disabled>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Correo Institucional</label>
                        <input type="email" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['correo_institucional'] ?? ''); ?>" disabled>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Teléfono *</label>
                        <input type="text" name="telefono" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['telefono'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Departamento</label>
                        <input type="text" name="departamento" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['departamento'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Rol</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['nombre_rol'] ?? ''); ?>" disabled>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Fecha de Registro</label>
                        <input type="text" class="form-control" 
                               value="<?php echo date('d/m/Y', strtotime($perfil['fecha_registro'] ?? '')); ?>" disabled>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Último Acceso</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $perfil['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($perfil['ultimo_acceso'])) : 'Nunca'; ?>" disabled>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <input type="text" class="form-control" 
                               value="<?php echo $perfil['activo'] ? 'Activo' : 'Inactivo'; ?>" disabled>
                    </div>
                    
                    <?php if (isset($perfil['cedula']) && $perfil['cedula']): ?>
                    <div class="col-md-6">
                        <label class="form-label">Cédula</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['cedula']); ?>" disabled>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($perfil['cargo']) && $perfil['cargo']): ?>
                    <div class="col-md-6">
                        <label class="form-label">Cargo</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($perfil['cargo']); ?>" disabled>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="actualizar_perfil" class="btn btn-purple">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Cambiar Contraseña -->
        <div class="content-card">
            <h2 class="card-title">Cambiar Contraseña</h2>
            
            <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Contraseña Actual *</label>
                        <input type="password" name="password_actual" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Nueva Contraseña *</label>
                        <input type="password" name="password_nueva" class="form-control" required minlength="6">
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Confirmar Nueva Contraseña *</label>
                        <input type="password" name="password_confirmar" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Por seguridad, se recomienda usar una contraseña fuerte que incluya letras, números y caracteres especiales.
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="cambiar_password" class="btn btn-warning">
                                <i class="bi bi-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Columna derecha: Avatar y actividad -->
    <div class="col-lg-4">
        <!-- Avatar y resumen -->
        <div class="content-card mb-4 text-center">
            <div class="mb-3">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #6B2C91 0%, #4A1D6B 100%); 
                            border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                    <span style="font-size: 48px; color: white; font-weight: bold;">
                        <?php 
                        $iniciales = '';
                        $nombres = explode(' ', $perfil['nombre_completo'] ?? '');
                        if (count($nombres) >= 2) {
                            $iniciales = substr($nombres[0], 0, 1) . substr($nombres[1], 0, 1);
                        } elseif (count($nombres) == 1) {
                            $iniciales = substr($nombres[0], 0, 2);
                        }
                        echo strtoupper($iniciales);
                        ?>
                    </span>
                </div>
            </div>
            
            <h4><?php echo htmlspecialchars($perfil['nombre_completo'] ?? ''); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($perfil['nombre_rol'] ?? ''); ?></p>
            
            <div class="d-flex justify-content-center gap-3">
                <div class="text-center">
                    <div class="fw-bold text-purple"><?php echo $perfil['activo'] ? 'Activo' : 'Inactivo'; ?></div>
                    <small>Estado</small>
                </div>
                <div class="text-center">
                    <div class="fw-bold text-purple">
                        <?php 
                        $fecha_registro = new DateTime($perfil['fecha_registro'] ?? date('Y-m-d'));
                        $hoy = new DateTime();
                        $diferencia = $hoy->diff($fecha_registro);
                        echo $diferencia->y > 0 ? $diferencia->y . ' años' : 
                             ($diferencia->m > 0 ? $diferencia->m . ' meses' : 
                             $diferencia->d . ' días');
                        ?>
                    </div>
                    <small>En el sistema</small>
                </div>
            </div>
        </div>
        
        <!-- Actividad Reciente -->
        <div class="content-card">
            <h2 class="card-title">Actividad Reciente</h2>
            
            <?php if (count($actividad) > 0): ?>
                <div class="timeline">
                    <?php foreach ($actividad as $item): ?>
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="timeline-icon me-3">
                                    <?php if ($item['tipo'] == 'solicitud'): ?>
                                        <i class="bi bi-file-earmark-text text-primary"></i>
                                    <?php else: ?>
                                        <i class="bi bi-calendar-event text-success"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['descripcion']); ?></div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($item['fecha'])); ?>
                                    </small>
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $item['estado'] == 'aprobada' ? 'success' : 
                                                 ($item['estado'] == 'pendiente' ? 'warning' : 
                                                 ($item['estado'] == 'rechazada' ? 'danger' : 'secondary'));
                                        ?>">
                                            <?php echo ucfirst($item['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-activity" style="font-size: 48px; color: #adb5bd;"></i>
                    <p class="text-muted mt-2">No hay actividad reciente</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="content-card mt-4">
            <h2 class="card-title">Acciones</h2>
            <div class="d-grid gap-2">
                <a href="dashboard_trabajadora.php" class="btn btn-outline-purple">
                    <i class="bi bi-house-door"></i> Ir al Dashboard
                </a>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir Perfil
                </button>
                <a href="../auth/logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 19px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.timeline-icon i {
    font-size: 18px;
}
</style>

<script>
// Auto-cerrar alertas
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Validación de formulario de contraseña
const formPassword = document.querySelector('form[name="cambiar_password"]');
if (formPassword) {
    formPassword.addEventListener('submit', function(e) {
        const password = this.querySelector('[name="password_nueva"]').value;
        const confirmar = this.querySelector('[name="password_confirmar"]').value;
        
        if (password !== confirmar) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('La contraseña debe tener al menos 6 caracteres');
            return false;
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require_once 'layout_trabajadora.php';
?>