<?php
// api.php - Backend Sistema Bienestar Estudiantil
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración base de datos
$db_config = [
    'host' => 'localhost',
    'db' => 'bienestar_estudiantil',
    'user' => 'root',
    'pass' => ''
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['db']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
}

session_start();

// Función de auditoría
function registrarAuditoria($pdo, $usuario_id, $accion, $tabla = null, $registro_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_address) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $accion, $tabla, $registro_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch(PDOException $e) {
        error_log("Error auditoría: " . $e->getMessage());
    }
}

// Router principal
$method = $_SERVER['REQUEST_METHOD'];
$request = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Función respuesta
function responder($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// ============ AUTENTICACIÓN ============
if ($request === 'login') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';
    
    if (!preg_match('/@utp\.ac\.pa$/', $correo)) {
        responder(['success' => false, 'message' => 'Solo correos institucionales UTP (@utp.ac.pa)']);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        responder(['success' => false, 'message' => 'Credenciales UTP inválidas']);
    }
    
    if ($usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time()) {
        $minutos = ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
        responder(['success' => false, 'message' => "Cuenta bloqueada por $minutos minutos"]);
    }
    
    if (!password_verify($password, $usuario['password_hash'])) {
        $intentos = $usuario['intentos_fallidos'] + 1;
        if ($intentos >= 5) {
            $bloqueado = date('Y-m-d H:i:s', time() + 1800);
            $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?")
                ->execute([$intentos, $bloqueado, $usuario['id']]);
            responder(['success' => false, 'message' => 'Cuenta bloqueada 30 minutos por seguridad']);
        }
        $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?")
            ->execute([$intentos, $usuario['id']]);
        responder(['success' => false, 'message' => 'Credenciales incorrectas. Intentos: ' . (5-$intentos)]);
    }
    
    $pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultima_sesion = NOW() WHERE id = ?")
        ->execute([$usuario['id']]);
    
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_rol'] = $usuario['rol'];
    $_SESSION['user_nombre'] = $usuario['nombre'];
    $_SESSION['user_correo'] = $usuario['correo'];
    
    registrarAuditoria($pdo, $usuario['id'], 'login', 'usuarios', $usuario['id']);
    
    responder([
        'success' => true,
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'correo' => $usuario['correo'],
            'rol' => $usuario['rol']
        ]
    ]);
}

if ($request === 'logout') {
    if (isset($_SESSION['user_id'])) {
        registrarAuditoria($pdo, $_SESSION['user_id'], 'logout', 'usuarios', $_SESSION['user_id']);
    }
    session_destroy();
    responder(['success' => true, 'message' => 'Sesión cerrada']);
}

if ($request === 'registrar') {
    $correo = $data['correo'] ?? '';
    $password = $data['password'] ?? '';
    $nombre = $data['nombre'] ?? '';
    
    if (!preg_match('/@utp\.ac\.pa$/', $correo)) {
        responder(['success' => false, 'message' => 'Solo correos institucionales UTP']);
    }
    
    if (strlen($password) < 8) {
        responder(['success' => false, 'message' => 'Contraseña mínimo 8 caracteres']);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    if ($stmt->fetch()) {
        responder(['success' => false, 'message' => 'Correo ya registrado']);
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (correo, password_hash, nombre, rol) VALUES (?, ?, ?, 'Estudiante')");
    $stmt->execute([$correo, $hash, $nombre]);
    
    responder(['success' => true, 'message' => 'Cuenta creada exitosamente']);
}

if ($request === 'verificar_sesion') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'authenticated' => false]);
    }
    responder([
        'success' => true,
        'authenticated' => true,
        'usuario' => [
            'id' => $_SESSION['user_id'],
            'nombre' => $_SESSION['user_nombre'],
            'correo' => $_SESSION['user_correo'],
            'rol' => $_SESSION['user_rol']
        ]
    ]);
}

// ============ SERVICIOS ============
if ($request === 'listar_servicios') {
    $where = ["1=1"];
    $params = [];
    
    if (!empty($data['tipo'])) {
        $where[] = "s.tipo = ?";
        $params[] = $data['tipo'];
    }
    if (!empty($data['categoria'])) {
        $where[] = "c.nombre = ?";
        $params[] = $data['categoria'];
    }
    if (!empty($data['estado'])) {
        $where[] = "s.estado = ?";
        $params[] = $data['estado'];
    }
    if (!empty($data['busqueda'])) {
        $where[] = "(s.nombre LIKE ? OR s.descripcion LIKE ?)";
        $params[] = "%{$data['busqueda']}%";
        $params[] = "%{$data['busqueda']}%";
    }
    
    $sql = "SELECT s.*, c.nombre as categoria, u.nombre as trabajador 
            FROM servicios s 
            JOIN categorias c ON s.categoria_id = c.id 
            JOIN usuarios u ON s.trabajador_social_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY s.fecha_publicacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    responder(['success' => true, 'servicios' => $stmt->fetchAll()]);
}

