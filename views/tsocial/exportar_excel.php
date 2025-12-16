<?php
/**
 * Exportar Reportes a Excel
 * Genera un archivo Excel (.xlsx) con los datos del reporte
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
                    ts.nombre_tipo as 'Tipo de Solicitud',
                    COUNT(*) as 'Total',
                    SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as 'Aprobadas',
                    SUM(CASE WHEN s.estado = 'rechazada' THEN 1 ELSE 0 END) as 'Rechazadas',
                    SUM(CASE WHEN s.estado = 'pendiente' THEN 1 ELSE 0 END) as 'Pendientes',
                    SUM(CASE WHEN s.estado = 'en_revision' THEN 1 ELSE 0 END) as 'En Revisión',
                    ROUND(AVG(TIMESTAMPDIFF(DAY, s.fecha_solicitud, COALESCE(s.fecha_respuesta, NOW()))), 1) as 'Tiempo Promedio (días)'
                FROM solicitudes s
                INNER JOIN tipos_solicitud ts ON s.id_tipo_solicitud = ts.id_tipo_solicitud
                WHERE s.id_trabajadora_asignada = ?
                AND DATE(s.fecha_solicitud) BETWEEN ? AND ?
                GROUP BY ts.nombre_tipo
                ORDER BY Total DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $titulo_reporte = 'Reporte de Solicitudes';
            $nombre_archivo = 'Reporte_Solicitudes';
            break;
            
        case 'citas':
            $stmt = $conn->prepare("
                SELECT 
                    s.nombre as 'Servicio',
                    COUNT(*) as 'Total Citas',
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as 'Completadas',
                    SUM(CASE WHEN c.estado = 'cancelada' THEN 1 ELSE 0 END) as 'Canceladas',
                    SUM(CASE WHEN c.estado = 'no_asistio' THEN 1 ELSE 0 END) as 'No Asistieron',
                    ROUND(AVG(TIMESTAMPDIFF(MINUTE, CONCAT(c.fecha_cita, ' ', c.hora_inicio), 
                                       CONCAT(c.fecha_cita, ' ', c.hora_fin))), 0) as 'Duración Promedio (min)'
                FROM citas c
                INNER JOIN servicios_ofertas s ON c.id_servicio = s.id_servicio
                WHERE c.id_trabajadora_social = ?
                AND c.fecha_cita BETWEEN ? AND ?
                GROUP BY s.nombre
                ORDER BY `Total Citas` DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $titulo_reporte = 'Reporte de Citas';
            $nombre_archivo = 'Reporte_Citas';
            break;
            
        case 'estudiantes':
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(u.facultad, 'Sin facultad') as 'Facultad',
                    COUNT(DISTINCT s.id_estudiante) as 'Estudiantes Únicos',
                    COUNT(*) as 'Total Solicitudes',
                    SUM(CASE WHEN s.estado = 'aprobada' THEN 1 ELSE 0 END) as 'Solicitudes Aprobadas',
                    CONCAT('$', ROUND(AVG(CASE WHEN b.monto_mensual IS NOT NULL THEN b.monto_mensual ELSE 0 END), 2)) as 'Monto Promedio'
                FROM solicitudes s
                INNER JOIN usuarios u ON s.id_estudiante = u.id_usuario
                LEFT JOIN beneficios_asignados b ON s.id_solicitud = b.id_solicitud
                WHERE s.id_trabajadora_asignada = ?
                AND DATE(s.fecha_solicitud) BETWEEN ? AND ?
                GROUP BY u.facultad
                ORDER BY `Estudiantes Únicos` DESC
            ");
            $stmt->execute([$_SESSION['user_id'], $fecha_inicio, $fecha_fin]);
            $titulo_reporte = 'Reporte por Facultad';
            $nombre_archivo = 'Reporte_Facultades';
            break;
    }
    
    $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay datos
    if (count($reporte_data) == 0) {
        die('No hay datos para exportar en el período seleccionado');
    }
    
    // Configurar headers para descarga de Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Agregar BOM UTF-8 para que Excel reconozca correctamente los caracteres especiales
    echo "\xEF\xBB\xBF";
    
    // Generar contenido HTML que Excel puede leer
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; }
            .header {
                background-color: #6B2C91;
                color: white;
                padding: 10px;
                text-align: center;
                font-size: 16px;
                font-weight: bold;
            }
            .info {
                padding: 10px;
                text-align: center;
                font-size: 12px;
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background-color: #6B2C91;
                color: white;
                padding: 10px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #000;
            }
            td {
                padding: 8px;
                text-align: center;
                border: 1px solid #ccc;
            }
            .total-row {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            Universidad Tecnológica de Panamá<br>
            Sistema de Bienestar Estudiantil<br>
            <?php echo $titulo_reporte; ?>
        </div>
        
        <div class="info">
            <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?><br>
            <strong>Generado por:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?><br>
            <strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php 
                    // Obtener nombres de columnas
                    $columnas = array_keys($reporte_data[0]);
                    foreach ($columnas as $columna): 
                    ?>
                        <th><?php echo htmlspecialchars($columna); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Inicializar totales
                $totales = array_fill_keys($columnas, 0);
                $es_numerico = [];
                
                foreach ($reporte_data as $fila): 
                ?>
                    <tr>
                        <?php 
                        foreach ($columnas as $columna):
                            $valor = $fila[$columna];
                            
                            // Detectar si es numérico para sumar
                            if (is_numeric($valor) || (is_string($valor) && preg_match('/^\$?[\d,\.]+$/', $valor))) {
                                $es_numerico[$columna] = true;
                                if (is_numeric($valor)) {
                                    $totales[$columna] += $valor;
                                }
                            }
                        ?>
                            <td><?php echo htmlspecialchars($valor); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Fila de totales -->
                <tr class="total-row">
                    <?php 
                    $primera_columna = true;
                    foreach ($columnas as $columna): 
                        if ($primera_columna) {
                            echo '<td><strong>TOTALES</strong></td>';
                            $primera_columna = false;
                        } elseif (isset($es_numerico[$columna]) && $es_numerico[$columna]) {
                            echo '<td><strong>' . number_format($totales[$columna], 2) . '</strong></td>';
                        } else {
                            echo '<td>-</td>';
                        }
                    endforeach; 
                    ?>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            Documento generado automáticamente por el Sistema de Bienestar Estudiantil UTP
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    die('Error al generar Excel: ' . $e->getMessage());
}
?>