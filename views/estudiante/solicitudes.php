<?php
/**
 * Mis Solicitudes
 * Lista de todas las solicitudes realizadas por el estudiante
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

try {
    $conn = getDBConnection();
    
    // Construir consulta base
    $sql = "SELECT s.*, ts.nombre_tipo, ts.descripcion as tipo_desc,
            CONCAT(u.nombre_completo) as trabajadora_asignada
            FROM solicitudes s
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            LEFT JOIN usuarios u ON s.id_trabajadora_asignada = u.id_usuario
            WHERE s.id_estudiante = ?";
    
    $params = [$_SESSION['user_id']];
    
    // Aplicar filtro de estado
    if ($filtro_estado !== 'todos') {
        $sql .= " AND s.estado = ?";
        $params[] = $filtro_estado;
    }
    
    // Aplicar búsqueda
    if (!empty($busqueda)) {
        $sql .= " AND (ts.nombre_tipo LIKE ? OR s.motivo LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $sql .= " ORDER BY s.fecha_solicitud DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    // Obtener estadísticas
    $stmt_stats = $conn->prepare("
        SELECT estado, COUNT(*) as total
        FROM solicitudes
        WHERE id_estudiante = ?
        GROUP BY estado
    ");
    $stmt_stats->execute([$_SESSION['user_id']]);
    $stats = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

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
$page_title = "Mis Solicitudes";
$page_subtitle = "Gestiona y revisa todas tus solicitudes";

// Capturar contenido
ob_start();
?>

<style>
    .solicitud-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-left: 4px solid #dee2e6;
        transition: all 0.3s;
    }
    
    .solicitud-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateX(5px);
    }
    
    .solicitud-card.aprobada { border-left-color: #198754; }
    .solicitud-card.rechazada { border-left-color: #dc3545; }
    .solicitud-card.en_revision { border-left-color: #ffc107; }
    .solicitud-card.pendiente { border-left-color: #0dcaf0; }
</style>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h3 class="text-primary"><?php echo array_sum($stats); ?></h3>
                <small class="text-muted">TOTAL</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h3 class="text-info"><?php echo $stats['pendiente'] ?? 0; ?></h3>
                <small class="text-muted">PENDIENTES</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h3 class="text-warning"><?php echo $stats['en_revision'] ?? 0; ?></h3>
                <small class="text-muted">EN REVISIÓN</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h3 class="text-success"><?php echo $stats['aprobada'] ?? 0; ?></h3>
                <small class="text-muted">APROBADAS</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select name="estado" class="form-select" onchange="this.form.submit()">
                    <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                    <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="en_revision" <?php echo $filtro_estado === 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                    <option value="aprobada" <?php echo $filtro_estado === 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                    <option value="rechazada" <?php echo $filtro_estado === 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="busqueda" class="form-control" placeholder="Buscar por tipo o motivo..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de solicitudes -->
<?php if (count($solicitudes) > 0): ?>
    <?php foreach ($solicitudes as $solicitud): ?>
        <div class="solicitud-card <?php echo $solicitud['estado']; ?>">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-2"><?php echo htmlspecialchars($solicitud['nombre_tipo']); ?></h5>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-hash"></i> <?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?>
                    </p>
                    <p class="mb-2"><?php echo htmlspecialchars(substr($solicitud['motivo'], 0, 150)) . '...'; ?></p>
                    <?php if ($solicitud['trabajadora_asignada']): ?>
                        <p class="small text-muted mb-0">
                            <i class="bi bi-person"></i> Asignada a: <?php echo htmlspecialchars($solicitud['trabajadora_asignada']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-<?php echo getEstadoBadgeClass($solicitud['estado']); ?> mb-3">
                        <?php echo getEstadoTexto($solicitud['estado']); ?>
                    </span>
                    <div>
                        <a href="ver_solicitud.php?id=<?php echo $solicitud['id_solicitud']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver Detalles
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox" style="font-size: 64px; opacity: 0.3;"></i>
        <p class="text-muted mt-3">No se encontraron solicitudes</p>
        <a href="nueva_solicitud.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Crear primera solicitud
        </a>
    </div>
<?php endif; ?>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_estudiante.php';
?>