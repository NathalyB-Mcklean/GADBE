<?php
/**
 * Gestión de Solicitudes - Trabajadora Social
 */

// Definir la ruta base
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    header("Location: ../auth/login.php");
    exit();
}

// Procesar acciones
$action = $_GET['action'] ?? '';
$id_solicitud = $_GET['id'] ?? 0;
$message = '';
$error = '';

try {
    $conn = getDBConnection();
    
    // Procesar acción de actualizar estado
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $id_solicitud = $_POST['id_solicitud'];
        $nuevo_estado = $_POST['estado'];
        $comentarios = $_POST['comentarios'] ?? '';
        
        // Obtener estado anterior
        $stmt = $conn->prepare("SELECT estado FROM solicitudes WHERE id_solicitud = ?");
        $stmt->execute([$id_solicitud]);
        $estado_anterior = $stmt->fetch()['estado'];
        
        // Actualizar solicitud
        $stmt = $conn->prepare("
            UPDATE solicitudes 
            SET estado = ?, 
                fecha_respuesta = NOW(),
                comentarios_trabajadora = ?,
                decision_final = ?
            WHERE id_solicitud = ?
        ");
        $stmt->execute([$nuevo_estado, $comentarios, $comentarios, $id_solicitud]);
        
        // Registrar en historial
        $stmt = $conn->prepare("
            INSERT INTO historial_solicitudes 
            (id_solicitud, id_usuario, estado_anterior, estado_nuevo, accion, comentarios)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $accion = "Cambio de estado: " . $estado_anterior . " → " . $nuevo_estado;
        $stmt->execute([$id_solicitud, $_SESSION['user_id'], $estado_anterior, $nuevo_estado, $accion, $comentarios]);
        
        $message = "Estado actualizado exitosamente";
        
        // Si se aprueba, crear beneficio automáticamente para ciertos tipos
        if ($nuevo_estado == 'aprobada') {
            crearBeneficioDesdeSolicitud($conn, $id_solicitud, $_SESSION['user_id']);
        }
    }
    
    // Procesar reasignación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reasignar'])) {
        $id_solicitud = $_POST['id_solicitud'];
        $nueva_trabajadora = $_POST['id_trabajadora'];
        
        $stmt = $conn->prepare("
            UPDATE solicitudes 
            SET id_trabajadora_asignada = ?, 
                fecha_asignacion = NOW()
            WHERE id_solicitud = ?
        ");
        $stmt->execute([$nueva_trabajadora, $id_solicitud]);
        
        $message = "Solicitud reasignada exitosamente";
    }
    
    // Cargar solicitudes según filtros
    $filtro_estado = $_GET['estado'] ?? 'todos';
    $filtro_tipo = $_GET['tipo'] ?? 'todos';
    $filtro_prioridad = $_GET['prioridad'] ?? 'todos';
    
    // Construir consulta base
    $query = "
        SELECT s.*, 
               ts.nombre_tipo,
               u.nombre_completo as estudiante_nombre,
               u.correo_institucional,
               u.facultad,
               u.carrera,
               t.nombre_completo as trabajadora_nombre,
               DATEDIFF(NOW(), s.fecha_solicitud) as dias_pendiente
        FROM solicitudes s
        INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
        INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
        LEFT JOIN usuarios t ON s.id_trabajadora_asignada = t.id_usuario
        WHERE 1=1
    ";
    
    $params = [];
    
    // Aplicar filtros
    if ($filtro_estado != 'todos') {
        $query .= " AND s.estado = ?";
        $params[] = $filtro_estado;
    }
    
    if ($filtro_tipo != 'todos') {
        $query .= " AND s.id_tipo_solicitud = ?";
        $params[] = $filtro_tipo;
    }
    
    if ($filtro_prioridad != 'todos') {
        $query .= " AND s.prioridad = ?";
        $params[] = $filtro_prioridad;
    }
    
    // Si no es coordinador, solo ver sus solicitudes asignadas o sin asignar
    if ($_SESSION['user_role'] !== 'Coordinador') {
        $query .= " AND (s.id_trabajadora_asignada = ? OR s.id_trabajadora_asignada IS NULL)";
        $params[] = $_SESSION['user_id'];
    }
    
    $query .= " ORDER BY 
        CASE s.prioridad 
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            WHEN 'baja' THEN 4
        END,
        s.fecha_solicitud DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();
    
    // Obtener estadísticas
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
            SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
            SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
            SUM(CASE WHEN id_trabajadora_asignada IS NULL THEN 1 ELSE 0 END) as sin_asignar
        FROM solicitudes
        WHERE id_trabajadora_asignada = ? OR id_trabajadora_asignada IS NULL
    ");
    $stmt_stats->execute([$_SESSION['user_id']]);
    $estadisticas = $stmt_stats->fetch();
    
    // Obtener tipos de solicitud para filtro
    $stmt_tipos = $conn->prepare("SELECT * FROM tipos_solicitud WHERE activo = 1 ORDER BY nombre_tipo");
    $stmt_tipos->execute();
    $tipos_solicitud = $stmt_tipos->fetchAll();
    
    // Obtener trabajadoras sociales para reasignación
    $stmt_trabajadoras = $conn->prepare("
        SELECT u.id_usuario, u.nombre_completo 
        FROM usuarios u 
        INNER JOIN roles r ON u.id_rol = r.id_rol 
        WHERE r.nombre_rol IN ('Trabajadora Social', 'Coordinador') 
        AND u.activo = 1
        ORDER BY u.nombre_completo
    ");
    $stmt_trabajadoras->execute();
    $trabajadoras = $stmt_trabajadoras->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar las solicitudes: " . $e->getMessage();
}

// Función para crear beneficio desde solicitud aprobada
function crearBeneficioDesdeSolicitud($conn, $id_solicitud, $id_trabajadora) {
    try {
        // Obtener información de la solicitud
        $stmt = $conn->prepare("
            SELECT s.*, u.id_usuario as id_estudiante, ts.nombre_tipo
            FROM solicitudes s
            INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            WHERE s.id_solicitud = ?
        ");
        $stmt->execute([$id_solicitud]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) return;
        
        // Determinar tipo de beneficio basado en tipo de solicitud
        $tipo_beneficio = $solicitud['nombre_tipo'];
        $monto = null;
        $descripcion = "Aprobado: " . $solicitud['motivo'];
        
        // Configurar montos específicos por tipo (ajustar según necesidades)
        if (strpos($tipo_beneficio, 'aliment') !== false) {
            $monto = 150.00;
        } elseif (strpos($tipo_beneficio, 'transporte') !== false) {
            $monto = 100.00;
        } elseif (strpos($tipo_beneficio, 'bibliográfico') !== false) {
            $monto = 200.00;
        }
        
        // Crear beneficio
        $stmt = $conn->prepare("
            INSERT INTO beneficios_asignados 
            (id_estudiante, id_solicitud, tipo_beneficio, monto_mensual, descripcion, fecha_inicio, estado)
            VALUES (?, ?, ?, ?, ?, CURDATE(), 'activo')
        ");
        $stmt->execute([
            $solicitud['id_estudiante'],
            $id_solicitud,
            $tipo_beneficio,
            $monto,
            $descripcion
        ]);
        
    } catch (Exception $e) {
        // Silenciar error, el beneficio se puede crear manualmente después
    }
}

// Variables para layout
$page_title = "Gestión de Solicitudes";
$page_subtitle = "Revisión y gestión de solicitudes estudiantiles";

// Capturar contenido
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

<!-- Estadísticas Rápidas -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="stats-card purple">
            <div class="stats-value"><?php echo $estadisticas['total'] ?? 0; ?></div>
            <div class="stats-label">Total</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card orange">
            <div class="stats-value"><?php echo $estadisticas['pendientes'] ?? 0; ?></div>
            <div class="stats-label">Pendientes</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card blue">
            <div class="stats-value"><?php echo $estadisticas['en_revision'] ?? 0; ?></div>
            <div class="stats-label">En Revisión</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card green">
            <div class="stats-value"><?php echo $estadisticas['aprobadas'] ?? 0; ?></div>
            <div class="stats-label">Aprobadas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card red">
            <div class="stats-value"><?php echo $estadisticas['rechazadas'] ?? 0; ?></div>
            <div class="stats-label">Rechazadas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card yellow">
            <div class="stats-value"><?php echo $estadisticas['sin_asignar'] ?? 0; ?></div>
            <div class="stats-label">Sin Asignar</div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="content-card mb-4">
    <h2 class="card-title">Filtros</h2>
    <form method="GET" action="" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="todos" <?php echo $filtro_estado == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="en_revision" <?php echo $filtro_estado == 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                <option value="aprobada" <?php echo $filtro_estado == 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                <option value="rechazada" <?php echo $filtro_estado == 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="todos">Todos los tipos</option>
                <?php foreach ($tipos_solicitud as $tipo): ?>
                    <option value="<?php echo $tipo['id_tipo_solicitud']; ?>" 
                        <?php echo $filtro_tipo == $tipo['id_tipo_solicitud'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-select">
                <option value="todos">Todas</option>
                <option value="urgente" <?php echo $filtro_prioridad == 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                <option value="alta" <?php echo $filtro_prioridad == 'alta' ? 'selected' : ''; ?>>Alta</option>
                <option value="media" <?php echo $filtro_prioridad == 'media' ? 'selected' : ''; ?>>Media</option>
                <option value="baja" <?php echo $filtro_prioridad == 'baja' ? 'selected' : ''; ?>>Baja</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-purple w-100">
                <i class="bi bi-funnel"></i> Aplicar Filtros
            </button>
        </div>
    </form>
</div>

<!-- Lista de Solicitudes -->
<div class="content-card">
    <h2 class="card-title d-flex justify-content-between align-items-center">
        <span>Solicitudes</span>
        <span class="badge bg-purple"><?php echo count($solicitudes); ?> encontradas</span>
    </h2>
    
    <?php if (count($solicitudes) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estudiante</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Fecha</th>
                        <th>Asignada a</th>
                        <th>Días</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <?php
                        // Determinar color según prioridad
                        $priority_class = '';
                        if ($solicitud['prioridad'] == 'urgente') $priority_class = 'table-danger';
                        elseif ($solicitud['prioridad'] == 'alta') $priority_class = 'table-warning';
                        elseif ($solicitud['prioridad'] == 'media') $priority_class = 'table-info';
                        
                        // Determinar color según días pendiente
                        $dias_class = '';
                        if ($solicitud['dias_pendiente'] > 7) $dias_class = 'text-danger fw-bold';
                        elseif ($solicitud['dias_pendiente'] > 3) $dias_class = 'text-warning';
                        ?>
                        <tr class="<?php echo $priority_class; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($solicitud['codigo_solicitud']); ?></strong>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($solicitud['estudiante_nombre']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($solicitud['correo_institucional']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($solicitud['nombre_tipo']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo getEstadoBadgeClass($solicitud['estado']); ?>">
                                    <?php echo getEstadoTexto($solicitud['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $solicitud['prioridad'] == 'urgente' ? 'danger' : 'secondary'; ?>">
                                    <?php echo ucfirst($solicitud['prioridad']); ?>
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
                            <td class="<?php echo $dias_class; ?>">
                                <?php echo $solicitud['dias_pendiente']; ?> días
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="detalle_solicitud.php?id=<?php echo $solicitud['id_solicitud']; ?>" 
                                       class="btn btn-outline-primary" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($_SESSION['user_role'] === 'Coordinador' || $solicitud['id_trabajadora_asignada'] == $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-outline-warning" 
                                                data-bs-toggle="modal" data-bs-target="#modalEstado<?php echo $solicitud['id_solicitud']; ?>"
                                                title="Cambiar estado">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['user_role'] === 'Coordinador'): ?>
                                        <button type="button" class="btn btn-outline-info" 
                                                data-bs-toggle="modal" data-bs-target="#modalReasignar<?php echo $solicitud['id_solicitud']; ?>"
                                                title="Reasignar">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Modal para cambiar estado -->
                                <div class="modal fade" id="modalEstado<?php echo $solicitud['id_solicitud']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cambiar Estado</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id_solicitud']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Nuevo Estado</label>
                                                        <select name="estado" class="form-select" required>
                                                            <option value="en_revision" <?php echo $solicitud['estado'] == 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                                                            <option value="aprobada" <?php echo $solicitud['estado'] == 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                                                            <option value="rechazada" <?php echo $solicitud['estado'] == 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                                                            <option value="pendiente" <?php echo $solicitud['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Comentarios</label>
                                                        <textarea name="comentarios" class="form-control" rows="3" 
                                                                  placeholder="Ingrese comentarios sobre la decisión..."><?php echo htmlspecialchars($solicitud['comentarios_trabajadora'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary">Guardar Cambios</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modal para reasignar -->
                                <div class="modal fade" id="modalReasignar<?php echo $solicitud['id_solicitud']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reasignar Solicitud</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_solicitud" value="<?php echo $solicitud['id_solicitud']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Reasignar a:</label>
                                                        <select name="id_trabajadora" class="form-select" required>
                                                            <option value="">Seleccionar trabajadora social...</option>
                                                            <?php foreach ($trabajadoras as $trabajadora): ?>
                                                                <option value="<?php echo $trabajadora['id_usuario']; ?>" 
                                                                    <?php echo $solicitud['id_trabajadora_asignada'] == $trabajadora['id_usuario'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($trabajadora['nombre_completo']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="alert alert-info">
                                                        <small>
                                                            <i class="bi bi-info-circle"></i> 
                                                            La trabajadora asignada recibirá una notificación y será responsable de revisar esta solicitud.
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" name="reasignar" class="btn btn-primary">Reasignar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación (si es necesario) -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted">
                Mostrando <?php echo count($solicitudes); ?> solicitudes
            </div>
            <nav>
                <ul class="pagination pagination-sm">
                    <li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">Siguiente</a></li>
                </ul>
            </nav>
        </div>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-file-earmark-x"></i>
            <h4>No se encontraron solicitudes</h4>
            <p>No hay solicitudes que coincidan con los filtros aplicados.</p>
            <a href="solicitudes_trabajadora.php" class="btn btn-outline-purple">
                <i class="bi bi-arrow-clockwise"></i> Limpiar filtros
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
    .btn-purple {
        background-color: #6B2C91;
        border-color: #6B2C91;
        color: white;
    }
    
    .btn-purple:hover {
        background-color: #4A1D6B;
        border-color: #4A1D6B;
        color: white;
    }
    
    .btn-outline-purple {
        color: #6B2C91;
        border-color: #6B2C91;
    }
    
    .btn-outline-purple:hover {
        background-color: #6B2C91;
        color: white;
    }
    
    .bg-purple {
        background-color: #6B2C91 !important;
    }
    
    .stats-card.red {
        border-left-color: #dc3545;
    }
    
    .stats-card.red .stats-value {
        color: #dc3545;
    }
    
    .stats-card.yellow {
        border-left-color: #ffc107;
    }
    
    .stats-card.yellow .stats-value {
        color: #ffc107;
    }
</style>

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
    
    // Activar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

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

// Incluir layout
require_once 'layout_trabajadora.php';
?>