# Implementación de Casos de Prueba según Documento

Este archivo mapea los casos de prueba del documento con la implementación en código.

---

## 1. INGRESAR AL SISTEMA

### Casos de Prueba Exitosos

| ID | Descripción | Estado | Ubicación Código | Test |
|----|-------------|--------|------------------|------|
| CP-ING-01 | Login con credenciales válidas | ✅ | AuthController::login() | EmailValidatorTest |
| CP-ING-02 | Registro exitoso | ✅ | AuthController::registrar() | - |
| CP-ING-10 | Recuperación de contraseña | ✅ | AuthController::solicitarRecuperacion() | - |

**Implementación**:
- Ver `src/Controllers/AuthController.php` líneas 43-117 (login)
- Ver `src/Controllers/AuthController.php` líneas 126-171 (registrar)
- Ver `src/Controllers/AuthController.php` líneas 221-262 (recuperación)

**Tests a Crear**:
```php
// tests/Integration/AuthControllerTest.php
public function test_CP_ING_01_login_exitoso()
public function test_CP_ING_02_registro_exitoso()
public function test_CP_ING_10_recuperacion_password()
```

### Casos de Prueba Fallidos

| ID | Descripción | Estado | Ubicación Código | Validador |
|----|-------------|--------|------------------|-----------|
| CP-ING-02 | Contraseña incorrecta | ✅ | AuthController::manejarLoginFallido() | PasswordValidator |
| CP-ING-03 | Campo correo vacío | ✅ | FormValidator::required() | FormValidator |
| CP-ING-04 | Bloqueo por múltiples intentos | ✅ | AuthController::manejarLoginFallido() | - |
| CP-ING-05 | Correo inválido | ✅ | EmailValidator::isInstitutional() | EmailValidator |
| CP-ING-06 | Bloqueo por intentos fallidos | ✅ | AuthController::estaBloqueada() | - |
| CP-ING-07 | Registro contraseñas no coinciden | ✅ | FormValidator::matches() | PasswordValidator |
| CP-ING-08 | Registro correo existente | ✅ | AuthController::existeCorreo() | - |
| CP-ING-09 | Formato correo no institucional | ✅ | EmailValidator::isInstitutional() | EmailValidator |

**Tests Implementados**:
- `tests/Unit/Validators/EmailValidatorTest.php`
- `tests/Unit/Validators/PasswordValidatorTest.php`

**Tests Pendientes**:
```php
// tests/Integration/AuthControllerTest.php
public function test_CP_ING_02_password_incorrecta()
public function test_CP_ING_03_campo_vacio()
public function test_CP_ING_04_bloqueo_multiples_intentos()
public function test_CP_ING_05_correo_invalido()
public function test_CP_ING_07_passwords_no_coinciden()
public function test_CP_ING_08_correo_existente()
```

---

## 2. GESTIONAR SERVICIOS Y OFERTAS

### Estado de Implementación

| Funcionalidad | Backend Legacy | Backend Refactorizado | Tests |
|---------------|----------------|----------------------|-------|
| Listar servicios | ✅ api.php:169 | ⚠️ Ejemplo en GUIA | ❌ |
| Crear servicio | ✅ api.php:203 | ⚠️ Ejemplo en GUIA | ❌ |
| Eliminar servicio | ✅ api.php:235 | ⚠️ Ejemplo en GUIA | ❌ |

### Casos de Prueba

| ID | Descripción | Implementación |
|----|-------------|----------------|
| CP-GSO-01 | Agregar servicio exitoso | Ver GUIA_MIGRACION.md - ServiciosController::crear() |
| CP-GSO-02 | Eliminar servicio exitoso | Ver GUIA_MIGRACION.md - ServiciosController::eliminar() |
| CP-GSO-03 | Cancelar operación | Response::json() en controlador |
| CP-GSO-04 | Nombre vacío | FormValidator::required('nombre') |
| CP-GSO-05 | Nombre duplicado | ServiciosController::existeServicio() |
| CP-GSO-06 | Eliminar con citas activas | ServiciosController::contarCitasActivas() |

**Para Implementar**:
1. Copiar código de ejemplo de GUIA_MIGRACION.md
2. Crear `src/Controllers/ServiciosController.php`
3. Crear `tests/Integration/ServiciosControllerTest.php`
4. Agregar rutas en `public/api.php`

---

## 3. REALIZAR CONSULTAS AVANZADAS

### Estado: 60% Implementado

| Funcionalidad | Backend | Frontend | Validación |
|---------------|---------|----------|------------|
| Búsqueda básica | ✅ api.php:169 | ✅ | ⚠️ Parcial |
| Filtros | ✅ api.php:169 | ✅ | ⚠️ Parcial |
| Validación 3+ caracteres | ❌ | ❌ | ❌ |
| Validación caracteres especiales | ❌ | ❌ | ❌ |

### Casos de Prueba

