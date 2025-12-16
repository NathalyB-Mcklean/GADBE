<?php
/**
 * Exportar Reportes a PDF (HTML a PDF simple)
 * Genera un HTML que el navegador puede imprimir como PDF
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    die('No autorizado');
}

if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    die('No autorizado');
}

// Obtener parámetros
$tipo_reporte = $_GET['tipo'] ?? 'solicitudes';
$fecha_inicio = $_GET['inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fin'] ?? date('Y-m-d');

try {
    $conn = getDBConnection();
    
    // Obtener datos según tipo de reporte
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
            $titulo_reporte = 'Reporte de Solicitudes';
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
            $titulo_reporte = 'Reporte de Citas';
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
            $titulo_reporte = 'Reporte por Facultad';
            break;
    }
    
    $reporte_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error al generar PDF: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_reporte; ?> - PDF</title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
        
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #6B2C91;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #6B2C91;
            margin: 0;
            font-size: 24px;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 16px;
            font-weight: normal;
        }
        
        .header h3 {
            margin: 10px 0 0 0;
            font-size: 18px;
            color: #333;
        }
        
        .info-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            border-left: 4px solid #6B2C91;
        }
        
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background-color: #6B2C91;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            border: 1px solid #555;
        }
        
        td {
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f0f0f0;
        }
        
        .total-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            border-top: 2px solid #6B2C91;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-info { background-color: #17a2b8; color: white; }
        .badge-secondary { background-color: #6c757d; color: white; }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background-color: #6B2C91;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background-color: #552270;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Imprimir / Guardar como PDF
    </button>
    
    <div class="header">
        <h1>Universidad Tecnológica de Panamá</h1>
        <h2>Sistema de Bienestar Estudiantil</h2>
        <h3><?php echo $titulo_reporte; ?></h3>
    </div>
    
    <div class="info-box">
        <p><strong>Período:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></p>
        <p><strong>Generado por:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
    
    <?php if (count($reporte_data) > 0): ?>
        <table>
            <thead>
                <tr>
                    <?php if ($tipo_reporte == 'solicitudes'): ?>
                        <th>Tipo de Solicitud</th>
                        <th>Total</th>
                        <th>Aprobadas</th>
                        <th>Rechazadas</th>
                        <th>Pendientes</th>
                        <th>En Revisión</th>
                        <th>Tiempo Promedio (días)</th>
                    <?php elseif ($tipo_reporte == 'citas'): ?>
                        <th>Servicio</th>
                        <th>Total Citas</th>
                        <th>Completadas</th>
                        <th>Canceladas</th>
                        <th>No Asistieron</th>
                        <th>Duración Promedio (min)</th>
                    <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                        <th>Facultad</th>
                        <th>Estudiantes Únicos</th>
                        <th>Total Solicitudes</th>
                        <th>Solicitudes Aprobadas</th>
                        <th>Monto Promedio</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte_data as $fila): ?>
                    <tr>
                        <?php if ($tipo_reporte == 'solicitudes'): ?>
                            <td style="text-align: left;"><?php echo htmlspecialchars($fila['nombre_tipo']); ?></td>
                            <td><strong><?php echo $fila['total']; ?></strong></td>
                            <td><span class="badge badge-success"><?php echo $fila['aprobadas']; ?></span></td>
                            <td><span class="badge badge-danger"><?php echo $fila['rechazadas']; ?></span></td>
                            <td><span class="badge badge-warning"><?php echo $fila['pendientes']; ?></span></td>
                            <td><span class="badge badge-info"><?php echo $fila['en_revision']; ?></span></td>
                            <td><?php echo round($fila['tiempo_promedio'], 1); ?></td>
                        <?php elseif ($tipo_reporte == 'citas'): ?>
                            <td style="text-align: left;"><?php echo htmlspecialchars($fila['servicio']); ?></td>
                            <td><strong><?php echo $fila['total']; ?></strong></td>
                            <td><span class="badge badge-success"><?php echo $fila['completadas']; ?></span></td>
                            <td><span class="badge badge-danger"><?php echo $fila['canceladas']; ?></span></td>
                            <td><span class="badge badge-secondary"><?php echo $fila['no_asistio']; ?></span></td>
                            <td><?php echo round($fila['duracion_promedio'], 0); ?></td>
                        <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                            <td style="text-align: left;"><?php echo htmlspecialchars($fila['facultad'] ?: 'Sin facultad'); ?></td>
                            <td><strong><?php echo $fila['estudiantes_unicos']; ?></strong></td>
                            <td><?php echo $fila['total_solicitudes']; ?></td>
                            <td><span class="badge badge-success"><?php echo $fila['solicitudes_aprobadas']; ?></span></td>
                            <td>$<?php echo number_format($fila['monto_promedio'], 2); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Fila de totales -->
                <tr class="total-row">
                    <?php if ($tipo_reporte == 'solicitudes'): ?>
                        <td style="text-align: left;"><strong>TOTALES</strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'total')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'aprobadas')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'rechazadas')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'pendientes')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'en_revision')); ?></strong></td>
                        <td><strong><?php echo round(array_sum(array_column($reporte_data, 'tiempo_promedio')) / count($reporte_data), 1); ?></strong></td>
                    <?php elseif ($tipo_reporte == 'citas'): ?>
                        <td style="text-align: left;"><strong>TOTALES</strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'total')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'completadas')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'canceladas')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'no_asistio')); ?></strong></td>
                        <td><strong><?php echo round(array_sum(array_column($reporte_data, 'duracion_promedio')) / count($reporte_data), 0); ?></strong></td>
                    <?php elseif ($tipo_reporte == 'estudiantes'): ?>
                        <td style="text-align: left;"><strong>TOTALES</strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'estudiantes_unicos')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'total_solicitudes')); ?></strong></td>
                        <td><strong><?php echo array_sum(array_column($reporte_data, 'solicitudes_aprobadas')); ?></strong></td>
                        <td><strong>$<?php echo number_format(array_sum(array_column($reporte_data, 'monto_promedio')) / count($reporte_data), 2); ?></strong></td>
                    <?php endif; ?>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 40px;">
            No hay datos para el período seleccionado
        </p>
    <?php endif; ?>
    
    <div class="footer">
        <p>Documento generado automáticamente por el Sistema de Bienestar Estudiantil UTP</p>
        <p>Este documento es válido para fines administrativos y de gestión interna</p>
    </div>
    
    <script>
        // Auto-imprimir después de cargar (opcional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>