if ($request === 'crear_servicio') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM servicios WHERE nombre = ?");
    $stmt->execute([$data['nombre']]);
    if ($stmt->fetch()) {
        responder(['success' => false, 'message' => 'Ya existe servicio con ese nombre']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO servicios (tipo, categoria_id, nombre, descripcion, trabajador_social_id, 
                          ubicacion, fecha_limite, estado, duracion, fecha_publicacion) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?, CURDATE())");
    
    $stmt->execute([
        $data['tipo'],
        $data['categoria_id'],
        $data['nombre'],
        $data['descripcion'],
        $_SESSION['user_id'],
        $data['ubicacion'] ?? 'Por definir',
        $data['fecha_limite'] ?? null,
        $data['duracion'] ?? 'Por definir'
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_servicio', 'servicios', $id);
    
    responder(['success' => true, 'message' => 'Servicio creado', 'id' => $id]);
}

if ($request === 'eliminar_servicio') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $id = $data['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM citas 
                          WHERE servicio_id = ? AND estado IN ('pendiente','confirmada')");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        responder(['success' => false, 'message' => "No se puede eliminar, tiene {$result['total']} citas activas"]);
    }
    
    $pdo->prepare("DELETE FROM servicios WHERE id = ?")->execute([$id]);
    registrarAuditoria($pdo, $_SESSION['user_id'], 'eliminar_servicio', 'servicios', $id);
    
    responder(['success' => true, 'message' => 'Servicio eliminado']);
}

// ============ CITAS ============
if ($request === 'listar_citas') {
    $where = ["1=1"];
    $params = [];
    
    if ($_SESSION['user_rol'] === 'Estudiante') {
        $where[] = "c.estudiante_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['user_rol'] === 'Trabajadora Social') {
        $where[] = "c.trabajador_social_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    $sql = "SELECT c.*, s.nombre as servicio, u.nombre as trabajador, e.nombre as estudiante 
            FROM citas c 
            JOIN servicios s ON c.servicio_id = s.id 
            JOIN usuarios u ON c.trabajador_social_id = u.id 
            JOIN usuarios e ON c.estudiante_id = e.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY c.fecha DESC, c.hora DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    responder(['success' => true, 'citas' => $stmt->fetchAll()]);
}

if ($request === 'crear_cita') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'message' => 'Debe iniciar sesión']);
    }
    
    if (strtotime($data['fecha']) < strtotime(date('Y-m-d'))) {
        responder(['success' => false, 'message' => 'No se pueden programar citas en fechas pasadas']);
    }
    
    $stmt = $pdo->prepare("SELECT trabajador_social_id FROM servicios WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$data['servicio_id']]);
    $servicio = $stmt->fetch();
    
    if (!$servicio) {
        responder(['success' => false, 'message' => 'Servicio no disponible']);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM citas WHERE fecha = ? AND hora = ? AND trabajador_social_id = ? 
                          AND estado IN ('pendiente','confirmada')");
    $stmt->execute([$data['fecha'], $data['hora'], $servicio['trabajador_social_id']]);
    
    if ($stmt->fetch()) {
        responder(['success' => false, 'message' => 'Horario ya no disponible']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO citas (estudiante_id, servicio_id, trabajador_social_id, fecha, hora, motivo, estado) 
                          VALUES (?, ?, ?, ?, ?, ?, 'pendiente')");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['servicio_id'],
        $servicio['trabajador_social_id'],
        $data['fecha'],
        $data['hora'],
        $data['motivo'] ?? null
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_cita', 'citas', $id);
    
    responder(['success' => true, 'message' => 'Cita programada', 'id' => $id]);
}

if ($request === 'cancelar_cita') {
    $id = $data['id'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE id = ? AND estudiante_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'cancelar_cita', 'citas', $id);
    responder(['success' => true, 'message' => 'Cita cancelada']);
}

// ============ EVALUACIONES ============
if ($request === 'crear_evaluacion') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'message' => 'Debe iniciar sesión']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO evaluaciones (estudiante_id, servicio_id, calificacion, comentario) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['servicio_id'] ?? null,
        $data['calificacion'],
        $data['comentario'] ?? null
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_evaluacion', 'evaluaciones', $id);
    
    responder(['success' => true, 'message' => 'Evaluación enviada']);
}

if ($request === 'listar_evaluaciones') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $stmt = $pdo->query("SELECT e.*, s.nombre as servicio, u.nombre as estudiante 
                        FROM evaluaciones e 
                        LEFT JOIN servicios s ON e.servicio_id = s.id 
                        JOIN usuarios u ON e.estudiante_id = u.id 
                        ORDER BY e.fecha_evaluacion DESC");
    
    responder(['success' => true, 'evaluaciones' => $stmt->fetchAll()]);
}

if ($request === 'estadisticas_evaluaciones') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $stats = [];
    
    // Total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM evaluaciones");
    $stats['total'] = $stmt->fetch()['total'];
    
    // Promedio
    $stmt = $pdo->query("SELECT calificacion, COUNT(*) as count FROM evaluaciones GROUP BY calificacion");
    $calificaciones = $stmt->fetchAll();
    $suma = 0;
    $total = 0;
    foreach ($calificaciones as $cal) {
        $valor = ['Muy Malo'=>1, 'Malo'=>2, 'Regular'=>3, 'Bueno'=>4, 'Excelente'=>5][$cal['calificacion']];
        $suma += $valor * $cal['count'];
        $total += $cal['count'];
    }
    $stats['promedio'] = $total > 0 ? round($suma / $total, 1) : 0;
    
    // Servicios evaluados
    $stmt = $pdo->query("SELECT COUNT(DISTINCT servicio_id) as total FROM evaluaciones WHERE servicio_id IS NOT NULL");
    $stats['servicios'] = $stmt->fetch()['total'];
    
    responder(['success' => true, 'estadisticas' => $stats]);
}

