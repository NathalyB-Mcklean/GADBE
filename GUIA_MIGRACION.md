# Guía Rápida de Migración del Código Legacy

Esta guía te ayudará a migrar el código monolítico de `api.php` al nuevo sistema refactorizado.

## 📋 Checklist de Migración

- [x] Estructura de carpetas creada
- [x] Sistema de configuración con .env
- [x] Validadores centralizados
- [x] Utilidades (Response, FileUploader)
- [x] Ejemplo de controlador (AuthController)
- [x] Service layer (AuditoriaService)
- [x] Tests unitarios de ejemplo
- [ ] Migrar todos los endpoints
- [ ] Actualizar frontend
- [ ] Ejecutar tests
- [ ] Deployment

---

## 🎯 Paso 1: Configurar Entorno

### 1.1 Instalar Dependencias

```bash
composer install
```

### 1.2 Configurar .env

```bash
cp .env.example .env
nano .env  # Editar con tus credenciales
```

### 1.3 Verificar Permisos

```bash
chmod 755 uploads/
chmod 755 uploads/documentos/
chmod 755 uploads/reportes/
```

---

## 🔄 Paso 2: Migrar Endpoints por Módulo

### Template de Migración

Para cada sección del `api.php`, seguir este patrón:

#### 2.1 Identificar el Código a Migrar

Ejemplo: Servicios (líneas 169-255 de api.php)

```php
// ANTES (api.php)
if ($request === 'listar_servicios') {
    $where = ["1=1"];
    $params = [];

    if (!empty($data['tipo'])) {
        $where[] = "s.tipo = ?";
        $params[] = $data['tipo'];
    }
    // ... más lógica
}
```

#### 2.2 Crear el Controlador

Crear `src/Controllers/ServiciosController.php`:

