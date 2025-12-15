<?php
/**
 * Archivo de Configuración de Base de Datos
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 * 
 * Este archivo maneja la conexión a la base de datos MySQL usando PDO
 * con manejo robusto de errores y configuración de seguridad.
 */

// Prevenir acceso directo al archivo
if (!defined('DB_CONFIG_INCLUDED')) {
    define('DB_CONFIG_INCLUDED', true);
}

// ============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ============================================

// Configuración del servidor de base de datos
define('DB_HOST', 'localhost');           // Host de la base de datos
define('DB_PORT', '3306');                 // Puerto de MySQL (por defecto 3306)
define('DB_NAME', 'bienestar_estudiantil_utp'); // Nombre de la base de datos
define('DB_USER', 'root');                 // Usuario de la base de datos
define('DB_PASS', '');                     // Contraseña de la base de datos (vacía por defecto en WAMP)
define('DB_CHARSET', 'utf8mb4');           // Conjunto de caracteres

// ============================================
// CONFIGURACIÓN DE ZONA HORARIA
// ============================================
date_default_timezone_set('America/Panama');

// ============================================
// CONFIGURACIÓN DE ERRORES (DESARROLLO)
// ============================================
// Cambiar a false en producción
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// CLASE DE CONEXIÓN A BASE DE DATOS
// ============================================

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    
    /**
     * Constructor privado para implementar patrón Singleton
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }
    
    /**
     * Obtener la instancia única de la clase (Singleton)
     * 
     * @return Database Instancia única de la clase
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establecer conexión a la base de datos usando PDO
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port=" . DB_PORT . ";dbname={$this->db_name};charset={$this->charset}";
            
            $options = [
                // Modo de error: lanzar excepciones
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
                // Modo de obtención: array asociativo
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                
                // Desactivar emulación de prepared statements (más seguro)
                PDO::ATTR_EMULATE_PREPARES => false,
                
                // Conexiones persistentes (mejor rendimiento)
                PDO::ATTR_PERSISTENT => true,
                
                // Convertir valores numéricos a strings (false para obtener tipos nativos)
                PDO::ATTR_STRINGIFY_FETCHES => false,
                
                // Timeouts
                PDO::ATTR_TIMEOUT => 5,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Configurar zona horaria de MySQL
            $this->connection->exec("SET time_zone = '-05:00'"); // Panamá GMT-5
            
            if (DEBUG_MODE) {
                error_log("✅ Conexión a base de datos establecida exitosamente");
            }
            
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }
    
    /**
     * Obtener la conexión PDO
     * 
     * @return PDO Objeto de conexión PDO
     */
    public function getConnection() {
        // Verificar si la conexión sigue activa
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * Manejar errores de conexión
     * 
     * @param PDOException $e Excepción de PDO
     */
    private function handleConnectionError($e) {
        $error_message = "❌ Error de conexión a la base de datos";
        
        if (DEBUG_MODE) {
            $error_message .= "\n";
            $error_message .= "Detalles: " . $e->getMessage() . "\n";
            $error_message .= "Código: " . $e->getCode() . "\n";
            $error_message .= "Host: " . $this->host . "\n";
            $error_message .= "Base de datos: " . $this->db_name . "\n";
            $error_message .= "Usuario: " . $this->username;
        }
        
        error_log($error_message);
        
        // En producción, mostrar mensaje genérico
        if (!DEBUG_MODE) {
            die("Error al conectar con la base de datos. Por favor, contacte al administrador del sistema.");
        } else {
            die("<pre>" . htmlspecialchars($error_message) . "</pre>");
        }
    }
    
    /**
     * Cerrar la conexión a la base de datos
     */
    public function closeConnection() {
        $this->connection = null;
        if (DEBUG_MODE) {
            error_log("🔌 Conexión a base de datos cerrada");
        }
    }
    
    /**
     * Verificar si la conexión está activa
     * 
     * @return bool True si la conexión está activa
     */
    public function isConnected() {
        try {
            return $this->connection !== null && $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtener información de la conexión
     * 
     * @return array Array con información de la conexión
     */
    public function getConnectionInfo() {
        if (!$this->isConnected()) {
            return ['status' => 'disconnected'];
        }
        
        return [
            'status' => 'connected',
            'host' => $this->host,
            'database' => $this->db_name,
            'charset' => $this->charset,
            'server_version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'client_version' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];
    }
    
    /**
     * Prevenir clonación del objeto (patrón Singleton)
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización del objeto (patrón Singleton)
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }
}

// ============================================
// FUNCIONES HELPER PARA ACCESO RÁPIDO
// ============================================

/**
 * Obtener conexión a la base de datos
 * 
 * @return PDO Objeto de conexión PDO
 */
function getDBConnection() {
    return Database::getInstance()->getConnection();
}

/**
 * Verificar conexión a la base de datos
 * 
 * @return bool True si está conectado
 */
function checkDBConnection() {
    return Database::getInstance()->isConnected();
}

/**
 * Obtener información de la conexión
 * 
 * @return array Información de la conexión
 */
function getDBInfo() {
    return Database::getInstance()->getConnectionInfo();
}

/**
 * Ejecutar una consulta preparada de manera segura
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return PDOStatement|false Statement ejecutado o false en caso de error
 */
function executeQuery($sql, $params = []) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("❌ Error en consulta: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
        }
        return false;
    }
}

/**
 * Obtener un solo registro
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return array|false Registro encontrado o false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Obtener múltiples registros
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return array Array de registros
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Insertar un registro y retornar el ID
 * 
 * @param string $sql Consulta SQL INSERT
 * @param array $params Parámetros para la consulta
 * @return int|false ID del registro insertado o false
 */
function insertRecord($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    if ($stmt) {
        return getDBConnection()->lastInsertId();
    }
    return false;
}

/**
 * Verificar si existe al menos un registro
 * 
 * @param string $sql Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return bool True si existe al menos un registro
 */
function recordExists($sql, $params = []) {
    $result = fetchOne($sql, $params);
    return $result !== false;
}

// ============================================
// INICIALIZACIÓN AUTOMÁTICA
// ============================================

// Inicializar la conexión al cargar el archivo (opcional)
// Descomenta la siguiente línea si quieres conexión automática
// Database::getInstance();

// ============================================
// TESTING (solo en modo debug)
// ============================================

if (DEBUG_MODE && basename($_SERVER['PHP_SELF']) === 'config.php') {
    echo "<h2>🔍 Test de Configuración de Base de Datos</h2>";
    echo "<hr>";
    
    // Test 1: Verificar conexión
    echo "<h3>1. Verificar Conexión</h3>";
    if (checkDBConnection()) {
        echo "✅ Conexión exitosa<br>";
    } else {
        echo "❌ Error en la conexión<br>";
    }
    
    // Test 2: Información de la conexión
    echo "<h3>2. Información de la Conexión</h3>";
    echo "<pre>";
    print_r(getDBInfo());
    echo "</pre>";
    
    // Test 3: Prueba de consulta simple
    echo "<h3>3. Prueba de Consulta Simple</h3>";
    try {
        $result = fetchOne("SELECT DATABASE() as current_db, NOW() as current_time");
        if ($result) {
            echo "✅ Base de datos actual: " . htmlspecialchars($result['current_db']) . "<br>";
            echo "✅ Hora del servidor: " . htmlspecialchars($result['current_time']) . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error en consulta: " . htmlspecialchars($e->getMessage());
    }
    
    // Test 4: Verificar tablas
    echo "<h3>4. Tablas en la Base de Datos</h3>";
    try {
        $tables = fetchAll("SHOW TABLES");
        if (count($tables) > 0) {
            echo "✅ Se encontraron " . count($tables) . " tablas:<br>";
            echo "<ul>";
            foreach ($tables as $table) {
                $table_name = array_values($table)[0];
                echo "<li>" . htmlspecialchars($table_name) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠️ No se encontraron tablas en la base de datos<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error al obtener tablas: " . htmlspecialchars($e->getMessage());
    }
    
    echo "<hr>";
    echo "<p><strong>Nota:</strong> Este test solo se muestra en modo DEBUG. Desactiva DEBUG_MODE en producción.</p>";
}

?>