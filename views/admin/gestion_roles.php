<?php
/**
 * Gestión de Roles - Administrador
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
$message = '';
$error = '';
$action = $_GET['action'] ?? '';
$id_rol = $_GET['id'] ?? 0;
$roles = [];
$permisos = [];
$rol_editar = null;

try {
    $conn = getDBConnection();
    
    // Obtener permisos para asignación
    $stmt_permisos = $conn->prepare("
        SELECT * FROM permisos 
        WHERE activo = 1 
        ORDER BY modulo, nombre_permiso
    ");
    $stmt_permisos->execute();
    $permisos = $stmt_permisos->fetchAll();
    
    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['crear_rol'])) {
            $nombre = $_POST['nombre_rol'];
            $descripcion = $_POST['descripcion'];
            $permisos_seleccionados = $_POST['permisos'] ?? [];
            
            // Verificar si el rol ya existe
            $stmt = $conn->prepare("SELECT COUNT(*) as existe FROM roles WHERE nombre_rol = ?");
            $stmt->execute([$nombre]);
            if ($stmt->fetch()['existe'] > 0) {
                $error = "Ya existe un rol con este nombre";
            } else {
                // Crear rol
                $stmt = $conn->prepare("
                    INSERT INTO roles (nombre_rol, descripcion, activo) 
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$nombre, $descripcion]);
                $id_rol_nuevo = $conn->lastInsertId();
                
                // Asignar permisos
                foreach ($permisos_seleccionados as $id_permiso) {
                    $stmt = $conn->prepare("
                        INSERT INTO roles_permisos (id_rol, id_permiso) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$id_rol_nuevo, $id_permiso]);
                }
                
                $message = "Rol creado exitosamente";
            }
        }
        
        if (isset($_POST['actualizar_rol'])) {
            $id_rol = $_POST['id_rol'];
            $nombre = $_POST['nombre_rol'];
            $descripcion = $_POST['descripcion'];
            $activo = isset($_POST['activo']) ? 1 : 0;
            $permisos_seleccionados = $_POST['permisos'] ?? [];
            
            // Verificar si el rol ya existe (excluyendo el actual)
            $stmt = $conn->prepare("SELECT COUNT(*) as existe FROM roles WHERE nombre_rol = ? AND id_rol != ?");
            $stmt->execute([$nombre, $id_rol]);
            if ($stmt->fetch()['existe'] > 0) {
                $error = "Ya existe otro rol con este nombre";
            } else {
                // Actualizar rol
                $stmt = $conn->prepare("
                    UPDATE roles 
                    SET nombre_rol = ?, descripcion = ?, activo = ?
                    WHERE id_rol = ?
                ");
                $stmt->execute([$nombre, $descripcion, $activo, $id_rol]);
                
                // Eliminar permisos actuales
                $stmt = $conn->prepare("DELETE FROM roles_permisos WHERE id_rol = ?");
                $stmt->execute([$id_rol]);
                
                // Asignar nuevos permisos
                foreach ($permisos_seleccionados as $id_permiso) {
                    $stmt = $conn->prepare("
                        INSERT INTO roles_permisos (id_rol, id_permiso) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$id_rol, $id_permiso]);
                }
                
                $message = "Rol actualizado exitosamente";
            }
        }
        
        if (isset($_POST['eliminar_rol'])) {
            $id_rol = $_POST['id_rol'];
            
            // Verificar si hay usuarios con este rol
            $stmt = $conn->prepare("SELECT COUNT(*) as usuarios FROM usuarios WHERE id_rol = ?");
            $stmt->execute([$id_rol]);
            $usuarios = $stmt->fetch()['usuarios'];
            
            if ($usuarios > 0) {
                $error = "No se puede eliminar. Hay $usuarios usuarios asignados a este rol.";
            } else {
                // Eliminar asignaciones de permisos primero
                $stmt = $conn->prepare("DELETE FROM roles_permisos WHERE id_rol = ?");
                $stmt->execute([$id_rol]);
                
                // Eliminar rol
                $stmt = $conn->prepare("DELETE FROM roles WHERE id_rol = ?");
                $stmt->execute([$id_rol]);
                
                $message = "Rol eliminado exitosamente";
            }
        }
    }
    
    // Cargar rol para editar
    if ($action == 'editar' && $id_rol > 0) {
        $stmt = $conn->prepare("SELECT * FROM roles WHERE id_rol = ?");
        $stmt->execute([$id_rol]);
        $rol_editar = $stmt->fetch();
        
        // Obtener permisos asignados
        if ($rol_editar) {
            $stmt = $conn->prepare("
                SELECT p.* 
                FROM permisos p
                INNER JOIN roles_permisos rp ON p.id_permiso = rp.id_permiso
                WHERE rp.id_rol = ?
            ");
            $stmt->execute([$id_rol]);
            $permisos_asignados = $stmt->fetchAll();
            $rol_editar['permisos_asignados'] = array_column($permisos_asignados, 'id_permiso');
        }
    }
    
    // Obtener lista de roles con estadísticas
    $query = "
        SELECT r.*, 
               COUNT(DISTINCT u.id_usuario) as total_usuarios,
               COUNT(DISTINCT rp.id_permiso) as total_permisos
        FROM roles r
        LEFT JOIN usuarios u ON r.id_rol = u.id_rol AND u.activo = 1
        LEFT JOIN roles_permisos rp ON r.id_rol = rp.id_rol
        GROUP BY r.id_rol
        ORDER BY r.nombre_rol
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    // Estadísticas
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total_roles,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as roles_activos,
            (SELECT COUNT(*) FROM permisos WHERE activo = 1) as total_permisos
        FROM roles
    ");
    $stmt_stats->execute();
    $estadisticas = $stmt_stats->fetch();
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$page_title = "Gestión de Roles";
$page_subtitle = "Administración de roles y asignación de permisos";

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stats-card red">
            <div class="stats-value"><?php echo $estadisticas['total_roles'] ?? 0; ?></div>
            <div class="stats-label">Roles Definidos</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card green">
            <div class="stats-value"><?php echo $estadisticas['roles_activos'] ?? 0; ?></div>
            <div class="stats-label">Roles Activos</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card blue">
            <div class="stats-value"><?php echo $estadisticas['total_permisos'] ?? 0; ?></div>
            <div class="stats-label">Permisos Disponibles</div>
        </div>
    </div>
</div>

<!-- Lista de Roles -->
<div class="content-card mb-4">
    <h2 class="card-title d-flex justify-content-between align-items-center">
        <span>Roles del Sistema</span>
        <a href="?action=nuevo" class="btn btn-admin btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo Rol
        </a>
    </h2>
    
    <?php if (count($roles) > 0): ?>
        <div class="row g-3">
            <?php foreach ($roles as $rol): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($rol['nombre_rol']); ?></h5>
                                    <?php if ($rol['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="?action=editar&id=<?php echo $rol['id_rol']; ?>">
                                                <i class="bi bi-pencil"></i> Editar
                                            </a>
                                        </li>
                                        <?php if ($rol['total_usuarios'] == 0): ?>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#modalEliminarRol<?php echo $rol['id_rol']; ?>">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($rol['descripcion']): ?>
                                <p class="card-text small mb-3"><?php echo htmlspecialchars($rol['descripcion']); ?></p>
                            <?php endif; ?>
                            
                            <div class="row small text-muted g-2">
                                <div class="col-12">
                                    <i class="bi bi-people"></i> 
                                    <?php echo $rol['total_usuarios']; ?> usuario(s) asignado(s)
                                </div>
                                <div class="col-12">
                                    <i class="bi bi-shield-check"></i> 
                                    <?php echo $rol['total_permisos']; ?> permiso(s) asignado(s)
                                </div>
                                <div class="col-12">
                                    <i class="bi bi-calendar"></i> 
                                    Creado: <?php echo date('d/m/Y', strtotime($rol['fecha_creacion'])); ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="gestion_permisos.php?rol=<?php echo $rol['id_rol']; ?>" 
                                   class="btn btn-outline-admin btn-sm w-100">
                                    <i class="bi bi-shield-check"></i> Gestionar Permisos
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Eliminar -->
                    <div class="modal fade" id="modalEliminarRol<?php echo $rol['id_rol']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Eliminar Rol</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="id_rol" value="<?php echo $rol['id_rol']; ?>">
                                        
                                        <div class="alert alert-danger">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>¿Está seguro de eliminar este rol?</strong>
                                            <br><br>
                                            <strong><?php echo htmlspecialchars($rol['nombre_rol']); ?></strong>
                                            <br><br>
                                            Esta acción no se puede deshacer. Se perderán todas las asignaciones de permisos.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="eliminar_rol" class="btn btn-danger">Eliminar Rol</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-person-badge"></i>
            <h4>No hay roles definidos</h4>
            <p>No se han definido roles en el sistema.</p>
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-plus-circle"></i> Crear primer rol
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Formulario de Creación/Edición -->
<?php if ($action == 'nuevo' || $action == 'editar'): ?>
<div class="content-card">
    <h2 class="card-title">
        <?php echo $action == 'nuevo' ? 'Crear Nuevo Rol' : 'Editar Rol'; ?>
    </h2>
    
    <form method="POST" action="">
        <?php if ($action == 'editar' && $rol_editar): ?>
            <input type="hidden" name="id_rol" value="<?php echo $rol_editar['id_rol']; ?>">
        <?php endif; ?>
        
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre del Rol *</label>
                <input type="text" name="nombre_rol" class="form-control" required
                       value="<?php echo $action == 'editar' ? htmlspecialchars($rol_editar['nombre_rol']) : ''; ?>">
                <small class="text-muted">Ej: "Coordinador de Bienestar", "Practicante"</small>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Estado</label>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="activo" id="activo" 
                           value="1" <?php echo ($action == 'editar' && $rol_editar['activo']) ? 'checked' : 'checked'; ?>>
                    <label class="form-check-label" for="activo">Rol activo</label>
                </div>
            </div>
            
            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?php 
                    echo $action == 'editar' ? htmlspecialchars($rol_editar['descripcion']) : ''; 
                ?></textarea>
                <small class="text-muted">Descripción detallada de las funciones y responsabilidades del rol</small>
            </div>
            
            <div class="col-12">
                <label class="form-label">Permisos Asignados</label>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <div class="row">
                        <?php 
                        // Agrupar permisos por módulo
                        $permisos_por_modulo = [];
                        foreach ($permisos as $permiso) {
                            $modulo = $permiso['modulo'];
                            if (!isset($permisos_por_modulo[$modulo])) {
                                $permisos_por_modulo[$modulo] = [];
                            }
                            $permisos_por_modulo[$modulo][] = $permiso;
                        }
                        ?>
                        
                        <?php foreach ($permisos_por_modulo as $modulo => $permisos_modulo): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <strong><?php echo ucfirst($modulo); ?></strong>
                                    </div>
                                    <div class="card-body p-2">
                                        <?php foreach ($permisos_modulo as $permiso): ?>
                                            <div class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permisos[]" 
                                                       value="<?php echo $permiso['id_permiso']; ?>"
                                                       id="permiso_<?php echo $permiso['id_permiso']; ?>"
                                                       <?php 
                                                       if ($action == 'editar' && isset($rol_editar['permisos_asignados'])) {
                                                           echo in_array($permiso['id_permiso'], $rol_editar['permisos_asignados']) ? 'checked' : '';
                                                       }
                                                       ?>>
                                                <label class="form-check-label" for="permiso_<?php echo $permiso['id_permiso']; ?>">
                                                    <?php echo htmlspecialchars($permiso['nombre_permiso']); ?>
                                                    <?php if ($permiso['descripcion']): ?>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($permiso['descripcion']); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <small class="text-muted">Seleccione los permisos que tendrá este rol</small>
            </div>
            
            <div class="col-12">
                <hr>
                <div class="d-flex justify-content-between">
                    <a href="gestion_roles.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" name="<?php echo $action == 'nuevo' ? 'crear_rol' : 'actualizar_rol'; ?>" 
                            class="btn btn-admin">
                        <i class="bi bi-save"></i> 
                        <?php echo $action == 'nuevo' ? 'Crear Rol' : 'Guardar Cambios'; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
// Auto-cerrar alertas
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Seleccionar/deseleccionar todos los permisos de un módulo
function toggleAll(modulo, checked) {
    const checkboxes = document.querySelectorAll(`input[name="permisos[]"][data-modulo="${modulo}"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}

// Asignar eventos a los checkboxes "Seleccionar todos"
document.addEventListener('DOMContentLoaded', function() {
    const selectAllButtons = document.querySelectorAll('.select-all-modulo');
    selectAllButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modulo = this.dataset.modulo;
            const checked = this.dataset.checked === 'false';
            toggleAll(modulo, checked);
            this.dataset.checked = checked;
            this.innerHTML = checked ? '<i class="bi bi-check-square"></i> Deseleccionar Todos' : 
                                       '<i class="bi bi-square"></i> Seleccionar Todos';
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>