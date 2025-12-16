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
$rol_actual = null;
$permisos_rol = [];

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        $conn = getDBConnection();
        
        // CREAR ROL
        if (isset($_POST['crear_rol'])) {
            $nombre = trim($_POST['nombre_rol']);
            $descripcion = trim($_POST['descripcion']);
            $permisos_seleccionados = $_POST['permisos'] ?? [];
            
            // Validar nombre único
            $stmt_check = $conn->prepare("SELECT COUNT(*) as existe FROM roles WHERE nombre_rol = ?");
            $stmt_check->execute([$nombre]);
            if ($stmt_check->fetch()['existe'] > 0) {
                $error = "Ya existe un rol con ese nombre";
            } else {
                // Insertar rol
                $stmt_insert = $conn->prepare("
                    INSERT INTO roles (nombre_rol, descripcion, activo) 
                    VALUES (?, ?, 1)
                ");
                $stmt_insert->execute([$nombre, $descripcion]);
                $id_rol = $conn->lastInsertId();
                
                // Asignar permisos
                if (count($permisos_seleccionados) > 0) {
                    $stmt_permisos = $conn->prepare("
                        INSERT INTO roles_permisos (id_rol, id_permiso) 
                        VALUES (?, ?)
                    ");
                    foreach ($permisos_seleccionados as $id_permiso) {
                        $stmt_permisos->execute([$id_rol, $id_permiso]);
                    }
                }
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Rol creado exitosamente'];
                header("Location: gestion_roles.php");
                exit();
            }
        }
        
        // ACTUALIZAR ROL
        if (isset($_POST['actualizar_rol'])) {
            $id_rol = intval($_POST['id_rol']);
            $nombre = trim($_POST['nombre_rol']);
            $descripcion = trim($_POST['descripcion']);
            $permisos_seleccionados = $_POST['permisos'] ?? [];
            
            // Validar nombre único (excepto el mismo rol)
            $stmt_check = $conn->prepare("
                SELECT COUNT(*) as existe 
                FROM roles 
                WHERE nombre_rol = ? AND id_rol != ?
            ");
            $stmt_check->execute([$nombre, $id_rol]);
            if ($stmt_check->fetch()['existe'] > 0) {
                $error = "Ya existe otro rol con ese nombre";
            } else {
                // Actualizar rol
                $stmt_update = $conn->prepare("
                    UPDATE roles 
                    SET nombre_rol = ?, descripcion = ? 
                    WHERE id_rol = ?
                ");
                $stmt_update->execute([$nombre, $descripcion, $id_rol]);
                
                // Eliminar permisos actuales
                $stmt_delete = $conn->prepare("DELETE FROM roles_permisos WHERE id_rol = ?");
                $stmt_delete->execute([$id_rol]);
                
                // Asignar nuevos permisos
                if (count($permisos_seleccionados) > 0) {
                    $stmt_permisos = $conn->prepare("
                        INSERT INTO roles_permisos (id_rol, id_permiso) 
                        VALUES (?, ?)
                    ");
                    foreach ($permisos_seleccionados as $id_permiso) {
                        $stmt_permisos->execute([$id_rol, $id_permiso]);
                    }
                }
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Rol actualizado exitosamente'];
                header("Location: gestion_roles.php");
                exit();
            }
        }
        
    } catch (Exception $e) {
        $error = "Error al procesar la acción: " . $e->getMessage();
    }
}

// Procesar acciones GET
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'eliminar' && $id > 0) {
    try {
        $conn = getDBConnection();
        
        // Verificar si hay usuarios con este rol
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM usuarios 
            WHERE id_rol = ? AND activo = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = "No se puede eliminar el rol porque hay {$result['count']} usuario(s) asignado(s).";
        } else {
            // Eliminar permisos del rol
            $stmt_permisos = $conn->prepare("DELETE FROM roles_permisos WHERE id_rol = ?");
            $stmt_permisos->execute([$id]);
            
            // Desactivar rol
            $stmt = $conn->prepare("UPDATE roles SET activo = 0 WHERE id_rol = ?");
            $stmt->execute([$id]);
            
            $mensaje = ['tipo' => 'success', 'texto' => 'Rol eliminado correctamente'];
            header("Location: gestion_roles.php");
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al eliminar el rol: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Si es editar, obtener datos del rol
    if ($action == 'editar' && $id > 0) {
        $stmt = $conn->prepare("SELECT * FROM roles WHERE id_rol = ? AND activo = 1");
        $stmt->execute([$id]);
        $rol_actual = $stmt->fetch();
        
        if ($rol_actual) {
            // Obtener permisos del rol
            $stmt_permisos_rol = $conn->prepare("
                SELECT id_permiso 
                FROM roles_permisos 
                WHERE id_rol = ?
            ");
            $stmt_permisos_rol->execute([$id]);
            $permisos_rol = $stmt_permisos_rol->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // Obtener roles activos
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($search) {
        $stmt = $conn->prepare("
            SELECT * FROM roles 
            WHERE activo = 1 
            AND (nombre_rol LIKE ? OR descripcion LIKE ?)
            ORDER BY nombre_rol
        ");
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol");
        $stmt->execute();
    }
    $roles = $stmt->fetchAll();
    
    // Obtener todos los permisos agrupados por módulo
    $stmt = $conn->prepare("
        SELECT * FROM permisos 
        WHERE activo = 1 
        ORDER BY modulo, nombre_permiso
    ");
    $stmt->execute();
    $todos_permisos = $stmt->fetchAll();
    
    // Agrupar permisos por módulo
    $permisos = [];
    foreach ($todos_permisos as $permiso) {
        $modulo = $permiso['modulo'];
        if (!isset($permisos[$modulo])) {
            $permisos[$modulo] = [];
        }
        $permisos[$modulo][] = $permiso;
    }
    
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

$page_title = "Gestión de Roles";
$page_subtitle = "Administrar roles y permisos del sistema";

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

<?php if ($action == 'nuevo' || $action == 'editar'): ?>
    <!-- Formulario de Crear/Editar Rol -->
    <div class="content-card">
        <h2 class="card-title">
            <?php echo $action == 'nuevo' ? 'Crear Nuevo Rol' : 'Editar Rol'; ?>
        </h2>
        
        <form method="POST" class="needs-validation" novalidate>
            <?php if ($action == 'editar'): ?>
                <input type="hidden" name="id_rol" value="<?php echo $rol_actual['id_rol']; ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-person-badge"></i> Nombre del Rol *
                    </label>
                    <input type="text" name="nombre_rol" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($rol_actual['nombre_rol']) : ''; ?>" 
                           placeholder="Ej: Coordinador de Servicios" required>
                    <div class="invalid-feedback">Por favor ingrese el nombre del rol</div>
                </div>
                
                <div class="col-12">
                    <label class="form-label">
                        <i class="bi bi-file-text"></i> Descripción
                    </label>
                    <textarea name="descripcion" class="form-control" rows="2" 
                              placeholder="Descripción breve del rol..."><?php echo $action == 'editar' ? htmlspecialchars($rol_actual['descripcion']) : ''; ?></textarea>
                </div>
                
                <div class="col-12">
                    <h5 class="mt-3 mb-3">
                        <i class="bi bi-shield-check"></i> Permisos del Rol
                    </h5>
                    <small class="text-muted d-block mb-3">
                        Seleccione los permisos que tendrá este rol
                    </small>
                    
                    <div class="row g-3">
                        <?php foreach ($permisos as $modulo => $permisos_modulo): ?>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <strong>
                                            <i class="bi bi-folder"></i> 
                                            <?php echo ucfirst(htmlspecialchars($modulo)); ?>
                                        </strong>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($permisos_modulo as $permiso): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permisos[]" 
                                                       value="<?php echo $permiso['id_permiso']; ?>"
                                                       id="permiso<?php echo $permiso['id_permiso']; ?>"
                                                       <?php echo in_array($permiso['id_permiso'], $permisos_rol) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="permiso<?php echo $permiso['id_permiso']; ?>">
                                                    <strong><?php echo htmlspecialchars($permiso['nombre_permiso']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                                    </small>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" name="<?php echo $action == 'nuevo' ? 'crear_rol' : 'actualizar_rol'; ?>" 
                        class="btn btn-admin">
                    <i class="bi bi-check-circle"></i> 
                    <?php echo $action == 'nuevo' ? 'Crear Rol' : 'Guardar Cambios'; ?>
                </button>
                <a href="gestion_roles.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Lista de Roles -->
    <div class="content-card">
        <h2 class="card-title">Gestión de Roles</h2>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <form class="d-flex" method="get">
                    <input type="text" class="form-control" placeholder="Buscar rol..." 
                           name="search" value="<?php echo htmlspecialchars($search); ?>">
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
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rol</th>
                            <th>Descripción</th>
                            <th>Permisos</th>
                            <th>Usuarios Asignados</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $rol): 
                            // Contar permisos del rol
                            $stmt_count = $conn->prepare("
                                SELECT COUNT(*) as total 
                                FROM roles_permisos 
                                WHERE id_rol = ?
                            ");
                            $stmt_count->execute([$rol['id_rol']]);
                            $count_permisos = $stmt_count->fetch()['total'];
                            
                            // Contar usuarios con este rol
                            $stmt_usuarios = $conn->prepare("
                                SELECT COUNT(*) as total 
                                FROM usuarios 
                                WHERE id_rol = ? AND activo = 1
                            ");
                            $stmt_usuarios->execute([$rol['id_rol']]);
                            $count_usuarios = $stmt_usuarios->fetch()['total'];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($rol['nombre_rol']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin descripción'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <i class="bi bi-shield-check"></i> 
                                        <?php echo $count_permisos; ?> permiso(s)
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="bi bi-people"></i> 
                                        <?php echo $count_usuarios; ?> usuario(s)
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalVer<?php echo $rol['id_rol']; ?>"
                                                title="Ver permisos">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="?action=editar&id=<?php echo $rol['id_rol']; ?>" 
                                           class="btn btn-outline-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEliminar<?php echo $rol['id_rol']; ?>"
                                                title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Modales para cada rol -->
            <?php foreach ($roles as $rol): 
                // Obtener permisos del rol para el modal
                $stmt_permisos_modal = $conn->prepare("
                    SELECT p.* 
                    FROM permisos p
                    INNER JOIN roles_permisos rp ON p.id_permiso = rp.id_permiso
                    WHERE rp.id_rol = ?
                    ORDER BY p.modulo, p.nombre_permiso
                ");
                $stmt_permisos_modal->execute([$rol['id_rol']]);
                $permisos_del_rol = $stmt_permisos_modal->fetchAll();
                
                // Agrupar por módulo
                $permisos_agrupados = [];
                foreach ($permisos_del_rol as $p) {
                    $permisos_agrupados[$p['modulo']][] = $p;
                }
            ?>
                
                <!-- Modal Ver Permisos -->
                <div class="modal fade" id="modalVer<?php echo $rol['id_rol']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-shield-check"></i> 
                                    Permisos de: <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (count($permisos_agrupados) > 0): ?>
                                    <div class="row g-3">
                                        <?php foreach ($permisos_agrupados as $modulo => $permisos_mod): ?>
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header bg-light">
                                                        <strong>
                                                            <i class="bi bi-folder"></i> 
                                                            <?php echo ucfirst(htmlspecialchars($modulo)); ?>
                                                        </strong>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-unstyled mb-0">
                                                            <?php foreach ($permisos_mod as $p): ?>
                                                                <li class="mb-2">
                                                                    <i class="bi bi-check-circle text-success"></i>
                                                                    <strong><?php echo htmlspecialchars($p['nombre_permiso']); ?></strong>
                                                                    <br>
                                                                    <small class="text-muted ms-4">
                                                                        <?php echo htmlspecialchars($p['descripcion']); ?>
                                                                    </small>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Este rol no tiene permisos asignados
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Eliminar -->
                <div class="modal fade" id="modalEliminar<?php echo $rol['id_rol']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-trash"></i> Eliminar Rol
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>¿Está seguro de eliminar este rol?</strong>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-1"><strong>Rol:</strong> <?php echo htmlspecialchars($rol['nombre_rol']); ?></p>
                                        <p class="mb-1"><strong>Descripción:</strong> <?php echo htmlspecialchars($rol['descripcion'] ?? 'N/A'); ?></p>
                                        <p class="mb-0">
                                            <strong>Usuarios asignados:</strong> 
                                            <?php 
                                            $stmt_u = $conn->prepare("SELECT COUNT(*) as t FROM usuarios WHERE id_rol = ? AND activo = 1");
                                            $stmt_u->execute([$rol['id_rol']]);
                                            echo $stmt_u->fetch()['t'];
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <p class="text-muted small mt-3 mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Nota:</strong> Solo se pueden eliminar roles sin usuarios asignados.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <a href="?action=eliminar&id=<?php echo $rol['id_rol']; ?>" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Sí, eliminar rol
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endforeach; ?>
            
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
<?php endif; ?>

<script>
// Validación de formularios
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>