```php
<?php
namespace GADBE\Controllers;

use PDO;
use GADBE\Utils\Response;
use GADBE\Validators\FormValidator;
use GADBE\Services\AuditoriaService;

class ServiciosController {
    private PDO $db;
    private AuditoriaService $auditoria;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->auditoria = new AuditoriaService($db);
    }

    /**
     * Lista servicios con filtros opcionales
     *
     * Caso de Prueba: CP-GSO-01
     *
     * @param array $filtros Filtros de búsqueda
     * @return never
     */
    public function listar(array $filtros = []): never {
        // Validar filtros
        $validator = new FormValidator($filtros);

        if (!empty($filtros['tipo'])) {
            $validator->in('tipo', ['Servicio', 'Oferta'], 'Tipo no válido');
        }

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Construir query
        $where = ["1=1"];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = "s.tipo = ?";
            $params[] = $filtros['tipo'];
        }

        if (!empty($filtros['categoria'])) {
            $where[] = "c.nombre = ?";
            $params[] = $filtros['categoria'];
        }

        if (!empty($filtros['busqueda'])) {
            // Validar búsqueda
            $busqueda = trim($filtros['busqueda']);

            if (strlen($busqueda) < 3) {
                Response::error('El término de búsqueda debe tener al menos 3 caracteres', 400);
            }

            // Solo letras, números y espacios
            if (!preg_match('/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ]+$/u', $busqueda)) {
                Response::error('Solo se permiten letras, números y espacios en la búsqueda', 400);
            }

            $where[] = "(s.nombre LIKE ? OR s.descripcion LIKE ?)";
            $params[] = "%{$busqueda}%";
            $params[] = "%{$busqueda}%";
        }

        // Ejecutar query
        try {
            $sql = "SELECT s.*, c.nombre as categoria, u.nombre as trabajador
                    FROM servicios s
                    JOIN categorias c ON s.categoria_id = c.id
                    JOIN usuarios u ON s.trabajador_social_id = u.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY s.fecha_publicacion DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $servicios = $stmt->fetchAll();

            Response::success(['servicios' => $servicios]);
        } catch (\PDOException $e) {
            error_log("Error al listar servicios: " . $e->getMessage());
            Response::serverError('Error al obtener los servicios');
        }
    }

    /**
     * Crea un nuevo servicio
     *
     * Casos de Prueba: CP-GSO-01 (exitoso), CP-GSO-04, CP-GSO-05 (fallidos)
     *
     * @param array $data Datos del servicio
     * @return never
     */
    public function crear(array $data): never {
        // Verificar permisos
        if (!isset($_SESSION['user_rol']) ||
            !in_array($_SESSION['user_rol'], ['Trabajadora Social', 'Administrador'])) {
            Response::forbidden();
        }

        // Validar entrada
        $validator = new FormValidator($data);
        $validator
            ->required('tipo')
            ->in('tipo', ['Servicio', 'Oferta'])
            ->required('nombre')
            ->minLength('nombre', 3)
            ->maxLength('nombre', 200)
            ->required('categoria_id')
            ->numeric('categoria_id')
            ->required('descripcion')
            ->minLength('descripcion', 10);

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Verificar duplicados (CP-GSO-05)
        if ($this->existeServicio($data['nombre'])) {
            Response::error('Ya existe un servicio/oferta con este nombre', 400);
        }

        // Crear servicio
        try {
            $stmt = $this->db->prepare("
                INSERT INTO servicios (tipo, categoria_id, nombre, descripcion,
                                      trabajador_social_id, ubicacion, fecha_limite,
                                      estado, duracion, fecha_publicacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?, CURDATE())
            ");

            $stmt->execute([
                $data['tipo'],
                $data['categoria_id'],
                trim($data['nombre']),
                trim($data['descripcion']),
                $_SESSION['user_id'],
                $data['ubicacion'] ?? 'Por definir',
                $data['fecha_limite'] ?? null,
                $data['duracion'] ?? 'Por definir'
            ]);

            $servicioId = (int)$this->db->lastInsertId();

            // Auditoría
            $this->auditoria->registrar(
                $_SESSION['user_id'],
                'crear_servicio',
                'servicios',
                $servicioId,
                ['nombre' => $data['nombre'], 'tipo' => $data['tipo']]
            );

            Response::success(
                ['id' => $servicioId],
                'Servicio creado exitosamente',
                201
            );
        } catch (\PDOException $e) {
            error_log("Error al crear servicio: " . $e->getMessage());
            Response::serverError('Error al crear el servicio');
        }
    }

    /**
     * Elimina un servicio
     *
     * Casos de Prueba: CP-GSO-02 (exitoso), CP-GSO-06 (fallido)
     *
     * @param array $data Datos con ID del servicio
     * @return never
     */
    public function eliminar(array $data): never {
        // Verificar permisos
        if (!isset($_SESSION['user_rol']) ||
            !in_array($_SESSION['user_rol'], ['Trabajadora Social', 'Administrador'])) {
            Response::forbidden();
        }

        $validator = new FormValidator($data);
        $validator->required('id')->numeric('id');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        $id = (int)$data['id'];

        // Verificar citas activas (CP-GSO-06)
        $citasActivas = $this->contarCitasActivas($id);

        if ($citasActivas > 0) {
            Response::error(
                "No se puede eliminar, tiene {$citasActivas} citas programadas. Cancele las citas primero.",
                400
            );
        }

        // Eliminar
        try {
            $stmt = $this->db->prepare("DELETE FROM servicios WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                Response::notFound('Servicio no encontrado');
            }

            $this->auditoria->registrar(
                $_SESSION['user_id'],
                'eliminar_servicio',
                'servicios',
                $id
            );

            Response::success(null, 'Servicio eliminado exitosamente');
        } catch (\PDOException $e) {
            error_log("Error al eliminar servicio: " . $e->getMessage());
            Response::serverError('Error al eliminar el servicio');
        }
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Verifica si existe un servicio con el nombre dado
     *
     * @param string $nombre Nombre del servicio
     * @return bool
     */
    private function existeServicio(string $nombre): bool {
        $stmt = $this->db->prepare("SELECT id FROM servicios WHERE nombre = ?");
        $stmt->execute([$nombre]);
        return $stmt->fetch() !== false;
    }

    /**
     * Cuenta citas activas de un servicio
     *
     * @param int $servicioId ID del servicio
     * @return int
     */
    private function contarCitasActivas(int $servicioId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM citas
            WHERE servicio_id = ?
            AND estado IN ('pendiente', 'confirmada')
        ");

        $stmt->execute([$servicioId]);
        $result = $stmt->fetch();

        return (int)$result['total'];
    }
}
```

#### 2.3 Crear el Router

Actualizar `public/api.php` (crear si no existe):

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Configuración
setSecurityHeaders();
session_start();

// Conexión BD
$pdo = getDatabase();