| ID | Descripción | Implementación Requerida |
|----|-------------|-------------------------|
| CP-CON-01 | Búsqueda con filtros | Ya implementado (api.php:169) |
| CP-CON-02 | Búsqueda sin filtros | Ya implementado (api.php:169) |
| CP-CON-03 | Limpiar filtros | Frontend + API |
| CP-CON-05 | Término inexistente | Ya maneja (devuelve array vacío) |
| CP-CON-06 | Término con pocos caracteres | **FALTA** - Agregar validación |
| CP-CON-07 | Caracteres no permitidos | **FALTA** - Agregar validación |
| CP-CON-08 | Pérdida de conexión | Frontend (manejo de errores) |

**Para Implementar**:
```php
// En ServiciosController::listar()
if (!empty($filtros['busqueda'])) {
    $busqueda = trim($filtros['busqueda']);

    // CP-CON-06: Mínimo 3 caracteres
    if (strlen($busqueda) < 3) {
        Response::error('El término de búsqueda debe tener al menos 3 caracteres', 400);
    }

    // CP-CON-07: Solo alfanuméricos y espacios
    if (!preg_match('/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ]+$/u', $busqueda)) {
        Response::error('Solo se permiten letras, números y espacios en la búsqueda', 400);
    }

    // ... continuar con búsqueda
}
```

---

## 4. GENERAR ESTADÍSTICAS

### Estado: 90% Backend, 0% PDF/Excel Real

| Funcionalidad | Estado | Ubicación |
|---------------|--------|-----------|
| Generar estadísticas | ✅ | api.php:626 |
| Filtros (fecha, categoría) | ✅ | api.php:626 |
| Exportar PDF | ⚠️ Simulado | api.php:684 |
| Exportar Excel | ⚠️ Simulado | api.php:696 |

### Casos de Prueba

| ID | Descripción | Estado | Implementación |
|----|-------------|--------|----------------|
| CP-EST-01 | Generar exitosamente | ✅ | api.php:626-682 |
| CP-EST-02 | Generar con filtros mínimos | ✅ | Ya soportado |
| CP-EST-04 | Error generación PDF | ⚠️ | Simulado (línea 684) |
| CP-EST-05 | Campos obligatorios incompletos | ❌ | **FALTA** validación |

**Para Implementar PDF/Excel Real**:

```bash
# Instalar dependencias
composer require tecnickcom/tcpdf
composer require phpoffice/phpspreadsheet
```

```php
// src/Controllers/EstadisticasController.php
public function exportarPDF(array $data): never {
    $validator = new FormValidator($data);
    $validator
        ->required('fecha_inicio')
        ->required('fecha_fin')
        ->required('categoria_id');

    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }

    try {
        // Generar estadísticas
        $stats = $this->generar($data);

        // Crear PDF con TCPDF
        $pdf = new \TCPDF();
        $pdf->AddPage();
        $pdf->writeHTML($this->generarHTMLReporte($stats));

        // Descargar
        $pdf->Output('reporte.pdf', 'D');
    } catch (\Exception $e) {
        Response::serverError('Error al generar PDF', $e);
    }
}
```

---

## 5. PROGRAMAR CITAS

### Estado: 100% Backend Legacy

| Funcionalidad | Backend | Tests |
|---------------|---------|-------|
| Crear cita | ✅ api.php:283 | ❌ |
| Validar fecha pasada | ✅ api.php:288 | ❌ |
| Verificar disponibilidad | ✅ api.php:300 | ❌ |
| Cancelar cita | ✅ api.php:325 | ❌ |

### Casos de Prueba

| ID | Descripción | Validación Existente |
|----|-------------|---------------------|
| CP-CIT-01 | Reservar cita exitosa | api.php:283-323 |
| CP-CIT-02 | Error horario no disponible | api.php:304-306 |
| CP-CIT-03 | Información incompleta | FormValidator (por implementar) |
| CP-CIT-04 | Fecha pasada | api.php:288-290 |
| CP-CIT-05 | Modificar cita | **FALTA** endpoint |
| CP-CIT-06 | Cancelar cita | api.php:325-333 |

**Para Migrar a Controlador**:
```php
// src/Controllers/CitasController.php
public function crear(array $data): never {
    // Validar
    $validator = new FormValidator($data);
    $validator
        ->required('servicio_id')
        ->numeric('servicio_id')
        ->required('fecha')
        ->date('fecha')
        ->dateNotPast('fecha')  // CP-CIT-04
        ->required('hora')
        ->required('motivo');

    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }

    // Verificar disponibilidad (CP-CIT-02)
    if ($this->horarioOcupado($data['fecha'], $data['hora'])) {
        Response::error('El horario seleccionado ya no está disponible', 409);
    }

    // Crear cita
    // ...
}
```

---

## 6. GESTIONAR AGENDA

### Casos de Prueba Clave

