<?php
/**
 * Layout común para todas las páginas del administrador
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

// Verificar que sea administrador
if ($_SESSION['user_role'] !== 'Administrador') {
    header("Location: ../auth/login.php");
    exit();
}

// Obtener información del administrador
$admin_info = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.id_usuario = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_info = $stmt->fetch();
} catch (Exception $e) {
    // Silenciar error si no se puede cargar la info
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Bienestar Estudiantil UTP - Administrador</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --utp-purple: #6B2C91;
            --utp-purple-dark: #4A1D6B;
            --utp-red: #dc3545;
            --utp-red-dark: #c82333;
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
            background: linear-gradient(180deg, var(--utp-red) 0%, var(--utp-red-dark) 100%);
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
            background: linear-gradient(135deg, var(--utp-red) 0%, var(--utp-red-dark) 100%);
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
        .stats-card.red { border-left-color: #dc3545; }
        .stats-card.teal { border-left-color: #20c997; }
        .stats-card.indigo { border-left-color: #6610f2; }
        .stats-card.pink { border-left-color: #e83e8c; }
        .stats-card.yellow { border-left-color: #ffc107; }
        
        .stats-card .stats-icon {
            font-size: 36px;
            opacity: 0.6;
        }
        
        .stats-card .stats-value {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stats-card.purple .stats-value { color: #6f42c1; }
        .stats-card.blue .stats-value { color: #0d6efd; }
        .stats-card.orange .stats-value { color: #fd7e14; }
        .stats-card.green .stats-value { color: #198754; }
        .stats-card.red .stats-value { color: #dc3545; }
        .stats-card.teal .stats-value { color: #20c997; }
        .stats-card.indigo .stats-value { color: #6610f2; }
        .stats-card.pink .stats-value { color: #e83e8c; }
        .stats-card.yellow .stats-value { color: #ffc107; }
        
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
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
            border-color: var(--utp-red);
            color: var(--utp-red);
        }
        
        .action-card i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--utp-red);
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
            color: var(--utp-red);
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
        
        .btn-admin {
            background-color: var(--utp-red);
            border-color: var(--utp-red);
            color: white;
        }
        
        .btn-admin:hover {
            background-color: var(--utp-red-dark);
            border-color: var(--utp-red-dark);
            color: white;
        }
        
        .btn-outline-admin {
            color: var(--utp-red);
            border-color: var(--utp-red);
        }
        
        .btn-outline-admin:hover {
            background-color: var(--utp-red);
            color: white;
        }
        
        .bg-admin {
            background-color: var(--utp-red) !important;
        }
        
        .text-admin {
            color: var(--utp-red) !important;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="../../images/utp.png" alt="UTP Logo" onerror="this.style.display='none'">
            <h5>Bienestar Estudiantil</h5>
            <small>Administrador</small>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php' ? 'active' : ''; ?>" href="dashboard_admin.php">
                <i class="bi bi-house-door"></i> Dashboard
            </a>
            
            <!-- Módulos de Gestión -->
            <div class="sidebar-heading px-3 mt-3 mb-1" style="font-size: 12px; color: rgba(255,255,255,0.5);">GESTIÓN</div>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'solicitudes_admin.php' ? 'active' : ''; ?>" href="solicitudes_admin.php">
                <i class="bi bi-file-earmark-text"></i> Gestión de Solicitudes
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'citas_admin.php' ? 'active' : ''; ?>" href="citas_admin.php">
                <i class="bi bi-calendar-event"></i> Agenda de Citas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'servicios_admin.php' ? 'active' : ''; ?>" href="servicios_admin.php">
                <i class="bi bi-list-check"></i> Servicios y Ofertas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes_admin.php' ? 'active' : ''; ?>" href="reportes_admin.php">
                <i class="bi bi-graph-up"></i> Reportes y Estadísticas
            </a>
            
            <!-- Administración del Sistema -->
            <div class="sidebar-heading px-3 mt-3 mb-1" style="font-size: 12px; color: rgba(255,255,255,0.5);">ADMINISTRACIÓN</div>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gestion_usuarios.php' ? 'active' : ''; ?>" href="gestion_usuarios.php">
                <i class="bi bi-people"></i> Gestión de Usuarios
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gestion_roles.php' ? 'active' : ''; ?>" href="gestion_roles.php">
                <i class="bi bi-person-badge"></i> Gestión de Roles
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gestion_permisos.php' ? 'active' : ''; ?>" href="gestion_permisos.php">
                <i class="bi bi-shield-check"></i> Gestión de Permisos
            </a>
            
            <!-- Configuración -->
            <div class="sidebar-heading px-3 mt-3 mb-1" style="font-size: 12px; color: rgba(255,255,255,0.5);">CONFIGURACIÓN</div>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracion_admin.php' ? 'active' : ''; ?>" href="configuracion_admin.php">
                <i class="bi bi-gear"></i> Configuración del Sistema
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs_admin.php' ? 'active' : ''; ?>" href="logs_admin.php">
                <i class="bi bi-journal-text"></i> Logs y Auditoría
            </a>
            
            <!-- Perfil -->
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'perfil_admin.php' ? 'active' : ''; ?>" href="perfil_admin.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <i class="bi bi-person-circle"></i> Mi Perfil
            </a>
            
            <a class="nav-link" href="../../auth/logout.php" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($admin_info) && $admin_info): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-admin"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                <?php if (isset($page_subtitle)): ?>
                    <p class="text-muted"><?php echo $page_subtitle; ?></p>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($admin_info['nombre_completo']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($admin_info['nombre_rol']); ?></small>
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