// Request
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Routing
try {
    switch ($action) {
        // ===== AUTH =====
        case 'login':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->login($data);
            break;

        case 'registrar':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->registrar($data);
            break;

        case 'logout':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->logout();
            break;

        case 'verificar_sesion':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->verificarSesion();
            break;

        case 'solicitar_recuperacion':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->solicitarRecuperacion($data);
            break;

        case 'restablecer_password':
            $controller = new \GADBE\Controllers\AuthController($pdo);
            $controller->restablecerPassword($data);
            break;

        // ===== SERVICIOS =====
        case 'listar_servicios':
            $controller = new \GADBE\Controllers\ServiciosController($pdo);
            $controller->listar($data);
            break;

        case 'crear_servicio':
            $controller = new \GADBE\Controllers\ServiciosController($pdo);
            $controller->crear($data);
            break;

        case 'eliminar_servicio':
            $controller = new \GADBE\Controllers\ServiciosController($pdo);
            $controller->eliminar($data);
            break;

        // ... más endpoints

        default:
            \GADBE\Utils\Response::notFound('Acción no válida: ' . $action);
    }
} catch (\Exception $e) {
    \GADBE\Utils\Response::serverError('Error interno', $e);
}
```

#### 2.4 Crear Tests

Crear `tests/Integration/ServiciosControllerTest.php`:

```php
<?php
namespace GADBE\Tests\Integration;

use PHPUnit\Framework\TestCase;
use GADBE\Controllers\ServiciosController;

class ServiciosControllerTest extends TestCase {
    private $pdo;
    private $controller;
    private $usuario;

    protected function setUp(): void {
        clearTestDatabase();

        $this->pdo = getDatabase();
        $this->controller = new ServiciosController($this->pdo);

        // Crear usuario de prueba
        $this->usuario = createTestUser([
            'rol' => 'Trabajadora Social'
        ]);

        // Simular sesión
        $_SESSION['user_id'] = $this->usuario['id'];
        $_SESSION['user_rol'] = $this->usuario['rol'];
    }

    /**
     * @test
     * CP-GSO-01: Crear servicio exitosamente
     */
    public function debe_crear_servicio_exitosamente(): void {
        $data = [
            'tipo' => 'Servicio',
            'categoria_id' => 1,
            'nombre' => 'Tutorías de Matemáticas',
            'descripcion' => 'Servicio de tutorías para estudiantes con dificultades en matemáticas',
            'ubicacion' => 'Edificio 1, Aula 101'
        ];

        ob_start();
        $this->controller->crear($data);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    /**
     * @test
     * CP-GSO-05: Rechazar servicio con nombre duplicado
     */
    public function debe_rechazar_nombre_duplicado(): void {
        // Crear primer servicio
        $this->crearServicioPrueba('Tutorías de Matemáticas');

        // Intentar crear duplicado
        $data = [
            'tipo' => 'Servicio',
            'categoria_id' => 1,
            'nombre' => 'Tutorías de Matemáticas',
            'descripcion' => 'Otro servicio'
        ];

        ob_start();
        $this->controller->crear($data);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('ya existe', $response['message']);
    }

    // Helper methods
    private function crearServicioPrueba(string $nombre): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO servicios (tipo, categoria_id, nombre, descripcion,
                                   trabajador_social_id, estado, fecha_publicacion)
            VALUES ('Servicio', 1, ?, 'Descripción de prueba', ?, 'activo', CURDATE())
        ");

        $stmt->execute([$nombre, $this->usuario['id']]);
        return (int)$this->pdo->lastInsertId();
    }
}
```

---

## 📝 Paso 3: Módulos a Migrar (en orden de prioridad)

### 3.1 Autenticación (✅ COMPLETADO)
- `AuthController.php` ya implementado
- Tests en `tests/Unit/Validators/`

### 3.2 Servicios (📝 Ejemplo arriba)
- Crear `ServiciosController.php`
- Tests en `tests/Integration/ServiciosControllerTest.php`

### 3.3 Citas
```bash
# Crear controlador
touch src/Controllers/CitasController.php

# Métodos a implementar:
# - listar()
# - crear()
# - cancelar()
# - modificar()
```

### 3.4 Evaluaciones
```bash
touch src/Controllers/EvaluacionesController.php

# Métodos:
# - crear()
# - listar()
# - estadisticas()
```

### 3.5 Solicitudes
```bash
touch src/Controllers/SolicitudesController.php

