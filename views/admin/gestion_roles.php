<?php
/**
 * Gestión de Roles - Administrador
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
$roles = [];
$permisos = [];
$error = null;
$mensaje = null;

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'eliminar' && $id > 0) {
    try {
        $conn = getDBConnection();
        // Verificar si hay usuarios con este rol
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios WHERE id_rol = ? AND activo = 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = "No se puede eliminar el rol porque hay usuarios asignados.";
        } else {
            $stmt = $conn->prepare("UPDATE roles SET activo = 0 WHERE id_rol = ?");
            $stmt->execute([$id]);
            $mensaje = ['tipo' => 'success', 'texto' => 'Rol eliminado correctamente.'];
        }
    } catch (Exception $e) {
        $error = "Error al eliminar el rol: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Obtener roles
    $stmt = $conn->prepare("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    // Obtener permisos para formulario
    $stmt = $conn->prepare("SELECT * FROM permisos WHERE activo = 1 ORDER BY modulo, nombre_permiso");
    $stmt->execute();
    $permisos = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar los roles: " . $e->getMessage();
}

$page_title = "Gestión de Roles";
$page_subtitle = "Administrar roles del sistema";

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
    <h2 class="card-title">Gestión de Roles</h2>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form class="d-flex" method="get">
                <input type="text" class="form-control" placeholder="Buscar rol..." name="search">
                <button type="submit" class="btn btn-admin ms-2">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-plus-circle"></i> Nuevo Rol
            </a>
        </div>
    </div>
    
    <?php if (count($roles) > 0): ?>
        <div class="row g-3">
            <?php foreach ($roles as $rol): ?>
                <div class="col-md-6">
                    <div class="list-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="list-item-title"><?php echo htmlspecialchars($rol['nombre_rol']); ?></div>
                                <div class="list-item-meta">
                                    <?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin descripción'); ?>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=editar&id=<?php echo $rol['id_rol']; ?>" class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?action=eliminar&id=<?php echo $rol['id_rol']; ?>" 
                                   class="btn btn-outline-danger" title="Eliminar"
                                   onclick="return confirm('¿Está seguro de eliminar este rol?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-person-badge"></i>
            <p>No hay roles registrados</p>
            <a href="?action=nuevo" class="btn btn-admin mt-3">
                <i class="bi bi-plus-circle"></i> Crear primer rol
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>