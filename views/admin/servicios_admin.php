<?php
/**
 * Gestión de Servicios y Ofertas - Administrador
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
$servicios = [];
$error = null;
$mensaje = null;

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'eliminar' && $id > 0) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE servicios_ofertas SET activo = 0 WHERE id_servicio = ?");
        $stmt->execute([$id]);
        $mensaje = ['tipo' => 'success', 'texto' => 'Servicio eliminado correctamente.'];
    } catch (Exception $e) {
        $error = "Error al eliminar el servicio: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT s.*, c.nombre_categoria, t.nombre_completo as trabajadora_nombre
        FROM servicios_ofertas s
        LEFT JOIN categorias_servicios c ON s.id_categoria = c.id_categoria
        LEFT JOIN usuarios t ON s.id_trabajadora_social = t.id_usuario
        WHERE s.activo = 1
        ORDER BY s.tipo, s.nombre
    ");
    $stmt->execute();
    $servicios = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar los servicios: " . $e->getMessage();
}

$page_title = "Servicios y Ofertas";
$page_subtitle = "Gestionar servicios y ofertas del sistema";

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($mensaje['texto']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="content-card">
    <h2 class="card-title">Servicios y Ofertas</h2>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form class="d-flex" method="get">
                <input type="text" class="form-control" placeholder="Buscar servicio..." name="search">
                <button type="submit" class="btn btn-admin ms-2">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-plus-circle"></i> Nuevo Servicio
            </a>
        </div>
    </div>
    
    <?php if (count($servicios) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Trabajadora</th>
                        <th>Fecha Límite</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicios as $servicio): ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php echo $servicio['tipo'] == 'servicio' ? 'info' : 'success'; ?>">
                                    <?php echo ucfirst($servicio['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($servicio['nombre']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($servicio['descripcion'], 0, 50)) . '...'; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($servicio['nombre_categoria'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($servicio['trabajadora_nombre'] ?? 'No asignada'); ?></td>
                            <td>
                                <?php if ($servicio['fecha_limite']): ?>
                                    <?php echo date('d/m/Y', strtotime($servicio['fecha_limite'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">No aplica</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $servicio['activo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $servicio['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?action=editar&id=<?php echo $servicio['id_servicio']; ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?action=eliminar&id=<?php echo $servicio['id_servicio']; ?>" 
                                       class="btn btn-outline-danger" title="Eliminar"
                                       onclick="return confirm('¿Está seguro de eliminar este servicio?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-list-check"></i>
            <p>No hay servicios registrados</p>
            <a href="?action=nuevo" class="btn btn-admin mt-3">
                <i class="bi bi-plus-circle"></i> Crear primer servicio
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>