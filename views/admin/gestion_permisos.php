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
$permiso_actual = null;

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        $conn = getDBConnection();
        
        // CREAR PERMISO
        if (isset($_POST['crear_permiso'])) {
            $nombre = trim($_POST['nombre_permiso']);
            $descripcion = trim($_POST['descripcion']);
            $modulo = trim($_POST['modulo']);
            
            // Validar nombre único
            $stmt_check = $conn->prepare("
                SELECT COUNT(*) as existe 
                FROM permisos 
                WHERE nombre_permiso = ?
            ");
            $stmt_check->execute([$nombre]);
            if ($stmt_check->fetch()['existe'] > 0) {
                $error = "Ya existe un permiso con ese nombre";
            } else {
                // Insertar permiso
                $stmt_insert = $conn->prepare("
                    INSERT INTO permisos (nombre_permiso, descripcion, modulo, activo) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt_insert->execute([$nombre, $descripcion, $modulo]);
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Permiso creado exitosamente'];
                header("Location: gestion_permisos.php");
                exit();
            }
        }
        
        // ACTUALIZAR PERMISO
        if (isset($_POST['actualizar_permiso'])) {
            $id_permiso = intval($_POST['id_permiso']);
            $nombre = trim($_POST['nombre_permiso']);
            $descripcion = trim($_POST['descripcion']);
            $modulo = trim($_POST['modulo']);
            
            // Validar nombre único (excepto el mismo permiso)
            $stmt_check = $conn->prepare("
                SELECT COUNT(*) as existe 
                FROM permisos 
                WHERE nombre_permiso = ? AND id_permiso != ?
            ");
            $stmt_check->execute([$nombre, $id_permiso]);
            if ($stmt_check->fetch()['existe'] > 0) {
                $error = "Ya existe otro permiso con ese nombre";
            } else {
                // Actualizar permiso
                $stmt_update = $conn->prepare("
                    UPDATE permisos 
                    SET nombre_permiso = ?, descripcion = ?, modulo = ? 
                    WHERE id_permiso = ?
                ");
                $stmt_update->execute([$nombre, $descripcion, $modulo, $id_permiso]);
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Permiso actualizado exitosamente'];
                header("Location: gestion_permisos.php");
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
        
        // Verificar si el permiso está asignado a roles
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM roles_permisos 
            WHERE id_permiso = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = "No se puede eliminar el permiso porque está asignado a {$result['count']} rol(es).";
        } else {
            // Desactivar permiso
            $stmt = $conn->prepare("UPDATE permisos SET activo = 0 WHERE id_permiso = ?");
            $stmt->execute([$id]);
            
            $mensaje = ['tipo' => 'success', 'texto' => 'Permiso eliminado correctamente'];
            header("Location: gestion_permisos.php");
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al eliminar el permiso: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Si es editar, obtener datos del permiso
    if ($action == 'editar' && $id > 0) {
        $stmt = $conn->prepare("SELECT * FROM permisos WHERE id_permiso = ? AND activo = 1");
        $stmt->execute([$id]);
        $permiso_actual = $stmt->fetch();
        
        if (!$permiso_actual) {
            $error = "Permiso no encontrado";
            $action = '';
        }
    }
    
    // Obtener permisos con búsqueda
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    $sql = "
        SELECT p.*, 
               COUNT(rp.id_rol) as asignado_a_roles
        FROM permisos p
        LEFT JOIN roles_permisos rp ON p.id_permiso = rp.id_permiso
        WHERE p.activo = 1
    ";
    
    if ($search) {
        $sql .= " AND (p.nombre_permiso LIKE ? OR p.descripcion LIKE ? OR p.modulo LIKE ?)";
    }
    
    $sql .= " GROUP BY p.id_permiso ORDER BY p.modulo, p.nombre_permiso";
    
    $stmt = $conn->prepare($sql);
    
    if ($search) {
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt->execute();
    }
    
    $permisos = $stmt->fetchAll();
    
    // Obtener módulos únicos
    $stmt_modulos = $conn->prepare("
        SELECT DISTINCT modulo 
        FROM permisos 
        WHERE activo = 1 
        ORDER BY modulo
    ");
    $stmt_modulos->execute();
    $modulos = $stmt_modulos->fetchAll(PDO::FETCH_COLUMN, 0);
    
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
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

<?php if ($action == 'nuevo' || $action == 'editar'): ?>
    <!-- Formulario de Crear/Editar Permiso -->
    <div class="content-card">
        <h2 class="card-title">
            <?php echo $action == 'nuevo' ? 'Crear Nuevo Permiso' : 'Editar Permiso'; ?>
        </h2>
        
        <form method="POST" class="needs-validation" novalidate>
            <?php if ($action == 'editar'): ?>
                <input type="hidden" name="id_permiso" value="<?php echo $permiso_actual['id_permiso']; ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-shield-check"></i> Nombre del Permiso *
                    </label>
                    <input type="text" name="nombre_permiso" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($permiso_actual['nombre_permiso']) : ''; ?>" 
                           placeholder="Ej: gestionar_servicios" required>
                    <small class="text-muted">Use formato: accion_modulo (sin espacios, minúsculas)</small>
                    <div class="invalid-feedback">Por favor ingrese el nombre del permiso</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-folder"></i> Módulo *
                    </label>
                    <?php if ($action == 'nuevo'): ?>
                        <select name="modulo" class="form-select" id="moduloSelect" required>
                            <option value="">Seleccione o escriba nuevo...</option>
                            <?php foreach ($modulos as $mod): ?>
                                <option value="<?php echo htmlspecialchars($mod); ?>">
                                    <?php echo ucfirst(htmlspecialchars($mod)); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="nuevo">➕ Crear nuevo módulo...</option>
                        </select>
                        <input type="text" name="modulo_nuevo" id="moduloNuevo" class="form-control mt-2" 
                               placeholder="Nombre del nuevo módulo" style="display: none;">
                    <?php else: ?>
                        <input type="text" name="modulo" class="form-control" 
                               value="<?php echo htmlspecialchars($permiso_actual['modulo']); ?>" required>
                    <?php endif; ?>
                    <div class="invalid-feedback">Por favor seleccione o ingrese un módulo</div>
                </div>
                
                <div class="col-12">
                    <label class="form-label">
                        <i class="bi bi-file-text"></i> Descripción *
                    </label>
                    <textarea name="descripcion" class="form-control" rows="3" 
                              placeholder="Descripción detallada del permiso..." required><?php echo $action == 'editar' ? htmlspecialchars($permiso_actual['descripcion']) : ''; ?></textarea>
                    <div class="invalid-feedback">Por favor ingrese una descripción</div>
                </div>
                
                <div class="col-12">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Ejemplos de Permisos:</h6>
                        <ul class="mb-0">
                            <li><strong>crear_servicios</strong> - Módulo: servicios</li>
                            <li><strong>gestionar_solicitudes</strong> - Módulo: solicitudes</li>
                            <li><strong>ver_estadisticas</strong> - Módulo: estadisticas</li>
                            <li><strong>generar_reportes</strong> - Módulo: reportes</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" name="<?php echo $action == 'nuevo' ? 'crear_permiso' : 'actualizar_permiso'; ?>" 
                        class="btn btn-admin">
                    <i class="bi bi-check-circle"></i> 
                    <?php echo $action == 'nuevo' ? 'Crear Permiso' : 'Guardar Cambios'; ?>
                </button>
                <a href="gestion_permisos.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Lista de Permisos -->
    <div class="content-card">
        <h2 class="card-title">Gestión de Permisos</h2>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <form class="d-flex" method="get">
                    <input type="text" class="form-control" placeholder="Buscar permiso..." 
                           name="search" value="<?php echo htmlspecialchars($search); ?>">
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
                <?php 
                $permisos_modulo = array_filter($permisos, function($p) use ($modulo) {
                    return $p['modulo'] == $modulo;
                });
                ?>
                <?php if (count($permisos_modulo) > 0): ?>
                    <div class="mb-4">
                        <h5 class="text-admin mb-3">
                            <i class="bi bi-folder"></i> 
                            <?php echo ucfirst(htmlspecialchars($modulo)); ?>
                            <span class="badge bg-secondary">
                                <?php echo count($permisos_modulo); ?> permiso(s)
                            </span>
                        </h5>
                        <div class="row g-3">
                            <?php foreach ($permisos_modulo as $permiso): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="bi bi-shield-check text-primary"></i>
                                                    <?php echo htmlspecialchars($permiso['nombre_permiso']); ?>
                                                </h6>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalVer<?php echo $permiso['id_permiso']; ?>"
                                                            title="Ver detalles">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="?action=editar&id=<?php echo $permiso['id_permiso']; ?>" 
                                                       class="btn btn-outline-warning" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalEliminar<?php echo $permiso['id_permiso']; ?>"
                                                            title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <p class="card-text text-muted small mb-2">
                                                <?php echo htmlspecialchars($permiso['descripcion'] ?? 'Sin descripción'); ?>
                                            </p>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info">
                                                    <i class="bi bi-diagram-3"></i>
                                                    Asignado a <?php echo $permiso['asignado_a_roles']; ?> rol(es)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Modales para cada permiso -->
            <?php foreach ($permisos as $permiso): 
                // Obtener roles que tienen este permiso
                $stmt_roles = $conn->prepare("
                    SELECT r.nombre_rol 
                    FROM roles r
                    INNER JOIN roles_permisos rp ON r.id_rol = rp.id_rol
                    WHERE rp.id_permiso = ? AND r.activo = 1
                    ORDER BY r.nombre_rol
                ");
                $stmt_roles->execute([$permiso['id_permiso']]);
                $roles_con_permiso = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
            ?>
                
                <!-- Modal Ver Detalles -->
                <div class="modal fade" id="modalVer<?php echo $permiso['id_permiso']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-shield-check"></i> 
                                    Detalles del Permiso
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Nombre del Permiso</label>
                                    <p class="fw-bold mb-0">
                                        <?php echo htmlspecialchars($permiso['nombre_permiso']); ?>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Módulo</label>
                                    <p class="mb-0">
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-folder"></i> 
                                            <?php echo ucfirst(htmlspecialchars($permiso['modulo'])); ?>
                                        </span>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Descripción</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($permiso['descripcion']); ?></p>
                                </div>
                                
                                <div>
                                    <label class="form-label text-muted small">Asignado a Roles</label>
                                    <?php if (count($roles_con_permiso) > 0): ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($roles_con_permiso as $rol): ?>
                                                <li class="mb-1">
                                                    <i class="bi bi-check-circle text-success"></i>
                                                    <?php echo htmlspecialchars($rol); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">
                                            <i class="bi bi-info-circle"></i>
                                            No está asignado a ningún rol
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <a href="?action=editar&id=<?php echo $permiso['id_permiso']; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Eliminar -->
                <div class="modal fade" id="modalEliminar<?php echo $permiso['id_permiso']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-trash"></i> Eliminar Permiso
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>¿Está seguro de eliminar este permiso?</strong>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-1">
                                            <strong>Permiso:</strong> 
                                            <?php echo htmlspecialchars($permiso['nombre_permiso']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Módulo:</strong> 
                                            <?php echo ucfirst(htmlspecialchars($permiso['modulo'])); ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Asignado a:</strong> 
                                            <?php echo count($roles_con_permiso); ?> rol(es)
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if (count($roles_con_permiso) > 0): ?>
                                    <div class="alert alert-danger mt-3">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <strong>Este permiso está asignado a:</strong>
                                        <ul class="mb-0 mt-2">
                                            <?php foreach ($roles_con_permiso as $rol): ?>
                                                <li><?php echo htmlspecialchars($rol); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-muted small mt-3 mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Nota:</strong> Solo se pueden eliminar permisos que no están asignados a ningún rol.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <?php if (count($roles_con_permiso) == 0): ?>
                                    <a href="?action=eliminar&id=<?php echo $permiso['id_permiso']; ?>" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Sí, eliminar permiso
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endforeach; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-shield-check"></i>
                <p>No hay permisos registrados</p>
                <?php if ($search): ?>
                    <a href="gestion_permisos.php" class="btn btn-secondary mt-3">
                        <i class="bi bi-arrow-left"></i> Ver todos los permisos
                    </a>
                <?php else: ?>
                    <a href="?action=nuevo" class="btn btn-admin mt-3">
                        <i class="bi bi-plus-circle"></i> Crear primer permiso
                    </a>
                <?php endif; ?>
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

// Manejo de módulo nuevo
<?php if ($action == 'nuevo'): ?>
document.getElementById('moduloSelect')?.addEventListener('change', function() {
    const moduloNuevo = document.getElementById('moduloNuevo');
    if (this.value === 'nuevo') {
        moduloNuevo.style.display = 'block';
        moduloNuevo.required = true;
        this.required = false;
    } else {
        moduloNuevo.style.display = 'none';
        moduloNuevo.required = false;
        this.required = true;
    }
});

// Validación especial para módulo
document.querySelector('form')?.addEventListener('submit', function(e) {
    const moduloSelect = document.getElementById('moduloSelect');
    const moduloNuevo = document.getElementById('moduloNuevo');
    
    if (moduloSelect.value === 'nuevo' && !moduloNuevo.value.trim()) {
        e.preventDefault();
        alert('Por favor ingrese el nombre del nuevo módulo');
        moduloNuevo.focus();
        return false;
    }
    
    if (moduloSelect.value === 'nuevo') {
        // Cambiar el name para enviar el valor correcto
        moduloNuevo.name = 'modulo';
        moduloSelect.name = '';
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>