// ============ CATEGORÍAS ============
if ($request === 'listar_categorias') {
    $stmt = $pdo->query("SELECT * FROM categorias WHERE activa = 1 ORDER BY nombre");
    responder(['success' => true, 'categorias' => $stmt->fetchAll()]);
}

// ============ TRABAJADORES SOCIALES ============
if ($request === 'listar_trabajadores') {
    $stmt = $pdo->query("SELECT id, nombre, correo FROM usuarios WHERE rol = 'Trabajadora Social' AND activo = 1");
    responder(['success' => true, 'trabajadores' => $stmt->fetchAll()]);
}


// ============ AGENDA ============
if ($request === 'listar_horarios') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $trabajador_id = $data['trabajador_id'] ?? $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM horarios_disponibles WHERE trabajador_social_id = ? ORDER BY dia_semana, hora_inicio");
    $stmt->execute([$trabajador_id]);
    responder(['success' => true, 'horarios' => $stmt->fetchAll()]);
}

if ($request === 'crear_horario') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO horarios_disponibles (trabajador_social_id, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['dia_semana'],
        $data['hora_inicio'],
        $data['hora_fin']
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_horario', 'horarios_disponibles', $id);
    
    responder(['success' => true, 'message' => 'Horario creado', 'id' => $id]);
}

if ($request === 'eliminar_horario') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $pdo->prepare("DELETE FROM horarios_disponibles WHERE id = ? AND trabajador_social_id = ?")
        ->execute([$data['id'], $_SESSION['user_id']]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'eliminar_horario', 'horarios_disponibles', $data['id']);
    responder(['success' => true, 'message' => 'Horario eliminado']);
}

if ($request === 'bloquear_horario') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    // Verificar si hay citas en el periodo
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM citas 
                          WHERE trabajador_social_id = ? 
                          AND fecha BETWEEN ? AND ? 
                          AND estado IN ('pendiente','confirmada')");
    $stmt->execute([$_SESSION['user_id'], $data['fecha_inicio'], $data['fecha_fin']]);
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        responder(['success' => false, 'message' => "No se puede bloquear, tiene {$result['total']} citas programadas"]);
    }
    
    $stmt = $pdo->prepare("INSERT INTO bloqueos_horarios (trabajador_social_id, fecha_inicio, fecha_fin, motivo, tipo) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['fecha_inicio'],
        $data['fecha_fin'],
        $data['motivo'],
        $data['tipo']
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'bloquear_horario', 'bloqueos_horarios', $id);
    
    responder(['success' => true, 'message' => 'Horario bloqueado', 'id' => $id]);
}

