<?php
/**
 * Servicios y Ofertas - Trabajadora Social
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
$action = $_GET['action'] ?? '';
$id_servicio = $_GET['id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['crear_servicio'])) {
            $tipo = $_POST['tipo'];
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $id_categoria = $_POST['id_categoria'];
            $ubicacion = $_POST['ubicacion'];
            $fecha_limite = $_POST['fecha_limite'] ?: null;
            $duracion = $_POST['duracion_estimada'];
            $requiere_cita = isset($_POST['requiere_cita']) ? 1 : 0;
            $requiere_documentacion = isset($_POST['requiere_documentacion']) ? 1 : 0;
            
            // Verificar nombre único
            $stmt = $conn->prepare("SELECT COUNT(*) as existe FROM servicios_ofertas WHERE nombre = ?");
            $stmt->execute([$nombre]);
            if ($stmt->fetch()['existe'] > 0) {
                $error = "Ya existe un servicio/oferta con este nombre";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO servicios_ofertas 
                    (tipo, nombre, descripcion, id_categoria, id_trabajadora_social, 
                     ubicacion, fecha_limite, duracion_estimada, requiere_cita, 
                     requiere_documentacion, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $tipo, $nombre, $descripcion, $id_categoria, $_SESSION['user_id'],
                    $ubicacion, $fecha_limite, $duracion, $requiere_cita, $requiere_documentacion
                ]);
                $message = "Servicio creado exitosamente";
            }
        }
        
        if (isset($_POST['actualizar_servicio'])) {
            $id_servicio = $_POST['id_servicio'];
            $tipo = $_POST['tipo'];
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $id_categoria = $_POST['id_categoria'];
            $ubicacion = $_POST['ubicacion'];
            $fecha_limite = $_POST['fecha_limite'] ?: null;
            $duracion = $_POST['duracion_estimada'];
            $requiere_cita = isset($_POST['requiere_cita']) ? 1 : 0;
            $requiere_documentacion = isset($_POST['requiere_documentacion']) ? 1 : 0;
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            // Verificar nombre único (excluyendo el actual)
            $stmt = $conn->prepare("SELECT COUNT(*) as existe FROM servicios_ofertas WHERE nombre = ? AND id_servicio != ?");
            $stmt->execute([$nombre, $id_servicio]);
            if ($stmt->fetch()['existe'] > 0) {
                $error = "Ya existe otro servicio/oferta con este nombre";
            } else {
                $stmt = $conn->prepare("
                    UPDATE servicios_ofertas 
                    SET tipo = ?, nombre = ?, descripcion = ?, id_categoria = ?, 
                        ubicacion = ?, fecha_limite = ?, duracion_estimada = ?, 
                        requiere_cita = ?, requiere_documentacion = ?, activo = ?,
                        fecha_modificacion = NOW()
                    WHERE id_servicio = ?
                ");
                $stmt->execute([
                    $tipo, $nombre, $descripcion, $id_categoria,
                    $ubicacion, $fecha_limite, $duracion, $requiere_cita,
                    $requiere_documentacion, $activo, $id_servicio
                ]);
                $message = "Servicio actualizado exitosamente";
            }
        }
        
        if (isset($_POST['eliminar_servicio'])) {
            $id_servicio = $_POST['id_servicio'];
            
            // Verificar si tiene CUALQUIER cita asociada (pasadas, presentes o futuras)
            $stmt_total = $conn->prepare("
                SELECT COUNT(*) as total_citas FROM citas 
                WHERE id_servicio = ?
            ");
            $stmt_total->execute([$id_servicio]);
            $total_citas = $stmt_total->fetch()['total_citas'];
            
            // Verificar citas activas (futuras y no canceladas)
            $stmt_activas = $conn->prepare("
                SELECT COUNT(*) as citas_activas,
                       GROUP_CONCAT(
                           CONCAT(DATE_FORMAT(fecha_cita, '%d/%m/%Y'), ' a las ', 
                                  DATE_FORMAT(hora_inicio, '%H:%i'))
                           ORDER BY fecha_cita 
                           SEPARATOR '<br>• '
                       ) as fechas_citas
                FROM citas 
                WHERE id_servicio = ? 
                AND fecha_cita >= CURDATE()
                AND estado NOT IN ('cancelada', 'completada', 'no_asistio')
            ");
            $stmt_activas->execute([$id_servicio]);
            $resultado_activas = $stmt_activas->fetch();
            $citas_activas = $resultado_activas['citas_activas'];
            $fechas_citas = $resultado_activas['fechas_citas'];
            
            // Obtener nombre del servicio para el mensaje
            $stmt_nombre = $conn->prepare("SELECT nombre FROM servicios_ofertas WHERE id_servicio = ?");
            $stmt_nombre->execute([$id_servicio]);
            $nombre_servicio = $stmt_nombre->fetch()['nombre'];
            
            // No permitir eliminación si hay citas asociadas
            if ($total_citas > 0) {
                if ($citas_activas > 0) {
                    $error = "<strong>❌ ERROR: No se puede eliminar el servicio</strong><br><br>" .
                             "<strong>Servicio:</strong> " . htmlspecialchars($nombre_servicio) . "<br><br>" .
                             "<strong>Motivo:</strong> Este servicio tiene <strong>" . $citas_activas . " cita(s) activa(s) programada(s)</strong>:<br><br>" .
                             "<div class='ms-3'>• " . $fechas_citas . "</div><br>" .
                             "<strong>Solución:</strong><br>" .
                             "1. Vaya a <strong>Gestión de Citas</strong><br>" .
                             "2. Cancele o elimine todas las citas asociadas a este servicio<br>" .
                             "3. Luego podrá eliminar el servicio sin problemas";
                } else {
                    $error = "<strong>❌ ERROR: No se puede eliminar el servicio</strong><br><br>" .
                             "<strong>Servicio:</strong> " . htmlspecialchars($nombre_servicio) . "<br><br>" .
                             "<strong>Motivo:</strong> Este servicio tiene <strong>" . $total_citas . " cita(s) en el historial</strong> " .
                             "(completadas o canceladas).<br><br>" .
                             "<strong>Política de integridad de datos:</strong><br>" .
                             "Los servicios con historial de citas no pueden ser eliminados para mantener " .
                             "la trazabilidad y los registros históricos del sistema.<br><br>" .
                             "<strong>Alternativa recomendada:</strong><br>" .
                             "En lugar de eliminar, puede <strong>desactivar</strong> este servicio:<br>" .
                             "1. Click en <strong>Editar</strong> el servicio<br>" .
                             "2. Desmarcar la opción <strong>'Servicio activo'</strong><br>" .
                             "3. Guardar cambios<br><br>" .
                             "El servicio dejará de aparecer en los listados pero mantendrá su historial.";
                }
            } else {
                // No hay citas asociadas, se puede eliminar
                try {
                    $stmt = $conn->prepare("DELETE FROM servicios_ofertas WHERE id_servicio = ?");
                    $stmt->execute([$id_servicio]);
                    $message = "✅ <strong>Servicio eliminado exitosamente</strong><br><br>" .
                               "<strong>Servicio eliminado:</strong> " . htmlspecialchars($nombre_servicio) . "<br>" .
                               "El servicio no tenía citas asociadas y ha sido eliminado permanentemente de la base de datos.";
                } catch (Exception $e) {
                    $error = "<strong>❌ ERROR al eliminar el servicio</strong><br><br>" .
                             "Ocurrió un error técnico: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
    
    // Obtener servicios según filtros
    $filtro_tipo = $_GET['tipo'] ?? 'todos';
    $filtro_categoria = $_GET['categoria'] ?? 'todos';
    $filtro_activo = $_GET['activo'] ?? 'todos';
    
    $query = "
        SELECT s.*, 
               c.nombre_categoria,
               u.nombre_completo as trabajadora_nombre,
               (SELECT COUNT(*) 
                FROM citas 
                WHERE id_servicio = s.id_servicio 
                AND fecha_cita >= CURDATE()
                AND estado NOT IN ('cancelada', 'completada', 'no_asistio')
               ) as citas_pendientes,
               (SELECT COUNT(*) 
                FROM citas 
                WHERE id_servicio = s.id_servicio
               ) as total_citas_historial
        FROM servicios_ofertas s
        LEFT JOIN categorias_servicios c ON s.id_categoria = c.id_categoria
        LEFT JOIN usuarios u ON s.id_trabajadora_social = u.id_usuario
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filtro_tipo != 'todos') {
        $query .= " AND s.tipo = ?";
        $params[] = $filtro_tipo;
    }
    
    if ($filtro_categoria != 'todos') {
        $query .= " AND s.id_categoria = ?";
        $params[] = $filtro_categoria;
    }
    
    if ($filtro_activo != 'todos') {
        $query .= " AND s.activo = ?";
        $params[] = ($filtro_activo == 'activos' ? 1 : 0);
    }
    
    // Si no es coordinador, solo ver sus servicios
    if ($_SESSION['user_role'] !== 'Coordinador') {
        $query .= " AND s.id_trabajadora_social = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $query .= " ORDER BY s.tipo, s.nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $servicios = $stmt->fetchAll();
    
    // Obtener categorías para filtro
    $stmt_categorias = $conn->prepare("SELECT * FROM categorias_servicios WHERE activo = 1 ORDER BY nombre_categoria");
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll();
    
    // Estadísticas
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN tipo = 'servicio' THEN 1 ELSE 0 END) as servicios,
            SUM(CASE WHEN tipo = 'oferta' THEN 1 ELSE 0 END) as ofertas,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN requiere_cita = 1 THEN 1 ELSE 0 END) as con_cita
        FROM servicios_ofertas 
        WHERE id_trabajadora_social = ?
    ");
    $stmt_stats->execute([$_SESSION['user_id']]);
    $estadisticas = $stmt_stats->fetch();
    
    // Cargar servicio para editar
    $servicio_editar = null;
    if ($action == 'editar' && $id_servicio > 0) {
        $stmt = $conn->prepare("SELECT * FROM servicios_ofertas WHERE id_servicio = ?");
        $stmt->execute([$id_servicio]);
        $servicio_editar = $stmt->fetch();
    }
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$page_title = "Servicios y Ofertas";
$page_subtitle = "Gestión de servicios y ofertas estudiantiles";

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo $message; // Permite HTML ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; // Permite HTML ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="stats-card purple">
            <div class="stats-value"><?php echo $estadisticas['total'] ?? 0; ?></div>
            <div class="stats-label">Total</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card blue">
            <div class="stats-value"><?php echo $estadisticas['servicios'] ?? 0; ?></div>
            <div class="stats-label">Servicios</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card green">
            <div class="stats-value"><?php echo $estadisticas['ofertas'] ?? 0; ?></div>
            <div class="stats-label">Ofertas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card orange">
            <div class="stats-value"><?php echo $estadisticas['activos'] ?? 0; ?></div>
            <div class="stats-label">Activos</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card teal">
            <div class="stats-value"><?php echo $estadisticas['con_cita'] ?? 0; ?></div>
            <div class="stats-label">Requieren Cita</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <button type="button" class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; width: 100%;"
                data-bs-toggle="modal" data-bs-target="#modalCrearServicio">
            <div class="stats-value"><i class="bi bi-plus-circle"></i></div>
            <div class="stats-label">Agregar</div>
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="content-card mb-4">
    <h2 class="card-title">Filtros</h2>
    <form method="GET" action="" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="todos">Todos</option>
                <option value="servicio" <?php echo $filtro_tipo == 'servicio' ? 'selected' : ''; ?>>Servicios</option>
                <option value="oferta" <?php echo $filtro_tipo == 'oferta' ? 'selected' : ''; ?>>Ofertas</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-select">
                <option value="todos">Todas</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo $categoria['id_categoria']; ?>" 
                        <?php echo $filtro_categoria == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="activo" class="form-select">
                <option value="todos">Todos</option>
                <option value="activos" <?php echo $filtro_activo == 'activos' ? 'selected' : ''; ?>>Activos</option>
                <option value="inactivos" <?php echo $filtro_activo == 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-purple w-100">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Lista de Servicios -->
<div class="content-card">
    <h2 class="card-title d-flex justify-content-between align-items-center">
        <span>Servicios y Ofertas</span>
        <span class="badge bg-purple"><?php echo count($servicios); ?> encontrados</span>
    </h2>
    
    <?php if (count($servicios) > 0): ?>
        <div class="row g-3">
            <?php foreach ($servicios as $servicio): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($servicio['nombre']); ?>
                                    </h5>
                                    <span class="badge bg-<?php echo $servicio['tipo'] == 'servicio' ? 'info' : 'success'; ?>">
                                        <?php echo ucfirst($servicio['tipo']); ?>
                                    </span>
                                    <?php if ($servicio['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                    
                                    <!-- Badges de estado de citas -->
                                    <?php if ($servicio['citas_pendientes'] > 0): ?>
                                        <span class="badge bg-warning text-dark" 
                                              title="Citas programadas activas">
                                            <i class="bi bi-calendar-check"></i> 
                                            <?php echo $servicio['citas_pendientes']; ?> programada(s)
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($servicio['total_citas_historial'] > 0 && $servicio['citas_pendientes'] == 0): ?>
                                        <span class="badge bg-info text-dark" 
                                              title="Tiene historial de citas">
                                            <i class="bi bi-clock-history"></i> 
                                            <?php echo $servicio['total_citas_historial']; ?> en historial
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" 
                                               data-bs-toggle="modal" 
                                               data-bs-target="#modalEditarServicio<?php echo $servicio['id_servicio']; ?>">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#"
                                               data-bs-toggle="modal"
                                               data-bs-target="#modalEliminarServicio<?php echo $servicio['id_servicio']; ?>">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($servicio['nombre_categoria']): ?>
                                <div class="mb-2">
                                    <i class="bi bi-tag"></i>
                                    <small class="text-muted"><?php echo htmlspecialchars($servicio['nombre_categoria']); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($servicio['descripcion']): ?>
                                <p class="card-text small mb-2">
                                    <?php echo htmlspecialchars(substr($servicio['descripcion'], 0, 150)); ?>
                                    <?php if (strlen($servicio['descripcion']) > 150): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="row small text-muted g-2">
                                <?php if ($servicio['ubicacion']): ?>
                                    <div class="col-12">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($servicio['ubicacion']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($servicio['fecha_limite']): ?>
                                    <div class="col-12">
                                        <i class="bi bi-calendar-x"></i> Vence: <?php echo date('d/m/Y', strtotime($servicio['fecha_limite'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($servicio['duracion_estimada']): ?>
                                    <div class="col-12">
                                        <i class="bi bi-clock"></i> Duración: <?php echo $servicio['duracion_estimada']; ?> min
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-12">
                                    <i class="bi bi-calendar-check"></i> 
                                    <?php echo $servicio['requiere_cita'] ? 'Requiere cita' : 'No requiere cita'; ?>
                                </div>
                                
                                <div class="col-12">
                                    <i class="bi bi-file-earmark-text"></i> 
                                    <?php echo $servicio['requiere_documentacion'] ? 'Requiere documentos' : 'No requiere documentos'; ?>
                                </div>
                                
                                <div class="col-12">
                                    <i class="bi bi-person"></i> 
                                    <?php echo htmlspecialchars($servicio['trabajadora_nombre']); ?>
                                </div>
                                
                                <div class="col-12">
                                    <?php if ($servicio['citas_pendientes'] > 0): ?>
                                        <i class="bi bi-calendar-event text-warning"></i> 
                                        <strong>Citas activas: <?php echo $servicio['citas_pendientes']; ?></strong>
                                    <?php elseif ($servicio['total_citas_historial'] > 0): ?>
                                        <i class="bi bi-calendar-check text-success"></i> 
                                        Historial: <?php echo $servicio['total_citas_historial']; ?> cita(s)
                                    <?php else: ?>
                                        <i class="bi bi-calendar-x text-muted"></i> 
                                        Sin citas asociadas
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Editar -->
                    <div class="modal fade" id="modalEditarServicio<?php echo $servicio['id_servicio']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar Servicio/Oferta</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_servicio" value="<?php echo $servicio['id_servicio']; ?>">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Tipo *</label>
                                                <select name="tipo" class="form-select" required>
                                                    <option value="servicio" <?php echo $servicio['tipo'] == 'servicio' ? 'selected' : ''; ?>>Servicio</option>
                                                    <option value="oferta" <?php echo $servicio['tipo'] == 'oferta' ? 'selected' : ''; ?>>Oferta</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Categoría</label>
                                                <select name="id_categoria" class="form-select">
                                                    <option value="">Sin categoría</option>
                                                    <?php foreach ($categorias as $categoria): ?>
                                                        <option value="<?php echo $categoria['id_categoria']; ?>"
                                                                <?php echo $servicio['id_categoria'] == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Nombre *</label>
                                                <input type="text" name="nombre" class="form-control" required 
                                                       value="<?php echo htmlspecialchars($servicio['nombre']); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Descripción</label>
                                                <textarea name="descripcion" class="form-control" rows="4"><?php echo htmlspecialchars($servicio['descripcion']); ?></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Ubicación</label>
                                                <input type="text" name="ubicacion" class="form-control" 
                                                       value="<?php echo htmlspecialchars($servicio['ubicacion']); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Fecha Límite (opcional)</label>
                                                <input type="date" name="fecha_limite" class="form-control" 
                                                       value="<?php echo $servicio['fecha_limite']; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Duración estimada (minutos)</label>
                                                <input type="number" name="duracion_estimada" class="form-control" 
                                                       min="15" max="480" value="<?php echo $servicio['duracion_estimada']; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Opciones</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="requiere_cita" 
                                                           id="requiere_cita_<?php echo $servicio['id_servicio']; ?>" 
                                                           value="1" <?php echo $servicio['requiere_cita'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="requiere_cita_<?php echo $servicio['id_servicio']; ?>">
                                                        Requiere cita previa
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="requiere_documentacion" 
                                                           id="requiere_doc_<?php echo $servicio['id_servicio']; ?>" 
                                                           value="1" <?php echo $servicio['requiere_documentacion'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="requiere_doc_<?php echo $servicio['id_servicio']; ?>">
                                                        Requiere documentación
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="activo" 
                                                           id="activo_<?php echo $servicio['id_servicio']; ?>" 
                                                           value="1" <?php echo $servicio['activo'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="activo_<?php echo $servicio['id_servicio']; ?>">
                                                        Servicio activo
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="actualizar_servicio" class="btn btn-primary">Guardar Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Eliminar -->
                    <div class="modal fade" id="modalEliminarServicio<?php echo $servicio['id_servicio']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Eliminar Servicio/Oferta</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_servicio" value="<?php echo $servicio['id_servicio']; ?>">
                                        
                                        <div class="alert alert-warning mb-3">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>¿Está seguro de eliminar este servicio/oferta?</strong>
                                            <br><br>
                                            <strong><?php echo htmlspecialchars($servicio['nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Tipo: <?php echo $servicio['tipo'] == 'servicio' ? 'Servicio' : 'Oferta'; ?> | 
                                                Categoría: <?php echo htmlspecialchars($servicio['nombre_categoria'] ?: 'Sin categoría'); ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Información de estado de citas -->
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title mb-2">
                                                    <i class="bi bi-info-circle"></i> Estado del Servicio
                                                </h6>
                                                <ul class="list-unstyled mb-0 small">
                                                    <li>
                                                        <strong>Citas activas programadas:</strong> 
                                                        <span class="badge bg-<?php echo $servicio['citas_pendientes'] > 0 ? 'warning' : 'success'; ?>">
                                                            <?php echo $servicio['citas_pendientes']; ?>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <strong>Total en historial:</strong> 
                                                        <span class="badge bg-<?php echo $servicio['total_citas_historial'] > 0 ? 'info' : 'secondary'; ?>">
                                                            <?php echo $servicio['total_citas_historial']; ?>
                                                        </span>
                                                    </li>
                                                    <li class="mt-2">
                                                        <?php if ($servicio['total_citas_historial'] > 0): ?>
                                                            <i class="bi bi-exclamation-circle text-warning"></i>
                                                            <span class="text-warning">
                                                                <strong>Advertencia:</strong> Este servicio tiene citas asociadas.
                                                            </span>
                                                        <?php else: ?>
                                                            <i class="bi bi-check-circle text-success"></i>
                                                            <span class="text-success">
                                                                Este servicio no tiene citas asociadas y puede ser eliminado.
                                                            </span>
                                                        <?php endif; ?>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-danger mt-3 mb-0">
                                            <small>
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <strong>Importante:</strong> Esta acción no se puede deshacer. 
                                                Si el servicio tiene citas asociadas, no podrá ser eliminado.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </button>
                                        <button type="submit" name="eliminar_servicio" class="btn btn-danger">
                                            <i class="bi bi-trash"></i> Confirmar Eliminación
                                        </button>
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
            <i class="bi bi-list-check" style="font-size: 64px;"></i>
            <h4>No se encontraron servicios</h4>
            <p>No hay servicios u ofertas que coincidan con los filtros aplicados.</p>
            <button type="button" class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#modalCrearServicio">
                <i class="bi bi-plus-circle"></i> Crear primer servicio
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Crear Servicio -->
<div class="modal fade" id="modalCrearServicio" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Servicio/Oferta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="servicio">Servicio</option>
                                <option value="oferta">Oferta</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="id_categoria" class="form-select">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required 
                                   placeholder="Ej: Tutorías de Matemáticas, Beca Alimenticia...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" 
                                      placeholder="Descripción detallada del servicio u oferta..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="ubicacion" class="form-control" 
                                   placeholder="Ej: Edificio 3, Sala 201">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha Límite (opcional)</label>
                            <input type="date" name="fecha_limite" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duración estimada (minutos)</label>
                            <input type="number" name="duracion_estimada" class="form-control" 
                                   min="15" max="480" value="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opciones</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requiere_cita" id="requiere_cita" value="1" checked>
                                <label class="form-check-label" for="requiere_cita">Requiere cita previa</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requiere_documentacion" id="requiere_documentacion" value="1">
                                <label class="form-check-label" for="requiere_documentacion">Requiere documentación</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_servicio" class="btn btn-primary">Crear Servicio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .card {
        transition: transform 0.2s;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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