<?php
/**
 * Servicios Disponibles
 * Catálogo de servicios y ofertas de Bienestar Estudiantil
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

// Variables de búsqueda avanzada
$termino_busqueda = '';
$categoria_filtro = '';
$ubicacion_filtro = '';
$estado_filtro = '';
$mensaje_error = '';
$mensaje_exito = '';

// ========== Cargar configuración de imágenes ==========
$imagenes_servicios = [
    'Asistencia provisional para transporte' => 'images/transporte.jpg',
    'Asistencia alimentaria' => 'images/alimentacion.jpg',
    'Crédito bibliográfico' => 'images/libros.jpg',
    'Mecenazgo académico' => 'images/mecenazgo.jpg',
    'Trabajo compensatorio para cubrir matrículas' => 'images/trabajo.jpg',
    'Acuerdo de pago para la matrícula' => 'images/pago.jpg',
    'Becas para estudiantes en situación de vulnerabilidad' => 'images/beca.jpg',
    'default' => 'images/servicio-default.jpg'
];

try {
    $conn = getDBConnection();
    
    // Obtener categorías y ubicaciones para filtros
    $stmt_cat = $conn->prepare("SELECT * FROM categorias_servicios WHERE activo = 1 ORDER BY nombre_categoria");
    $stmt_cat->execute();
    $categorias = $stmt_cat->fetchAll();
    
    $stmt_ubic = $conn->prepare("SELECT DISTINCT ubicacion FROM servicios_ofertas WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion");
    $stmt_ubic->execute();
    $ubicaciones = $stmt_ubic->fetchAll(PDO::FETCH_COLUMN);
    
    // Procesar búsqueda
    $servicios_por_categoria = [];
    
    // Construir consulta SQL base
    $sql = "SELECT so.*, cs.nombre_categoria, cs.descripcion as categoria_desc 
            FROM servicios_ofertas so 
            INNER JOIN categorias_servicios cs ON so.id_categoria = cs.id_categoria 
            WHERE so.activo = 1";
    
    $params = [];
    $conditions = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['limpiar'])) {
            // Limpiar filtros
            $termino_busqueda = '';
            $categoria_filtro = '';
            $ubicacion_filtro = '';
            $estado_filtro = '';
            $mensaje_exito = 'Filtros limpiados correctamente';
        } else {
            // Obtener parámetros de búsqueda
            $termino_busqueda = trim($_POST['termino'] ?? '');
            $categoria_filtro = $_POST['categoria_id'] ?? '';
            $ubicacion_filtro = $_POST['ubicacion'] ?? '';
            $estado_filtro = $_POST['estado'] ?? '';
            
            // Validar término de búsqueda si se proporcionó
            if (!empty($termino_busqueda)) {
                if (strlen($termino_busqueda) < 3) {
                    $mensaje_error = "El término de búsqueda debe contener al menos 3 caracteres";
                } elseif (!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/', $termino_busqueda)) {
                    $mensaje_error = "El término de búsqueda contiene caracteres no permitidos. Solo se permiten letras, números y espacios";
                } else {
                    // CORRECCIÓN: Usar marcadores de posición únicos para cada LIKE
                    $conditions[] = "(so.nombre LIKE :termino1 OR so.descripcion LIKE :termino2 OR cs.nombre_categoria LIKE :termino3)";
                    $param_value = '%' . $termino_busqueda . '%';
                    $params[':termino1'] = $param_value;
                    $params[':termino2'] = $param_value;
                    $params[':termino3'] = $param_value;
                }
            }
            
            // Agregar condición de categoría
            if (!empty($categoria_filtro) && empty($mensaje_error)) {
                $conditions[] = "so.id_categoria = :categoria_id";
                $params[':categoria_id'] = $categoria_filtro;
            }
            
            // Agregar condición de ubicación
            if (!empty($ubicacion_filtro) && empty($mensaje_error)) {
                $conditions[] = "so.ubicacion = :ubicacion";
                $params[':ubicacion'] = $ubicacion_filtro;
            }
            
            // Agregar condición de estado
            if (!empty($estado_filtro) && empty($mensaje_error)) {
                if ($estado_filtro === 'activo') {
                    $conditions[] = "(so.fecha_limite IS NULL OR so.fecha_limite >= CURDATE())";
                } elseif ($estado_filtro === 'expirado') {
                    $conditions[] = "so.fecha_limite < CURDATE()";
                }
            }
        }
    }
    
    // Si no hay condiciones (búsqueda inicial sin filtros), mostrar solo activos
    if (empty($conditions) && empty($mensaje_error)) {
        $conditions[] = "(so.fecha_limite IS NULL OR so.fecha_limite >= CURDATE())";
    }
    
    // Agregar condiciones a la consulta
    if (!empty($conditions) && empty($mensaje_error)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY cs.nombre_categoria, so.nombre";
    
    // Debug: Mostrar consulta SQL (solo para desarrollo)
    // echo "<pre>SQL: " . htmlspecialchars($sql) . "</pre>";
    // echo "<pre>Params: " . print_r($params, true) . "</pre>";
    
    // Ejecutar consulta solo si no hay errores
    if (empty($mensaje_error)) {
        $stmt = $conn->prepare($sql);
        
        // Pasar parámetros solo si existen
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        
        $servicios = $stmt->fetchAll();
        
        // Agrupar por categoría
        foreach ($servicios as $servicio) {
            $categoria = $servicio['nombre_categoria'];
            if (!isset($servicios_por_categoria[$categoria])) {
                $servicios_por_categoria[$categoria] = [];
            }
            $servicios_por_categoria[$categoria][] = $servicio;
        }
    }
    
} catch (Exception $e) {
    // Para depuración: mostrar el error real
    $mensaje_error = "Error: " . $e->getMessage() . " (Código: " . $e->getCode() . ")";
    error_log("Error en servicios_disponibles.php: " . $e->getMessage());
    $servicios_por_categoria = [];
}

// Configurar variables para el layout
$page_title = "Servicios Disponibles";
$page_subtitle = "Explora todos los servicios que Bienestar Estudiantil tiene para ti";

// Capturar el contenido
ob_start();
?>

<style>
    .search-box {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }
    
    .service-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border-left: 4px solid #2d8659;
        transition: all 0.3s;
    }
    
    .service-card:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-5px);
    }
    
    .service-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
    }
    
    .service-content {
        padding: 20px;
    }
    
    .category-header {
        background: linear-gradient(135deg, #2d8659 0%, #1a5c3a 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        margin-top: 30px;
    }
    
    .btn-servicio {
        background-color: #0d6efd;
        color: white;
        border: none;
    }
    
    .btn-servicio:hover {
        background-color: #0b5ed7;
        color: white;
    }
    
    .btn-oferta {
        background-color: #2d8659;
        color: white;
        border: none;
    }
    
    .btn-oferta:hover {
        background-color: #1a5c3a;
        color: white;
    }
    
    .filter-badge {
        background: #e9ecef;
        color: #495057;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.875rem;
        margin-right: 5px;
        margin-bottom: 5px;
        display: inline-block;
    }
    
    .active-filters {
        background: #f8f9fa;
        border-left: 4px solid #0d6efd;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<div class="search-box">
    <h4 class="mb-4"><i class="bi bi-search"></i> Búsqueda Avanzada</h4>
    
    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($mensaje_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="searchForm">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label for="termino" class="form-label">Término de búsqueda</label>
                <input type="text" 
                       class="form-control" 
                       id="termino" 
                       name="termino" 
                       value="<?php echo htmlspecialchars($termino_busqueda); ?>"
                       placeholder="Ej: tutorías, becas, actividades...">
                <div class="form-text">Mínimo 3 caracteres. Solo letras, números y espacios.</div>
            </div>
            
            <div class="col-md-4 mb-3">
                <label for="categoria_id" class="form-label">Categoría</label>
                <select class="form-select" id="categoria_id" name="categoria_id">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>" 
                            <?php echo ($categoria_filtro == $cat['id_categoria']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 mb-3">
                <label for="ubicacion" class="form-label">Ubicación</label>
                <select class="form-select" id="ubicacion" name="ubicacion">
                    <option value="">Todas las ubicaciones</option>
                    <?php foreach ($ubicaciones as $ubic): ?>
                        <option value="<?php echo htmlspecialchars($ubic); ?>" 
                            <?php echo ($ubicacion_filtro == $ubic) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ubic); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?php echo ($estado_filtro == 'activo') ? 'selected' : ''; ?>>Activos</option>
                    <option value="expirado" <?php echo ($estado_filtro == 'expirado') ? 'selected' : ''; ?>>Expirados</option>
                </select>
            </div>
        </div>
        
        <div class="d-flex justify-content-between">
            <div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button type="submit" name="limpiar" value="1" class="btn btn-outline-secondary">
                    <i class="bi bi-eraser"></i> Limpiar filtros
                </button>
            </div>
            
            <?php if (!empty($termino_busqueda)): ?>
                <div class="text-muted">
                    Buscando: <strong>"<?php echo htmlspecialchars($termino_busqueda); ?>"</strong>
                </div>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if (!empty($termino_busqueda) || !empty($categoria_filtro) || !empty($ubicacion_filtro) || !empty($estado_filtro)): ?>
        <div class="active-filters mt-3">
            <h6><i class="bi bi-funnel"></i> Filtros aplicados:</h6>
            <div>
                <?php if (!empty($termino_busqueda)): ?>
                    <span class="filter-badge">
                        <i class="bi bi-search"></i> Término: <?php echo htmlspecialchars($termino_busqueda); ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($categoria_filtro)): 
                    $cat_nombre = '';
                    foreach ($categorias as $cat) {
                        if ($cat['id_categoria'] == $categoria_filtro) {
                            $cat_nombre = $cat['nombre_categoria'];
                            break;
                        }
                    }
                ?>
                    <span class="filter-badge">
                        <i class="bi bi-tag"></i> Categoría: <?php echo htmlspecialchars($cat_nombre); ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($ubicacion_filtro)): ?>
                    <span class="filter-badge">
                        <i class="bi bi-geo-alt"></i> Ubicación: <?php echo htmlspecialchars($ubicacion_filtro); ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($estado_filtro)): ?>
                    <span class="filter-badge">
                        <i class="bi bi-clock"></i> Estado: <?php echo ($estado_filtro == 'activo') ? 'Activos' : 'Expirados'; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="alert alert-info mb-4">
    <h6 class="mb-2"><i class="bi bi-info-circle"></i> Tipos de servicios:</h6>
    <span class="badge bg-info me-2"><i class="bi bi-calendar-check"></i> SERVICIO</span> 
    <small>Agenda una cita directamente</small>
    <br>
    <span class="badge bg-warning text-dark mt-2 me-2"><i class="bi bi-file-earmark-text"></i> OFERTA</span> 
    <small>Requiere solicitud formal con documentos</small>
</div>

<?php if (count($servicios_por_categoria) > 0): ?>
    <?php foreach ($servicios_por_categoria as $categoria => $servicios_cat): ?>
        <div class="category-header">
            <h3 class="mb-0"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($categoria); ?></h3>
        </div>
        
        <div class="row">
            <?php foreach ($servicios_cat as $servicio): ?>
                <?php
                // Obtener imagen del servicio
                $imagen = $imagenes_servicios[$servicio['nombre']] ?? $imagenes_servicios['default'];
                ?>
                <div class="col-md-6">
                    <div class="service-card">
                        <img src="../../<?php echo htmlspecialchars($imagen); ?>" 
                             alt="<?php echo htmlspecialchars($servicio['nombre']); ?>"
                             class="service-image"
                             onerror="this.src='../../images/servicio-default.jpg'">
                        
                        <div class="service-content">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="text-success mb-0"><?php echo htmlspecialchars($servicio['nombre']); ?></h5>
                                <span class="badge bg-<?php echo $servicio['tipo'] === 'oferta' ? 'warning text-dark' : 'info'; ?>">
                                    <?php echo strtoupper($servicio['tipo']); ?>
                                </span>
                            </div>
                            
                            <p class="text-muted mb-3">
                                <?php 
                                $desc = htmlspecialchars($servicio['descripcion']);
                                echo strlen($desc) > 200 ? substr($desc, 0, 200) . '...' : $desc;
                                ?>
                            </p>
                            
                            <div class="mb-3">
                                <?php if ($servicio['requiere_cita']): ?>
                                    <span class="badge bg-primary me-2">
                                        <i class="bi bi-calendar-check"></i> Requiere cita
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($servicio['requiere_documentacion']): ?>
                                    <span class="badge bg-secondary me-2">
                                        <i class="bi bi-file-earmark-text"></i> Requiere documentación
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($servicio['duracion_estimada']): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-clock"></i> 
                                        <?php 
                                        $dias = $servicio['duracion_estimada'];
                                        if ($dias >= 365) {
                                            echo round($dias/365, 1) . ' año(s)';
                                        } else if ($dias >= 30) {
                                            echo round($dias/30) . ' mes(es)';
                                        } else {
                                            echo $dias . ' día(s)';
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($servicio['ubicacion']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($servicio['ubicacion']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($servicio['fecha_limite']): 
                                $fecha_limite = strtotime($servicio['fecha_limite']);
                                $hoy = strtotime('today');
                                $estado_servicio = ($fecha_limite >= $hoy) ? 'activo' : 'expirado';
                            ?>
                                <div class="alert alert-<?php echo $estado_servicio == 'activo' ? 'warning' : 'danger'; ?> mb-3 py-2">
                                    <small><i class="bi bi-calendar-event"></i> 
                                    <?php echo ($estado_servicio == 'activo') ? 'Disponible hasta: ' : 'Expirado el: '; ?>
                                    <?php echo date('d/m/Y', $fecha_limite); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($servicio['tipo'] === 'servicio'): ?>
                                <a href="citas.php?accion=nueva&id_servicio=<?php echo $servicio['id_servicio']; ?>" 
                                   class="btn btn-servicio w-100">
                                    <i class="bi bi-calendar-plus"></i> Agendar Cita
                                </a>
                            <?php else: ?>
                                <a href="nueva_solicitud.php?id_servicio=<?php echo $servicio['id_servicio']; ?>" 
                                   class="btn btn-oferta w-100">
                                    <i class="bi bi-file-earmark-plus"></i> Solicitar Beneficio
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox" style="font-size: 64px; opacity: 0.3;"></i>
        <p class="text-muted mt-3">
            <?php if (!empty($termino_busqueda) || !empty($categoria_filtro) || !empty($ubicacion_filtro) || !empty($estado_filtro)): ?>
                No se encontraron servicios con los criterios de búsqueda seleccionados
            <?php else: ?>
                No hay servicios disponibles en este momento
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<script>
// Validación del formulario en el cliente
document.getElementById('searchForm').addEventListener('submit', function(e) {
    const termino = document.getElementById('termino').value.trim();
    
    // Si el término no está vacío, validar
    if (termino.length > 0) {
        // Validar mínimo 3 caracteres
        if (termino.length < 3) {
            e.preventDefault();
            alert('El término de búsqueda debe contener al menos 3 caracteres');
            return false;
        }
        
        // Validar caracteres permitidos
        const regex = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/;
        if (!regex.test(termino)) {
            e.preventDefault();
            alert('El término de búsqueda contiene caracteres no permitidos. Solo se permiten letras, números y espacios');
            return false;
        }
    }
    
    return true;
});
</script>

<?php
// Obtener el contenido capturado
$content = ob_get_clean();

// Incluir el layout del estudiante
require_once 'layout_estudiante.php';
?>