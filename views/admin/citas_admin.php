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
$message = null;
$trabajadoras = [];
$servicios = [];

try {
    $conn = getDBConnection();
    
    // Procesar acciones POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // CREAR NUEVA CITA
        if (isset($_POST['crear_cita'])) {
            $id_estudiante = $_POST['id_estudiante'];
            $id_trabajadora = $_POST['id_trabajadora'];
            $id_servicio = $_POST['id_servicio'];
            $fecha_cita = $_POST['fecha_cita'];
            $hora_inicio = $_POST['hora_inicio'];
            $hora_fin = $_POST['hora_fin'];
            $motivo = $_POST['motivo_cita'] ?? '';
            
            // Generar código único
            $codigo_cita = 'CITA-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Verificar disponibilidad
            $stmt_check = $conn->prepare("
                SELECT COUNT(*) as ocupado 
                FROM citas 
                WHERE id_trabajadora_social = ? 
                AND fecha_cita = ? 
                AND hora_inicio = ? 
                AND estado IN ('confirmada', 'pendiente_confirmacion')
            ");
            $stmt_check->execute([$id_trabajadora, $fecha_cita, $hora_inicio]);
            $ocupado = $stmt_check->fetch()['ocupado'];
            
            if ($ocupado == 0) {
                $stmt_insert = $conn->prepare("
                    INSERT INTO citas 
                    (codigo_cita, id_estudiante, id_trabajadora_social, id_servicio, 
                     fecha_cita, hora_inicio, hora_fin, notas_estudiante, estado, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada', NOW())
                ");
                $stmt_insert->execute([
                    $codigo_cita, $id_estudiante, $id_trabajadora, $id_servicio,
                    $fecha_cita, $hora_inicio, $hora_fin, $motivo
                ]);
                $message = "✅ Cita creada exitosamente. Código: " . $codigo_cita;
            } else {
                $error = "❌ El horario seleccionado no está disponible";
            }
        }
        
        // REAGENDAR CITA
        if (isset($_POST['reagendar_cita'])) {
            $id_cita = $_POST['id_cita'];
            $nueva_fecha = $_POST['nueva_fecha'];
            $nueva_hora = $_POST['nueva_hora'];
            $nueva_hora_fin = $_POST['nueva_hora_fin'];
            
            $stmt_update = $conn->prepare("
                UPDATE citas 
                SET fecha_cita = ?, hora_inicio = ?, hora_fin = ?, estado = 'confirmada'
                WHERE id_cita = ?
            ");
            $stmt_update->execute([$nueva_fecha, $nueva_hora, $nueva_hora_fin, $id_cita]);
            $message = "✅ Cita reagendada exitosamente";
        }
        
        // CANCELAR CITA
        if (isset($_POST['cancelar_cita'])) {
            $id_cita = $_POST['id_cita'];
            $motivo = $_POST['motivo_cancelacion'];
            
            $stmt_cancel = $conn->prepare("
                UPDATE citas 
                SET estado = 'cancelada', motivo_cancelacion = ?
                WHERE id_cita = ?
            ");
            $stmt_cancel->execute([$motivo, $id_cita]);
            $message = "✅ Cita cancelada exitosamente";
        }
        
        // ELIMINAR CITA PERMANENTEMENTE
        if (isset($_POST['eliminar_cita'])) {
            $id_cita = $_POST['id_cita'];
            
            // Obtener info antes de eliminar
            $stmt_info = $conn->prepare("
                SELECT codigo_cita, fecha_cita, hora_inicio 
                FROM citas 
                WHERE id_cita = ?
            ");
            $stmt_info->execute([$id_cita]);
            $cita_info = $stmt_info->fetch();
            
            // Eliminar
            $stmt_delete = $conn->prepare("DELETE FROM citas WHERE id_cita = ?");
            $stmt_delete->execute([$id_cita]);
            
            $message = "✅ Cita eliminada permanentemente. Código: " . $cita_info['codigo_cita'];
        }
    }
    
    // Obtener lista de trabajadoras sociales para el formulario
    $stmt_trabajadoras = $conn->prepare("
        SELECT u.id_usuario, u.nombre_completo 
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE r.nombre_rol = 'Trabajadora Social' AND u.activo = 1
        ORDER BY u.nombre_completo
    ");
    $stmt_trabajadoras->execute();
    $trabajadoras = $stmt_trabajadoras->fetchAll();
    
    // Obtener todos los estudiantes activos para el dropdown
    $stmt_estudiantes = $conn->prepare("
        SELECT u.id_usuario, u.nombre_completo, u.correo_institucional, u.facultad
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE r.nombre_rol = 'Estudiante' AND u.activo = 1
        ORDER BY u.nombre_completo
    ");
    $stmt_estudiantes->execute();
    $estudiantes = $stmt_estudiantes->fetchAll();
    
    // Obtener servicios activos
    $stmt_servicios = $conn->prepare("
        SELECT s.id_servicio, s.nombre, s.tipo, c.nombre_categoria
        FROM servicios_ofertas s
        LEFT JOIN categorias_servicios c ON s.id_categoria = c.id_categoria
        WHERE s.activo = 1 AND s.requiere_cita = 1
        ORDER BY c.nombre_categoria, s.nombre
    ");
    $stmt_servicios->execute();
    $servicios = $stmt_servicios->fetchAll();
    
    // Obtener citas del día
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
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo $message; ?>
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
            <button type="button" class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#modalNuevaCita">
                <i class="bi bi-plus-circle"></i> Nueva Cita
            </button>
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
                                    <button type="button" class="btn btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalVerCita<?php echo $cita['id_cita']; ?>"
                                            title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalReagendar<?php echo $cita['id_cita']; ?>"
                                            title="Reagendar">
                                        <i class="bi bi-calendar-event"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalCancelar<?php echo $cita['id_cita']; ?>"
                                            title="Cancelar">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-dark" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEliminar<?php echo $cita['id_cita']; ?>"
                                            title="Eliminar permanentemente">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modales para cada cita -->
        <?php foreach ($citas as $cita): ?>
            
            <!-- Modal Ver Detalles -->
            <div class="modal fade" id="modalVerCita<?php echo $cita['id_cita']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-info-circle"></i> Detalles de la Cita
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted mb-3">
                                                <i class="bi bi-person-circle"></i> Información del Estudiante
                                            </h6>
                                            <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($cita['estudiante_nombre']); ?></p>
                                            <p class="mb-0"><strong>Correo:</strong> <?php echo htmlspecialchars($cita['correo_institucional']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted mb-3">
                                                <i class="bi bi-person-badge"></i> Trabajadora Social
                                            </h6>
                                            <p class="mb-0"><strong><?php echo htmlspecialchars($cita['trabajadora_nombre']); ?></strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted mb-3">
                                                <i class="bi bi-calendar-check"></i> Detalles de la Cita
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-2"><strong>Código:</strong> <?php echo htmlspecialchars($cita['codigo_cita']); ?></p>
                                                    <p class="mb-2"><strong>Servicio:</strong> <?php echo htmlspecialchars($cita['servicio_nombre']); ?></p>
                                                    <p class="mb-2"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-2"><strong>Hora:</strong> 
                                                        <?php echo date('H:i', strtotime($cita['hora_inicio'])); ?> - 
                                                        <?php echo date('H:i', strtotime($cita['hora_fin'])); ?>
                                                    </p>
                                                    <p class="mb-2"><strong>Estado:</strong> 
                                                        <span class="badge bg-<?php echo getEstadoCitaBadgeClass($cita['estado']); ?>">
                                                            <?php echo getEstadoCitaTexto($cita['estado']); ?>
                                                        </span>
                                                    </p>
                                                    <p class="mb-2"><strong>Creada:</strong> <?php echo date('d/m/Y H:i', strtotime($cita['fecha_creacion'])); ?></p>
                                                </div>
                                            </div>
                                            <?php if (!empty($cita['notas_estudiante'])): ?>
                                                <hr>
                                                <p class="mb-0"><strong>Motivo:</strong><br><?php echo nl2br(htmlspecialchars($cita['notas_estudiante'])); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($cita['motivo_cancelacion'])): ?>
                                                <hr>
                                                <div class="alert alert-danger mb-0">
                                                    <strong>Motivo de cancelación:</strong><br><?php echo nl2br(htmlspecialchars($cita['motivo_cancelacion'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Reagendar -->
            <div class="modal fade" id="modalReagendar<?php echo $cita['id_cita']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-event"></i> Reagendar Cita
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                
                                <div class="alert alert-info">
                                    <strong>Cita actual:</strong><br>
                                    Fecha: <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?><br>
                                    Hora: <?php echo date('H:i', strtotime($cita['hora_inicio'])); ?> - 
                                          <?php echo date('H:i', strtotime($cita['hora_fin'])); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nueva Fecha</label>
                                    <input type="date" name="nueva_fecha" class="form-control" 
                                           value="<?php echo $cita['fecha_cita']; ?>"
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Hora Inicio</label>
                                        <input type="time" name="nueva_hora" class="form-control" 
                                               value="<?php echo date('H:i', strtotime($cita['hora_inicio'])); ?>"
                                               min="08:00" max="17:00" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Hora Fin</label>
                                        <input type="time" name="nueva_hora_fin" class="form-control" 
                                               value="<?php echo date('H:i', strtotime($cita['hora_fin'])); ?>"
                                               min="08:00" max="18:00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" name="reagendar_cita" class="btn btn-warning">
                                    <i class="bi bi-check-circle"></i> Confirmar Reagendamiento
                                </button>
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
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-x-circle"></i> Cancelar Cita
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>¿Está seguro de cancelar esta cita?</strong>
                                </div>
                                
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Estudiante:</strong> <?php echo htmlspecialchars($cita['estudiante_nombre']); ?></p>
                                        <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_inicio'])); ?></p>
                                        <p class="mb-0"><strong>Servicio:</strong> <?php echo htmlspecialchars($cita['servicio_nombre']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Motivo de Cancelación</label>
                                    <textarea name="motivo_cancelacion" class="form-control" rows="3" 
                                              placeholder="Explique el motivo de la cancelación..." required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener cita</button>
                                <button type="submit" name="cancelar_cita" class="btn btn-danger">
                                    <i class="bi bi-x-circle"></i> Sí, cancelar cita
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Modal Eliminar Permanentemente -->
            <div class="modal fade" id="modalEliminar<?php echo $cita['id_cita']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-trash"></i> Eliminar Cita Permanentemente
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                                
                                <div class="alert alert-danger">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-exclamation-triangle-fill"></i> 
                                        ¡ADVERTENCIA! Esta acción NO se puede deshacer
                                    </h6>
                                    <p class="mb-0">La cita será eliminada permanentemente de la base de datos.</p>
                                </div>
                                
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Información de la Cita:</h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <li><strong>Código:</strong> <?php echo htmlspecialchars($cita['codigo_cita']); ?></li>
                                            <li><strong>Estudiante:</strong> <?php echo htmlspecialchars($cita['estudiante_nombre']); ?></li>
                                            <li><strong>Servicio:</strong> <?php echo htmlspecialchars($cita['servicio_nombre']); ?></li>
                                            <li><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_inicio'])); ?></li>
                                            <li><strong>Estado:</strong> 
                                                <span class="badge bg-<?php echo getEstadoCitaBadgeClass($cita['estado']); ?>">
                                                    <?php echo getEstadoCitaTexto($cita['estado']); ?>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-info-circle"></i> 
                                    <strong>Nota:</strong> Solo use esta opción si la cita fue creada por error. 
                                    Para cancelar una cita normalmente, use el botón "Cancelar".
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    No, mantener cita
                                </button>
                                <button type="submit" name="eliminar_cita" class="btn btn-dark">
                                    <i class="bi bi-trash-fill"></i> Sí, eliminar permanentemente
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>No hay citas programadas para esta fecha</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Nueva Cita -->
<div class="modal fade" id="modalNuevaCita" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formNuevaCita">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Crear Nueva Cita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Seleccionar Estudiante -->
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-person-circle"></i> Estudiante *</label>
                            <select name="id_estudiante" class="form-select" required id="selectEstudiante">
                                <option value="">Seleccione un estudiante...</option>
                                <?php foreach ($estudiantes as $estudiante): ?>
                                    <option value="<?php echo $estudiante['id_usuario']; ?>"
                                            data-correo="<?php echo htmlspecialchars($estudiante['correo_institucional']); ?>"
                                            data-facultad="<?php echo htmlspecialchars($estudiante['facultad'] ?? 'N/A'); ?>">
                                        <?php echo htmlspecialchars($estudiante['nombre_completo']); ?> 
                                        - <?php echo htmlspecialchars($estudiante['correo_institucional']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Total de estudiantes: <?php echo count($estudiantes); ?>
                            </small>
                        </div>
                        
                        <!-- Información del estudiante seleccionado -->
                        <div class="col-12" id="infoEstudianteSeleccionado" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="card-title mb-2">
                                        <i class="bi bi-person-check"></i> Información del Estudiante
                                    </h6>
                                    <div class="row small">
                                        <div class="col-md-6">
                                            <p class="mb-0"><strong>Correo:</strong> <span id="infoCorreo"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-0"><strong>Facultad:</strong> <span id="infoFacultad"></span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-person-badge"></i> Trabajadora Social *</label>
                            <select name="id_trabajadora" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($trabajadoras as $trabajadora): ?>
                                    <option value="<?php echo $trabajadora['id_usuario']; ?>">
                                        <?php echo htmlspecialchars($trabajadora['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><i class="bi bi-briefcase"></i> Servicio *</label>
                            <select name="id_servicio" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php 
                                $categoria_actual = '';
                                foreach ($servicios as $servicio): 
                                    if ($servicio['nombre_categoria'] != $categoria_actual) {
                                        if ($categoria_actual != '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($servicio['nombre_categoria'] ?: 'Sin categoría') . '">';
                                        $categoria_actual = $servicio['nombre_categoria'];
                                    }
                                ?>
                                    <option value="<?php echo $servicio['id_servicio']; ?>">
                                        <?php echo htmlspecialchars($servicio['nombre']); ?> 
                                        (<?php echo $servicio['tipo'] == 'servicio' ? 'Servicio' : 'Oferta'; ?>)
                                    </option>
                                <?php 
                                endforeach; 
                                if ($categoria_actual != '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-calendar"></i> Fecha *</label>
                            <input type="date" name="fecha_cita" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-clock"></i> Hora Inicio *</label>
                            <input type="time" name="hora_inicio" class="form-control" 
                                   min="08:00" max="17:00" value="09:00" required>
                            <small class="text-muted">Horario: 8:00 AM - 5:00 PM</small>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-clock-history"></i> Hora Fin *</label>
                            <input type="time" name="hora_fin" class="form-control" 
                                   min="08:00" max="18:00" value="10:00" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label"><i class="bi bi-chat-left-text"></i> Motivo de la Cita</label>
                            <textarea name="motivo_cita" class="form-control" rows="3" 
                                      placeholder="Describa brevemente el motivo de la cita..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="submit" name="crear_cita" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Crear Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Mostrar información del estudiante seleccionado
document.getElementById('selectEstudiante').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    
    if (this.value) {
        // Obtener datos del option seleccionado
        const correo = selectedOption.getAttribute('data-correo');
        const facultad = selectedOption.getAttribute('data-facultad');
        
        // Mostrar información
        document.getElementById('infoCorreo').textContent = correo;
        document.getElementById('infoFacultad').textContent = facultad;
        document.getElementById('infoEstudianteSeleccionado').style.display = 'block';
    } else {
        // Ocultar información si no hay selección
        document.getElementById('infoEstudianteSeleccionado').style.display = 'none';
    }
});
</script>

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