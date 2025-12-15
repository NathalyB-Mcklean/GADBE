<?php
/**
 * Dashboard de Trabajadora Social
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

// Definir la ruta base del proyecto
$base_path = dirname(dirname(dirname(__FILE__)));

// Incluir archivos necesarios
require_once $base_path . '/config/config.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Verificar que sea trabajadora social o coordinador
if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    header("Location: ../auth/login.php");
    exit();
}

// Inicializar variables
$solicitudes_pendientes = 0;
$solicitudes_hoy = 0;
$solicitudes_sin_asignar = 0;
$citas_hoy = 0;
$citas_pendientes = 0;
$total_estudiantes = 0;
$ultimas_solicitudes = [];
$citas_proximas = [];
$error = null;

try {
    $conn = getDBConnection();
    
    // 1. Solicitudes pendientes asignadas
    $stmt_pendientes = $conn->prepare("
        SELECT COUNT(*) as total FROM solicitudes 
        WHERE id_trabajadora_asignada = ? 
        AND estado IN ('pendiente', 'en_revision')
    ");
    $stmt_pendientes->execute([$_SESSION['user_id']]);
    $solicitudes_pendientes = $stmt_pendientes->fetch()['total'];
    
    // 2. Solicitudes recibidas hoy
    $stmt_hoy = $conn->prepare("
        SELECT COUNT(*) as total FROM solicitudes 
        WHERE DATE(fecha_solicitud) = CURDATE()
    ");
    $stmt_hoy->execute();
    $solicitudes_hoy = $stmt_hoy->fetch()['total'];
    
    // 3. Solicitudes sin asignar
    $stmt_sin_asignar = $conn->prepare("
        SELECT COUNT(*) as total FROM solicitudes 
        WHERE id_trabajadora_asignada IS NULL 
        AND estado IN ('pendiente', 'en_revision')
    ");
    $stmt_sin_asignar->execute();
    $solicitudes_sin_asignar = $stmt_sin_asignar->fetch()['total'];
    
    // 4. Citas de hoy
    $stmt_citas_hoy = $conn->prepare("
        SELECT COUNT(*) as total FROM citas 
        WHERE id_trabajadora_social = ? 
        AND fecha_cita = CURDATE()
        AND estado IN ('confirmada', 'pendiente_confirmacion')
    ");
    $stmt_citas_hoy->execute([$_SESSION['user_id']]);
    $citas_hoy = $stmt_citas_hoy->fetch()['total'];
    
    // 5. Citas pendientes (futuras)
    $stmt_citas_pendientes = $conn->prepare("
        SELECT COUNT(*) as total FROM citas 
        WHERE id_trabajadora_social = ? 
        AND fecha_cita >= CURDATE()
        AND estado IN ('confirmada', 'pendiente_confirmacion')
    ");
    $stmt_citas_pendientes->execute([$_SESSION['user_id']]);
    $citas_pendientes = $stmt_citas_pendientes->fetch()['total'];
    
    // 6. Total de estudiantes atendidos
    $stmt_estudiantes = $conn->prepare("
        SELECT COUNT(DISTINCT id_estudiante) as total 
        FROM (
            SELECT id_estudiante FROM solicitudes WHERE id_trabajadora_asignada = ?
            UNION
            SELECT id_estudiante FROM citas WHERE id_trabajadora_social = ?
        ) as estudiantes
    ");
    $stmt_estudiantes->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $total_estudiantes = $stmt_estudiantes->fetch()['total'];
    
    // 7. Últimas solicitudes asignadas (5 más recientes)
    $stmt_ultimas = $conn->prepare("
        SELECT s.*, 
               ts.nombre_tipo,
               e.nombre_completo as estudiante_nombre,
               e.correo_institucional,
               e.facultad
        FROM solicitudes s
        INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
        INNER JOIN usuarios e ON s.id_estudiante = e.id_usuario
        WHERE s.id_trabajadora_asignada = ?
        ORDER BY s.fecha_solicitud DESC
        LIMIT 5
    ");
    $stmt_ultimas->execute([$_SESSION['user_id']]);
    $ultimas_solicitudes = $stmt_ultimas->fetchAll();
    
    // 8. Próximas citas (5 más cercanas)
    $stmt_prox_citas = $conn->prepare("
        SELECT c.*, 
               s.nombre as servicio_nombre,
               e.nombre_completo as estudiante_nombre,
               e.correo_institucional
        FROM citas c
        INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
        INNER JOIN usuarios e ON c.id_estudiante = e.id_usuario
        WHERE c.id_trabajadora_social = ? 
        AND c.fecha_cita >= CURDATE()
        AND c.estado IN ('confirmada', 'pendiente_confirmacion')
        ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
        LIMIT 5
    ");
    $stmt_prox_citas->execute([$_SESSION['user_id']]);
    $citas_proximas = $stmt_prox_citas->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar el dashboard: " . $e->getMessage();
}

// Función para obtener el color según el estado
function getEstadoBadgeClass($estado) {
    switch($estado) {
        case 'aprobada': return 'success';
        case 'rechazada': return 'danger';
        case 'en_revision': return 'warning';
        case 'pendiente': return 'info';
        case 'completada': return 'secondary';
        default: return 'secondary';
    }
}

// Función para obtener el texto del estado
function getEstadoTexto($estado) {
    switch($estado) {
        case 'aprobada': return 'Aprobada';
        case 'rechazada': return 'Rechazada';
        case 'en_revision': return 'En Revisión';
        case 'pendiente': return 'Pendiente';
        case 'completada': return 'Completada';
        default: return ucfirst(str_replace('_', ' ', $estado));
    }
}

// Variables para el layout
$page_title = "Dashboard";
$page_subtitle = "Panel de control de Trabajadora Social";

// Capturar contenido
ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-4">
        <div class="stats-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Solicitudes Pendientes</div>
                    <div class="stats-value"><?php echo $solicitudes_pendientes; ?></div>
                    <div class="stats-desc">Asignadas a usted</div>
                </div>
                <i class="bi bi-file-earmark-text stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="stats-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Citas de Hoy</div>
                    <div class="stats-value"><?php echo $citas_hoy; ?></div>
                    <div class="stats-desc">Agendadas para hoy</div>
                </div>
                <i class="bi bi-calendar-check stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="stats-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Solicitudes Sin Asignar</div>
                    <div class="stats-value"><?php echo $solicitudes_sin_asignar; ?></div>
                    <div class="stats-desc">Necesitan asignación</div>
                </div>
                <i class="bi bi-person-plus stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="stats-card green">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Citas Programadas</div>
                    <div class="stats-value"><?php echo $citas_pendientes; ?></div>
                    <div class="stats-desc">Próximas citas</div>
                </div>
                <i class="bi bi-calendar-event stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="stats-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Solicitudes de Hoy</div>
                    <div class="stats-value"><?php echo $solicitudes_hoy; ?></div>
                    <div class="stats-desc">Recibidas hoy</div>
                </div>
                <i class="bi bi-clock-history stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="stats-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Estudiantes Atendidos</div>
                    <div class="stats-value"><?php echo $total_estudiantes; ?></div>
                    <div class="stats-desc">Total atendidos</div>
                </div>
                <i class="bi bi-people stats-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="content-card mb-4">
    <h2 class="card-title">Acciones Rápidas</h2>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <a href="solicitudes_trabajadora.php" class="action-card">
                <i class="bi bi-file-earmark-text"></i>
                <div class="action-title">Revisar Solicitudes</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="citas_trabajadora.php" class="action-card">
                <i class="bi bi-calendar-plus"></i>
                <div class="action-title">Gestionar Agenda</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="servicios_trabajadora.php" class="action-card">
                <i class="bi bi-plus-circle"></i>
                <div class="action-title">Crear Servicio</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="reportes_trabajadora.php" class="action-card">
                <i class="bi bi-graph-up"></i>
                <div class="action-title">Ver Reportes</div>
            </a>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="row g-3">
    <!-- Últimas Solicitudes Asignadas -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Últimas Solicitudes Asignadas</h2>
            <?php if (count($ultimas_solicitudes) > 0): ?>
                <?php foreach ($ultimas_solicitudes as $solicitud): ?>
                    <div class="list-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="list-item-title"><?php echo htmlspecialchars($solicitud['nombre_tipo']); ?></div>
                            <span class="badge bg-<?php echo getEstadoBadgeClass($solicitud['estado']); ?>">
                                <?php echo getEstadoTexto($solicitud['estado']); ?>
                            </span>
                        </div>
                        <div class="list-item-meta">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($solicitud['estudiante_nombre']); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building"></i> <?php echo htmlspecialchars($solicitud['facultad']); ?>
                        </div>
                        <div class="list-item-meta">
                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-hash"></i> <?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?>
                        </div>
                        <?php if (!empty($solicitud['motivo'])): ?>
                            <div class="list-item-meta mt-1">
                                <?php echo htmlspecialchars(substr($solicitud['motivo'], 0, 100)) . '...'; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="solicitudes_trabajadora.php" class="btn btn-outline-purple">
                        Ver todas las solicitudes <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <p>No tienes solicitudes asignadas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Próximas Citas -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Próximas Citas</h2>
            <?php if (count($citas_proximas) > 0): ?>
                <?php foreach ($citas_proximas as $cita): ?>
                    <div class="list-item" style="border-left-color: #0d6efd;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="list-item-title">
                                <i class="bi bi-calendar-event text-primary"></i>
                                <?php 
                                    $fecha_formateada = date('d/m/Y', strtotime($cita['fecha_cita']));
                                    $hora_formateada = date('H:i', strtotime($cita['hora_inicio']));
                                    echo $fecha_formateada . ' - ' . $hora_formateada;
                                ?>
                            </div>
                            <span class="badge bg-<?php echo $cita['estado'] === 'confirmada' ? 'success' : 'warning'; ?>">
                                <?php echo $cita['estado'] === 'confirmada' ? 'Confirmada' : 'Pendiente'; ?>
                            </span>
                        </div>
                        <div class="list-item-meta">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($cita['estudiante_nombre']); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($cita['correo_institucional']); ?>
                        </div>
                        <div class="list-item-meta">
                            <i class="bi bi-list-check"></i> <?php echo htmlspecialchars($cita['servicio_nombre']); ?>
                        </div>
                        <?php if (!empty($cita['notas_estudiante'])): ?>
                            <div class="list-item-meta">
                                <i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars(substr($cita['notas_estudiante'], 0, 80)) . '...'; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="citas_trabajadora.php" class="btn btn-outline-primary">
                        Ver agenda completa <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>No tienes citas programadas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .btn-outline-purple {
        color: #6B2C91;
        border-color: #6B2C91;
    }
    
    .btn-outline-purple:hover {
        color: white;
        background-color: #6B2C91;
        border-color: #6B2C91;
    }
    
    .text-purple {
        color: #6B2C91 !important;
    }
</style>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_trabajadora.php';
?>