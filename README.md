# GADBE - Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil

[![SonarCloud](https://sonarcloud.io/images/project_badges/sonarcloud-white.svg)](https://sonarcloud.io/summary/new_code?id=NathalyB-Mcklean_GADBE)

Sistema web para la gestión de servicios, citas, solicitudes y evaluaciones del Departamento de Bienestar Estudiantil de la Universidad Tecnológica de Panamá.

## 📋 Tabla de Contenidos

- [Características](#características)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Guía de Refactorización](#guía-de-refactorización)
- [Testing](#testing)
- [SonarQube](#sonarqube)
- [Casos de Uso Implementados](#casos-de-uso-implementados)
- [Documentación](#documentación)

---

## ✨ Características

### Módulos Implementados

- ✅ **Autenticación y Autorización**
  - Login con correos institucionales UTP (@utp.ac.pa)
  - Registro de usuarios
  - Recuperación de contraseña
  - Control de intentos fallidos y bloqueo temporal
  - Gestión de roles y permisos

- ✅ **Gestión de Servicios y Ofertas**
  - Crear, modificar y eliminar servicios
  - Validación de duplicados
  - Control de servicios con citas activas

- ✅ **Sistema de Citas**
  - Programación de citas
  - Validación de disponibilidad
  - Prevención de conflictos de horarios
  - Cancelación de citas

- ✅ **Gestión de Agenda**
  - Configuración de horarios disponibles
  - Bloqueo de horarios (vacaciones, etc.)
  - Vista de agenda semanal/mensual

- ✅ **Sistema de Solicitudes**
  - Creación de solicitudes de servicios
  - Guardado de borradores
  - **NUEVO:** Sistema de carga de documentos
  - Límite de solicitudes activas

- ✅ **Gestión de Solicitudes (Trabajadora Social)**
  - Aprobar/Rechazar solicitudes
  - Solicitar información adicional
  - Auditoría completa

- ✅ **Evaluaciones de Satisfacción**
  - Encuestas de servicios
  - Estadísticas de satisfacción

- ✅ **Estadísticas y Reportes**
  - Generación de estadísticas
  - **EN PROGRESO:** Exportación a PDF/Excel

---

## 📁 Estructura del Proyecto

```
GADBE/
├── config/                      # Configuración
│   ├── database.php            # Conexión a BD (desde .env)
│   └── config.php              # Configuración general
│
├── src/                        # Código fuente
│   ├── Controllers/            # Controladores (MVC)
│   │   ├── AuthController.php
│   │   ├── ServiciosController.php (PENDIENTE)
│   │   ├── CitasController.php (PENDIENTE)
│   │   └── ...
│   │
│   ├── Models/                 # Modelos de datos (PENDIENTE)
│   │   ├── Usuario.php
│   │   └── ...
│   │
│   ├── Validators/             # Validadores reutilizables
│   │   ├── EmailValidator.php
│   │   ├── PasswordValidator.php
│   │   └── FormValidator.php
│   │
│   ├── Services/               # Lógica de negocio
│   │   ├── AuditoriaService.php
│   │   └── NotificationService.php (PENDIENTE)
│   │
│   └── Utils/                  # Utilidades
│       ├── Response.php
│       └── FileUploader.php
│
├── tests/                      # Tests unitarios e integración
│   ├── Unit/
│   │   └── Validators/
│   └── Integration/
│
├── public/                     # Archivos públicos
│   ├── index.php               # Entry point (PENDIENTE)
│   └── api.php                 # Router API (PENDIENTE)
│
├── uploads/                    # Archivos subidos
│   ├── documentos/
│   └── reportes/
│
├── api.php                     # ⚠️ ARCHIVO LEGACY (740 líneas)
├── index.html                  # Frontend actual
├── .env.example                # Ejemplo de configuración
├── composer.json               # Dependencias PHP
├── phpunit.xml                 # Configuración de tests
├── sonar-project.properties    # Configuración SonarQube
└── README.md                   # Este archivo
```

---

## 🔧 Requisitos

- PHP >= 8.0
- MySQL/MariaDB >= 5.7
- Composer
- Extensiones PHP:
  - pdo_mysql
  - mbstring
  - json
  - fileinfo

---

## 📦 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/NathalyB-Mcklean/GADBE.git
cd GADBE
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:

```env
DB_HOST=localhost
DB_NAME=bienestar_estudiantil
DB_USER=root
DB_PASS=tu_password
```

### 4. Crear base de datos

```sql
CREATE DATABASE bienestar_estudiantil;
```

Importar el esquema desde `estructura_inicial.html` o ejecutar el script SQL.

### 5. Configurar permisos

```bash
chmod 755 uploads/
chmod 755 uploads/documentos/
chmod 755 uploads/reportes/
```

---

## ⚙️ Configuración

### Variables de Entorno Importantes

```env
# Seguridad
MAX_LOGIN_ATTEMPTS=5        # Intentos antes de bloqueo
LOCKOUT_TIME=1800           # Tiempo de bloqueo (segundos)
PASSWORD_MIN_LENGTH=8       # Longitud mínima de contraseña

# Archivos
MAX_UPLOAD_SIZE=5242880     # 5MB en bytes
ALLOWED_EXTENSIONS=pdf,jpg,jpeg,png

# Email (para recuperación de contraseña)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_email@gmail.com
MAIL_PASSWORD=tu_app_password
```

---

## 🔄 Guía de Refactorización

### Problema Actual

El archivo `api.php` tiene **740 líneas** con:
- ❌ Todas las rutas en un solo archivo
- ❌ Lógica de negocio mezclada con acceso a datos
- ❌ Código duplicado
- ❌ Credenciales hardcodeadas
- ❌ Violación de principios SOLID

### Solución: Arquitectura Refactorizada

Ya se han creado las bases:
1. ✅ Sistema de configuración con `.env`
2. ✅ Validadores centralizados
3. ✅ Utilidades reutilizables (Response, FileUploader)
4. ✅ Ejemplo de controlador refactorizado (`AuthController`)
5. ✅ Service layer (`AuditoriaService`)

### Pasos para Completar la Refactorización

#### 1. Migrar Rutas de `api.php` a Controladores

**Ejemplo: Servicios**

Crear `src/Controllers/ServiciosController.php`:

```php
<?php
namespace GADBE\Controllers;

use PDO;
use GADBE\Utils\Response;
use GADBE\Validators\FormValidator;

class ServiciosController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function listar(array $filtros = []): never {
        // Lógica de listar servicios desde api.php línea 169-201
        // Usar FormValidator para validar filtros
        // Usar Response::success() para respuesta
    }

    public function crear(array $data): never {
        // Lógica de api.php línea 203-233
        // Implementar validaciones con FormValidator
    }

    // ... más métodos
}
```

#### 2. Crear Router Principal

Crear `public/api.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

setSecurityHeaders();
session_start();

$pdo = getDatabase();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Manejar OPTIONS para CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Routing
switch ($action) {
    // Auth
    case 'login':
        $controller = new \GADBE\Controllers\AuthController($pdo);
        $controller->login($data);
        break;

    case 'registrar':
        $controller = new \GADBE\Controllers\AuthController($pdo);
        $controller->registrar($data);
        break;

    // Servicios
    case 'listar_servicios':
        $controller = new \GADBE\Controllers\ServiciosController($pdo);
        $controller->listar($data);
        break;

    // ... más rutas

    default:
        \GADBE\Utils\Response::error('Acción no válida', 404);
}
```

#### 3. Implementar Tests para Cada Controlador

Ver ejemplos en `tests/Unit/Validators/`

```php
<?php
namespace GADBE\Tests\Integration;

use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase {
    public function test_login_exitoso() {
        // CP-ING-01
        // Implementar test
    }

    public function test_login_con_password_incorrecta() {
        // CP-ING-02
    }

    // ... más tests según casos de prueba del documento
}
```

---

## 🧪 Testing

### Ejecutar Tests

```bash
# Todos los tests
composer test

# Con cobertura
composer test-coverage

# Solo tests unitarios
./vendor/bin/phpunit tests/Unit

# Solo tests de integración
./vendor/bin/phpunit tests/Integration
```

### Casos de Prueba Documentados

Consultar `ANALISIS_COBERTURA.md` para ver:
- Casos implementados
- Casos pendientes
- Cobertura por módulo

### Ejemplo de Test

```php
/**
 * @test
 * CP-ING-01: Inicio de sesión exitoso
 */
public function debe_permitir_login_con_credenciales_validas(): void {
    $usuario = createTestUser([
        'correo' => 'test.user@utp.ac.pa',
        'password' => 'Test1234'
    ]);

    $controller = new AuthController(getDatabase());

    // Simular request
    $_POST = [
        'correo' => 'test.user@utp.ac.pa',
        'password' => 'Test1234'
    ];

    ob_start();
    $controller->login($_POST);
    $output = ob_get_clean();

    $response = json_decode($output, true);

    $this->assertTrue($response['success']);
    $this->assertArrayHasKey('usuario', $response['data']);
}
```

---

## 📊 SonarQube

### Ejecutar Análisis Local

```bash
# Ejecutar tests con cobertura primero
composer test-coverage

# Análisis estático
composer analyse

# Code style
composer cs-check
```

### Integración con SonarCloud

El proyecto está configurado para análisis automático con GitHub Actions.

Ver `sonar-project.properties` para configuración.

### Métricas Objetivo

| Métrica | Objetivo | Estado Actual |
|---------|----------|---------------|
| Cobertura | > 80% | 0% → Implementar tests |
| Code Smells | < 50 | ~150 → Refactorizar |
| Bugs | 0 | TBD |
| Vulnerabilidades | 0 | 3 → Migrar .env |
| Duplicación | < 3% | ~8% |
| Complejidad Ciclomática | < 10 por función | Reducir api.php |

---

## 📚 Casos de Uso Implementados

### ✅ Implementados (100%)

1. **Ingresar al Sistema**
   - CP-ING-01: Login exitoso
   - CP-ING-02 a CP-ING-04: Validaciones
   - CP-ING-05: Bloqueo por intentos
   - CP-ING-06 a CP-ING-09: Registro
   - ⚠️ CP-ING-10: Recuperación contraseña (backend listo, falta email)

2. **Gestionar Servicios**
   - CP-GSO-01 a CP-GSO-06: CRUD completo

3. **Programar Citas**
   - CP-CIT-01 a CP-CIT-06: Gestión completa

4. **Gestionar Agenda**
   - CP-GAA-01 a CP-GAA-06

5. **Gestión de Solicitudes**
   - CP-SOL-01: Crear solicitud
   - ⚠️ CP-SOL-02 a CP-SOL-05: Upload archivos (implementado, falta integrar)

### ⚠️ Parcialmente Implementados

6. **Consultas Avanzadas** (60%)
   - Falta: validaciones frontend

7. **Evaluaciones** (80%)
   - Falta: sistema de múltiples preguntas

8. **Estadísticas** (90%)
   - Falta: generación real de PDF/Excel

9. **Roles y Permisos** (80%)
   - Falta: CRUD completo de roles

---

## 🔐 Seguridad

### Implementado

- ✅ Validación de emails institucionales
- ✅ Passwords hasheados con bcrypt
- ✅ Prepared statements (SQL injection)
- ✅ XSS prevention con htmlspecialchars
- ✅ Control de intentos de login
- ✅ Bloqueo temporal de cuentas
- ✅ Auditoría de acciones

### Pendiente

- ⚠️ CORS más restrictivo
- ⚠️ CSRF tokens
- ⚠️ Rate limiting
- ⚠️ Sanitización de archivos subidos

---

## 📝 Documentación Adicional

- **ANALISIS_COBERTURA.md**: Análisis completo de cobertura de casos de uso
- **Documento de Casos de Prueba**: Ver sección "CASOS DE PRUEBAS" del documento proporcionado
- **PHPDoc**: Todas las clases nuevas tienen documentación completa

---

## 🚀 Próximos Pasos

### Prioridad Alta

1. **Completar Refactorización**
   - [ ] Migrar todas las rutas de `api.php` a controladores
   - [ ] Crear modelos de datos (Usuario, Servicio, Cita, etc.)
   - [ ] Implementar NotificationService para emails

2. **Implementar Funcionalidades Faltantes**
   - [ ] Sistema completo de upload de archivos
   - [ ] Envío de emails (recuperación, notificaciones)
   - [ ] Generación real de PDF con TCPDF
   - [ ] Generación real de Excel con PhpSpreadsheet

3. **Testing**
   - [ ] Tests para todos los controladores
   - [ ] Tests de integración
   - [ ] Cobertura > 80%

### Prioridad Media

4. **Frontend**
   - [ ] Actualizar `index.html` para usar nuevos endpoints
   - [ ] Mejorar UX/UI
   - [ ] Validaciones en frontend

5. **DevOps**
   - [ ] CI/CD con GitHub Actions
   - [ ] Docker compose para desarrollo
   - [ ] Deployment automático

---

## 👥 Equipo

**Grupo 1SF132**
- Abdiel Abrego (9-765-799)
- Nathaly Bonilla (8-1021-1364)
- Eimy Félix (8-1010-2376)
- Amanda Green (8-1023-1761)

**Profesora**: Geralis Garrido

**Universidad**: Universidad Tecnológica de Panamá
**Curso**: Mantenimiento y Pruebas de Software

---

## 📄 Licencia

Este proyecto es propiedad de la Universidad Tecnológica de Panamá.

---

## 🆘 Soporte

Para preguntas o issues:
1. Revisar `ANALISIS_COBERTURA.md`
2. Consultar documentación de casos de uso
3. Crear issue en GitHub

---

**Última actualización**: Diciembre 2025
