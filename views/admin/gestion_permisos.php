<?php
/**
 * Gestión de Permisos - Administrador
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
$permisos = [];
$modulos = [];
$error = null;
$mensaje = null;

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'eliminar' && $id > 0) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE permisos SET activo = 0 WHERE id_permiso = ?");
        $stmt->execute([$id]);
        $mensaje = ['tipo' => 'success', 'texto' => 'Permiso eliminado correctamente.'];
    } catch (Exception $e) {
        $error = "Error al eliminar el permiso: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Obtener permisos
    $stmt = $conn->prepare("
        SELECT p.*, 
               COUNT(rp.id_rol) as asignado_a_roles
        FROM permisos p
        LEFT JOIN roles_permisos rp ON p.id_permiso = rp.id_permiso
        WHERE p.activo = 1
        GROUP BY p.id_permiso
        ORDER BY p.modulo, p.nombre_permiso
    ");
    $stmt->execute();
    $permisos = $stmt->fetchAll();
    
    // Obtener módulos únicos
    $stmt = $conn->prepare("SELECT DISTINCT modulo FROM permisos WHERE activo = 1 ORDER BY modulo");
    $stmt->execute();
    $modulos = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
} catch (Exception $e) {
    $error = "Error al cargar los permisos: " . $e->getMessage();
}

$page_title = "Gestión de Permisos";
$page_subtitle = "Administrar permisos del sistema";

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
    <h2 class="card-title">Gestión de Permisos</h2>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form class="d-flex" method="get">
                <input type="text" class="form-control" placeholder="Buscar permiso..." name="search">
                <button type="submit" class="btn btn-admin ms-2">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-plus-circle"></i> Nuevo Permiso
            </a>
        </div>
    </div>
    
    <?php if (count($permisos) > 0): ?>
        <?php foreach ($modulos as $modulo): ?>
            <div class="mb-4">
                <h5 class="text-admin mb-3"><?php echo ucfirst($modulo); ?></h5>
                <div class="row g-3">
                    <?php 
                    $permisos_modulo = array_filter($permisos, function($p) use ($modulo) {
                        return $p['modulo'] == $modulo;
                    });
                    ?>
                    <?php foreach ($permisos_modulo as $permiso): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="list-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="list-item-title"><?php echo htmlspecialchars($permiso['nombre_permiso']); ?></div>
                                        <div class="list-item-meta">
                                            <?php echo htmlspecialchars($permiso['descripcion'] ?? 'Sin descripción'); ?>
                                        </div>
                                        <div class="list-item-meta">
                                            <small class="text-muted">
                                                Asignado a <?php echo $permiso['asignado_a_roles']; ?> roles
                                            </small>
                                        </div>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=editar&id=<?php echo $permiso['id_permiso']; ?>" class="btn btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?action=eliminar&id=<?php echo $permiso['id_permiso']; ?>" 
                                           class="btn btn-outline-danger" title="Eliminar"
                                           onclick="return confirm('¿Está seguro de eliminar este permiso?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-shield-check"></i>
            <p>No hay permisos registrados</p>
            <a href="?action=nuevo" class="btn btn-admin mt-3">
                <i class="bi bi-plus-circle"></i> Crear primer permiso
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>