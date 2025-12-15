<?php
/**
 * Reportes y Estadísticas del Sistema
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
$reportes = [];
$estadisticas_generales = [];
$reportes_guardados = [];
$filtros = [
    'fecha_inicio' => isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01'),
    'fecha_fin' => isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d'),
    'tipo_servicio' => isset($_GET['tipo_servicio']) ? $_GET['tipo_servicio'] : '',
    'categoria' => isset($_GET['categoria']) ? $_GET['categoria'] : '',
    'facultad' => isset($_GET['facultad']) ? $_GET['facultad'] : '',
    'estado' => isset($_GET['estado']) ? $_GET['estado'] : ''
];

$mensaje = '';
$error = '';

try {
    $conn = getDBConnection();
    
    // Obtener categorías de servicios para el filtro
    $stmt = $conn->query("SELECT id_categoria, nombre_categoria FROM categorias_servicios WHERE activo = 1 ORDER BY nombre_categoria");
    $categorias = $stmt->fetchAll();
    
    // Obtener facultades para el filtro
    $stmt = $conn->query("SELECT DISTINCT facultad FROM usuarios WHERE facultad IS NOT NULL AND facultad != '' ORDER BY facultad");
    $facultades = $stmt->fetchAll();
    
    // Generar reporte según los filtros
    if (isset($_GET['generar'])) {
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        // Filtro por fecha
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $where_conditions[] = "s.fecha_solicitud BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $filtros['fecha_inicio'];
            $params[] = $filtros['fecha_fin'];
            $param_types .= 'ss';
        }
        
        // Filtro por categoría
        if (!empty($filtros['categoria'])) {
            $where_conditions[] = "c.id_categoria = ?";
            $params[] = $filtros['categoria'];
            $param_types .= 'i';
        }
        
        // Filtro por facultad
        if (!empty($filtros['facultad'])) {
            $where_conditions[] = "u.facultad = ?";
            $params[] = $filtros['facultad'];
            $param_types .= 's';
        }
        
        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $where_conditions[] = "s.estado = ?";
            $params[] = $filtros['estado'];
            $param_types .= 's';
        }
        
        // Filtro por tipo de servicio
        if (!empty($filtros['tipo_servicio'])) {
            $where_conditions[] = "ts.nombre_tipo = ?";
            $params[] = $filtros['tipo_servicio'];
            $param_types .= 's';
        }
        
        // Construir WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // 1. Estadísticas generales del período
        $sql_general = "
            SELECT 
                COUNT(DISTINCT s.id_solicitud) as total_solicitudes,
                COUNT(DISTINCT s.id_estudiante) as estudiantes_unicos,
                COUNT(DISTINCT s.id_trabajadora_asignada) as trabajadoras_involucradas,
                AVG(TIMESTAMPDIFF(HOUR, s.fecha_solicitud, s.fecha_respuesta)) as tiempo_respuesta_promedio,
                SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as solicitudes_aprobadas,
                SUM(CASE WHEN s.estado = 'rechazada' THEN 1 ELSE 0 END) as solicitudes_rechazadas,
                SUM(CASE WHEN s.estado = 'pendiente' THEN 1 ELSE 0 END) as solicitudes_pendientes,
                SUM(CASE WHEN s.estado = 'en_revision' THEN 1 ELSE 0 END) as solicitudes_revision
            FROM solicitudes s
            INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            LEFT JOIN servicios_ofertas so ON s.id_servicio = so.id_servicio
            LEFT JOIN categorias_servicios c ON so.id_categoria = c.id_categoria
            $where_clause
        ";
        
        $stmt = $conn->prepare($sql_general);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $estadisticas_generales = $stmt->fetch();
        
        // 2. Solicitudes por tipo
        $sql_tipos = "
            SELECT 
                ts.nombre_tipo,
                COUNT(*) as cantidad,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM solicitudes s2 $where_clause)), 1) as porcentaje
            FROM solicitudes s
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
            LEFT JOIN servicios_ofertas so ON s.id_servicio = so.id_servicio
            LEFT JOIN categorias_servicios c ON so.id_categoria = c.id_categoria
            $where_clause
            GROUP BY ts.nombre_tipo
            ORDER BY cantidad DESC
        ";
        
        $stmt = $conn->prepare($sql_tipos);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $solicitudes_por_tipo = $stmt->fetchAll();
        
        // 3. Solicitudes por estado
        $sql_estados = "
            SELECT 
                s.estado,
                COUNT(*) as cantidad,
                CASE s.estado
                    WHEN 'pendiente' THEN 'Pendiente'
                    WHEN 'en_revision' THEN 'En Revisión'
                    WHEN 'aprobada' THEN 'Aprobada'
                    WHEN 'rechazada' THEN 'Rechazada'
                    WHEN 'requiere_informacion' THEN 'Requiere Info'
                    ELSE s.estado
                END as estado_texto
            FROM solicitudes s
            INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            LEFT JOIN servicios_ofertas so ON s.id_servicio = so.id_servicio
            LEFT JOIN categorias_servicios c ON so.id_categoria = c.id_categoria
            $where_clause
            GROUP BY s.estado
            ORDER BY cantidad DESC
        ";
        
        $stmt = $conn->prepare($sql_estados);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $solicitudes_por_estado = $stmt->fetchAll();
        
        // 4. Solicitudes por facultad
        $sql_facultades = "
            SELECT 
                u.facultad,
                COUNT(*) as cantidad,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM solicitudes s2 $where_clause)), 1) as porcentaje
            FROM solicitudes s
            INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
            INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
            LEFT JOIN servicios_ofertas so ON s.id_servicio = so.id_servicio
            LEFT JOIN categorias_servicios c ON so.id_categoria = c.id_categoria
            $where_clause
            GROUP BY u.facultad
            HAVING u.facultad IS NOT NULL AND u.facultad != ''
            ORDER BY cantidad DESC
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($sql_facultades);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        $solicitudes_por_facultad = $stmt->fetchAll();
        
        // 5. Citas en el período
        $sql_citas = "
            SELECT 
                DATE(c.fecha_cita) as fecha,
                COUNT(*) as cantidad_citas,
                SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN c.estado = 'cancelada' OR c.estado = 'no_asistio' THEN 1 ELSE 0 END) as canceladas
            FROM citas c
            WHERE c.fecha_cita BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(c.fecha_cita)
            ORDER BY fecha DESC
            LIMIT 15
        ";
        
        $stmt = $conn->prepare($sql_citas);
        $stmt->execute([$filtros['fecha_inicio'], $filtros['fecha_fin']]);
        $citas_por_fecha = $stmt->fetchAll();
        
        // 6. Trabajadoras sociales más activas
        $sql_trabajadoras = "
            SELECT 
                t.nombre_completo,
                COUNT(DISTINCT s.id_solicitud) as solicitudes_atendidas,
                COUNT(DISTINCT c.id_cita) as citas_atendidas,
                AVG(TIMESTAMPDIFF(HOUR, s.fecha_solicitud, s.fecha_respuesta)) as tiempo_respuesta_promedio
            FROM usuarios t
            LEFT JOIN solicitudes s ON t.id_usuario = s.id_trabajadora_asignada 
                AND s.fecha_solicitud BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            LEFT JOIN citas c ON t.id_usuario = c.id_trabajadora_social 
                AND c.fecha_cita BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            WHERE t.id_rol IN (2, 4) AND t.activo = 1
            GROUP BY t.id_usuario
            ORDER BY solicitudes_atendidas DESC
        ";
        
        $stmt = $conn->prepare($sql_trabajadoras);
        $stmt->execute([
            $filtros['fecha_inicio'], $filtros['fecha_fin'],
            $filtros['fecha_inicio'], $filtros['fecha_fin']
        ]);
        $trabajadoras_activas = $stmt->fetchAll();
        
    }
    
    // Obtener reportes guardados (si existiera la tabla)
    $stmt = $conn->query("
        SELECT 'reporte_general' as tipo, 'Reporte General del Sistema' as nombre, NOW() as fecha
        UNION ALL
        SELECT 'solicitudes_mensual' as tipo, 'Solicitudes Mensuales' as nombre, NOW() as fecha
        LIMIT 5
    ");
    $reportes_guardados = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al generar reportes: " . $e->getMessage();
}

$page_title = "Reportes y Estadísticas";
$page_subtitle = "Análisis y estadísticas del sistema";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Panel de Filtros -->
    <div class="col-lg-3">
        <div class="content-card mb-4">
            <h2 class="card-title">Filtros del Reporte</h2>
            <form method="GET" action="" id="filtrosForm">
                <div class="mb-3">
                    <label class="form-label">Rango de Fechas</label>
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="date" class="form-control" name="fecha_inicio" 
                                   value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                        </div>
                        <div class="col-12">
                            <input type="date" class="form-control" name="fecha_fin" 
                                   value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Categoría de Servicio</label>
                    <select class="form-select" name="categoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id_categoria']; ?>" 
                                <?php echo $filtros['categoria'] == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nombre_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Facultad</label>
                    <select class="form-select" name="facultad">
                        <option value="">Todas las facultades</option>
                        <?php foreach ($facultades as $fac): ?>
                            <option value="<?php echo htmlspecialchars($fac['facultad']); ?>" 
                                <?php echo $filtros['facultad'] == $fac['facultad'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fac['facultad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Estado de Solicitud</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" <?php echo $filtros['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="en_revision" <?php echo $filtros['estado'] == 'en_revision' ? 'selected' : ''; ?>>En Revisión</option>
                        <option value="aprobada" <?php echo $filtros['estado'] == 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                        <option value="rechazada" <?php echo $filtros['estado'] == 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                        <option value="requiere_informacion" <?php echo $filtros['estado'] == 'requiere_informacion' ? 'selected' : ''; ?>>Requiere Información</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Tipo de Servicio</label>
                    <input type="text" class="form-control" name="tipo_servicio" 
                           value="<?php echo htmlspecialchars($filtros['tipo_servicio']); ?>">
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="generar" value="1" class="btn btn-admin">
                        <i class="bi bi-graph-up"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn btn-outline-admin" onclick="limpiarFiltros()">
                        <i class="bi bi-eraser"></i> Limpiar Filtros
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Reportes Guardados -->
        <div class="content-card">
            <h2 class="card-title">Reportes Guardados</h2>
            <?php if (count($reportes_guardados) > 0): ?>
                <div class="list-group">
                    <?php foreach ($reportes_guardados as $reporte): ?>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($reporte['nombre']); ?></h6>
                                <small><?php echo date('d/m/Y', strtotime($reporte['fecha'])); ?></small>
                            </div>
                            <small class="text-muted">Tipo: <?php echo htmlspecialchars($reporte['tipo']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-folder"></i>
                    <p>No hay reportes guardados</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Contenido Principal -->
    <div class="col-lg-9">
        <!-- Resumen Estadístico -->
        <?php if (isset($_GET['generar'])): ?>
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="card-title m-0">Resumen Estadístico</h2>
                <div>
                    <button class="btn btn-outline-success btn-sm" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="exportarPDF()">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stats-card purple">
                        <div class="stats-label">Total Solicitudes</div>
                        <div class="stats-value"><?php echo $estadisticas_generales['total_solicitudes'] ?? 0; ?></div>
                        <div class="stats-desc">En el período</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="stats-card blue">
                        <div class="stats-label">Aprobadas</div>
                        <div class="stats-value"><?php echo $estadisticas_generales['solicitudes_aprobadas'] ?? 0; ?></div>
                        <div class="stats-desc">
                            <?php 
                            $total = $estadisticas_generales['total_solicitudes'] ?? 1;
                            $aprobadas = $estadisticas_generales['solicitudes_aprobadas'] ?? 0;
                            echo round(($aprobadas / $total) * 100, 1) . '%';
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="stats-card orange">
                        <div class="stats-label">Tiempo Respuesta</div>
                        <div class="stats-value"><?php echo round($estadisticas_generales['tiempo_respuesta_promedio'] ?? 0, 1); ?></div>
                        <div class="stats-desc">Horas promedio</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6">
                    <div class="stats-card green">
                        <div class="stats-label">Estudiantes Únicos</div>
                        <div class="stats-value"><?php echo $estadisticas_generales['estudiantes_unicos'] ?? 0; ?></div>
                        <div class="stats-desc">Con solicitudes</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos y Tablas -->
        <div class="row g-3">
            <!-- Solicitudes por Tipo -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h3 class="card-title">Solicitudes por Tipo</h3>
                    <?php if (count($solicitudes_por_tipo) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipo de Solicitud</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes_por_tipo as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nombre_tipo']); ?></td>
                                            <td><strong><?php echo $item['cantidad']; ?></strong></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-admin" role="progressbar" 
                                                         style="width: <?php echo $item['porcentaje']; ?>%">
                                                        <?php echo $item['porcentaje']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-pie-chart"></i>
                            <p>No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Solicitudes por Estado -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h3 class="card-title">Distribución por Estado</h3>
                    <?php if (count($solicitudes_por_estado) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Cantidad</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes_por_estado as $item): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['estado'] == 'aprobada' ? 'success' : 
                                                         ($item['estado'] == 'rechazada' ? 'danger' : 
                                                         ($item['estado'] == 'pendiente' ? 'warning' : 
                                                         ($item['estado'] == 'en_revision' ? 'info' : 'secondary'))); 
                                                ?>">
                                                    <?php echo htmlspecialchars($item['estado_texto']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo $item['cantidad']; ?></strong></td>
                                            <td class="text-end">
                                                <?php 
                                                $total = $estadisticas_generales['total_solicitudes'] ?? 1;
                                                $porcentaje = round(($item['cantidad'] / $total) * 100, 1);
                                                echo $porcentaje . '%';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-bar-chart"></i>
                            <p>No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Solicitudes por Facultad -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h3 class="card-title">Top 10 Facultades</h3>
                    <?php if (count($solicitudes_por_facultad) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Facultad</th>
                                        <th>Cantidad</th>
                                        <th>Porcentaje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes_por_facultad as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['facultad']); ?></td>
                                            <td><strong><?php echo $item['cantidad']; ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?php echo min($item['porcentaje'], 100); ?>%">
                                                        </div>
                                                    </div>
                                                    <span class="ms-2"><?php echo $item['porcentaje']; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-building"></i>
                            <p>No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Trabajadoras más activas -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h3 class="card-title">Trabajadoras más Activas</h3>
                    <?php if (count($trabajadoras_activas) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Trabajadora</th>
                                        <th>Solicitudes</th>
                                        <th>Citas</th>
                                        <th>Tiempo Prom.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trabajadoras_activas as $trabajadora): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($trabajadora['nombre_completo']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $trabajadora['solicitudes_atendidas']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $trabajadora['citas_atendidas']; ?></span></td>
                                            <td><?php echo round($trabajadora['tiempo_respuesta_promedio'] ?? 0, 1); ?>h</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-person-heart"></i>
                            <p>No hay datos para mostrar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Evolución de Citas -->
            <div class="col-12">
                <div class="content-card">
                    <h3 class="card-title">Evolución de Citas (Últimos 15 días)</h3>
                    <?php if (count($citas_por_fecha) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Total Citas</th>
                                        <th>Completadas</th>
                                        <th>Canceladas</th>
                                        <th>Tasa de Éxito</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citas_por_fecha as $cita): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($cita['fecha'])); ?></td>
                                            <td><strong><?php echo $cita['cantidad_citas']; ?></strong></td>
                                            <td><span class="badge bg-success"><?php echo $cita['completadas']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $cita['canceladas']; ?></span></td>
                                            <td>
                                                <?php 
                                                $total = $cita['cantidad_citas'];
                                                $completadas = $cita['completadas'];
                                                $tasa = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;
                                                ?>
                                                <span class="<?php echo $tasa >= 80 ? 'text-success' : 'text-warning'; ?>">
                                                    <?php echo $tasa; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-event"></i>
                            <p>No hay citas en el período</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Pantalla inicial -->
        <div class="content-card">
            <div class="text-center py-5">
                <i class="bi bi-graph-up-arrow" style="font-size: 80px; color: #adb5bd;"></i>
                <h3 class="mt-4">Generador de Reportes</h3>
                <p class="text-muted">Selecciona los filtros en el panel lateral y haz clic en "Generar Reporte" para visualizar las estadísticas del sistema.</p>
                <div class="mt-4">
                    <div class="row g-3 justify-content-center">
                        <div class="col-md-4">
                            <div class="stats-card purple">
                                <i class="bi bi-file-earmark-text stats-icon"></i>
                                <div class="stats-label">Reportes Disponibles</div>
                                <div class="stats-value">5</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card blue">
                                <i class="bi bi-calendar-check stats-icon"></i>
                                <div class="stats-label">Período Predeterminado</div>
                                <div class="stats-value">Este Mes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Función para limpiar filtros
function limpiarFiltros() {
    document.querySelector('#filtrosForm').reset();
    // Establecer fechas por defecto
    const hoy = new Date().toISOString().split('T')[0];
    const primerDiaMes = new Date();
    primerDiaMes.setDate(1);
    const primerDiaStr = primerDiaMes.toISOString().split('T')[0];
    
    document.querySelector('input[name="fecha_inicio"]').value = primerDiaStr;
    document.querySelector('input[name="fecha_fin"]').value = hoy;
}

// Funciones para exportar (placeholder)
function exportarExcel() {
    alert('Función de exportación a Excel en desarrollo. Se generaría el archivo con los datos actuales.');
    // Aquí iría la lógica real para generar Excel
    // window.location.href = 'exportar_excel.php?' + new URLSearchParams(window.location.search);
}

function exportarPDF() {
    alert('Función de exportación a PDF en desarrollo. Se generaría el reporte en formato PDF.');
    // Aquí iría la lógica real para generar PDF
    // window.location.href = 'exportar_pdf.php?' + new URLSearchParams(window.location.search);
}

// Auto-seleccionar fechas por defecto si no están definidas
document.addEventListener('DOMContentLoaded', function() {
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]');
    const fechaFin = document.querySelector('input[name="fecha_fin"]');
    
    if (!fechaInicio.value) {
        const primerDiaMes = new Date();
        primerDiaMes.setDate(1);
        fechaInicio.value = primerDiaMes.toISOString().split('T')[0];
    }
    
    if (!fechaFin.value) {
        fechaFin.value = new Date().toISOString().split('T')[0];
    }
    
    // Validar que fecha_inicio <= fecha_fin
    fechaInicio.addEventListener('change', function() {
        if (fechaInicio.value > fechaFin.value) {
            fechaFin.value = fechaInicio.value;
        }
    });
    
    fechaFin.addEventListener('change', function() {
        if (fechaInicio.value > fechaFin.value) {
            fechaInicio.value = fechaFin.value;
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>