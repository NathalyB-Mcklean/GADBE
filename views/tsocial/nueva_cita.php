<?php
/**
 * Nueva Cita - Trabajadora Social
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Verificar rol
if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$error = '';
$estudiantes = [];
$servicios = [];
$horarios_disponibles = [];

try {
    $conn = getDBConnection();
    
    // Procesar el formulario cuando se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_cita'])) {
        
        $id_estudiante = $_POST['id_estudiante'];
        $id_servicio = $_POST['id_servicio'];
        $fecha_cita = $_POST['fecha_cita'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fin = $_POST['hora_fin'];
        $ubicacion = $_POST['ubicacion'] ?? '';
        $notas_trabajadora = $_POST['notas_trabajadora'] ?? '';
        $estado = $_POST['estado'] ?? 'confirmada';
        
        // Validaciones
        if (empty($id_estudiante)) {
            throw new Exception("Debe seleccionar un estudiante");
        }
        
        if (empty($id_servicio)) {
            throw new Exception("Debe seleccionar un servicio");
        }
        
        if (empty($fecha_cita)) {
            throw new Exception("Debe seleccionar una fecha");
        }
        
        if (empty($hora_inicio) || empty($hora_fin)) {
            throw new Exception("Debe especificar la hora de inicio y fin");
        }
        
        // Validar que la fecha no sea en el pasado
        if (strtotime($fecha_cita) < strtotime(date('Y-m-d'))) {
            throw new Exception("No se pueden crear citas en fechas pasadas");
        }
        
        // Validar que la hora de fin sea mayor que la hora de inicio
        if (strtotime($hora_fin) <= strtotime($hora_inicio)) {
            throw new Exception("La hora de fin debe ser posterior a la hora de inicio");
        }
        
        // Verificar disponibilidad - no debe haber otra cita en ese horario
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as conflictos
            FROM citas
            WHERE id_trabajadora_social = ?
            AND fecha_cita = ?
            AND estado NOT IN ('cancelada', 'no_asistio')
            AND (
                (hora_inicio <= ? AND hora_fin > ?) OR
                (hora_inicio < ? AND hora_fin >= ?) OR
                (hora_inicio >= ? AND hora_fin <= ?)
            )
        ");
        $stmt_check->execute([
            $_SESSION['user_id'],
            $fecha_cita,
            $hora_inicio, $hora_inicio,
            $hora_fin, $hora_fin,
            $hora_inicio, $hora_fin
        ]);
        
        $resultado = $stmt_check->fetch();
        
        if ($resultado['conflictos'] > 0) {
            throw new Exception("Ya existe una cita programada en ese horario. Por favor, seleccione otro horario.");
        }
        
        // Verificar que el estudiante no tenga otra cita en ese horario
        $stmt_check_est = $conn->prepare("
            SELECT COUNT(*) as conflictos
            FROM citas
            WHERE id_estudiante = ?
            AND fecha_cita = ?
            AND estado NOT IN ('cancelada', 'no_asistio')
            AND (
                (hora_inicio <= ? AND hora_fin > ?) OR
                (hora_inicio < ? AND hora_fin >= ?) OR
                (hora_inicio >= ? AND hora_fin <= ?)
            )
        ");
        $stmt_check_est->execute([
            $id_estudiante,
            $fecha_cita,
            $hora_inicio, $hora_inicio,
            $hora_fin, $hora_fin,
            $hora_inicio, $hora_fin
        ]);
        
        $resultado_est = $stmt_check_est->fetch();
        
        if ($resultado_est['conflictos'] > 0) {
            throw new Exception("El estudiante ya tiene una cita programada en ese horario.");
        }
        
        // Generar código único para la cita
        $codigo_cita = 'CITA-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Verificar que el código sea único
        $stmt_codigo = $conn->prepare("SELECT COUNT(*) as existe FROM citas WHERE codigo_cita = ?");
        $stmt_codigo->execute([$codigo_cita]);
        while ($stmt_codigo->fetch()['existe'] > 0) {
            $codigo_cita = 'CITA-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt_codigo->execute([$codigo_cita]);
        }
        
        // Insertar la cita
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
                ubicacion,
                notas_trabajadora,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt_insert->execute([
            $codigo_cita,
            $id_estudiante,
            $id_servicio,
            $_SESSION['user_id'],
            $fecha_cita,
            $hora_inicio,
            $hora_fin,
            $estado,
            $ubicacion,
            $notas_trabajadora
        ]);
        
        $message = "Cita creada exitosamente con código: " . $codigo_cita;
        
        // Redirigir a la lista de citas después de 2 segundos
        header("refresh:2;url=citas_trabajadora.php");
        
    }
    
    // Obtener lista de estudiantes activos
    $stmt_estudiantes = $conn->prepare("
        SELECT u.id_usuario, u.nombre_completo, u.correo_institucional, 
               u.telefono, u.facultad, u.carrera
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE r.nombre_rol = 'Estudiante' 
        AND u.activo = 1
        ORDER BY u.nombre_completo
    ");
    $stmt_estudiantes->execute();
    $estudiantes = $stmt_estudiantes->fetchAll();
    
    // Obtener servicios activos de la trabajadora social
    $stmt_servicios = $conn->prepare("
        SELECT s.*, c.nombre_categoria
        FROM servicios_ofertas s
        LEFT JOIN categorias_servicios c ON s.id_categoria = c.id_categoria
        WHERE s.activo = 1
        AND (s.id_trabajadora_social = ? OR s.id_trabajadora_social IS NULL)
        ORDER BY c.nombre_categoria, s.nombre
    ");
    $stmt_servicios->execute([$_SESSION['user_id']]);
    $servicios = $stmt_servicios->fetchAll();
    
    // Obtener horarios de atención de la trabajadora
    $stmt_horarios = $conn->prepare("
        SELECT * FROM horarios_atencion
        WHERE id_trabajadora_social = ?
        AND activo = 1
        ORDER BY dia_semana, hora_inicio
    ");
    $stmt_horarios->execute([$_SESSION['user_id']]);
    $horarios_disponibles = $stmt_horarios->fetchAll();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$page_title = "Nueva Cita";
$page_subtitle = "Programar nueva cita con estudiante";

ob_start();
?>

<!-- Alertas -->
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

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="citas_trabajadora.php">Agenda de Citas</a></li>
        <li class="breadcrumb-item active">Nueva Cita</li>
    </ol>
</nav>

<!-- Formulario de Nueva Cita -->
<div class="card shadow-sm">
    <div class="card-header bg-purple text-white">
        <h5 class="mb-0">
            <i class="bi bi-calendar-plus"></i> Programar Nueva Cita
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="formNuevaCita">
            <div class="row g-3">
                
                <!-- Selección de Estudiante -->
                <div class="col-md-6">
                    <label for="id_estudiante" class="form-label">
                        <i class="bi bi-person"></i> Estudiante *
                    </label>
                    <select name="id_estudiante" id="id_estudiante" class="form-select" required>
                        <option value="">Seleccionar estudiante...</option>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <option value="<?php echo $estudiante['id_usuario']; ?>"
                                    data-correo="<?php echo htmlspecialchars($estudiante['correo_institucional']); ?>"
                                    data-telefono="<?php echo htmlspecialchars($estudiante['telefono']); ?>"
                                    data-facultad="<?php echo htmlspecialchars($estudiante['facultad']); ?>"
                                    data-carrera="<?php echo htmlspecialchars($estudiante['carrera']); ?>">
                                <?php echo htmlspecialchars($estudiante['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Busque por nombre del estudiante</small>
                </div>
                
                <!-- Información del Estudiante (se muestra al seleccionar) -->
                <div class="col-md-6" id="infoEstudiante" style="display: none;">
                    <label class="form-label">Información del Estudiante</label>
                    <div class="card bg-light">
                        <div class="card-body p-2">
                            <small>
                                <div><strong>Correo:</strong> <span id="estudianteCorreo"></span></div>
                                <div><strong>Teléfono:</strong> <span id="estudianteTelefono"></span></div>
                                <div><strong>Facultad:</strong> <span id="estudianteFacultad"></span></div>
                                <div><strong>Carrera:</strong> <span id="estudianteCarrera"></span></div>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Selección de Servicio -->
                <div class="col-md-12">
                    <label for="id_servicio" class="form-label">
                        <i class="bi bi-card-list"></i> Servicio/Oferta *
                    </label>
                    <select name="id_servicio" id="id_servicio" class="form-select" required>
                        <option value="">Seleccionar servicio u oferta...</option>
                        <?php 
                        $categoria_actual = '';
                        foreach ($servicios as $servicio): 
                            if ($categoria_actual != $servicio['nombre_categoria']) {
                                if ($categoria_actual != '') echo '</optgroup>';
                                $categoria_actual = $servicio['nombre_categoria'];
                                echo '<optgroup label="📂 ' . htmlspecialchars($categoria_actual ?: 'Sin Categoría') . '">';
                            }
                        ?>
                            <option value="<?php echo $servicio['id_servicio']; ?>"
                                    data-duracion="<?php echo $servicio['duracion_estimada']; ?>"
                                    data-ubicacion="<?php echo htmlspecialchars($servicio['ubicacion']); ?>"
                                    data-nombre="<?php echo htmlspecialchars($servicio['nombre']); ?>"
                                    data-categoria="<?php echo htmlspecialchars($servicio['nombre_categoria']); ?>"
                                    data-tipo="<?php echo $servicio['tipo']; ?>">
                                <?php 
                                $icono = $servicio['tipo'] == 'servicio' ? '🔧' : '🎁';
                                echo $icono . ' ' . htmlspecialchars($servicio['nombre']);
                                ?>
                            </option>
                        <?php 
                        endforeach; 
                        if ($categoria_actual != '') echo '</optgroup>';
                        ?>
                    </select>
                    <small class="form-text text-muted">
                        Los servicios están organizados por categoría. 🔧 = Servicio | 🎁 = Oferta
                    </small>
                </div>
                
                <!-- Información del Servicio Seleccionado -->
                <div class="col-md-12" id="infoServicio" style="display: none;">
                    <div class="card bg-light border-primary">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">
                                <i class="bi bi-info-circle-fill text-primary"></i> 
                                Información del Servicio/Oferta
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <small>
                                        <strong>Categoría:</strong> 
                                        <span id="servicioCategoria" class="badge bg-secondary"></span>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small>
                                        <strong>Tipo:</strong> 
                                        <span id="servicioTipo" class="badge bg-info"></span>
                                    </small>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <small>
                                        <strong>Duración estimada:</strong> 
                                        <span id="servicioDuracion"></span> minutos
                                    </small>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <small>
                                        <strong>Ubicación:</strong> 
                                        <span id="servicioUbicacion"></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estado de la Cita -->
                <div class="col-md-6">
                    <label for="estado" class="form-label">
                        <i class="bi bi-check-circle"></i> Estado de la Cita
                    </label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="confirmada">Confirmada</option>
                        <option value="pendiente_confirmacion">Pendiente de Confirmación</option>
                    </select>
                    <small class="form-text text-muted">Seleccione el estado inicial de la cita</small>
                </div>
                
                <!-- Fecha de la Cita -->
                <div class="col-md-6">
                    <label for="fecha_cita" class="form-label">
                        <i class="bi bi-calendar-event"></i> Fecha de la Cita *
                    </label>
                    <input type="date" 
                           name="fecha_cita" 
                           id="fecha_cita" 
                           class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           required>
                    <small class="form-text text-muted">No se permiten fechas pasadas</small>
                </div>
                
                <!-- Hora de Inicio -->
                <div class="col-md-6">
                    <label for="hora_inicio" class="form-label">
                        <i class="bi bi-clock"></i> Hora de Inicio *
                    </label>
                    <input type="time" 
                           name="hora_inicio" 
                           id="hora_inicio" 
                           class="form-control" 
                           required>
                    <small class="form-text text-muted">Hora de inicio de la cita</small>
                </div>
                
                <!-- Hora de Fin -->
                <div class="col-md-6">
                    <label for="hora_fin" class="form-label">
                        <i class="bi bi-clock-fill"></i> Hora de Fin *
                    </label>
                    <input type="time" 
                           name="hora_fin" 
                           id="hora_fin" 
                           class="form-control" 
                           required>
                    <small class="form-text text-muted">Se calcula automáticamente según el servicio</small>
                </div>
                
                <!-- Ubicación -->
                <div class="col-md-12">
                    <label for="ubicacion" class="form-label">
                        <i class="bi bi-geo-alt"></i> Ubicación
                    </label>
                    <input type="text" 
                           name="ubicacion" 
                           id="ubicacion" 
                           class="form-control" 
                           placeholder="Ej: Oficina de Bienestar Estudiantil, Edificio 3, Sala 201">
                    <small class="form-text text-muted">Se autocompletará con la ubicación del servicio</small>
                </div>
                
                <!-- Notas de la Trabajadora -->
                <div class="col-md-12">
                    <label for="notas_trabajadora" class="form-label">
                        <i class="bi bi-journal-text"></i> Notas / Observaciones
                    </label>
                    <textarea name="notas_trabajadora" 
                              id="notas_trabajadora" 
                              class="form-control" 
                              rows="3"
                              placeholder="Notas adicionales sobre la cita (opcional)"></textarea>
                </div>
                
                <!-- Información de Horarios Disponibles -->
                <?php if (!empty($horarios_disponibles)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Sus Horarios de Atención:</h6>
                        <div class="row">
                            <?php 
                            $dias_semana = [
                                1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 
                                4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'
                            ];
                            foreach ($horarios_disponibles as $horario): 
                            ?>
                                <div class="col-md-4 mb-2">
                                    <strong><?php echo $dias_semana[$horario['dia_semana']]; ?>:</strong>
                                    <?php echo substr($horario['hora_inicio'], 0, 5); ?> - 
                                    <?php echo substr($horario['hora_fin'], 0, 5); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Botones de Acción -->
            <div class="mt-4 d-flex justify-content-between">
                <a href="citas_trabajadora.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
                <button type="submit" name="crear_cita" class="btn btn-primary">
                    <i class="bi bi-save"></i> Crear Cita
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Información Adicional -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Consejos para Programar Citas</h6>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Verifique que el horario seleccionado esté dentro de sus horarios de atención</li>
            <li>Asegúrese de dejar tiempo suficiente entre citas consecutivas</li>
            <li>La duración se calcula automáticamente según el servicio seleccionado</li>
            <li>Puede agregar notas para recordar detalles importantes de la cita</li>
            <li>El estudiante recibirá una notificación automática por correo</li>
        </ul>
    </div>
</div>

<style>
.bg-purple {
    background: linear-gradient(135deg, #6b2c91, #2d8659);
}

.form-label {
    font-weight: 600;
    color: #333;
}

.form-control:focus,
.form-select:focus {
    border-color: #6b2c91;
    box-shadow: 0 0 0 0.2rem rgba(107, 44, 145, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #2d8659, #1a5c3a);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1a5c3a, #2d8659);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.card {
    border: none;
    border-radius: 10px;
}

.alert-info {
    background-color: #e8f4f8;
    border-color: #bee5eb;
    color: #0c5460;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectEstudiante = document.getElementById('id_estudiante');
    const selectServicio = document.getElementById('id_servicio');
    const inputHoraInicio = document.getElementById('hora_inicio');
    const inputHoraFin = document.getElementById('hora_fin');
    const inputUbicacion = document.getElementById('ubicacion');
    const infoEstudiante = document.getElementById('infoEstudiante');
    
    // Mostrar información del estudiante al seleccionar
    selectEstudiante.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (this.value) {
            document.getElementById('estudianteCorreo').textContent = selectedOption.dataset.correo || 'N/A';
            document.getElementById('estudianteTelefono').textContent = selectedOption.dataset.telefono || 'N/A';
            document.getElementById('estudianteFacultad').textContent = selectedOption.dataset.facultad || 'N/A';
            document.getElementById('estudianteCarrera').textContent = selectedOption.dataset.carrera || 'N/A';
            infoEstudiante.style.display = 'block';
        } else {
            infoEstudiante.style.display = 'none';
        }
    });
    
    // Autocompletar ubicación y calcular hora de fin según el servicio
    selectServicio.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const infoServicio = document.getElementById('infoServicio');
        
        if (this.value) {
            // Mostrar información del servicio
            const categoria = selectedOption.dataset.categoria || 'Sin categoría';
            const tipo = selectedOption.dataset.tipo;
            const duracion = selectedOption.dataset.duracion;
            const ubicacion = selectedOption.dataset.ubicacion || 'No especificada';
            
            document.getElementById('servicioCategoria').textContent = categoria;
            document.getElementById('servicioTipo').textContent = tipo === 'servicio' ? 'Servicio' : 'Oferta';
            document.getElementById('servicioTipo').className = tipo === 'servicio' ? 'badge bg-primary' : 'badge bg-success';
            document.getElementById('servicioDuracion').textContent = duracion;
            document.getElementById('servicioUbicacion').textContent = ubicacion;
            
            infoServicio.style.display = 'block';
            
            // Autocompletar ubicación
            if (ubicacion && ubicacion !== 'No especificada') {
                inputUbicacion.value = ubicacion;
            }
            
            // Calcular hora de fin si hay hora de inicio
            if (inputHoraInicio.value) {
                calcularHoraFin();
            }
        } else {
            infoServicio.style.display = 'none';
        }
    });
    
    // Calcular hora de fin cuando cambia la hora de inicio
    inputHoraInicio.addEventListener('change', calcularHoraFin);
    
    function calcularHoraFin() {
        const horaInicio = inputHoraInicio.value;
        const selectServicioOption = selectServicio.options[selectServicio.selectedIndex];
        
        if (horaInicio && selectServicio.value) {
            const duracion = parseInt(selectServicioOption.dataset.duracion) || 30;
            
            // Convertir hora de inicio a minutos
            const [horas, minutos] = horaInicio.split(':').map(Number);
            let totalMinutos = (horas * 60) + minutos + duracion;
            
            // Convertir de vuelta a formato HH:MM
            const horasFin = Math.floor(totalMinutos / 60);
            const minutosFin = totalMinutos % 60;
            
            inputHoraFin.value = String(horasFin).padStart(2, '0') + ':' + String(minutosFin).padStart(2, '0');
        }
    }
    
    // Validar que la hora de fin sea mayor que la de inicio
    inputHoraFin.addEventListener('change', function() {
        if (inputHoraInicio.value && inputHoraFin.value) {
            if (inputHoraFin.value <= inputHoraInicio.value) {
                alert('La hora de fin debe ser posterior a la hora de inicio');
                inputHoraFin.value = '';
            }
        }
    });
    
    // Validación del formulario antes de enviar
    document.getElementById('formNuevaCita').addEventListener('submit', function(e) {
        const fechaCita = new Date(document.getElementById('fecha_cita').value);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        
        if (fechaCita < hoy) {
            e.preventDefault();
            alert('No se pueden crear citas en fechas pasadas');
            return false;
        }
        
        if (!selectEstudiante.value) {
            e.preventDefault();
            alert('Debe seleccionar un estudiante');
            return false;
        }
        
        if (!selectServicio.value) {
            e.preventDefault();
            alert('Debe seleccionar un servicio');
            return false;
        }
        
        if (inputHoraFin.value <= inputHoraInicio.value) {
            e.preventDefault();
            alert('La hora de fin debe ser posterior a la hora de inicio');
            return false;
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout_trabajadora.php';
?>