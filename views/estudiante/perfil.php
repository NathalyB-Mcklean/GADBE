<?php
/**
 * Mi Perfil
 * Información personal del estudiante
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

try {
    $conn = getDBConnection();
    
    // Obtener información del estudiante
    $stmt = $conn->prepare("
        SELECT u.*, d.cedula, d.estado_academico, d.tipo_usuario
        FROM usuarios u
        LEFT JOIN directorio_utp d ON u.id_directorio = d.id_directorio
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $estudiante = $stmt->fetch();
    
    // Procesar actualización de perfil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
        $telefono = trim($_POST['telefono']);
        
        $stmt_update = $conn->prepare("
            UPDATE usuarios 
            SET telefono = ?
            WHERE id_usuario = ?
        ");
        
        if ($stmt_update->execute([$telefono, $_SESSION['user_id']])) {
            $mensaje = 'Perfil actualizado correctamente';
            $tipo_mensaje = 'success';
            // Recargar datos
            $stmt->execute([$_SESSION['user_id']]);
            $estudiante = $stmt->fetch();
        } else {
            $mensaje = 'Error al actualizar el perfil';
            $tipo_mensaje = 'danger';
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Bienestar Estudiantil UTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --utp-green: #2d8659;
            --utp-green-dark: #1a5c3a;
        }
        
        body { background-color: #f8f9fa; }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(180deg, var(--utp-green) 0%, var(--utp-green-dark) 100%);
            color: white;
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-brand img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
        }
        
        .nav-link i { width: 20px; margin-right: 10px; }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--utp-green) 0%, var(--utp-green-dark) 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            color: var(--utp-green);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .info-row {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #212529;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../../images/utp.png" alt="UTP" onerror="this.style.display='none'">
            <h5>Bienestar Estudiantil</h5>
            <small>Universidad Tecnológica de Panamá</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
            <a class="nav-link" href="solicitudes.php"><i class="bi bi-file-earmark-text"></i> Mis Solicitudes</a>
            <a class="nav-link" href="nueva_solicitud.php"><i class="bi bi-plus-circle"></i> Nueva Solicitud</a>
            <a class="nav-link" href="citas.php"><i class="bi bi-calendar-event"></i> Mis Citas</a>
            <a class="nav-link" href="servicios.php"><i class="bi bi-list-check"></i> Servicios Disponibles</a>
            <a class="nav-link" href="evaluaciones.php"><i class="bi bi-star"></i> Evaluaciones</a>
            <a class="nav-link active" href="perfil.php"><i class="bi bi-person-circle"></i> Mi Perfil</a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <h2><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></h2>
            <p class="mb-0">
                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($estudiante['correo_institucional']); ?>
            </p>
        </div>
        
        <div class="row">
            <!-- Información Personal -->
            <div class="col-lg-6">
                <div class="info-card">
                    <h4 class="text-success mb-4">
                        <i class="bi bi-person"></i> Información Personal
                    </h4>
                    
                    <div class="info-row">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></div>
                    </div>
                    
                    <?php if ($estudiante['cedula']): ?>
                    <div class="info-row">
                        <div class="info-label">Cédula</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['cedula']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <div class="info-label">Correo Institucional</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['correo_institucional']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value">
                            <?php echo $estudiante['telefono'] ? htmlspecialchars($estudiante['telefono']) : 'No especificado'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información Académica -->
            <div class="col-lg-6">
                <div class="info-card">
                    <h4 class="text-success mb-4">
                        <i class="bi bi-mortarboard"></i> Información Académica
                    </h4>
                    
                    <div class="info-row">
                        <div class="info-label">Facultad</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['facultad']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Carrera</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['carrera']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Año de Ingreso</div>
                        <div class="info-value"><?php echo htmlspecialchars($estudiante['año_ingreso']); ?></div>
                    </div>
                    
                    <?php if ($estudiante['estado_academico']): ?>
                    <div class="info-row">
                        <div class="info-label">Estado Académico</div>
                        <div class="info-value">
                            <span class="badge bg-success"><?php echo ucfirst($estudiante['estado_academico']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actualizar Teléfono -->
        <div class="info-card">
            <h4 class="text-success mb-4">
                <i class="bi bi-pencil"></i> Actualizar Información
            </h4>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" 
                               value="<?php echo htmlspecialchars($estudiante['telefono']); ?>"
                               placeholder="6000-1234">
                        <small class="text-muted">Formato: XXXX-XXXX</small>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" name="actualizar_perfil" class="btn btn-success">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="text-center mt-4">
            <a href="../auth/logout.php" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>