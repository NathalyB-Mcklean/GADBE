<?php
/**
 * Gestión de Solicitudes - Administrador
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
$solicitudes = [];
$estados = ['pendiente', 'en_revision', 'aprobada', 'rechazada', 'requiere_informacion', 'derivada'];
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
$error = null;

try {
    $conn = getDBConnection();
    
    $sql = "
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
    ";
    
    $params = [];
    
    if ($estado_filtro) {
        $sql .= " WHERE s.estado = ?";
        $params[] = $estado_filtro;
    }
    
    $sql .= " ORDER BY s.fecha_solicitud DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar las solicitudes: " . $e->getMessage();
}

$page_title = "Gestión de Solicitudes";
$page_subtitle = "Administrar solicitudes de los estudiantes";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="content-card">
    <h2 class="card-title">Gestión de Solicitudes</h2>
    
    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-md-3">
            <form method="get" class="d-flex">
                <select class="form-select" name="estado" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo $estado; ?>" <?php echo $estado_filtro == $estado ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $estado)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="col-md-9 text-end">
            <a href="#" class="btn btn-admin">
                <i class="bi bi-download"></i> Exportar
            </a>
        </div>
    </div>
    
    <?php if (count($solicitudes) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Asignada a</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($solicitud['estudiante_nombre']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($solicitud['facultad']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($solicitud['nombre_tipo']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                            <td>
                                <?php if ($solicitud['trabajadora_nombre']): ?>
                                    <?php echo htmlspecialchars($solicitud['trabajadora_nombre']); ?>
                                <?php else: ?>
                                    <span class="badge bg-warning">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getEstadoBadgeClass($solicitud['estado']); ?>">
                                    <?php echo getEstadoTexto($solicitud['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="#" class="btn btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-warning" title="Asignar">
                                        <i class="bi bi-person-plus"></i>
                                    </a>
                                    <a href="#" class="btn btn-outline-info" title="Seguimiento">
                                        <i class="bi bi-chat-left-text"></i>
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
            <i class="bi bi-file-earmark-text"></i>
            <p>No hay solicitudes</p>
        </div>
    <?php endif; ?>
</div>

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
        'requiere_informacion' => 'Requiere Info',
        'derivada' => 'Derivada'
    ];
    return $estados[$estado] ?? ucfirst($estado);
}

$content = ob_get_clean();
require_once 'layout_admin.php';
?>