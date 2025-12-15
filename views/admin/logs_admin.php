<?php
/**
 * Logs y Auditoría del Sistema
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
$logs = [];
$error = null;
$tipo_log = isset($_GET['tipo']) ? $_GET['tipo'] : 'sistema'; // 'sistema' o 'acceso'

try {
    $conn = getDBConnection();
    
    if ($tipo_log == 'sistema') {
        $stmt = $conn->prepare("
            SELECT ls.*, u.nombre_completo 
            FROM logs_sistema ls
            LEFT JOIN usuarios u ON ls.id_usuario = u.id_usuario
            ORDER BY ls.fecha_hora DESC
            LIMIT 100
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT la.*, u.nombre_completo 
            FROM logs_acceso la
            LEFT JOIN usuarios u ON la.id_usuario = u.id_usuario
            ORDER BY la.fecha_hora DESC
            LIMIT 100
        ");
    }
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar los logs: " . $e->getMessage();
}

$page_title = "Logs y Auditoría";
$page_subtitle = "Registros de actividad del sistema";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="content-card">
    <h2 class="card-title">Logs y Auditoría</h2>
    
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tipo_log == 'sistema' ? 'active' : ''; ?>" href="?tipo=sistema">Logs del Sistema</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tipo_log == 'acceso' ? 'active' : ''; ?>" href="?tipo=acceso">Logs de Acceso</a>
        </li>
    </ul>
    
    <?php if (count($logs) > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <?php if ($tipo_log == 'sistema'): ?>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>Registro</th>
                        <?php else: ?>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>IP</th>
                            <th>Exitoso</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <?php if ($tipo_log == 'sistema'): ?>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($log['nombre_completo'] ?? 'Sistema'); ?></td>
                                <td><?php echo htmlspecialchars($log['modulo']); ?></td>
                                <td><?php echo htmlspecialchars($log['accion']); ?></td>
                                <td><?php echo htmlspecialchars($log['tabla_afectada']); ?></td>
                                <td><?php echo $log['id_registro_afectado']; ?></td>
                            <?php else: ?>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha_hora'])); ?></td>
                                <td><?php echo htmlspecialchars($log['nombre_completo'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['accion']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <?php if ($log['exitoso']): ?>
                                        <span class="badge bg-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-journal-text"></i>
            <p>No hay registros de logs</p>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>