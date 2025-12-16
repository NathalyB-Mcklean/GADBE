<?php
/**
 * Reportes y Estadísticas - Trabajadora Social
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
$reporte_data = [];
$estadisticas = [];
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo'] ?? 'solicitudes';

try {
    $conn = getDBConnection();
    
    // Generar reporte según tipo
    switch ($tipo_reporte) {
        case 'solicitudes':
            $stmt = $conn->prepare("
                SELECT 
                    ts.nombre_tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                    SUM(CASE WHEN s.estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN s.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN s.estado = 'en_revision' THEN 1 ELSE 0 END) as en_revision,
                    AVG(TIMESTAMPDIFF(DAY, s.fecha_solicitud, COALESCE(s.fecha_respuesta, NOW()))) as tiempo_promedio
                FROM solicitudes s
                INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
                WHERE s.id_trabajadora_asignada = ?
                AND DATE(s.fecha_solicitud) BETWEEN ? AND ?
                GROUP BY ts.nombre_tipo
                ORDER BY total DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $reporte_data = $stmt->fetchAll();
            break;
            
        case 'citas':
            $stmt = $conn->prepare("
                SELECT 
                    s.nombre as servicio,
                    COUNT(*) as total,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN c.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN c.estado = 'no_asistio' THEN 1 ELSE 0 END) as no_asistio,
                    AVG(TIMESTAMPDIFF(MINUTE, CONCAT(c.fecha_cita, ' ', c.hora_inicio), 
                                       CONCAT(c.fecha_cita, ' ', c.hora_fin))) as duracion_promedio
                FROM citas c
                INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
                WHERE c.id_trabajadora_social = ?
                AND c.fecha_cita BETWEEN ? AND ?
                GROUP BY s.nombre
                ORDER BY total DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $reporte_data = $stmt->fetchAll();
            break;
            
        case 'estudiantes':
            $stmt = $conn->prepare("
                SELECT 
                    u.facultad,
                    COUNT(DISTINCT s.id_estudiante) as estudiantes_unicos,
                    COUNT(*) as total_solicitudes,
                    SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as solicitudes_aprobadas,
                    AVG(CASE WHEN b.monto_mensual IS NOT NULL THEN b.monto_mensual ELSE 0 END) as monto_promedio
                FROM solicitudes s
                INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
                LEFT JOIN beneficios_asignados b ON s.id_solicitud = b.id_solicitud
                WHERE s.id_trabajadora_asignada = ?
                AND DATE(s.fecha_solicitud) BETWEEN ? AND ?
                GROUP BY u.facultad
                ORDER BY estudiantes_unicos DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $reporte_data = $stmt->fetchAll();
            break;
    }
    
    // Obtener datos para tendencias temporales
    $tendencias_data = [];
    switch ($tipo_reporte) {
        case 'solicitudes':
            $stmt_tendencias = $conn->prepare("
                SELECT 
                    DATE(fecha_solicitud) as fecha,
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
                FROM solicitudes
                WHERE id_trabajadora_asignada = ?
                AND DATE(fecha_solicitud) BETWEEN ? AND ?
                GROUP BY DATE(fecha_solicitud)
                ORDER BY fecha ASC
            ");
            $stmt_tendencias->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $tendencias_data = $stmt_tendencias->fetchAll();
            break;
            
        case 'citas':
            $stmt_tendencias = $conn->prepare("
                SELECT 
                    DATE(fecha_cita) as fecha,
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas
                FROM citas
                WHERE id_trabajadora_social = ?
                AND fecha_cita BETWEEN ? AND ?
                GROUP BY DATE(fecha_cita)
                ORDER BY fecha ASC
            ");
            $stmt_tendencias->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $tendencias_data = $stmt_tendencias->fetchAll();
            break;
            
        case 'estudiantes':
            $stmt_tendencias = $conn->prepare("
                SELECT 
                    DATE(s.fecha_solicitud) as fecha,
                    COUNT(DISTINCT s.id_estudiante) as estudiantes_unicos,
                    COUNT(*) as total_solicitudes,
                    SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas
                FROM solicitudes s
                WHERE s.id_trabajadora_asignada = ?
                AND DATE(s.fecha_solicitud) BETWEEN ? AND ?
                GROUP BY DATE(s.fecha_solicitud)
                ORDER BY fecha ASC
            ");
            $stmt_tendencias->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $tendencias_data = $stmt_tendencias->fetchAll();
            break;
    }
    
    // Estadísticas generales
    $stmt_stats = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM solicitudes WHERE id_trabajadora_asignada = ? AND estado = 'aprobada') as solicitudes_aprobadas,
            (SELECT COUNT(*) FROM citas WHERE id_trabajadora_social = ? AND estado = 'completada') as citas_completadas,
            (SELECT COUNT(DISTINCT id_estudiante) FROM solicitudes WHERE id_trabajadora_asignada = ?) as estudiantes_atendidos,
            (SELECT COALESCE(SUM(monto_mensual), 0) FROM beneficios_asignados ba 
             INNER JOIN solicitudes s ON ba.id_solicitud = s.id_solicitud 
             WHERE s.id_trabajadora_asignada = ? AND ba.estado = 'activo') as beneficios_activos,
            (SELECT AVG(TIMESTAMPDIFF(DAY, fecha_solicitud, fecha_respuesta)) 
             FROM solicitudes 
             WHERE id_trabajadora_asignada = ? AND fecha_respuesta IS NOT NULL) as tiempo_respuesta_promedio
    ");
    $stmt_stats->execute([
        $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], 
        $_SESSION['user_id'], $_SESSION['user_id']
    ]);
    $estadisticas = $stmt_stats->fetch();
    
} catch (Exception $e) {
    $error = "Error al generar reportes: " . $e->getMessage();
}

$page_title = "Reportes y Estadísticas";
$page_subtitle = "Análisis y métricas de gestión";

ob_start();
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Estadísticas Generales -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="stats-card purple">
            <div class="stats-value"><?php echo $estadisticas['solicitudes_aprobadas'] ?? 0; ?></div>
            <div class="stats-label">Solicitudes Aprobadas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card blue">
            <div class="stats-value"><?php echo $estadisticas['citas_completadas'] ?? 0; ?></div>
            <div class="stats-label">Citas Completadas</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card green">
            <div class="stats-value"><?php echo $estadisticas['estudiantes_atendidos'] ?? 0; ?></div>
            <div class="stats-label">Estudiantes Atendidos</div>
        </div>
    </div>

    <div class="col-md-2 col-6">
        <div class="stats-card teal">
            <div class="stats-value"><?php echo round($estadisticas['tiempo_respuesta_promedio'] ?? 0, 1); ?>d</div>
            <div class="stats-label">Tiempo Respuesta</div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stats-card" style="border-left-color: #6f42c1; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <div class="stats-value">
                <i class="bi bi-printer"></i>
            </div>
            <div class="stats-label">
                <button class="btn btn-link p-0 border-0" onclick="window.print()">
                    Imprimir Reporte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Reporte -->
<div class="content-card mb-4">
    <h2 class="card-title">Configurar Reporte</h2>
    <form method="GET" action="" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Tipo de Reporte</label>
            <select name="tipo" class="form-select" onchange="this.form.submit()">
                <option value="solicitudes" <?php echo $tipo_reporte == 'solicitudes' ? 'selected' : ''; ?>>Solicitudes</option>
                <option value="citas" <?php echo $tipo_reporte == 'citas' ? 'selected' : ''; ?>>Citas</option>
                <option value="estudiantes" <?php echo $tipo_reporte == 'estudiantes' ? 'selected' : ''; ?>>Estudiantes por Facultad</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control" 
                   value="<?php echo $fecha_inicio; ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha Fin</label>
            <input type="date" name="fecha_fin" class="form-control" 
                   value="<?php echo $fecha_fin; ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-purple w-100">
                <i class="bi bi-graph-up"></i> Generar Reporte
            </button>
        </div>
    </form>
</div>

<!-- Resultados del Reporte -->
<div class="content-card">
    <h2 class="card-title d-flex justify-content-between align-items-center">
        <span>
            <?php 
            $titulos = [
                'solicitudes' => 'Reporte de Solicitudes',
                'citas' => 'Reporte de Citas',
                'estudiantes' => 'Reporte por Facultad'
            ];
            echo $titulos[$tipo_reporte] ?? 'Reporte';
            ?>
        </span>
        <small class="text-muted">
            Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        </small>
    </h2>
    
    <?php if (count($reporte_data) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <?php if ($tipo_reporte == 'solicitudes'): ?>
                            <th>Tipo de Solicitud</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Aprobadas</th>
                            <th class="text-center">Rechazadas</th>
                            <th class="text-center">Pendientes</th>
                            <th class="text-center">En Revisión</th>
                            <th class="text-center">Tiempo Promedio (días)</th>
                        <?php elseif ($tipo_reporte == 'citas'): ?>
                            <th>Servicio</th>
                            <th class="text-center">Total Citas</th>
                            <th class="text-center">Completadas</th>
                            <th class="text-center">Canceladas</th>
                            <th class="text-center">No Asistieron</th>
                            <th class="text-center">Duración Promedio (min)</th>
                        <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                            <th>Facultad</th>
                            <th class="text-center">Estudiantes Únicos</th>
                            <th class="text-center">Total Solicitudes</th>
                            <th class="text-center">Solicitudes Aprobadas</th>
                            <th class="text-center">Monto Promedio</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte_data as $fila): ?>
                        <tr>
                            <?php if ($tipo_reporte == 'solicitudes'): ?>
                                <td><?php echo htmlspecialchars($fila['nombre_tipo']); ?></td>
                                <td class="text-center"><strong><?php echo $fila['total']; ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $fila['aprobadas']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $fila['rechazadas']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning"><?php echo $fila['pendientes']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $fila['en_revision']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php echo round($fila['tiempo_promedio'], 1); ?>
                                </td>
                            <?php elseif ($tipo_reporte == 'citas'): ?>
                                <td><?php echo htmlspecialchars($fila['servicio']); ?></td>
                                <td class="text-center"><strong><?php echo $fila['total']; ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $fila['completadas']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?php echo $fila['canceladas']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo $fila['no_asistio']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php echo round($fila['duracion_promedio'], 0); ?>
                                </td>
                            <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                                <td><?php echo htmlspecialchars($fila['facultad'] ?: 'Sin facultad'); ?></td>
                                <td class="text-center"><strong><?php echo $fila['estudiantes_unicos']; ?></strong></td>
                                <td class="text-center"><?php echo $fila['total_solicitudes']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $fila['solicitudes_aprobadas']; ?></span>
                                </td>
                                <td class="text-center">
                                    $<?php echo number_format($fila['monto_promedio'], 2); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <?php if ($tipo_reporte == 'solicitudes'): ?>
                            <th>TOTALES</th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'total')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'aprobadas')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'rechazadas')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'pendientes')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'en_revision')); ?></th>
                            <th class="text-center">
                                <?php 
                                $promedio = count($reporte_data) > 0 ? 
                                    array_sum(array_column($reporte_data, 'tiempo_promedio')) / count($reporte_data) : 0;
                                echo round($promedio, 1);
                                ?>
                            </th>
                        <?php elseif ($tipo_reporte == 'citas'): ?>
                            <th>TOTALES</th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'total')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'completadas')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'canceladas')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'no_asistio')); ?></th>
                            <th class="text-center">
                                <?php 
                                $promedio = count($reporte_data) > 0 ? 
                                    array_sum(array_column($reporte_data, 'duracion_promedio')) / count($reporte_data) : 0;
                                echo round($promedio, 0);
                                ?>
                            </th>
                        <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                            <th>TOTALES</th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'estudiantes_unicos')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'total_solicitudes')); ?></th>
                            <th class="text-center"><?php echo array_sum(array_column($reporte_data, 'solicitudes_aprobadas')); ?></th>
                            <th class="text-center">
                                <?php 
                                $promedio = count($reporte_data) > 0 ? 
                                    array_sum(array_column($reporte_data, 'monto_promedio')) / count($reporte_data) : 0;
                                echo '$' . number_format($promedio, 2);
                                ?>
                            </th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Gráficos con Chart.js -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="content-card">
                    <h4 class="card-title">
                        <i class="bi bi-pie-chart-fill text-purple"></i> 
                        Distribución por 
                        <?php 
                        if ($tipo_reporte == 'solicitudes') echo 'Estado';
                        elseif ($tipo_reporte == 'citas') echo 'Estado';
                        else echo 'Facultad';
                        ?>
                    </h4>
                    <canvas id="chartDistribucion" style="max-height: 300px;"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <h4 class="card-title">
                        <i class="bi bi-graph-up text-primary"></i> 
                        <?php echo $tipo_reporte == 'solicitudes' ? 'Top 5 Tipos de Solicitudes' : ($tipo_reporte == 'citas' ? 'Top 5 Servicios Más Solicitados' : 'Top 5 Facultades'); ?>
                    </h4>
                    <canvas id="chartTop" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Tendencias Temporales -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="content-card">
                    <h4 class="card-title">
                        <i class="bi bi-bar-chart-line-fill text-success"></i> 
                        Tendencias en el Período Seleccionado
                        <small class="text-muted ms-2"><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></small>
                    </h4>
                    <canvas id="chartTendencias" style="max-height: 350px;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Botones de Exportación -->
        <div class="d-flex justify-content-end mt-4 gap-2">
            <button class="btn btn-outline-purple" onclick="exportarPDF()">
                <i class="bi bi-file-pdf"></i> Exportar PDF
            </button>
            <button class="btn btn-outline-success" onclick="exportarExcel()">
                <i class="bi bi-file-excel"></i> Exportar Excel
            </button>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-graph-up" style="font-size: 64px;"></i>
            <h4>No hay datos para el período seleccionado</h4>
            <p>No se encontraron registros que coincidan con los filtros aplicados.</p>
            <a href="reportes_trabajadora.php" class="btn btn-outline-purple">
                <i class="bi bi-arrow-clockwise"></i> Intentar con otros filtros
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Datos de PHP a JavaScript
const reporteData = <?php echo json_encode($reporte_data); ?>;
const tendenciasData = <?php echo json_encode($tendencias_data); ?>;
const tipoReporte = '<?php echo $tipo_reporte; ?>';

// Paleta de colores UTP
const coloresUTP = {
    purple: '#6B2C91',
    green: '#00A859',
    blue: '#0d6efd',
    orange: '#fd7e14',
    red: '#dc3545',
    teal: '#20c997',
    yellow: '#ffc107',
    cyan: '#0dcaf0',
    pink: '#d63384',
    indigo: '#6610f2'
};

// ========================================
// 1. GRÁFICO DE DISTRIBUCIÓN (Pie/Doughnut)
// ========================================
if (reporteData.length > 0) {
    const ctxDistribucion = document.getElementById('chartDistribucion').getContext('2d');
    let dataDistribucion, labelsDistribucion;
    
    if (tipoReporte === 'solicitudes') {
        // Distribución por estado
        const totales = reporteData.reduce((acc, item) => {
            acc.aprobadas += parseInt(item.aprobadas);
            acc.rechazadas += parseInt(item.rechazadas);
            acc.pendientes += parseInt(item.pendientes);
            acc.en_revision += parseInt(item.en_revision);
            return acc;
        }, {aprobadas: 0, rechazadas: 0, pendientes: 0, en_revision: 0});
        
        labelsDistribucion = ['Aprobadas', 'Rechazadas', 'Pendientes', 'En Revisión'];
        dataDistribucion = [totales.aprobadas, totales.rechazadas, totales.pendientes, totales.en_revision];
        
    } else if (tipoReporte === 'citas') {
        // Distribución por estado de citas
        const totales = reporteData.reduce((acc, item) => {
            acc.completadas += parseInt(item.completadas);
            acc.canceladas += parseInt(item.canceladas);
            acc.no_asistio += parseInt(item.no_asistio);
            return acc;
        }, {completadas: 0, canceladas: 0, no_asistio: 0});
        
        labelsDistribucion = ['Completadas', 'Canceladas', 'No Asistieron'];
        dataDistribucion = [totales.completadas, totales.canceladas, totales.no_asistio];
        
    } else { // estudiantes
        // Top 5 facultades
        const top5 = reporteData.slice(0, 5);
        labelsDistribucion = top5.map(item => item.facultad || 'Sin facultad');
        dataDistribucion = top5.map(item => parseInt(item.estudiantes_unicos));
    }
    
    new Chart(ctxDistribucion, {
        type: 'doughnut',
        data: {
            labels: labelsDistribucion,
            datasets: [{
                data: dataDistribucion,
                backgroundColor: [
                    coloresUTP.green,
                    coloresUTP.red,
                    coloresUTP.yellow,
                    coloresUTP.blue,
                    coloresUTP.orange
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// ========================================
// 2. GRÁFICO TOP 5 (Barra Horizontal)
// ========================================
if (reporteData.length > 0) {
    const ctxTop = document.getElementById('chartTop').getContext('2d');
    const top5 = reporteData.slice(0, 5);
    let labelsTop, dataTop;
    
    if (tipoReporte === 'solicitudes') {
        labelsTop = top5.map(item => item.nombre_tipo);
        dataTop = top5.map(item => parseInt(item.total));
    } else if (tipoReporte === 'citas') {
        labelsTop = top5.map(item => item.servicio);
        dataTop = top5.map(item => parseInt(item.total));
    } else {
        labelsTop = top5.map(item => item.facultad || 'Sin facultad');
        dataTop = top5.map(item => parseInt(item.estudiantes_unicos));
    }
    
    new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: labelsTop,
            datasets: [{
                label: tipoReporte === 'solicitudes' ? 'Solicitudes' : 
                       tipoReporte === 'citas' ? 'Citas' : 'Estudiantes',
                data: dataTop,
                backgroundColor: coloresUTP.purple,
                borderColor: coloresUTP.purple,
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y: {
                    ticks: {
                        autoSkip: false,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// ========================================
// 3. GRÁFICO DE TENDENCIAS (Línea Temporal)
// ========================================
if (tendenciasData.length > 0) {
    const ctxTendencias = document.getElementById('chartTendencias').getContext('2d');
    const fechas = tendenciasData.map(item => {
        const fecha = new Date(item.fecha);
        return fecha.toLocaleDateString('es-PA', { day: '2-digit', month: 'short' });
    });
    
    let datasets = [];
    
    if (tipoReporte === 'solicitudes') {
        datasets = [
            {
                label: 'Total Solicitudes',
                data: tendenciasData.map(item => parseInt(item.total)),
                borderColor: coloresUTP.purple,
                backgroundColor: coloresUTP.purple + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Aprobadas',
                data: tendenciasData.map(item => parseInt(item.aprobadas)),
                borderColor: coloresUTP.green,
                backgroundColor: coloresUTP.green + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Rechazadas',
                data: tendenciasData.map(item => parseInt(item.rechazadas)),
                borderColor: coloresUTP.red,
                backgroundColor: coloresUTP.red + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }
        ];
    } else if (tipoReporte === 'citas') {
        datasets = [
            {
                label: 'Total Citas',
                data: tendenciasData.map(item => parseInt(item.total)),
                borderColor: coloresUTP.purple,
                backgroundColor: coloresUTP.purple + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Completadas',
                data: tendenciasData.map(item => parseInt(item.completadas)),
                borderColor: coloresUTP.green,
                backgroundColor: coloresUTP.green + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Canceladas',
                data: tendenciasData.map(item => parseInt(item.canceladas)),
                borderColor: coloresUTP.red,
                backgroundColor: coloresUTP.red + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }
        ];
    } else { // estudiantes
        datasets = [
            {
                label: 'Estudiantes Únicos',
                data: tendenciasData.map(item => parseInt(item.estudiantes_unicos)),
                borderColor: coloresUTP.blue,
                backgroundColor: coloresUTP.blue + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Total Solicitudes',
                data: tendenciasData.map(item => parseInt(item.total_solicitudes)),
                borderColor: coloresUTP.purple,
                backgroundColor: coloresUTP.purple + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Aprobadas',
                data: tendenciasData.map(item => parseInt(item.aprobadas)),
                borderColor: coloresUTP.green,
                backgroundColor: coloresUTP.green + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }
        ];
    }
    
    new Chart(ctxTendencias, {
        type: 'line',
        data: {
            labels: fechas,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            const fecha = new Date(tendenciasData[index].fecha);
                            return fecha.toLocaleDateString('es-PA', { 
                                weekday: 'long', 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Fecha'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Cantidad'
                    },
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

// ========================================
// FUNCIONES DE EXPORTACIÓN
// ========================================
function exportarPDF() {
    const tipo = '<?php echo $tipo_reporte; ?>';
    const inicio = '<?php echo $fecha_inicio; ?>';
    const fin = '<?php echo $fecha_fin; ?>';
    
    // Abrir en nueva ventana para imprimir como PDF
    window.open(`exportar_pdf.php?tipo=${tipo}&inicio=${inicio}&fin=${fin}`, '_blank');
}

function exportarExcel() {
    const tipo = '<?php echo $tipo_reporte; ?>';
    const inicio = '<?php echo $fecha_inicio; ?>';
    const fin = '<?php echo $fecha_fin; ?>';
    
    // Redirigir al script de exportación Excel
    window.location.href = `exportar_excel.php?tipo=${tipo}&inicio=${inicio}&fin=${fin}`;
}

// Auto-cerrar alertas
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<style>
@media print {
    .sidebar, .btn, .modal, .stats-card:last-child, .empty-state, .content-card:last-child .row {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
    }
    
    .content-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .page-header {
        page-break-after: avoid;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
}
</style>

<?php
$content = ob_get_clean();
require_once 'layout_trabajadora.php';
?>