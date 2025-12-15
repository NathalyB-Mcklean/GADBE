<?php
/**
 * Agenda de Citas - Trabajadora Social
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
$citas = [];
$estadisticas = [];
$fecha_seleccionada = $_GET['fecha'] ?? date('Y-m-d');
$vista = $_GET['vista'] ?? 'semana'; // semana, mes, dia

try {
    $conn = getDBConnection();
    
    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['confirmar_cita'])) {
            $id_cita = $_POST['id_cita'];
            $stmt = $conn->prepare("UPDATE citas SET estado = 'confirmada' WHERE id_cita = ?");
            $stmt->execute([$id_cita]);
            $message = "Cita confirmada exitosamente";
        }
        
        if (isset($_POST['cancelar_cita'])) {
            $id_cita = $_POST['id_cita'];
            $motivo = $_POST['motivo_cancelacion'] ?? '';
            $stmt = $conn->prepare("UPDATE citas SET estado = 'cancelada', motivo_cancelacion = ? WHERE id_cita = ?");
            $stmt->execute([$motivo, $id_cita]);
            $message = "Cita cancelada exitosamente";
        }
        
        if (isset($_POST['reprogramar_cita'])) {
            $id_cita = $_POST['id_cita'];
            $nueva_fecha = $_POST['nueva_fecha'];
            $nueva_hora = $_POST['nueva_hora'];
            
            // Verificar disponibilidad
            $stmt = $conn->prepare("
                SELECT COUNT(*) as ocupado 
                FROM citas 
                WHERE id_trabajadora_social = ? 
                AND fecha_cita = ? 
                AND hora_inicio = ? 
                AND estado IN ('confirmada', 'pendiente_confirmacion')
            ");
            $stmt->execute([$_SESSION['user_id'], $nueva_fecha, $nueva_hora]);
            $ocupado = $stmt->fetch()['ocupado'];
            
            if ($ocupado == 0) {
                $stmt = $conn->prepare("
                    UPDATE citas 
                    SET fecha_cita = ?, hora_inicio = ?, estado = 'confirmada'
                    WHERE id_cita = ?
                ");
                $stmt->execute([$nueva_fecha, $nueva_hora, $id_cita]);
                $message = "Cita reprogramada exitosamente";
            } else {
                $error = "El horario seleccionado no está disponible";
            }
        }
    }
    
    // Obtener estadísticas
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'confirmada' AND fecha_cita >= CURDATE() THEN 1 ELSE 0 END) as proximas,
            SUM(CASE WHEN estado = 'pendiente_confirmacion' THEN 1 ELSE 0 END) as pendientes_confirmacion,
            SUM(CASE WHEN fecha_cita = CURDATE() THEN 1 ELSE 0 END) as hoy,
            SUM(CASE WHEN fecha_cita < CURDATE() AND estado = 'completada' THEN 1 ELSE 0 END) as completadas
        FROM citas 
        WHERE id_trabajadora_social = ?
    ");
    $stmt_stats->execute([$_SESSION['user_id']]);
    $estadisticas = $stmt_stats->fetch();
    
    // Obtener citas según vista
    if ($vista == 'dia') {
        // Citas del día seleccionado
        $stmt = $conn->prepare("
            SELECT c.*, 
                   s.nombre as servicio_nombre,
                   u.nombre_completo as estudiante_nombre,
                   u.correo_institucional,
                   u.telefono,
                   u.facultad
            FROM citas c
            INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
            INNER JOIN usuarios u ON c.id_estudiante = u.id_usuario
            WHERE c.id_trabajadora_social = ? 
            AND c.fecha_cita = ?
            AND c.estado NOT IN ('cancelada')
            ORDER BY c.hora_inicio
        ");
        $stmt->execute([$_SESSION['user_id'], $fecha_seleccionada]);
        $citas = $stmt->fetchAll();
    } else {
        // Citas de la semana actual (desde hoy)
        $stmt = $conn->prepare("
            SELECT c.*, 
                   s.nombre as servicio_nombre,
                   u.nombre_completo as estudiante_nombre,
                   u.correo_institucional,
                   u.telefono
            FROM citas c
            INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
            INNER JOIN usuarios u ON c.id_estudiante = u.id_usuario
            WHERE c.id_trabajadora_social = ? 
            AND c.fecha_cita >= CURDATE()
            AND c.fecha_cita <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND c.estado NOT IN ('cancelada')
            ORDER BY c.fecha_cita, c.hora_inicio
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $citas = $stmt->fetchAll();
    }
    
    // Obtener horarios disponibles
    $stmt_horarios = $conn->prepare("
        SELECT * FROM horarios_atencion 
        WHERE id_trabajadora_social = ? 
        AND activo = 1
        ORDER BY dia_semana, hora_inicio
    ");
    $stmt_horarios->execute([$_SESSION['user_id']]);
    $horarios = $stmt_horarios->fetchAll();
    
    // Obtener bloqueos de horario
    $stmt_bloqueos = $conn->prepare("
        SELECT * FROM bloqueos_horarios 
        WHERE id_trabajadora_social = ? 
        AND fecha_fin >= CURDATE()
        ORDER BY fecha_inicio
    ");
    $stmt_bloqueos->execute([$_SESSION['user_id']]);
    $bloqueos = $stmt_bloqueos->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar la agenda: " . $e->getMessage();
}

$page_title = "Agenda de Citas";
$page_subtitle = "Gestión de citas y horarios de atención";

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

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="stats-card purple">
            <div class="stats-value"><?php echo $estadisticas['total'] ?? 0; ?></div>
            <div class="stats-label">Total Citas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card green">
            <div class="stats-value"><?php echo $estadisticas['proximas'] ?? 0; ?></div>
            <div class="stats-label">Próximas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card orange">
            <div class="stats-value"><?php echo $estadisticas['pendientes_confirmacion'] ?? 0; ?></div>
            <div class="stats-label">Pendientes</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card blue">
            <div class="stats-value"><?php echo $estadisticas['hoy'] ?? 0; ?></div>
            <div class="stats-label">Hoy</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card teal">
            <div class="stats-value"><?php echo $estadisticas['completadas'] ?? 0; ?></div>
            <div class="stats-label">Completadas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <a href="nueva_cita.php" class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; text-decoration: none;">
            <div class="stats-value"><i class="bi bi-plus-circle"></i></div>
            <div class="stats-label">Nueva Cita</div>
        </a>
    </div>
</div>

<!-- Controles de Vista -->
<div class="content-card mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="card-title mb-0">Mi Agenda</h2>
            <small class="text-muted">
                <?php 
                if ($vista == 'dia') {
                    echo "Vista diaria - " . date('d/m/Y', strtotime($fecha_seleccionada));
                } else {
                    echo "Próximas citas (7 días)";
                }
                ?>
            </small>
        </div>
        
        <div class="btn-group">
            <a href="?vista=semana" class="btn btn-outline-purple <?php echo $vista == 'semana' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-week"></i> Semana
            </a>
            <a href="?vista=dia&fecha=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-purple <?php echo $vista == 'dia' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-day"></i> Día
            </a>
        </div>
    </div>
    
    <!-- Selector de fecha para vista día -->
    <?php if ($vista == 'dia'): ?>
    <form method="GET" class="row g-3 mt-3">
        <input type="hidden" name="vista" value="dia">
        <div class="col-md-4">
            <label class="form-label">Seleccionar fecha:</label>
            <input type="date" name="fecha" class="form-control" value="<?php echo $fecha_seleccionada; ?>" 
                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-purple w-100">
                <i class="bi bi-search"></i> Ver
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Lista de Citas -->
<div class="content-card">
    <?php if (count($citas) > 0): ?>
        <div class="row g-3">
            <?php foreach ($citas as $cita): ?>
                <?php
                // Determinar clase según estado
                $card_class = '';
                $badge_class = '';
                if ($cita['estado'] == 'confirmada') {
                    $card_class = 'border-start border-4 border-success';
                    $badge_class = 'bg-success';
                } elseif ($cita['estado'] == 'pendiente_confirmacion') {
                    $card_class = 'border-start border-4 border-warning';
                    $badge_class = 'bg-warning';
                } elseif ($cita['estado'] == 'completada') {
                    $card_class = 'border-start border-4 border-secondary';
                    $badge_class = 'bg-secondary';
                }
                
                // Verificar si es hoy
                $es_hoy = $cita['fecha_cita'] == date('Y-m-d');
                $es_pasada = $cita['fecha_cita'] < date('Y-m-d');
                ?>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 <?php echo $card_class; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($cita['estudiante_nombre']); ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($cita['correo_institucional']); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php 
                                    echo $cita['estado'] == 'pendiente_confirmacion' ? 'Pendiente' : 
                                         ucfirst($cita['estado']);
                                    ?>
                                </span>
                            </div>
                            
                            <hr class="my-2">
                            
                            <div class="mb-2">
                                <i class="bi bi-calendar3 text-primary"></i>
                                <strong><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></strong>
                                <?php if ($es_hoy): ?>
                                    <span class="badge bg-info ms-2">Hoy</span>
                                <?php elseif ($es_pasada): ?>
                                    <span class="badge bg-secondary ms-2">Pasada</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-2">
                                <i class="bi bi-clock text-primary"></i>
                                <?php echo date('H:i', strtotime($cita['hora_inicio'])); ?> - 
                                <?php echo date('H:i', strtotime($cita['hora_fin'])); ?>
                            </div>
                            
                            <div class="mb-2">
                                <i class="bi bi-list-check text-primary"></i>
                                <?php echo htmlspecialchars($cita['servicio_nombre']); ?>
                            </div>
                            
                            <?php if ($cita['ubicacion']): ?>
                            <div class="mb-2">
                                <i class="bi bi-geo-alt text-primary"></i>
                                <small><?php echo htmlspecialchars($cita['ubicacion']); ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cita['notas_estudiante']): ?>
                            <div class="mb-3">
                                <i class="bi bi-chat-left-text text-primary"></i>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($cita['notas_estudiante'], 0, 100)); ?>...</small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Acciones -->
                            <div class="btn-group w-100">
                                <?php if ($cita['estado'] == 'pendiente_confirmacion' && !$es_pasada): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                        <button type="submit" name="confirmar_cita" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-circle"></i> Confirmar
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (!$es_pasada && $cita['estado'] != 'cancelada'): ?>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" data-bs-target="#modalReprogramar<?php echo $cita['id_cita']; ?>">
                                        <i class="bi bi-clock-history"></i> Reprogramar
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" data-bs-target="#modalCancelar<?php echo $cita['id_cita']; ?>">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Reprogramar -->
                    <div class="modal fade" id="modalReprogramar<?php echo $cita['id_cita']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reprogramar Cita</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Nueva Fecha</label>
                                            <input type="date" name="nueva_fecha" class="form-control" 
                                                   min="<?php echo date('Y-m-d'); ?>" 
                                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Nueva Hora</label>
                                            <input type="time" name="nueva_hora" class="form-control" 
                                                   min="08:00" max="17:00" step="900" required>
                                            <small class="text-muted">Horario de atención: 8:00 AM - 5:00 PM</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="reprogramar_cita" class="btn btn-primary">Reprogramar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Cancelar -->
                    <div class="modal fade" id="modalCancelar<?php echo $cita['id_cita']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Cancelar Cita</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                        
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            ¿Está seguro de cancelar esta cita? El estudiante será notificado.
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Motivo de cancelación</label>
                                            <textarea name="motivo_cancelacion" class="form-control" rows="3" 
                                                      placeholder="Ingrese el motivo de la cancelación..." required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No cancelar</button>
                                        <button type="submit" name="cancelar_cita" class="btn btn-danger">Confirmar Cancelación</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x" style="font-size: 64px;"></i>
            <h4>No hay citas programadas</h4>
            <p>
                <?php if ($vista == 'dia'): ?>
                    No tienes citas para el día <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?>.
                <?php else: ?>
                    No tienes citas programadas para los próximos 7 días.
                <?php endif; ?>
            </p>
            <a href="nueva_cita.php" class="btn btn-purple">
                <i class="bi bi-plus-circle"></i> Agendar Nueva Cita
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Gestión de Horarios (Opcional) -->
<?php if (count($horarios) > 0): ?>
<div class="content-card mt-4">
    <h2 class="card-title">Mis Horarios de Atención</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Horario</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $horario): ?>
                <tr>
                    <td><?php echo ucfirst($horario['dia_semana']); ?></td>
                    <td><?php echo date('H:i', strtotime($horario['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($horario['hora_fin'])); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $horario['activo'] ? 'success' : 'secondary'; ?>">
                            <?php echo $horario['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
    .border-teal {
        border-color: #20c997 !important;
    }
    
    .bg-teal {
        background-color: #20c997 !important;
    }
    
    .stats-card.teal {
        border-left-color: #20c997;
    }
    
    .stats-card.teal .stats-value {
        color: #20c997;
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
</script>

<?php
$content = ob_get_clean();
require_once 'layout_trabajadora.php';
?>