# Métodos:
# - crear()
# - listar()
# - gestionar()  (aprobar/rechazar)
# - guardarBorrador()
# - subirDocumentos()  (usa FileUploader)
```

### 3.6 Agenda
```bash
touch src/Controllers/AgendaController.php

# Métodos:
# - listarHorarios()
# - crearHorario()
# - eliminarHorario()
# - bloquearHorario()
# - obtenerAgenda()
```

### 3.7 Estadísticas
```bash
touch src/Controllers/EstadisticasController.php

# Métodos:
# - generar()
# - exportarPDF()
# - exportarExcel()
```

### 3.8 Roles y Permisos
```bash
touch src/Controllers/RolesController.php

# Métodos:
# - listar()
# - asignar()
# - crear()
# - eliminar()
# - modificar()
```

---

## ✅ Paso 4: Verificación

### 4.1 Ejecutar Tests

```bash
# Todos los tests
composer test

# Solo los nuevos
./vendor/bin/phpunit tests/Integration/ServiciosControllerTest.php
```

### 4.2 Análisis de Código

```bash
# Code style
composer cs-check

# Análisis estático
composer analyse
```

### 4.3 SonarQube

```bash
# Local (requiere sonar-scanner)
sonar-scanner

# O esperar análisis automático en GitHub
```

---

## 🎨 Paso 5: Actualizar Frontend

### 5.1 Cambiar URLs

En `index.html`, actualizar llamadas AJAX:

```javascript
// ANTES
fetch('api.php?action=login', {
    method: 'POST',
    body: JSON.stringify({ correo, password })
})

// DESPUÉS (sin cambios, sigue igual)
// Pero ahora usa el nuevo sistema refactorizado
fetch('api.php?action=login', {
    method: 'POST',
    body: JSON.stringify({ correo, password })
})
```

### 5.2 Manejo de Errores Mejorado

```javascript
// Aprovechar nuevo formato de errores
fetch('api.php?action=crear_servicio', {
    method: 'POST',
    body: JSON.stringify(data)
})
.then(res => res.json())
.then(response => {
    if (response.success) {
        mostrarExito(response.message);
    } else {
        // Nuevo: errores estructurados
        if (response.errors) {
            mostrarErroresValidacion(response.errors);
        } else {
            mostrarError(response.message);
        }
    }
});
```

---

## 📊 Seguimiento del Progreso

### Checklist de Migración por Módulo

```markdown
- [x] Auth (6/6 endpoints)
  - [x] login
  - [x] registrar
  - [x] logout
  - [x] verificar_sesion
  - [x] solicitar_recuperacion
  - [x] restablecer_password

- [ ] Servicios (0/3 endpoints)
  - [ ] listar_servicios
  - [ ] crear_servicio
  - [ ] eliminar_servicio

- [ ] Citas (0/3 endpoints)
  - [ ] listar_citas
  - [ ] crear_cita
  - [ ] cancelar_cita

- [ ] Evaluaciones (0/3 endpoints)
  - [ ] crear_evaluacion
  - [ ] listar_evaluaciones
  - [ ] estadisticas_evaluaciones

... (continuar para todos)
```

---

## 🚨 Problemas Comunes

### Error: "Class 'GADBE\Controllers\...' not found"

**Solución**:
```bash
composer dump-autoload
```

### Error: "Call to undefined function getDatabase()"

**Solución**: Asegurarse que `vendor/autoload.php` carga los archivos de config:

```json
// composer.json
"autoload": {
    "files": [
        "config/config.php",
        "config/database.php"
    ]
}
```

### Headers Already Sent

**Solución**: No hacer `echo` ni output antes de las respuestas JSON.

---

## 📚 Recursos

- **Documento de Casos de Uso**: Ver PDF proporcionado
- **ANALISIS_COBERTURA.md**: Mapeo completo de funcionalidades
- **README.md**: Documentación general
- **PHPDoc en clases**: Todas las clases tienen documentación inline

---

## 💡 Tips

1. **Migrar un módulo a la vez**: No intentar hacerlo todo junto
2. **Escribir tests primero**: Test-Driven Development
3. **Commit frecuente**: Un commit por endpoint migrado
4. **Revisar SonarQube**: Después de cada migración
5. **Mantener `api.php` legacy**: Hasta completar la migración

---

**¡Éxito con la refactorización!** 🚀
