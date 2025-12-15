<?php
/**
 * Servicios Disponibles
 * Catálogo de servicios y ofertas de Bienestar Estudiantil
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

try {
    $conn = getDBConnection();
    
    // Obtener servicios activos
    $stmt = $conn->prepare("
        SELECT so.*, cs.nombre_categoria, cs.descripcion as categoria_desc
        FROM servicios_ofertas so
        INNER JOIN categorias_servicios cs ON so.id_categoria = cs.id_categoria
        WHERE so.activo = 1
        AND (so.fecha_limite IS NULL OR so.fecha_limite >= CURDATE())
        ORDER BY cs.nombre_categoria, so.nombre
    ");
    $stmt->execute();
    $servicios = $stmt->fetchAll();
    
    // Agrupar por categoría
    $servicios_por_categoria = [];
    foreach ($servicios as $servicio) {
        $categoria = $servicio['nombre_categoria'];
        if (!isset($servicios_por_categoria[$categoria])) {
            $servicios_por_categoria[$categoria] = [];
        }
        $servicios_por_categoria[$categoria][] = $servicio;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $servicios_por_categoria = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios Disponibles - Bienestar Estudiantil UTP</title>
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
        
        .service-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid var(--utp-green);
            transition: all 0.3s;
        }
        
        .service-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--utp-green) 0%, var(--utp-green-dark) 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            margin-top: 30px;
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
            <a class="nav-link active" href="servicios.php"><i class="bi bi-list-check"></i> Servicios Disponibles</a>
            <a class="nav-link" href="evaluaciones.php"><i class="bi bi-star"></i> Evaluaciones</a>
            <a class="nav-link" href="perfil.php"><i class="bi bi-person-circle"></i> Mi Perfil</a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-success">Servicios Disponibles</h1>
                <p class="text-muted">Explora todos los servicios que Bienestar Estudiantil tiene para ti</p>
            </div>
            <a href="../auth/logout.php" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (count($servicios_por_categoria) > 0): ?>
            <?php foreach ($servicios_por_categoria as $categoria => $servicios_cat): ?>
                <div class="category-header">
                    <h3 class="mb-0"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($categoria); ?></h3>
                </div>
                
                <div class="row">
                    <?php foreach ($servicios_cat as $servicio): ?>
                        <div class="col-md-6">
                            <div class="service-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="text-success mb-0"><?php echo htmlspecialchars($servicio['nombre']); ?></h5>
                                    <span class="badge bg-<?php echo $servicio['tipo'] === 'oferta' ? 'warning' : 'info'; ?>">
                                        <?php echo ucfirst($servicio['tipo']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($servicio['descripcion']); ?></p>
                                
                                <div class="mb-3">
                                    <?php if ($servicio['requiere_cita']): ?>
                                        <span class="badge bg-primary me-2">
                                            <i class="bi bi-calendar-check"></i> Requiere cita
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($servicio['requiere_documentacion']): ?>
                                        <span class="badge bg-secondary me-2">
                                            <i class="bi bi-file-earmark-text"></i> Requiere documentación
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($servicio['duracion_estimada']): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-clock"></i> <?php echo $servicio['duracion_estimada']; ?> min
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($servicio['ubicacion']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($servicio['ubicacion']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($servicio['fecha_limite']): ?>
                                    <div class="alert alert-warning mb-3">
                                        <small><i class="bi bi-calendar-event"></i> Disponible hasta: <?php echo date('d/m/Y', strtotime($servicio['fecha_limite'])); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($servicio['requiere_cita']): ?>
                                    <a href="citas.php?servicio=<?php echo $servicio['id_servicio']; ?>" class="btn btn-primary">
                                        <i class="bi bi-calendar-plus"></i> Programar cita
                                    </a>
                                <?php else: ?>
                                    <a href="nueva_solicitud.php?servicio=<?php echo $servicio['id_servicio']; ?>" class="btn btn-success">
                                        <i class="bi bi-file-earmark-plus"></i> Solicitar este servicio
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 64px; opacity: 0.3;"></i>
                <p class="text-muted mt-3">No hay servicios disponibles en este momento</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>