if ($request === 'obtener_agenda') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $trabajador_id = $data['trabajador_id'] ?? $_SESSION['user_id'];
    $fecha_inicio = $data['fecha_inicio'] ?? date('Y-m-d');
    $fecha_fin = $data['fecha_fin'] ?? date('Y-m-d', strtotime('+7 days'));
    
    // Obtener citas
    $stmt = $pdo->prepare("SELECT c.*, s.nombre as servicio, u.nombre as estudiante 
                          FROM citas c 
                          JOIN servicios s ON c.servicio_id = s.id 
                          JOIN usuarios u ON c.estudiante_id = u.id 
                          WHERE c.trabajador_social_id = ? 
                          AND c.fecha BETWEEN ? AND ? 
                          ORDER BY c.fecha, c.hora");
    $stmt->execute([$trabajador_id, $fecha_inicio, $fecha_fin]);
    $citas = $stmt->fetchAll();
    
    // Obtener horarios disponibles
    $stmt = $pdo->prepare("SELECT * FROM horarios_disponibles WHERE trabajador_social_id = ?");
    $stmt->execute([$trabajador_id]);
    $horarios = $stmt->fetchAll();
    
    // Obtener bloqueos
    $stmt = $pdo->prepare("SELECT * FROM bloqueos_horarios 
                          WHERE trabajador_social_id = ? 
                          AND fecha_fin >= ?");
    $stmt->execute([$trabajador_id, date('Y-m-d')]);
    $bloqueos = $stmt->fetchAll();
    
    responder(['success' => true, 'citas' => $citas, 'horarios' => $horarios, 'bloqueos' => $bloqueos]);
}

// ============ SOLICITUDES ============
if ($request === 'crear_solicitud') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'message' => 'Debe iniciar sesión']);
    }
    
    // Verificar límite de solicitudes activas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM solicitudes 
                          WHERE estudiante_id = ? 
                          AND estado IN ('pendiente','en_revision')");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    if ($result['total'] >= 3) {
        responder(['success' => false, 'message' => 'Límite de 3 solicitudes activas alcanzado']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO solicitudes (estudiante_id, servicio_id, motivo, estado) 
                          VALUES (?, ?, ?, 'pendiente')");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['servicio_id'],
        $data['motivo']
    ]);
    
    $id = $pdo->lastInsertId();
    registrarAuditoria($pdo, $_SESSION['user_id'], 'crear_solicitud', 'solicitudes', $id);
    
    responder(['success' => true, 'message' => 'Solicitud creada', 'id' => $id]);
}

if ($request === 'listar_solicitudes') {
    $where = ["1=1"];
    $params = [];
    
    if ($_SESSION['user_rol'] === 'Estudiante') {
        $where[] = "s.estudiante_id = ?";
        $params[] = $_SESSION['user_id'];
    } elseif ($_SESSION['user_rol'] === 'Trabajadora Social') {
        // Puede ver solicitudes de servicios que ella maneja
        $where[] = "ser.trabajador_social_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    if (!empty($data['estado'])) {
        $where[] = "s.estado = ?";
        $params[] = $data['estado'];
    }
    
    $sql = "SELECT s.*, ser.nombre as servicio, u.nombre as estudiante 
            FROM solicitudes s 
            JOIN servicios ser ON s.servicio_id = ser.id 
            JOIN usuarios u ON s.estudiante_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY s.fecha_solicitud DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    responder(['success' => true, 'solicitudes' => $stmt->fetchAll()]);
}

if ($request === 'gestionar_solicitud') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $accion = $data['accion']; // 'aprobar', 'rechazar', 'solicitar_info'
    $estado = ['aprobar' => 'aprobada', 'rechazar' => 'rechazada', 'solicitar_info' => 'en_revision'][$accion];
    
    $stmt = $pdo->prepare("UPDATE solicitudes SET estado = ?, comentarios_trabajador = ?, revisado_por = ?, fecha_revision = NOW() 
                          WHERE id = ?");
    $stmt->execute([
        $estado,
        $data['comentario'] ?? '',
        $_SESSION['user_id'],
        $data['id']
    ]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'gestionar_solicitud', 'solicitudes', $data['id']);
    responder(['success' => true, 'message' => "Solicitud {$accion}"]);
}

if ($request === 'guardar_borrador') {
    if (!isset($_SESSION['user_id'])) {
        responder(['success' => false, 'message' => 'Debe iniciar sesión']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO solicitudes (estudiante_id, servicio_id, motivo, estado) 
                          VALUES (?, ?, ?, 'borrador') 
                          ON DUPLICATE KEY UPDATE motivo = ?");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['servicio_id'] ?? null,
        $data['motivo'] ?? '',
        $data['motivo'] ?? ''
    ]);
    
    responder(['success' => true, 'message' => 'Borrador guardado']);
}

