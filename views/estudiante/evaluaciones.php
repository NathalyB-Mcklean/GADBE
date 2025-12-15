<?php
/**
 * Evaluaciones de Servicios
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
$evaluaciones_pendientes = [];
$evaluaciones_completadas = [];
$encuesta_actual = null;
$preguntas_encuesta = [];
$error = null;
$success = null;

try {
    $conn = getDBConnection();
    
    // Obtener encuestas disponibles para el estudiante
    $stmt_encuestas = $conn->prepare("
        SELECT e.*, s.nombre as servicio_nombre
        FROM encuestas e
        INNER JOIN servicios_ofertas s ON e.id_servicio = s.id_servicio
        WHERE e.activa = 1 
        AND (e.fecha_fin IS NULL OR e.fecha_fin >= CURDATE())
        AND e.id_servicio IN (
            SELECT id_servicio FROM citas 
            WHERE id_estudiante = ? 
            AND estado = 'completada'
            GROUP BY id_servicio
        )
        AND e.id_encuesta NOT IN (
            SELECT id_encuesta FROM respuestas_encuesta 
            WHERE id_estudiante = ? 
            AND estado = 'completada'
        )
    ");
    $stmt_encuestas->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $evaluaciones_pendientes = $stmt_encuestas->fetchAll();
    
    // Obtener evaluaciones completadas
    $stmt_completadas = $conn->prepare("
        SELECT re.*, e.titulo, s.nombre as servicio_nombre
        FROM respuestas_encuesta re
        INNER JOIN encuestas e ON re.id_encuesta = e.id_encuesta
        INNER JOIN servicios_ofertas s ON re.id_servicio = s.id_servicio
        WHERE re.id_estudiante = ?
        ORDER BY re.fecha_respuesta DESC
    ");
    $stmt_completadas->execute([$_SESSION['user_id']]);
    $evaluaciones_completadas = $stmt_completadas->fetchAll();
    
    // Verificar si se está completando una evaluación específica
    if (isset($_GET['evaluar']) && is_numeric($_GET['evaluar'])) {
        $id_encuesta = $_GET['evaluar'];
        
        // Obtener información de la encuesta
        $stmt_encuesta = $conn->prepare("
            SELECT e.*, s.nombre as servicio_nombre
            FROM encuestas e
            INNER JOIN servicios_ofertas s ON e.id_servicio = s.id_servicio
            WHERE e.id_encuesta = ? AND e.activa = 1
        ");
        $stmt_encuesta->execute([$id_encuesta]);
        $encuesta_actual = $stmt_encuesta->fetch();
        
        if ($encuesta_actual) {
            // Verificar que el estudiante tenga acceso a esta encuesta
            $stmt_acceso = $conn->prepare("
                SELECT COUNT(*) as count FROM citas 
                WHERE id_estudiante = ? 
                AND id_servicio = ?
                AND estado = 'completada'
            ");
            $stmt_acceso->execute([$_SESSION['user_id'], $encuesta_actual['id_servicio']]);
            $acceso = $stmt_acceso->fetch();
            
            if ($acceso['count'] == 0) {
                throw new Exception("No tiene acceso a esta evaluación");
            }
            
            // Verificar que no haya completado ya esta encuesta
            $stmt_comprobacion = $conn->prepare("
                SELECT * FROM respuestas_encuesta 
                WHERE id_encuesta = ? AND id_estudiante = ?
            ");
            $stmt_comprobacion->execute([$id_encuesta, $_SESSION['user_id']]);
            
            if ($stmt_comprobacion->fetch()) {
                throw new Exception("Ya ha completado esta evaluación");
            }
            
            // Obtener preguntas de la encuesta
            $stmt_preguntas = $conn->prepare("
                SELECT * FROM preguntas_encuesta 
                WHERE id_encuesta = ?
                ORDER BY orden
            ");
            $stmt_preguntas->execute([$id_encuesta]);
            $preguntas_encuesta = $stmt_preguntas->fetchAll();
        }
    }
    
    // Procesar envío de evaluación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluacion'])) {
        $id_encuesta = $_POST['id_encuesta'] ?? '';
        $id_servicio = $_POST['id_servicio'] ?? '';
        
        if (empty($id_encuesta) || empty($id_servicio)) {
            throw new Exception("Datos de evaluación inválidos");
        }
        
        // Crear respuesta principal
        $stmt_respuesta = $conn->prepare("
            INSERT INTO respuestas_encuesta (
                id_encuesta,
                id_estudiante,
                id_servicio,
                estado
            ) VALUES (?, ?, ?, 'completada')
        ");
        $stmt_respuesta->execute([$id_encuesta, $_SESSION['user_id'], $id_servicio]);
        $id_respuesta = $conn->lastInsertId();
        
        // Procesar cada pregunta
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'pregunta_') === 0) {
                $id_pregunta = str_replace('pregunta_', '', $key);
                
                // Validar que la pregunta existe
                $stmt_pregunta = $conn->prepare("
                    SELECT * FROM preguntas_encuesta 
                    WHERE id_pregunta = ? AND id_encuesta = ?
                ");
                $stmt_pregunta->execute([$id_pregunta, $id_encuesta]);
                $pregunta = $stmt_pregunta->fetch();
                
                if ($pregunta) {
                    // Validar preguntas obligatorias
                    if ($pregunta['obligatoria'] && empty($value)) {
                        throw new Exception("La pregunta '{$pregunta['texto_pregunta']}' es obligatoria");
                    }
                    
                    // Insertar respuesta
                    $stmt_detalle = $conn->prepare("
                        INSERT INTO detalles_respuesta_encuesta (
                            id_respuesta,
                            id_pregunta,
                            valor_respuesta
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt_detalle->execute([$id_respuesta, $id_pregunta, $value]);
                }
            }
        }
        
        $success = "¡Gracias! Su evaluación ha sido registrada exitosamente.";
        header("refresh:2;url=evaluaciones.php");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Variables para el layout
$page_title = "Evaluaciones de Servicios";
$page_subtitle = "Ayúdanos a mejorar nuestros servicios";

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
    
    .encuesta-card {
        border-left: 4px solid #0d6efd;
        padding: 20px;
        margin-bottom: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .encuesta-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .pregunta-item {
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #eee;
    }
    
    .pregunta-item:last-child {
        border-bottom: none;
    }
    
    .rating-stars {
        display: flex;
        gap: 5px;
        font-size: 24px;
        color: #ffc107;
    }
    
    .star {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .star:hover {
        transform: scale(1.2);
    }
    
    .star.selected {
        color: #ff9800;
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
    
    .progress-container {
        margin: 30px 0;
    }
    
    .progress-step {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
        color: #6c757d;
    }
    
    .step-number.active {
        background: #2d8659;
        color: white;
    }
    
    .step-number.completed {
        background: #198754;
        color: white;
    }
    
    .step-content {
        flex-grow: 1;
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

<div class="page-header">
    <h1><i class="bi bi-star-fill"></i> Evaluaciones de Servicios</h1>
</div>

<?php if ($encuesta_actual): ?>
    <!-- Formulario de evaluación -->
    <div class="content-card">
        <div class="progress-container">
            <div class="progress-step">
                <div class="step-number completed">1</div>
                <div class="step-content">
                    <h6>Selección de evaluación</h6>
                    <small>Ha seleccionado: <?php echo htmlspecialchars($encuesta_actual['servicio_nombre']); ?></small>
                </div>
            </div>
            <div class="progress-step">
                <div class="step-number active">2</div>
                <div class="step-content">
                    <h6>Completar evaluación</h6>
                    <small>Responda todas las preguntas</small>
                </div>
            </div>
            <div class="progress-step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h6>Confirmación</h6>
                    <small>Enviar y confirmar evaluación</small>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i>
            <strong><?php echo htmlspecialchars($encuesta_actual['titulo']); ?></strong><br>
            <?php echo htmlspecialchars($encuesta_actual['descripcion']); ?>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="id_encuesta" value="<?php echo $encuesta_actual['id_encuesta']; ?>">
            <input type="hidden" name="id_servicio" value="<?php echo $encuesta_actual['id_servicio']; ?>">
            
            <?php foreach ($preguntas_encuesta as $index => $pregunta): ?>
                <div class="pregunta-item">
                    <h5>
                        <?php echo ($index + 1) . '. ' . htmlspecialchars($pregunta['texto_pregunta']); ?>
                        <?php if ($pregunta['obligatoria']): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </h5>
                    
                    <?php if ($pregunta['tipo_pregunta'] === 'escala'): ?>
                        <div class="rating-stars mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="star" data-value="<?php echo $i; ?>">
                                    <i class="bi bi-star"></i>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="pregunta_<?php echo $pregunta['id_pregunta']; ?>" id="rating_<?php echo $pregunta['id_pregunta']; ?>">
                        
                    <?php elseif ($pregunta['tipo_pregunta'] === 'multiple'): ?>
                        <?php 
                        $opciones = json_decode($pregunta['opciones'], true);
                        if (is_array($opciones)):
                        ?>
                            <div class="btn-group-vertical" role="group">
                                <?php foreach ($opciones as $opcion): ?>
                                    <button type="button" class="btn btn-outline-primary text-start mb-2 multiple-option"
                                            data-pregunta="<?php echo $pregunta['id_pregunta']; ?>"
                                            data-value="<?php echo htmlspecialchars($opcion); ?>">
                                        <?php echo htmlspecialchars($opcion); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="pregunta_<?php echo $pregunta['id_pregunta']; ?>" id="multiple_<?php echo $pregunta['id_pregunta']; ?>">
                        <?php endif; ?>
                        
                    <?php elseif ($pregunta['tipo_pregunta'] === 'abierta'): ?>
                        <textarea class="form-control" 
                                  name="pregunta_<?php echo $pregunta['id_pregunta']; ?>"
                                  rows="3" 
                                  placeholder="Escriba su respuesta aquí..."
                                  <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="evaluaciones.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" name="submit_evaluacion" class="btn btn-utp">
                    <i class="bi bi-send-fill"></i> Enviar Evaluación
                </button>
            </div>
        </form>
    </div>
    
<?php else: ?>
    <!-- Lista de evaluaciones pendientes -->
    <?php if (count($evaluaciones_pendientes) > 0): ?>
        <div class="content-card">
            <h3 class="mb-4">Evaluaciones Pendientes</h3>
            <p class="text-muted mb-4">Complete las siguientes evaluaciones para ayudarnos a mejorar nuestros servicios.</p>
            
            <?php foreach ($evaluaciones_pendientes as $encuesta): ?>
                <div class="encuesta-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($encuesta['titulo']); ?></h5>
                            <p class="text-muted mb-1">
                                <i class="bi bi-list-check"></i> <?php echo htmlspecialchars($encuesta['servicio_nombre']); ?>
                            </p>
                            <p class="mb-1">
                                <?php echo htmlspecialchars($encuesta['descripcion']); ?>
                            </p>
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> 
                                <?php 
                                    if ($encuesta['fecha_fin']) {
                                        echo 'Disponible hasta: ' . date('d/m/Y', strtotime($encuesta['fecha_fin']));
                                    } else {
                                        echo 'Disponible indefinidamente';
                                    }
                                ?>
                            </small>
                        </div>
                        <a href="?evaluar=<?php echo $encuesta['id_encuesta']; ?>" class="btn btn-utp">
                            <i class="bi bi-pencil-square"></i> Completar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Lista de evaluaciones completadas -->
    <?php if (count($evaluaciones_completadas) > 0): ?>
        <div class="content-card">
            <h3 class="mb-4">Evaluaciones Completadas</h3>
            
            <?php foreach ($evaluaciones_completadas as $evaluacion): ?>
                <div class="encuesta-card" style="border-left-color: #198754;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($evaluacion['titulo']); ?></h5>
                            <p class="text-muted mb-1">
                                <i class="bi bi-list-check"></i> <?php echo htmlspecialchars($evaluacion['servicio_nombre']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-calendar"></i> 
                                Completada: <?php echo date('d/m/Y H:i', strtotime($evaluacion['fecha_respuesta'])); ?>
                            </p>
                        </div>
                        <span class="badge bg-success">Completada</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (count($evaluaciones_pendientes) === 0 && count($evaluaciones_completadas) === 0): ?>
        <div class="content-card">
            <div class="empty-state">
                <i class="bi bi-star"></i>
                <h4>No hay evaluaciones disponibles</h4>
                <p class="mb-4">Complete servicios o citas para habilitar evaluaciones.</p>
                <a href="servicios.php" class="btn btn-utp">
                    <i class="bi bi-list-ul"></i> Ver servicios disponibles
                </a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
    // Manejar estrellas de calificación
    document.querySelectorAll('.star').forEach(star => {
        star.addEventListener('click', function() {
            const preguntaId = this.closest('.pregunta-item').querySelector('input[type="hidden"]').id.split('_')[1];
            const value = this.getAttribute('data-value');
            
            // Actualizar estrellas visualmente
            const stars = this.parentElement.querySelectorAll('.star');
            stars.forEach((s, index) => {
                if (index < value) {
                    s.classList.add('selected');
                    s.querySelector('i').className = 'bi bi-star-fill';
                } else {
                    s.classList.remove('selected');
                    s.querySelector('i').className = 'bi bi-star';
                }
            });
            
            // Actualizar valor oculto
            document.getElementById(`rating_${preguntaId}`).value = value;
        });
    });
    
    // Manejar opciones múltiples
    document.querySelectorAll('.multiple-option').forEach(option => {
        option.addEventListener('click', function() {
            const preguntaId = this.getAttribute('data-pregunta');
            const value = this.getAttribute('data-value');
            
            // Remover selección previa
            document.querySelectorAll(`[data-pregunta="${preguntaId}"]`).forEach(btn => {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            });
            
            // Seleccionar actual
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
            
            // Actualizar valor oculto
            document.getElementById(`multiple_${preguntaId}`).value = value;
        });
    });
</script>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_estudiante.php';
?>