<?php
/**
 * Gestión de Usuarios - Administrador
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
$usuarios = [];
$roles = [];
$error = null;
$mensaje = null;

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'eliminar' && $id > 0) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $mensaje = ['tipo' => 'success', 'texto' => 'Usuario desactivado correctamente.'];
    } catch (Exception $e) {
        $error = "Error al desactivar el usuario: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Obtener usuarios
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.activo = 1
        ORDER BY u.nombre_completo
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll();
    
    // Obtener roles para formulario
    $stmt = $conn->prepare("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar los usuarios: " . $e->getMessage();
}

$page_title = "Gestión de Usuarios";
$page_subtitle = "Administrar usuarios del sistema";

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
    <h2 class="card-title">Gestión de Usuarios</h2>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <form class="d-flex" method="get">
                <input type="text" class="form-control" placeholder="Buscar usuario..." name="search">
                <button type="submit" class="btn btn-admin ms-2">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-person-plus"></i> Nuevo Usuario
            </a>
        </div>
    </div>
    
    <?php if (count($usuarios) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Facultad/Depto</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>
                                <div><?php echo htmlspecialchars($usuario['nombre_completo']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($usuario['telefono'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['correo_institucional']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $usuario['nombre_rol'] === 'Administrador' ? 'danger' : 
                                         ($usuario['nombre_rol'] === 'Trabajadora Social' ? 'success' : 'info');
                                ?>">
                                    <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($usuario['facultad']): ?>
                                    <?php echo htmlspecialchars($usuario['facultad']); ?>
                                <?php elseif ($usuario['departamento']): ?>
                                    <?php echo htmlspecialchars($usuario['departamento']); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($usuario['ultimo_acceso']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?action=editar&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?action=eliminar&id=<?php echo $usuario['id_usuario']; ?>" 
                                       class="btn btn-outline-danger" title="Desactivar"
                                       onclick="return confirm('¿Está seguro de desactivar este usuario?');">
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
            <i class="bi bi-people"></i>
            <p>No hay usuarios registrados</p>
            <a href="?action=nuevo" class="btn btn-admin mt-3">
                <i class="bi bi-person-plus"></i> Crear primer usuario
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>