// ============ ESTADÍSTICAS ============
if ($request === 'generar_estadisticas') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($data['fecha_inicio'])) {
        $where[] = "c.fecha >= ?";
        $params[] = $data['fecha_inicio'];
    }
    
    if (!empty($data['fecha_fin'])) {
        $where[] = "c.fecha <= ?";
        $params[] = $data['fecha_fin'];
    }
    
    if (!empty($data['categoria_id'])) {
        $where[] = "s.categoria_id = ?";
        $params[] = $data['categoria_id'];
    }
    
    // Estadísticas de citas
    $sql_citas = "SELECT 
                    COUNT(*) as total_citas,
                    SUM(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                    SUM(CASE WHEN c.estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
                    AVG(CASE WHEN c.estado = 'completada' THEN 1 ELSE 0 END) * 100 as tasa_exito
                  FROM citas c
                  JOIN servicios s ON c.servicio_id = s.id
                  WHERE " . implode(' AND ', $where);
    
    $stmt = $pdo->prepare($sql_citas);
    $stmt->execute($params);
    $estadisticas_citas = $stmt->fetch();
    
    // Estadísticas de evaluaciones
    $sql_eval = "SELECT 
                   COUNT(*) as total_evaluaciones,
                   AVG(CASE 
                     WHEN calificacion = 'Excelente' THEN 5
                     WHEN calificacion = 'Bueno' THEN 4
                     WHEN calificacion = 'Regular' THEN 3
                     WHEN calificacion = 'Malo' THEN 2
                     WHEN calificacion = 'Muy Malo' THEN 1
                   END) as promedio_general
                 FROM evaluaciones e
                 JOIN servicios s ON e.servicio_id = s.id
                 WHERE " . implode(' AND ', $where);
    
    $stmt = $pdo->prepare($sql_eval);
    $stmt->execute($params);
    $estadisticas_eval = $stmt->fetch();
    
    responder(['success' => true, 'citas' => $estadisticas_citas, 'evaluaciones' => $estadisticas_eval]);
}

if ($request === 'exportar_pdf') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    // Simular generación de PDF (en producción usarías una librería como TCPDF o Dompdf)
    $filename = "reporte_" . date('Y-m-d_H-i-s') . ".pdf";
    $content = "Reporte generado el " . date('Y-m-d H:i:s');
    
    responder(['success' => true, 'message' => 'PDF generado', 'filename' => $filename, 'content' => base64_encode($content)]);
}

if ($request === 'exportar_excel') {
    if (!in_array($_SESSION['user_rol'] ?? '', ['Trabajadora Social', 'Administrador'])) {
        responder(['success' => false, 'message' => 'Sin permisos']);
    }
    
    $filename = "reporte_" . date('Y-m-d_H-i-s') . ".xlsx";
    
    responder(['success' => true, 'message' => 'Excel generado', 'filename' => $filename]);
}

// ============ ROLES Y PERMISOS ============
if ($request === 'listar_roles_permisos') {
    if ($_SESSION['user_rol'] !== 'Administrador') {
        responder(['success' => false, 'message' => 'Solo administrador']);
    }
    
    $stmt = $pdo->query("SELECT rp.*, p.nombre as permiso_nombre, p.modulo 
                        FROM roles_permisos rp 
                        JOIN permisos p ON rp.permiso_id = p.id 
                        ORDER BY rp.rol, p.modulo");
    responder(['success' => true, 'roles_permisos' => $stmt->fetchAll()]);
}

if ($request === 'asignar_rol') {
    if ($_SESSION['user_rol'] !== 'Administrador') {
        responder(['success' => false, 'message' => 'Solo administrador']);
    }
    
    // Verificar que no sea auto-asignación de administrador
    if ($data['usuario_id'] == $_SESSION['user_id'] && $data['rol'] !== 'Administrador') {
        responder(['success' => false, 'message' => 'No puede modificar sus propios privilegios administrativos']);
    }
    
    $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")
        ->execute([$data['rol'], $data['usuario_id']]);
    
    registrarAuditoria($pdo, $_SESSION['user_id'], 'asignar_rol', 'usuarios', $data['usuario_id']);
    responder(['success' => true, 'message' => 'Rol asignado']);
}

// Agregar esto ANTES del último responder() en api.php
// (antes de: responder(['success' => false, 'message' => 'Acción no válida']))

// Acción no encontrada
responder(['success' => false, 'message' => 'Acción no válida: ' . $request]);
?>