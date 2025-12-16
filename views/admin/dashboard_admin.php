<?php
/**
 * Dashboard de Administrador
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
$estadisticas = [];
$ultimas_solicitudes = [];
$citas_proximas = [];
$usuarios_recientes = [];
$alertas = [];
$error = null;

try {
    $conn = getDBConnection();
    
    // 1. Estadísticas generales del sistema
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
            (SELECT COUNT(*) FROM usuarios WHERE id_rol = 3 AND activo = 1) as total_estudiantes,
            (SELECT COUNT(*) FROM usuarios WHERE id_rol IN (2,4) AND activo = 1) as total_trabajadoras,
            (SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente') as solicitudes_pendientes,
            (SELECT COUNT(*) FROM solicitudes WHERE estado = 'en_revision') as solicitudes_revision,
            (SELECT COUNT(*) FROM citas WHERE fecha_cita = CURDATE() AND estado IN ('confirmada', 'pendiente_confirmacion')) as citas_hoy,
            (SELECT COUNT(*) FROM servicios_ofertas WHERE activo = 1) as servicios_activos,
            (SELECT COALESCE(SUM(monto_mensual), 0) FROM beneficios_asignados WHERE estado = 'activo') as total_beneficios,
            (SELECT COUNT(*) FROM solicitudes WHERE fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as solicitudes_semana,
            (SELECT COUNT(*) FROM citas WHERE fecha_cita >= CURDATE() AND fecha_cita <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as citas_semana
    ");
    $stmt->execute();
    $estadisticas = $stmt->fetch();
    
    // 2. Últimas solicitudes (10 más recientes)
    $stmt = $conn->prepare("
        SELECT s.*, 
               ts.nombre_tipo,
               e.nombre_completo as estudiante_nombre,
               e.correo_institucional,
               e.facultad,
               t.nombre_completo as trabajadora_nombre
        FROM solicitudes s
        INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
        INNER JOIN usuarios e ON s.id_estudiante = e.id_usuario
        LEFT JOIN usuarios t ON s.id_trabajadora_asignada = t.id_usuario
        ORDER BY s.fecha_solicitud DESC
        LIMIT 10
    ");
    $stmt->execute();
    $ultimas_solicitudes = $stmt->fetchAll();
    
    // 3. Próximas citas (10 más cercanas)
    $stmt = $conn->prepare("
        SELECT c.*, 
               s.nombre as servicio_nombre,
               e.nombre_completo as estudiante_nombre,
               e.correo_institucional,
               t.nombre_completo as trabajadora_nombre
        FROM citas c
        INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
        INNER JOIN usuarios e ON c.id_estudiante = e.id_usuario
        INNER JOIN usuarios t ON c.id_trabajadora_social = t.id_usuario
        WHERE c.fecha_cita >= CURDATE()
        AND c.estado IN ('confirmada', 'pendiente_confirmacion')
        ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
        LIMIT 10
    ");
    $stmt->execute();
    $citas_proximas = $stmt->fetchAll();
    
    // 4. Usuarios recientemente registrados (últimos 5)
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.activo = 1
        ORDER BY u.fecha_registro DESC
        LIMIT 5
    ");
    $stmt->execute();
    $usuarios_recientes = $stmt->fetchAll();
    
    // 5. Alertas del sistema
    // Solicitudes pendientes por más de 3 días
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM solicitudes 
        WHERE estado IN ('pendiente', 'en_revision')
        AND DATEDIFF(NOW(), fecha_solicitud) > 3
    ");
    $stmt->execute();
    $solicitudes_atrasadas = $stmt->fetch()['count'];
    
    if ($solicitudes_atrasadas > 0) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensaje' => "$solicitudes_atrasadas solicitudes tienen más de 3 días pendientes",
            'icono' => 'bi-exclamation-triangle'
        ];
    }
    
    // Citas sin confirmar para hoy
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM citas 
        WHERE fecha_cita = CURDATE()
        AND estado = 'pendiente_confirmacion'
    ");
    $stmt->execute();
    $citas_sin_confirmar = $stmt->fetch()['count'];
    
    if ($citas_sin_confirmar > 0) {
        $alertas[] = [
            'tipo' => 'danger',
            'mensaje' => "$citas_sin_confirmar citas de hoy sin confirmar",
            'icono' => 'bi-calendar-x'
        ];
    }
    
    // Usuarios sin actividad reciente
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM usuarios 
        WHERE id_rol IN (2,4) 
        AND activo = 1
        AND (ultimo_acceso IS NULL OR ultimo_acceso < DATE_SUB(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute();
    $usuarios_inactivos = $stmt->fetch()['count'];
    
    if ($usuarios_inactivos > 0) {
        $alertas[] = [
            'tipo' => 'info',
            'mensaje' => "$usuarios_inactivos trabajadoras sin actividad reciente",
            'icono' => 'bi-person-x'
        ];
    }
    
} catch (Exception $e) {
    $error = "Error al cargar el dashboard: " . $e->getMessage();
}

$page_title = "Dashboard";
$page_subtitle = "Panel de control de Administrador";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Alertas del Sistema -->
<?php if (count($alertas) > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="content-card">
            <h2 class="card-title">
                <i class="bi bi-bell"></i> Alertas del Sistema
            </h2>
            <div class="row g-3">
                <?php foreach ($alertas as $alerta): ?>
                <div class="col-md-4">
                    <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show">
                        <i class="bi <?php echo $alerta['icono']; ?>"></i>
                        <?php echo htmlspecialchars($alerta['mensaje']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stats-card red">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Total Usuarios</div>
                    <div class="stats-value"><?php echo $estadisticas['total_usuarios'] ?? 0; ?></div>
                    <div class="stats-desc">En el sistema</div>
                </div>
                <i class="bi bi-people stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-6">
        <div class="stats-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Solicitudes Pendientes</div>
                    <div class="stats-value"><?php echo $estadisticas['solicitudes_pendientes'] ?? 0; ?></div>
                    <div class="stats-desc">Por revisar</div>
                </div>
                <i class="bi bi-file-earmark-text stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-6">
        <div class="stats-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Citas Hoy</div>
                    <div class="stats-value"><?php echo $estadisticas['citas_hoy'] ?? 0; ?></div>
                    <div class="stats-desc">Agendadas para hoy</div>
                </div>
                <i class="bi bi-calendar-check stats-icon"></i>
            </div>
        </div>
    </div>
    

    
    <div class="col-md-3 col-6">
        <div class="stats-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Estudiantes</div>
                    <div class="stats-value"><?php echo $estadisticas['total_estudiantes'] ?? 0; ?></div>
                    <div class="stats-desc">Registrados</div>
                </div>
                <i class="bi bi-person-video3 stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-6">
        <div class="stats-card teal">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Trabajadoras</div>
                    <div class="stats-value"><?php echo $estadisticas['total_trabajadoras'] ?? 0; ?></div>
                    <div class="stats-desc">Activas</div>
                </div>
                <i class="bi bi-person-heart stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-6">
        <div class="stats-card indigo">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Servicios Activos</div>
                    <div class="stats-value"><?php echo $estadisticas['servicios_activos'] ?? 0; ?></div>
                    <div class="stats-desc">Disponibles</div>
                </div>
                <i class="bi bi-list-check stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-6">
        <div class="stats-card pink">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Última Semana</div>
                    <div class="stats-value"><?php echo $estadisticas['solicitudes_semana'] ?? 0; ?></div>
                    <div class="stats-desc">Nuevas solicitudes</div>
                </div>
                <i class="bi bi-graph-up stats-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="content-card mb-4">
    <h2 class="card-title">Acciones Rápidas</h2>
    <div class="row g-3">
        <div class="col-md-2 col-4">
            <a href="gestion_usuarios.php?action=nuevo" class="action-card">
                <i class="bi bi-person-plus"></i>
                <div class="action-title">Agregar Usuario</div>
            </a>
        </div>
        <div class="col-md-2 col-4">
            <a href="solicitudes_admin.php" class="action-card">
                <i class="bi bi-funnel"></i>
                <div class="action-title">Revisar Solicitudes</div>
            </a>
        </div>
        <div class="col-md-2 col-4">
            <a href="reportes_admin.php" class="action-card">
                <i class="bi bi-graph-up-arrow"></i>
                <div class="action-title">Ver Reportes</div>
            </a>
        </div>
        <div class="col-md-2 col-4">
            <a href="servicios_admin.php?action=nuevo" class="action-card">
                <i class="bi bi-plus-circle"></i>
                <div class="action-title">Crear Servicio</div>
            </a>
        </div>
        <div class="col-md-2 col-4">
            <a href="gestion_roles.php" class="action-card">
                <i class="bi bi-person-badge"></i>
                <div class="action-title">Gestionar Roles</div>
            </a>
        </div>
        <div class="col-md-2 col-4">
            <a href="logs_admin.php" class="action-card">
                <i class="bi bi-journal-text"></i>
                <div class="action-title">Ver Logs</div>
            </a>
        </div>
    </div>
</div>

<!-- Contenido Principal -->
<div class="row g-3">
    <!-- Últimas Solicitudes -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Últimas Solicitudes</h2>
            <?php if (count($ultimas_solicitudes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Asignada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_solicitudes as $solicitud): ?>
                                <tr>
                                    <td>
                                        <div><?php echo htmlspecialchars($solicitud['estudiante_nombre']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($solicitud['facultad']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($solicitud['nombre_tipo']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getEstadoBadgeClass($solicitud['estado']); ?>">
                                            <?php echo getEstadoTexto($solicitud['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                                    <td>
                                        <?php if ($solicitud['trabajadora_nombre']): ?>
                                            <?php echo htmlspecialchars($solicitud['trabajadora_nombre']); ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="solicitudes_admin.php" class="btn btn-outline-admin">
                        Ver todas las solicitudes <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <p>No hay solicitudes recientes</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Próximas Citas -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Próximas Citas</h2>
            <?php if (count($citas_proximas) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Estudiante</th>
                                <th>Servicio</th>
                                <th>Trabajadora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($citas_proximas as $cita): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($cita['hora_inicio'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($cita['estudiante_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($cita['servicio_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($cita['trabajadora_nombre']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $cita['estado'] === 'confirmada' ? 'success' : 'warning'; ?>">
                                            <?php echo $cita['estado'] === 'confirmada' ? 'Confirmada' : 'Pendiente'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="citas_admin.php" class="btn btn-outline-primary">
                        Ver agenda completa <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>No hay citas programadas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Usuarios Recientes -->
<div class="content-card mt-4">
    <h2 class="card-title">Usuarios Recientemente Registrados</h2>
    <?php if (count($usuarios_recientes) > 0): ?>
        <div class="row g-3">
            <?php foreach ($usuarios_recientes as $usuario): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="list-item">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #6B2C91 0%, #4A1D6B 100%); 
                                            border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: white; font-weight: bold;">
                                        <?php 
                                        $iniciales = '';
                                        $nombres = explode(' ', $usuario['nombre_completo']);
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
                            <div>
                                <div class="list-item-title"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></div>
                                <div class="list-item-meta">
                                    <span class="badge bg-<?php 
                                        echo $usuario['nombre_rol'] === 'Administrador' ? 'danger' : 
                                             ($usuario['nombre_rol'] === 'Trabajadora Social' ? 'success' : 'info');
                                    ?>">
                                        <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                    </span>
                                    <span class="mx-2">•</span>
                                    <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="gestion_usuarios.php" class="btn btn-outline-admin">
                Ver todos los usuarios <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <p>No hay usuarios registrados</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-cerrar alertas después de 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php
// Función helper para colores de estado
function getEstadoBadgeClass($estado) {
    switch($estado) {
        case 'aprobada': return 'success';
        case 'rechazada': return 'danger';
        case 'en_revision': return 'warning';
        case 'pendiente': return 'info';
        case 'requiere_informacion': return 'secondary';
        default: return 'secondary';
    }
}

function getEstadoTexto($estado) {
    $estados = [
        'pendiente' => 'Pendiente',
        'en_revision' => 'En Revisión',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'requiere_informacion' => 'Requiere Info'
    ];
    return $estados[$estado] ?? ucfirst($estado);
}

$content = ob_get_clean();
require_once 'layout_admin.php';
?>