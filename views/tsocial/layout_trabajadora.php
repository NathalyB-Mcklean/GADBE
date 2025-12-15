<?php
/**
 * Layout común para todas las páginas de la trabajadora social
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Verificar que sea trabajadora social o coordinador
if ($_SESSION['user_role'] !== 'Trabajadora Social' && $_SESSION['user_role'] !== 'Coordinador') {
    header("Location: ../auth/login.php");
    exit();
}

// Obtener información de la trabajadora social
$trabajadora_info = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $trabajadora_info = $stmt->fetch();
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
            --utp-purple: #6B2C91;
            --utp-purple-dark: #4A1D6B;
            --utp-green: #2d8659;
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
            background: linear-gradient(180deg, var(--utp-purple) 0%, var(--utp-purple-dark) 100%);
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
            background: linear-gradient(135deg, var(--utp-purple) 0%, var(--utp-purple-dark) 100%);
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
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-card.purple { border-left-color: #6f42c1; }
        .stats-card.blue { border-left-color: #0d6efd; }
        .stats-card.orange { border-left-color: #fd7e14; }
        .stats-card.green { border-left-color: #198754; }
        
        .stats-card .stats-icon {
            font-size: 36px;
            opacity: 0.6;
        }
        
        .stats-card .stats-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--utp-purple);
            margin: 10px 0;
        }
        
        .stats-card .stats-label {
            font-size: 13px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stats-card .stats-desc {
            font-size: 12px;
            color: #adb5bd;
            margin-top: 5px;
        }
        
        .action-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            text-decoration: none;
            color: #495057;
            display: block;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(107, 44, 145, 0.2);
            border-color: var(--utp-purple);
            color: var(--utp-purple);
        }
        
        .action-card i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--utp-purple);
        }
        
        .action-card .action-title {
            font-weight: 600;
            font-size: 14px;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .content-card .card-title {
            color: var(--utp-purple);
            font-size: 18px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .list-item {
            padding: 15px;
            border-left: 4px solid #dee2e6;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .list-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .list-item-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }
        
        .list-item-meta {
            font-size: 12px;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../../images/utp.png" alt="UTP Logo" onerror="this.style.display='none'">
            <h5>Bienestar Estudiantil</h5>
            <small>Trabajadora Social</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_trabajadora.php' ? 'active' : ''; ?>" href="dashboard_trabajadora.php">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'solicitudes_trabajadora.php' ? 'active' : ''; ?>" href="solicitudes_trabajadora.php">
                <i class="bi bi-file-earmark-text"></i> Gestión de Solicitudes
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'citas_trabajadora.php' ? 'active' : ''; ?>" href="citas_trabajadora.php">
                <i class="bi bi-calendar-event"></i> Agenda de Citas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'servicios_trabajadora.php' ? 'active' : ''; ?>" href="servicios_trabajadora.php">
                <i class="bi bi-list-check"></i> Servicios y Ofertas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes_trabajadora.php' ? 'active' : ''; ?>" href="reportes_trabajadora.php">
                <i class="bi bi-graph-up"></i> Reportes y Estadísticas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perfil_trabajadora.php' ? 'active' : ''; ?>" href="perfil_trabajadora.php">
                <i class="bi bi-person-circle"></i> Mi Perfil
            </a>
            
            <?php if ($_SESSION['user_role'] === 'Coordinador'): ?>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_trabajadora.php' ? 'active' : ''; ?>" href="admin_trabajadora.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <i class="bi bi-gear"></i> Administración
            </a>
            <?php endif; ?>
            
            <a class="nav-link" href="../auth/logout.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($trabajadora_info) && $trabajadora_info): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-purple"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                <?php if (isset($page_subtitle)): ?>
                    <p class="text-muted"><?php echo $page_subtitle; ?></p>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($trabajadora_info['nombre_completo']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($trabajadora_info['nombre_rol']); ?></small>
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