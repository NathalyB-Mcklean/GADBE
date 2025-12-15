<?php
/**
 * Mis Citas
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

// Verificar que el usuario esté autenticado y sea estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

// Inicializar variables
$citas = [];
$citas_pasadas = [];
$citas_futuras = [];
$error = null;
$success = null;
$mostrar_formulario = false;
$servicios_disponibles = [];
$trabajadoras_sociales = [];

try {
    $conn = getDBConnection();
    
    // Verificar si se está creando una nueva cita
    if (isset($_GET['accion']) && $_GET['accion'] === 'nueva') {
        $mostrar_formulario = true;
        
        // Obtener servicios disponibles para citas
        $stmt_servicios = $conn->query("
            SELECT * FROM servicios_ofertas 
            WHERE activo = 1 AND requiere_cita = 1
            ORDER BY nombre
        ");
        $servicios_disponibles = $stmt_servicios->fetchAll();
        
        // Obtener trabajadoras sociales
        $stmt_ts = $conn->query("
            SELECT u.* FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            WHERE r.nombre_rol IN ('Trabajadora Social', 'Coordinador')
            AND u.activo = 1
            ORDER BY u.nombre_completo
        ");
        $trabajadoras_sociales = $stmt_ts->fetchAll();
    }
    
    // Procesar cancelación de cita
    if (isset($_GET['cancelar']) && is_numeric($_GET['cancelar'])) {
        $id_cita = $_GET['cancelar'];
        
        // Verificar que la cita pertenezca al estudiante
        $stmt_check = $conn->prepare("
            SELECT * FROM citas 
            WHERE id_cita = ? AND id_estudiante = ?
            AND estado IN ('confirmada', 'pendiente_confirmacion')
            AND fecha_cita >= CURDATE()
        ");
        $stmt_check->execute([$id_cita, $_SESSION['user_id']]);
        $cita = $stmt_check->fetch();
        
        if ($cita) {
            $stmt_cancel = $conn->prepare("
                UPDATE citas 
                SET estado = 'cancelada', 
                    fecha_cancelacion = NOW(),
                    motivo_cancelacion = ?
                WHERE id_cita = ?
            ");
            $stmt_cancel->execute(['Cancelada por el estudiante', $id_cita]);
            
            $success = "Cita cancelada exitosamente";
        } else {
            $error = "No se puede cancelar esta cita";
        }
    }
    
    // Procesar nueva cita
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_cita'])) {
        $id_servicio = $_POST['id_servicio'] ?? '';
        $id_trabajadora_social = $_POST['id_trabajadora_social'] ?? '';
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');
        
        // Validaciones
        if (empty($id_servicio) || empty($id_trabajadora_social) || empty($fecha_cita) || empty($hora_cita)) {
            throw new Exception("Todos los campos obligatorios deben ser completados");
        }
        
        // Verificar que la fecha sea futura
        if (strtotime($fecha_cita) < strtotime(date('Y-m-d'))) {
            throw new Exception("No se pueden programar citas en fechas pasadas");
        }
        
        // Verificar disponibilidad
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as count FROM citas 
            WHERE id_trabajadora_social = ? 
            AND fecha_cita = ? 
            AND hora_inicio = ?
            AND estado NOT IN ('cancelada', 'completada')
        ");
        $stmt_check->execute([$id_trabajadora_social, $fecha_cita, $hora_cita]);
        $disponibilidad = $stmt_check->fetch();
        
        if ($disponibilidad['count'] > 0) {
            throw new Exception("El horario seleccionado no está disponible. Por favor, elija otro horario.");
        }
        
        // Generar código de cita
        $codigo_cita = 'CITA-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calcular hora de fin (1 hora por defecto)
        $hora_fin = date('H:i:s', strtotime($hora_cita) + 3600);
        
        // Insertar cita
        $stmt_insert = $conn->prepare("
            INSERT INTO citas (
                codigo_cita,
                id_estudiante,
                id_servicio,
                id_trabajadora_social,
                fecha_cita,
                hora_inicio,
                hora_fin,
                estado,
                notas_estudiante,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmada', ?, NOW())
        ");
        
        $stmt_insert->execute([
            $codigo_cita,
            $_SESSION['user_id'],
            $id_servicio,
            $id_trabajadora_social,
            $fecha_cita,
            $hora_cita,
            $hora_fin,
            $notas
        ]);
        
        $success = "Cita programada exitosamente con el código: $codigo_cita";
        $mostrar_formulario = false;
    }
    
    // Obtener citas del estudiante
    $stmt_citas = $conn->prepare("
        SELECT c.*, 
               s.nombre as servicio_nombre,
               CONCAT(u.nombre_completo) as trabajadora_social,
               ts.nombre_tipo
        FROM citas c
        INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
        INNER JOIN usuarios u ON c.id_trabajadora_social = u.id_usuario
        INNER JOIN tipos_solicitud ts ON s.id_categoria = ts.id_tipo_solicitud
        WHERE c.id_estudiante = ?
        ORDER BY c.fecha_cita DESC, c.hora_inicio DESC
    ");
    $stmt_citas->execute([$_SESSION['user_id']]);
    $citas = $stmt_citas->fetchAll();
    
    // Separar citas pasadas y futuras
    foreach ($citas as $cita) {
        if (strtotime($cita['fecha_cita']) < strtotime(date('Y-m-d'))) {
            $citas_pasadas[] = $cita;
        } else {
            $citas_futuras[] = $cita;
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Variables para el layout
$page_title = "Mis Citas";
$page_subtitle = "Gestiona tus citas con las trabajadoras sociales";

// Capturar contenido
ob_start();
?>

<style>
    .content-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .page-header {
        background: linear-gradient(135deg, #2d8659 0%, #1a5c3a 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        margin: 0;
        font-size: 28px;
    }
    
    .cita-card {
        border-left: 4px solid;
        padding: 20px;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .cita-card.futura {
        border-left-color: #0d6efd;
    }
    
    .cita-card.pasada {
        border-left-color: #6c757d;
    }
    
    .cita-card.cancelada {
        border-left-color: #dc3545;
        opacity: 0.7;
    }
    
    .cita-card.completada {
        border-left-color: #198754;
    }
    
    .estado-badge {
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 20px;
    }
    
    .btn-utp {
        background: linear-gradient(135deg, #2d8659 0%, #1a5c3a 100%);
        color: white;
        border: none;
        padding: 10px 25px;
        font-weight: 600;
    }
    
    .btn-utp:hover {
        background: linear-gradient(135deg, #1a5c3a 0%, #2d8659 100%);
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
</style>

<!-- Mostrar mensajes -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1><i class="bi bi-calendar-event"></i> Mis Citas</h1>
    <?php if (!$mostrar_formulario): ?>
        <a href="?accion=nueva" class="btn btn-light">
            <i class="bi bi-calendar-plus"></i> Nueva Cita
        </a>
    <?php else: ?>
        <a href="citas.php" class="btn btn-light">
            <i class="bi bi-arrow-left"></i> Volver a Mis Citas
        </a>
    <?php endif; ?>
</div>

<?php if ($mostrar_formulario): ?>
    <!-- Formulario para nueva cita -->
    <div class="content-card">
        <h3 class="mb-4">Programar Nueva Cita</h3>
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_servicio" class="form-label required">Servicio</label>
                    <select class="form-select" id="id_servicio" name="id_servicio" required>
                        <option value="">Seleccione un servicio</option>
                        <?php foreach ($servicios_disponibles as $servicio): ?>
                            <option value="<?php echo $servicio['id_servicio']; ?>">
                                <?php echo htmlspecialchars($servicio['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="id_trabajadora_social" class="form-label required">Trabajadora Social</label>
                    <select class="form-select" id="id_trabajadora_social" name="id_trabajadora_social" required>
                        <option value="">Seleccione una trabajadora social</option>
                        <?php foreach ($trabajadoras_sociales as $ts): ?>
                            <option value="<?php echo $ts['id_usuario']; ?>">
                                <?php echo htmlspecialchars($ts['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_cita" class="form-label required">Fecha</label>
                    <input type="date" class="form-control" id="fecha_cita" name="fecha_cita" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="hora_cita" class="form-label required">Hora</label>
                    <select class="form-select" id="hora_cita" name="hora_cita" required>
                        <option value="">Seleccione una hora</option>
                        <?php for ($h = 8; $h <= 16; $h++): ?>
                            <?php for ($m = 0; $m < 60; $m += 30): ?>
                                <?php $hora = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?php echo $hora; ?>"><?php echo $hora; ?></option>
                            <?php endfor; ?>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="notas" class="form-label">Notas (Opcional)</label>
                <textarea class="form-control" id="notas" name="notas" rows="3"
                          placeholder="Notas adicionales para la cita..."></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="citas.php" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" name="crear_cita" class="btn btn-utp">
                    <i class="bi bi-calendar-check"></i> Programar Cita
                </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Lista de citas -->
    <?php if (count($citas_futuras) > 0): ?>
        <div class="content-card">
            <h3 class="mb-4">Próximas Citas</h3>
            <?php foreach ($citas_futuras as $cita): ?>
                <div class="cita-card futura">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($cita['servicio_nombre']); ?></h5>
                            <p class="text-muted mb-1">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($cita['trabajadora_social']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-calendar"></i> 
                                <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?>
                                <i class="bi bi-clock ms-3"></i> 
                                <?php echo date('H:i', strtotime($cita['hora_inicio'])); ?>
                            </p>
                            <?php if (!empty($cita['notas_estudiante'])): ?>
                                <p class="mb-1"><i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($cita['notas_estudiante']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <span class="badge estado-badge bg-<?php 
                                switch($cita['estado']) {
                                    case 'confirmada': echo 'success'; break;
                                    case 'pendiente_confirmacion': echo 'warning'; break;
                                    case 'cancelada': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $cita['estado'])); ?>
                            </span>
                            <div class="mt-2">
                                <?php if ($cita['estado'] === 'confirmada' || $cita['estado'] === 'pendiente_confirmacion'): ?>
                                    <a href="?cancelar=<?php echo $cita['id_cita']; ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('¿Está seguro de que desea cancelar esta cita?')">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </a>
                                <?php endif; ?>
                                </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (count($citas_pasadas) > 0): ?>
        <div class="content-card">
            <h3 class="mb-4">Citas Pasadas</h3>
            <?php foreach ($citas_pasadas as $cita): ?>
                <div class="cita-card pasada">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($cita['servicio_nombre']); ?></h5>
                            <p class="text-muted mb-1">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($cita['trabajadora_social']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-calendar"></i> 
                                <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?>
                                <i class="bi bi-clock ms-3"></i> 
                                <?php echo date('H:i', strtotime($cita['hora_inicio'])); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge estado-badge bg-<?php 
                                switch($cita['estado']) {
                                    case 'completada': echo 'success'; break;
                                    case 'cancelada': echo 'danger'; break;
                                    case 'no_asistio': echo 'warning'; break;
                                    default: echo 'secondary';
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $cita['estado'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (count($citas) === 0): ?>
        <div class="content-card">
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h4>No tienes citas programadas</h4>
                <p class="mb-4">Programa tu primera cita con una trabajadora social.</p>
                <a href="?accion=nueva" class="btn btn-utp">
                    <i class="bi bi-calendar-plus"></i> Programar mi primera cita
                </a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
    // Establecer la fecha mínima como hoy
    document.getElementById('fecha_cita')?.min = new Date().toISOString().split('T')[0];
</script>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_estudiante.php';
?>