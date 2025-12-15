<?php
/**
 * Layout común para todas las páginas del estudiante
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

// Obtener información del estudiante para mostrar en el sidebar
$estudiante_info = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, d.cedula, d.estado_academico
        FROM usuarios u
        LEFT JOIN directorio_utp d ON u.id_directorio = d.id_directorio
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $estudiante_info = $stmt->fetch();
} catch (Exception $e) {
    // Silenciar error si no se puede cargar la info
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Bienestar Estudiantil UTP</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --utp-green: #2d8659;
            --utp-green-dark: #1a5c3a;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
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
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
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
            border-radius: 0;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--utp-green) 0%, var(--utp-green-dark) 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                padding: 0;
            }
            
            .sidebar.show {
                width: 250px;
                padding: 20px 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../../images/utp.png" alt="UTP Logo" onerror="this.style.display='none'">
            <h5>Bienestar Estudiantil</h5>
            <small>Universidad Tecnológica de Panamá</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'solicitudes.php' ? 'active' : ''; ?>" href="solicitudes.php">
                <i class="bi bi-file-earmark-text"></i> Mis Solicitudes
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'nueva_solicitud.php' ? 'active' : ''; ?>" href="nueva_solicitud.php">
                <i class="bi bi-plus-circle"></i> Nueva Solicitud
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'citas.php' ? 'active' : ''; ?>" href="citas.php">
                <i class="bi bi-calendar-event"></i> Mis Citas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'servicios.php' ? 'active' : ''; ?>" href="servicios.php">
                <i class="bi bi-list-check"></i> Servicios Disponibles
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'evaluaciones.php' ? 'active' : ''; ?>" href="evaluaciones.php">
                <i class="bi bi-star"></i> Evaluaciones
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : ''; ?>" href="perfil.php">
                <i class="bi bi-person-circle"></i> Mi Perfil
            </a>
            <a class="nav-link" href="../auth/logout.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($estudiante_info) && $estudiante_info): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-success"><?php echo isset($page_title) ? $page_title : 'Bienestar Estudiantil'; ?></h1>
                <?php if (isset($page_subtitle)): ?>
                    <p class="text-muted"><?php echo $page_subtitle; ?></p>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($estudiante_info['nombre_completo']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($estudiante_info['correo_institucional']); ?></small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aquí se insertará el contenido específico de cada página -->
        <?php if (isset($content)) echo $content; ?>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>