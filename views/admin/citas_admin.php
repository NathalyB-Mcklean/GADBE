<?php
/**
 * Agenda de Citas - Administrador
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
$citas = [];
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$error = null;

try {
    $conn = getDBConnection();
    
    $sql = "
        SELECT c.*, 
               s.nombre as servicio_nombre,
               e.nombre_completo as estudiante_nombre,
               e.correo_institucional,
               t.nombre_completo as trabajadora_nombre
        FROM citas c
        INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
        INNER JOIN usuarios e ON c.id_estudiante = e.id_usuario
        INNER JOIN usuarios t ON c.id_trabajadora_social = t.id_usuario
        WHERE c.fecha_cita = ?
        ORDER BY c.hora_inicio ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fecha_filtro]);
    $citas = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar las citas: " . $e->getMessage();
}

$page_title = "Agenda de Citas";
$page_subtitle = "Administrar citas programadas";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="content-card">
    <h2 class="card-title">Agenda de Citas</h2>
    
    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-md-3">
            <form method="get" class="d-flex">
                <input type="date" class="form-control" name="fecha" value="<?php echo htmlspecialchars($fecha_filtro); ?>" 
                       onchange="this.form.submit()">
            </form>
        </div>
        <div class="col-md-9 text-end">
            <a href="#" class="btn btn-admin">
                <i class="bi bi-plus-circle"></i> Nueva Cita
            </a>
        </div>
    </div>
    
    <?php if (count($citas) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Estudiante</th>
                        <th>Servicio</th>
                        <th>Trabajadora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('H:i', strtotime($cita['hora_inicio'])); ?></strong> - 
                                <?php echo date('H:i', strtotime($cita['hora_fin'])); ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($cita['estudiante_nombre']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($cita['correo_institucional']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($cita['servicio_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($cita['trabajadora_nombre']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo getEstadoCitaBadgeClass($cita['estado']); ?>">
                                    <?php echo getEstadoCitaTexto($cita['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="#" class="btn btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-warning" title="Reagendar">
                                        <i class="bi bi-calendar-event"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-danger" title="Cancelar">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>No hay citas programadas para esta fecha</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Función helper para colores de estado de citas
function getEstadoCitaBadgeClass($estado) {
    switch($estado) {
        case 'confirmada': return 'success';
        case 'cancelada': return 'danger';
        case 'pendiente_confirmacion': return 'warning';
        case 'completada': return 'info';
        case 'no_asistio': return 'secondary';
        default: return 'secondary';
    }
}

function getEstadoCitaTexto($estado) {
    $estados = [
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
        'pendiente_confirmacion' => 'Pendiente',
        'completada' => 'Completada',
        'no_asistio' => 'No Asistió'
    ];
    return $estados[$estado] ?? ucfirst($estado);
}

$content = ob_get_clean();
require_once 'layout_admin.php';
?>