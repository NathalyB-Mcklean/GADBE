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

// Acción no encontrada
responder(['success' => false, 'message' => 'Acción no válida: ' . $request]);
?>