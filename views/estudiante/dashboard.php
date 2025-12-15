<?php
/**
 * Dashboard de Estudiante
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

// Verificar que sea estudiante
if ($_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

// Inicializar variables
$total_solicitudes = 0;
$solicitudes_pendientes = 0;
$solicitudes_aprobadas = 0;
$proximas_citas = 0;
$ultimas_solicitudes = [];
$citas_proximas = [];
$estudiante = null;
$error = null;

try {
    $conn = getDBConnection();
    
    // Obtener información del estudiante
    $stmt = $conn->prepare("
        SELECT u.*, d.cedula, d.estado_academico
        FROM usuarios u
        LEFT JOIN directorio_utp d ON u.id_directorio = d.id_directorio
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $estudiante = $stmt->fetch();
    
    if (!$estudiante) {
        throw new Exception("No se pudo cargar la información del estudiante");
    }
    
    // Verificar si existe la tabla solicitudes
    $stmt_check = $conn->query("SHOW TABLES LIKE 'solicitudes'");
    $tabla_solicitudes_existe = $stmt_check->rowCount() > 0;
    
    if ($tabla_solicitudes_existe) {
        // 1. Total de solicitudes
        $stmt_solicitudes = $conn->prepare("
            SELECT COUNT(*) as total FROM solicitudes WHERE id_estudiante = ?
        ");
        $stmt_solicitudes->execute([$_SESSION['user_id']]);
        $total_solicitudes = $stmt_solicitudes->fetch()['total'];
        
        // 2. Solicitudes pendientes
        $stmt_pendientes = $conn->prepare("
            SELECT COUNT(*) as total FROM solicitudes 
            WHERE id_estudiante = ? AND estado IN ('pendiente', 'en_revision')
        ");
        $stmt_pendientes->execute([$_SESSION['user_id']]);
        $solicitudes_pendientes = $stmt_pendientes->fetch()['total'];
        
        // 3. Solicitudes aprobadas
        $stmt_aprobadas = $conn->prepare("
            SELECT COUNT(*) as total FROM solicitudes 
            WHERE id_estudiante = ? AND estado = 'aprobada'
        ");
        $stmt_aprobadas->execute([$_SESSION['user_id']]);
        $solicitudes_aprobadas = $stmt_aprobadas->fetch()['total'];
        
        // 5. Últimas solicitudes (5 más recientes)
        $stmt_ultimas = $conn->prepare("
            SELECT s.*, ts.nombre_tipo, ts.descripcion as tipo_desc
            FROM solicitudes s
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            WHERE s.id_estudiante = ?
            ORDER BY s.fecha_solicitud DESC
            LIMIT 5
        ");
        $stmt_ultimas->execute([$_SESSION['user_id']]);
        $ultimas_solicitudes = $stmt_ultimas->fetchAll();
    }
    
    // Verificar si existe la tabla citas
    $stmt_check_citas = $conn->query("SHOW TABLES LIKE 'citas'");
    $tabla_citas_existe = $stmt_check_citas->rowCount() > 0;
    
    if ($tabla_citas_existe) {
        // 4. Próximas citas
        $stmt_citas = $conn->prepare("
            SELECT COUNT(*) as total FROM citas 
            WHERE id_estudiante = ? AND estado IN ('confirmada', 'pendiente_confirmacion')
            AND fecha_cita >= CURDATE()
        ");
        $stmt_citas->execute([$_SESSION['user_id']]);
        $proximas_citas = $stmt_citas->fetch()['total'];
        
        // 6. Próximas citas (3 más cercanas)
        $stmt_prox_citas = $conn->prepare("
            SELECT c.*, 
                   c.fecha_cita,
                   c.hora_inicio,
                   CONCAT(u.nombre_completo) as trabajadora_social
            FROM citas c
            INNER JOIN usuarios u ON c.id_trabajadora_social = u.id_usuario
            WHERE c.id_estudiante = ? AND c.estado IN ('confirmada', 'pendiente_confirmacion')
            AND c.fecha_cita >= CURDATE()
            ORDER BY c.fecha_cita ASC, c.hora_inicio ASC
            LIMIT 3
        ");
        $stmt_prox_citas->execute([$_SESSION['user_id']]);
        $citas_proximas = $stmt_prox_citas->fetchAll();
    }
    
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
        case 'borrador': return 'secondary';
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
        case 'borrador': return 'Borrador';
        default: return ucfirst($estado);
    }
}

// Variables para el layout
$page_title = "Dashboard";
$page_subtitle = "Bienvenido al sistema de Bienestar Estudiantil";

// Capturar contenido
ob_start();
?>

<style>
    /* Stats cards */
    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.3s;
        border-left: 4px solid;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .stats-card.blue { border-left-color: #0d6efd; }
    .stats-card.orange { border-left-color: #fd7e14; }
    .stats-card.green { border-left-color: #198754; }
    .stats-card.purple { border-left-color: #6f42c1; }
    
    .stats-card .stats-icon {
        font-size: 36px;
        opacity: 0.6;
    }
    
    .stats-card .stats-value {
        font-size: 36px;
        font-weight: 700;
        color: #2d8659;
        margin: 10px 0;
    }
    
    .stats-card .stats-label {
        font-size: 13px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .stats-card .stats-desc {
        font-size: 12px;
        color: #adb5bd;
        margin-top: 5px;
    }
    
    /* Quick actions */
    .action-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 25px;
        text-align: center;
        transition: all 0.3s;
        text-decoration: none;
        color: #495057;
        display: block;
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(45, 134, 89, 0.2);
        border-color: #2d8659;
        color: #2d8659;
    }
    
    .action-card i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #2d8659;
    }
    
    .action-card .action-title {
        font-weight: 600;
        font-size: 14px;
    }
    
    /* Content cards */
    .content-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .content-card .card-title {
        color: #2d8659;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        margin-bottom: 20px;
    }
    
    /* List items */
    .list-item {
        padding: 15px;
        border-left: 4px solid #dee2e6;
        margin-bottom: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        transition: all 0.3s;
    }
    
    .list-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
    
    .list-item-title {
        font-weight: 600;
        color: #212529;
        margin-bottom: 5px;
    }
    
    .list-item-meta {
        font-size: 12px;
        color: #6c757d;
    }
    
    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #adb5bd;
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }
</style>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stats-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Total Solicitudes</div>
                    <div class="stats-value"><?php echo $total_solicitudes; ?></div>
                    <div class="stats-desc">Solicitudes realizadas</div>
                </div>
                <i class="bi bi-file-earmark-text stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stats-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Pendientes</div>
                    <div class="stats-value"><?php echo $solicitudes_pendientes; ?></div>
                    <div class="stats-desc">En proceso de revisión</div>
                </div>
                <i class="bi bi-hourglass-split stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stats-card green">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Aprobadas</div>
                    <div class="stats-value"><?php echo $solicitudes_aprobadas; ?></div>
                    <div class="stats-desc">Solicitudes aprobadas</div>
                </div>
                <i class="bi bi-check-circle stats-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stats-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stats-label">Próximas Citas</div>
                    <div class="stats-value"><?php echo $proximas_citas; ?></div>
                    <div class="stats-desc">Citas programadas</div>
                </div>
                <i class="bi bi-calendar-check stats-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="content-card mb-4">
    <h2 class="card-title">Acciones Rápidas</h2>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <a href="nueva_solicitud.php" class="action-card">
                <i class="bi bi-file-earmark-plus"></i>
                <div class="action-title">Nueva Solicitud</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="citas.php?accion=nueva" class="action-card">
                <i class="bi bi-calendar-plus"></i>
                <div class="action-title">Programar Cita</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="servicios.php" class="action-card">
                <i class="bi bi-list-ul"></i>
                <div class="action-title">Ver Servicios</div>
            </a>
        </div>
        <div class="col-md-6 col-lg-3">
            <a href="evaluaciones.php" class="action-card">
                <i class="bi bi-star-fill"></i>
                <div class="action-title">Evaluar Servicio</div>
            </a>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="row g-3">
    <!-- Últimas Solicitudes -->
    <div class="col-lg-6">
        <div class="content-card">
            <h2 class="card-title">Últimas Solicitudes</h2>
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
                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="solicitudes.php" class="btn btn-outline-success">
                        Ver todas las solicitudes <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <p>No tienes solicitudes registradas</p>
                    <a href="nueva_solicitud.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Crear primera solicitud
                    </a>
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
                        <div class="list-item-title">
                            <i class="bi bi-calendar-event text-primary"></i>
                            <?php 
                                $fecha_formateada = date('d/m/Y', strtotime($cita['fecha_cita']));
                                $hora_formateada = date('H:i', strtotime($cita['hora_inicio']));
                                echo $fecha_formateada . ' - ' . $hora_formateada;
                            ?>
                        </div>
                        <div class="list-item-meta">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($cita['trabajadora_social']); ?>
                        </div>
                        <?php if (!empty($cita['notas_estudiante'])): ?>
                            <div class="list-item-meta">
                                <i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($cita['notas_estudiante']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="list-item-meta">
                            <span class="badge bg-<?php echo $cita['estado'] === 'confirmada' ? 'success' : 'warning'; ?>">
                                <?php echo $cita['estado'] === 'confirmada' ? 'Confirmada' : 'Pendiente confirmación'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="citas.php" class="btn btn-outline-primary">
                        Ver todas las citas <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>No tienes citas programadas</p>
                    <a href="citas.php?accion=nueva" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Programar una cita
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_estudiante.php';
?>