| ID | Descripción | Implementación |
|----|-------------|----------------|
| CP-GAA-01 | Visualizar agenda | api.php:490 |
| CP-GAA-02 | Bloquear horarios | api.php:457 |
| CP-GAA-03 | Reagendar citas | **FALTA** |
| CP-GAA-04 | Intento programar en bloqueado | Validar en crear_cita |
| CP-GAA-05 | Reagendar con conflicto | **FALTA** |
| CP-GAA-06 | Intento bloquear con citas | api.php:462-472 |

---

## 7. SOLICITAR SERVICIOS

### Funcionalidades Faltantes

| Funcionalidad | Estado | Implementación |
|---------------|--------|----------------|
| Crear solicitud básica | ✅ | api.php:526 |
| Guardar borrador | ✅ | api.php:607 |
| **Upload documentos** | ⚠️ | FileUploader creado, falta integrar |
| Validar formatos | ⚠️ | FileUploader::validate() |
| Límite solicitudes | ✅ | api.php:532 |

### Casos de Prueba

| ID | Descripción | Para Implementar |
|----|-------------|------------------|
| CP-SOL-01 | Solicitud exitosa | Ya existe (api.php:526) |
| CP-SOL-02 | **Múltiples documentos** | Usar FileUploader::uploadMultiple() |
| CP-SOL-03 | Guardar borrador | Ya existe (api.php:607) |
| CP-SOL-04 | **Documentación incompleta** | Validar archivos requeridos |
| CP-SOL-05 | **Formato no permitido** | FileUploader ya valida |
| CP-SOL-06 | Límite alcanzado | Ya existe (api.php:532) |

**Implementación Requerida**:
```php
// src/Controllers/SolicitudesController.php
public function crear(array $data): never {
    // ... validaciones básicas

    // Upload documentos (CP-SOL-02, CP-SOL-04, CP-SOL-05)
    $uploader = new FileUploader();

    if (!empty($_FILES['documentos'])) {
        $result = $uploader->uploadMultiple($_FILES['documentos']);

        if (!empty($result['errors'])) {
            Response::error('Error en documentos', 400, $result['errors']);
        }

        // Asociar documentos a solicitud
        $data['documentos_paths'] = $result['uploaded'];
    }

    // Validar documentos obligatorios (CP-SOL-04)
    if (empty($data['documentos_paths'])) {
        Response::error('Debe adjuntar al menos un documento', 400);
    }

    // Crear solicitud
    // ...
}
```

---

## 8. GESTIONAR PERMISOS Y ROLES

### Estado: 80% Implementado

| Funcionalidad | Backend | Tests |
|---------------|---------|-------|
| Listar roles | ✅ api.php:707 | ❌ |
| Asignar rol | ✅ api.php:719 | ❌ |
| Prevenir auto-eliminación | ✅ api.php:725 | ❌ |
| **Crear rol** | ❌ | ❌ |
| **Eliminar rol** | ❌ | ❌ |
| **Validar con UTP** | ❌ | ❌ |

### Casos de Prueba Pendientes

| ID | Descripción | Para Implementar |
|----|-------------|------------------|
| CP-GPR-01 | Crear rol | Nuevo endpoint |
| CP-GPR-02 | Modificar permisos | Nuevo endpoint |
| CP-GPR-03 | Asignar rol | Ya existe (api.php:719) |
| CP-GPR-04 | Eliminar rol con usuarios | Validación + nuevo endpoint |
| CP-GPR-05 | Validar categoría UTP | Integración con sistema UTP |
| CP-GPR-06 | Auto-eliminación privilegios | Ya existe (api.php:725) |

---

## RESUMEN DE IMPLEMENTACIÓN

### Completado ✅
- Autenticación (100%)
- Validadores centralizados (100%)
- Utilidades (Response, FileUploader) (100%)
- Auditoría (100%)

### En Progreso ⚠️
- Servicios (ejemplo completo en GUIA)
- Consultas avanzadas (validaciones frontend)
- Estadísticas (falta PDF/Excel real)
- Solicitudes (falta integrar FileUploader)

### Pendiente ❌
- Migración completa de todos los módulos
- Tests de integración
- Notificaciones por email
- Generación real de reportes
- Recordatorios automáticos

---

## CÓMO USAR ESTE DOCUMENTO

1. **Para Implementar un Caso de Prueba**:
   - Buscar el ID (ej: CP-ING-01)
   - Ver ubicación del código
   - Revisar ejemplo de implementación
   - Crear test correspondiente

2. **Para Crear Tests**:
   - Ver sección "Tests a Crear"
   - Usar como template los existentes en `tests/Unit/Validators/`
   - Seguir naming: `test_CP_XXX_YY_descripcion()`

3. **Para Migrar Funcionalidad**:
   - Ver GUIA_MIGRACION.md para patrón completo
   - Crear controlador siguiendo ejemplo
   - Implementar validaciones con validadores existentes
   - Crear tests ANTES de implementar (TDD)

---

**Última Actualización**: Diciembre 2025
