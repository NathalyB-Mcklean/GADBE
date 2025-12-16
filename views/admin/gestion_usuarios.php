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
$usuario_actual = null;

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        $conn = getDBConnection();
        
        // CREAR USUARIO
        if (isset($_POST['crear_usuario'])) {
            $correo = trim($_POST['correo_institucional']);
            $nombre = trim($_POST['nombre_completo']);
            $id_rol = intval($_POST['id_rol']);
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            $telefono = trim($_POST['telefono']);
            $facultad = trim($_POST['facultad']);
            $carrera = trim($_POST['carrera']);
            $año_ingreso = $_POST['año_ingreso'];
            $departamento = trim($_POST['departamento']);
            
            // Validaciones
            if ($password !== $password_confirm) {
                $error = "Las contraseñas no coinciden";
            } else {
                // Verificar correo único
                $stmt_check = $conn->prepare("
                    SELECT COUNT(*) as existe 
                    FROM usuarios 
                    WHERE correo_institucional = ?
                ");
                $stmt_check->execute([$correo]);
                if ($stmt_check->fetch()['existe'] > 0) {
                    $error = "Ya existe un usuario con ese correo";
                } else {
                    // Hash de contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar usuario
                    $stmt_insert = $conn->prepare("
                        INSERT INTO usuarios 
                        (correo_institucional, nombre_completo, password, id_rol, 
                         telefono, facultad, carrera, año_ingreso, departamento, activo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt_insert->execute([
                        $correo, $nombre, $password_hash, $id_rol,
                        $telefono ?: null, 
                        $facultad ?: null, 
                        $carrera ?: null,
                        $año_ingreso ?: null,
                        $departamento ?: null
                    ]);
                    
                    $mensaje = ['tipo' => 'success', 'texto' => 'Usuario creado exitosamente'];
                    header("Location: gestion_usuarios.php");
                    exit();
                }
            }
        }
        
        // ACTUALIZAR USUARIO
        if (isset($_POST['actualizar_usuario'])) {
            $id_usuario = intval($_POST['id_usuario']);
            $correo = trim($_POST['correo_institucional']);
            $nombre = trim($_POST['nombre_completo']);
            $id_rol = intval($_POST['id_rol']);
            $telefono = trim($_POST['telefono']);
            $facultad = trim($_POST['facultad']);
            $carrera = trim($_POST['carrera']);
            $año_ingreso = $_POST['año_ingreso'];
            $departamento = trim($_POST['departamento']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            // PROTECCIÓN: No permitir que el admin cambie su propio rol
            if ($id_usuario == $_SESSION['user_id']) {
                // Obtener rol actual
                $stmt_rol_actual = $conn->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
                $stmt_rol_actual->execute([$id_usuario]);
                $rol_actual = $stmt_rol_actual->fetch();
                
                if ($rol_actual && $rol_actual['id_rol'] != $id_rol) {
                    $error = "No puede cambiar su propio rol. Pídale a otro administrador que lo haga.";
                }
            }
            
            if (!$error) {
                // Validar correo único (excepto el mismo usuario)
                $stmt_check = $conn->prepare("
                    SELECT COUNT(*) as existe 
                    FROM usuarios 
                    WHERE correo_institucional = ? AND id_usuario != ?
                ");
                $stmt_check->execute([$correo, $id_usuario]);
                if ($stmt_check->fetch()['existe'] > 0) {
                    $error = "Ya existe otro usuario con ese correo";
                }
            }
            
            if (!$error) {
                // Actualizar usuario
                $stmt_update = $conn->prepare("
                    UPDATE usuarios 
                    SET correo_institucional = ?, nombre_completo = ?, id_rol = ?,
                        telefono = ?, facultad = ?, carrera = ?, año_ingreso = ?,
                        departamento = ?, activo = ?
                    WHERE id_usuario = ?
                ");
                $stmt_update->execute([
                    $correo, $nombre, $id_rol,
                    $telefono ?: null,
                    $facultad ?: null,
                    $carrera ?: null,
                    $año_ingreso ?: null,
                    $departamento ?: null,
                    $activo,
                    $id_usuario
                ]);
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Usuario actualizado exitosamente'];
                header("Location: gestion_usuarios.php");
                exit();
            }
        }
        
        // CAMBIAR CONTRASEÑA
        if (isset($_POST['cambiar_password'])) {
            $id_usuario = intval($_POST['id_usuario']);
            $nueva_password = $_POST['nueva_password'];
            $confirmar_password = $_POST['confirmar_password'];
            
            if ($nueva_password !== $confirmar_password) {
                $error = "Las contraseñas no coinciden";
            } else {
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                
                $stmt_update = $conn->prepare("
                    UPDATE usuarios 
                    SET password = ?, intentos_fallidos = 0, cuenta_bloqueada_hasta = NULL
                    WHERE id_usuario = ?
                ");
                $stmt_update->execute([$password_hash, $id_usuario]);
                
                $mensaje = ['tipo' => 'success', 'texto' => 'Contraseña actualizada exitosamente'];
                header("Location: gestion_usuarios.php");
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

if ($action == 'desactivar' && $id > 0) {
    try {
        $conn = getDBConnection();
        
        // PROTECCIÓN: No permitir desactivar el propio usuario
        if ($id == $_SESSION['user_id']) {
            $error = "No puede desactivar su propia cuenta";
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ?");
            $stmt->execute([$id]);
            $mensaje = ['tipo' => 'success', 'texto' => 'Usuario desactivado correctamente'];
            header("Location: gestion_usuarios.php");
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al desactivar el usuario: " . $e->getMessage();
    }
}

if ($action == 'activar' && $id > 0) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $mensaje = ['tipo' => 'success', 'texto' => 'Usuario activado correctamente'];
        header("Location: gestion_usuarios.php");
        exit();
    } catch (Exception $e) {
        $error = "Error al activar el usuario: " . $e->getMessage();
    }
}

try {
    $conn = getDBConnection();
    
    // Si es editar, obtener datos del usuario
    if ($action == 'editar' && $id > 0) {
        $stmt = $conn->prepare("
            SELECT u.*, r.nombre_rol
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = ?
        ");
        $stmt->execute([$id]);
        $usuario_actual = $stmt->fetch();
        
        if (!$usuario_actual) {
            $error = "Usuario no encontrado";
            $action = '';
        }
    }
    
    // Obtener usuarios con búsqueda
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $rol_filter = isset($_GET['rol']) ? intval($_GET['rol']) : 0;
    $estado_filter = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
    
    $sql = "
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (u.nombre_completo LIKE ? OR u.correo_institucional LIKE ? OR u.telefono LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($rol_filter > 0) {
        $sql .= " AND u.id_rol = ?";
        $params[] = $rol_filter;
    }
    
    if ($estado_filter === 'activos') {
        $sql .= " AND u.activo = 1";
    } elseif ($estado_filter === 'inactivos') {
        $sql .= " AND u.activo = 0";
    }
    
    $sql .= " ORDER BY u.nombre_completo";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
    
    // Obtener roles para filtros y formularios
    $stmt_roles = $conn->prepare("SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol");
    $stmt_roles->execute();
    $roles = $stmt_roles->fetchAll();
    
    // Obtener estadísticas
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos,
            SUM(CASE WHEN ultimo_acceso IS NULL THEN 1 ELSE 0 END) as nunca_ingresaron
        FROM usuarios
    ");
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch();
    
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

$page_title = "Gestión de Usuarios";
$page_subtitle = "Administrar usuarios del sistema";

ob_start();
?>

<style>
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.stat-item {
    text-align: center;
}
.stat-number {
    font-size: 2rem;
    font-weight: bold;
}
.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
}
</style>

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
    <!-- Formulario de Crear/Editar Usuario -->
    <div class="content-card">
        <h2 class="card-title">
            <?php echo $action == 'nuevo' ? 'Crear Nuevo Usuario' : 'Editar Usuario'; ?>
        </h2>
        
        <form method="POST" class="needs-validation" novalidate>
            <?php if ($action == 'editar'): ?>
                <input type="hidden" name="id_usuario" value="<?php echo $usuario_actual['id_usuario']; ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-envelope"></i> Correo Institucional *
                    </label>
                    <input type="email" name="correo_institucional" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['correo_institucional'] ?? '') : ''; ?>" 
                           placeholder="usuario@utp.ac.pa" required>
                    <div class="invalid-feedback">Por favor ingrese un correo válido</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-person"></i> Nombre Completo *
                    </label>
                    <input type="text" name="nombre_completo" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['nombre_completo'] ?? '') : ''; ?>" 
                           placeholder="Nombre completo del usuario" required>
                    <div class="invalid-feedback">Por favor ingrese el nombre completo</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-person-badge"></i> Rol *
                    </label>
                    <select name="id_rol" class="form-select" required 
                            <?php echo ($action == 'editar' && $usuario_actual['id_usuario'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                        <option value="">Seleccione un rol...</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id_rol']; ?>"
                                    <?php echo ($action == 'editar' && $usuario_actual['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($action == 'editar' && $usuario_actual['id_usuario'] == $_SESSION['user_id']): ?>
                        <input type="hidden" name="id_rol" value="<?php echo $usuario_actual['id_rol']; ?>">
                        <small class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            No puede cambiar su propio rol
                        </small>
                    <?php endif; ?>
                    <div class="invalid-feedback">Por favor seleccione un rol</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-telephone"></i> Teléfono
                    </label>
                    <input type="text" name="telefono" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['telefono'] ?? '') : ''; ?>" 
                           placeholder="0000-0000">
                </div>
                
                <?php if ($action == 'nuevo'): ?>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-key"></i> Contraseña *
                        </label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Mínimo 6 caracteres" minlength="6" required>
                        <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-key-fill"></i> Confirmar Contraseña *
                        </label>
                        <input type="password" name="password_confirm" class="form-control" 
                               placeholder="Repita la contraseña" minlength="6" required>
                        <div class="invalid-feedback">Las contraseñas deben coincidir</div>
                    </div>
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-building"></i> Facultad
                    </label>
                    <input type="text" name="facultad" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['facultad'] ?? '') : ''; ?>" 
                           placeholder="Ej: Ingeniería de Sistemas">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-mortarboard"></i> Carrera
                    </label>
                    <input type="text" name="carrera" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['carrera'] ?? '') : ''; ?>" 
                           placeholder="Ej: Licenciatura en Ingeniería de Sistemas">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-calendar"></i> Año de Ingreso
                    </label>
                    <input type="number" name="año_ingreso" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['año_ingreso'] ?? '') : ''; ?>" 
                           placeholder="Ej: 2024" min="1990" max="2030">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="bi bi-briefcase"></i> Departamento
                    </label>
                    <input type="text" name="departamento" class="form-control" 
                           value="<?php echo $action == 'editar' ? htmlspecialchars($usuario_actual['departamento'] ?? '') : ''; ?>" 
                           placeholder="Ej: Dirección de Bienestar Estudiantil">
                </div>
                
                <?php if ($action == 'editar'): ?>
                    <div class="col-12">
                        <?php if ($usuario_actual['id_usuario'] == $_SESSION['user_id']): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Esta es su propia cuenta.</strong> No puede desactivarla.
                            </div>
                            <input type="hidden" name="activo" value="1">
                        <?php else: ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" 
                                       id="activoCheck" <?php echo $usuario_actual['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activoCheck">
                                    Usuario activo
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4">
                <button type="submit" name="<?php echo $action == 'nuevo' ? 'crear_usuario' : 'actualizar_usuario'; ?>" 
                        class="btn btn-admin">
                    <i class="bi bi-check-circle"></i> 
                    <?php echo $action == 'nuevo' ? 'Crear Usuario' : 'Guardar Cambios'; ?>
                </button>
                <a href="gestion_usuarios.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <?php if ($action == 'editar'): ?>
                    <button type="button" class="btn btn-warning" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalPassword<?php echo $usuario_actual['id_usuario']; ?>">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if ($action == 'editar'): ?>
        <!-- Modal Cambiar Contraseña -->
        <div class="modal fade" id="modalPassword<?php echo $usuario_actual['id_usuario']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="bi bi-key"></i> Cambiar Contraseña
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="id_usuario" value="<?php echo $usuario_actual['id_usuario']; ?>">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Usuario: <strong><?php echo htmlspecialchars($usuario_actual['nombre_completo']); ?></strong>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña *</label>
                                <input type="password" name="nueva_password" class="form-control" 
                                       placeholder="Mínimo 6 caracteres" minlength="6" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <input type="password" name="confirmar_password" class="form-control" 
                                       placeholder="Repita la contraseña" minlength="6" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="cambiar_password" class="btn btn-warning">
                                <i class="bi bi-key"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Lista de Usuarios -->
    
    <!-- Estadísticas -->
    <div class="stats-card">
        <div class="row">
            <div class="col-md-3 stat-item">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Usuarios</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-number"><?php echo $stats['activos']; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-number"><?php echo $stats['inactivos']; ?></div>
                <div class="stat-label">Inactivos</div>
            </div>
            <div class="col-md-3 stat-item">
                <div class="stat-number"><?php echo $stats['nunca_ingresaron']; ?></div>
                <div class="stat-label">Sin Acceso</div>
            </div>
        </div>
    </div>
    
    <div class="content-card">
        <h2 class="card-title">Gestión de Usuarios</h2>
        
        <!-- Filtros -->
        <form method="get" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Buscar usuario..." 
                           name="search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="rol" class="form-select">
                        <option value="">Todos los roles</option>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id_rol']; ?>" 
                                    <?php echo $rol_filter == $rol['id_rol'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select">
                        <option value="todos" <?php echo $estado_filter == 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                        <option value="activos" <?php echo $estado_filter == 'activos' ? 'selected' : ''; ?>>Solo activos</option>
                        <option value="inactivos" <?php echo $estado_filter == 'inactivos' ? 'selected' : ''; ?>>Solo inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-admin w-100">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </div>
            </div>
        </form>
        
        <div class="text-end mb-3">
            <a href="gestion_usuarios.php" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise"></i> Limpiar Filtros
            </a>
            <a href="?action=nuevo" class="btn btn-admin">
                <i class="bi bi-person-plus"></i> Nuevo Usuario
            </a>
        </div>
        
        <?php if (count($usuarios) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Usuario</th>
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
                                    <div class="fw-bold"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></div>
                                    <small class="text-muted">
                                        <i class="bi bi-telephone"></i> 
                                        <?php echo htmlspecialchars($usuario['telefono'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['correo_institucional']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $usuario['nombre_rol'] === 'Administrador' ? 'danger' : 
                                             ($usuario['nombre_rol'] === 'Trabajadora Social' ? 'success' : 
                                             ($usuario['nombre_rol'] === 'Coordinador' ? 'warning' : 'info'));
                                    ?>">
                                        <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($usuario['facultad']): ?>
                                        <small><?php echo htmlspecialchars($usuario['facultad']); ?></small>
                                    <?php elseif ($usuario['departamento']): ?>
                                        <small><?php echo htmlspecialchars($usuario['departamento']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($usuario['ultimo_acceso']): ?>
                                        <small><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalVer<?php echo $usuario['id_usuario']; ?>"
                                                title="Ver detalles">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="?action=editar&id=<?php echo $usuario['id_usuario']; ?>" 
                                           class="btn btn-outline-warning" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($usuario['id_usuario'] != $_SESSION['user_id']): ?>
                                            <?php if ($usuario['activo']): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalDesactivar<?php echo $usuario['id_usuario']; ?>"
                                                        title="Desactivar">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalActivar<?php echo $usuario['id_usuario']; ?>"
                                                        title="Activar">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled
                                                    title="No puede desactivar su propia cuenta">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Modales para cada usuario -->
            <?php foreach ($usuarios as $usuario): ?>
                
                <!-- Modal Ver Detalles -->
                <div class="modal fade" id="modalVer<?php echo $usuario['id_usuario']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-person-circle"></i> 
                                    Detalles del Usuario
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="text-muted small">Nombre Completo</label>
                                        <p class="fw-bold"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Correo Institucional</label>
                                        <p><?php echo htmlspecialchars($usuario['correo_institucional']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Rol</label>
                                        <p>
                                            <span class="badge bg-<?php 
                                                echo $usuario['nombre_rol'] === 'Administrador' ? 'danger' : 
                                                     ($usuario['nombre_rol'] === 'Trabajadora Social' ? 'success' : 'info');
                                            ?>">
                                                <?php echo htmlspecialchars($usuario['nombre_rol']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Teléfono</label>
                                        <p><?php echo htmlspecialchars($usuario['telefono'] ?? 'N/A'); ?></p>
                                    </div>
                                    <?php if ($usuario['facultad']): ?>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Facultad</label>
                                            <p><?php echo htmlspecialchars($usuario['facultad']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Carrera</label>
                                            <p><?php echo htmlspecialchars($usuario['carrera'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Año de Ingreso</label>
                                            <p><?php echo htmlspecialchars($usuario['año_ingreso'] ?? 'N/A'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($usuario['departamento']): ?>
                                        <div class="col-md-6">
                                            <label class="text-muted small">Departamento</label>
                                            <p><?php echo htmlspecialchars($usuario['departamento']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Fecha de Registro</label>
                                        <p><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Último Acceso</label>
                                        <p>
                                            <?php if ($usuario['ultimo_acceso']): ?>
                                                <?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Nunca ha ingresado</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="text-muted small">Estado</label>
                                        <p>
                                            <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <a href="?action=editar&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Desactivar -->
                <div class="modal fade" id="modalDesactivar<?php echo $usuario['id_usuario']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-x-circle"></i> Desactivar Usuario
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>¿Está seguro de desactivar este usuario?</strong>
                                </div>
                                
                                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                                <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario['correo_institucional']); ?></p>
                                <p><strong>Rol:</strong> <?php echo htmlspecialchars($usuario['nombre_rol']); ?></p>
                                
                                <p class="text-muted small">
                                    <i class="bi bi-info-circle"></i>
                                    El usuario no podrá acceder al sistema hasta que sea reactivado.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <a href="?action=desactivar&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-danger">
                                    <i class="bi bi-x-circle"></i> Sí, desactivar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Activar -->
                <div class="modal fade" id="modalActivar<?php echo $usuario['id_usuario']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-check-circle"></i> Activar Usuario
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>¿Está seguro de activar este usuario?</p>
                                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($usuario['nombre_completo']); ?></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <a href="?action=activar&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Sí, activar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endforeach; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p>No hay usuarios que coincidan con los filtros</p>
                <a href="gestion_usuarios.php" class="btn btn-secondary mt-3">
                    <i class="bi bi-arrow-clockwise"></i> Limpiar Filtros
                </a>
                <a href="?action=nuevo" class="btn btn-admin mt-3">
                    <i class="bi bi-person-plus"></i> Crear Nuevo Usuario
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

// Validar que las contraseñas coincidan
<?php if ($action == 'nuevo'): ?>
document.querySelector('form')?.addEventListener('submit', function(e) {
    const pass = document.querySelector('input[name="password"]').value;
    const confirm = document.querySelector('input[name="password_confirm"]').value;
    
    if (pass !== confirm) {
        e.preventDefault();
        alert('Las contraseñas no coinciden');
        return false;
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>