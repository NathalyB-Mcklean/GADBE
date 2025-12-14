# Análisis de Cobertura de Casos de Uso - Sistema GADBE

## Fecha: 2025-12-14

---

## 1. CASOS DE USO IMPLEMENTADOS

### ✅ Ingresar al Sistema (100% implementado)
- **Login** (`/api.php?action=login`)
  - ✅ Validación correo institucional UTP (@utp.ac.pa)
  - ✅ Verificación de contraseña con password_verify
  - ✅ Control de intentos fallidos (5 intentos)
  - ✅ Bloqueo temporal de cuenta (30 minutos)
  - ✅ Registro de auditoría
  - ✅ Gestión de sesiones

- **Registro** (`/api.php?action=registrar`)
  - ✅ Validación correo institucional
  - ✅ Validación contraseña (mínimo 8 caracteres)
  - ✅ Verificación duplicados
  - ✅ Hash de contraseñas

- **Logout** (`/api.php?action=logout`)
  - ✅ Cierre de sesión
  - ✅ Registro de auditoría

### ✅ Gestionar Servicios y Ofertas (100% implementado)
- **Listar** (`/api.php?action=listar_servicios`)
  - ✅ Filtros por tipo, categoría, estado
  - ✅ Búsqueda por texto

- **Crear** (`/api.php?action=crear_servicio`)
  - ✅ Validación de duplicados
  - ✅ Control de permisos (Trabajadora Social/Administrador)
  - ✅ Auditoría

- **Eliminar** (`/api.php?action=eliminar_servicio`)
  - ✅ Verificación de citas activas
  - ✅ Control de permisos

### ✅ Programar Citas (100% implementado)
- **Crear Cita** (`/api.php?action=crear_cita`)
  - ✅ Validación fecha pasada
  - ✅ Verificación disponibilidad de horario
  - ✅ Prevención conflictos de horarios
  - ✅ Asignación automática trabajadora social

- **Cancelar Cita** (`/api.php?action=cancelar_cita`)
  - ✅ Solo estudiante propietario puede cancelar
  - ✅ Auditoría

- **Listar Citas** (`/api.php?action=listar_citas`)
  - ✅ Filtrado por rol (Estudiante/Trabajadora Social)

### ✅ Gestionar Agenda de Atención (90% implementado)
- **Horarios Disponibles**
  - ✅ Crear horarios (`crear_horario`)
  - ✅ Listar horarios (`listar_horarios`)
  - ✅ Eliminar horarios (`eliminar_horario`)

- **Bloqueos**
  - ✅ Bloquear horarios (`bloquear_horario`)
  - ✅ Verificación citas programadas antes de bloquear
  - ✅ Obtener agenda completa (`obtener_agenda`)

- **Faltante:**
  - ⚠️ Reasignación masiva de citas
  - ⚠️ Recordatorios automáticos 24h antes
  - ⚠️ Confirmación de asistencia

### ✅ Solicitar Servicios (90% implementado)
- **Crear Solicitud** (`/api.php?action=crear_solicitud`)
  - ✅ Límite de 3 solicitudes activas
  - ✅ Registro de solicitud

- **Guardar Borrador** (`/api.php?action=guardar_borrador`)
  - ✅ Implementado

- **Listar Solicitudes** (`/api.php?action=listar_solicitudes`)
  - ✅ Filtrado por rol

- **Faltante:**
  - ❌ Sistema de carga de documentos adjuntos
  - ❌ Validación de formatos de documentos (PDF, JPG, PNG)
  - ❌ Validación de tamaño de archivos

### ✅ Gestión de Solicitudes (100% implementado)
- **Gestionar Solicitud** (`/api.php?action=gestionar_solicitud`)
  - ✅ Aprobar solicitudes
  - ✅ Rechazar solicitudes con justificación
  - ✅ Solicitar información adicional
  - ✅ Control de permisos
  - ✅ Auditoría

### ⚠️ Realizar Evaluaciones de Satisfacción (80% implementado)
- **Crear Evaluación** (`/api.php?action=crear_evaluacion`)
  - ✅ Registro de evaluación con calificación y comentario

- **Listar Evaluaciones** (`/api.php?action=listar_evaluaciones`)
  - ✅ Solo para Trabajadora Social/Administrador

- **Estadísticas** (`/api.php?action=estadisticas_evaluaciones`)
  - ✅ Cálculo de promedio
  - ✅ Conteo total

- **Faltante:**
  - ❌ Sistema de múltiples preguntas por encuesta
  - ❌ Tipos de preguntas (escala, selección múltiple, texto abierto)
  - ❌ Validación de preguntas obligatorias
  - ❌ Guardado de borrador de evaluación
  - ❌ Modificar respuestas antes de envío

### ⚠️ Realizar Consultas Avanzadas (60% implementado)
- **Búsqueda Básica**
  - ✅ Búsqueda por texto en servicios
  - ✅ Filtros básicos (tipo, categoría, estado)

- **Faltante:**
  - ❌ Validación mínimo 3 caracteres en frontend
  - ❌ Validación caracteres especiales
  - ❌ Mensaje específico "No se encontraron resultados"
  - ❌ Sugerencias de ajuste de filtros
  - ❌ Limpieza de filtros activos

### ✅ Generar Estadísticas (90% implementado)
- **Generar Estadísticas** (`/api.php?action=generar_estadisticas`)
  - ✅ Filtros por fecha
  - ✅ Filtros por categoría
  - ✅ Estadísticas de citas (total, completadas, canceladas, tasa de éxito)
  - ✅ Estadísticas de evaluaciones (promedio)

- **Exportar**
  - ⚠️ PDF (`exportar_pdf`) - Implementación básica, necesita librería real
  - ⚠️ Excel (`exportar_excel`) - Implementación básica, necesita librería real

- **Faltante:**
  - ❌ Validación campos obligatorios en frontend
  - ❌ Generación real de PDF (usar TCPDF o Dompdf)
  - ❌ Generación real de Excel (usar PhpSpreadsheet)
  - ❌ Gráficos visuales (barras, líneas, circular)

### ✅ Gestionar Permisos y Roles (80% implementado)
- **Listar Roles y Permisos** (`/api.php?action=listar_roles_permisos`)
  - ✅ Implementado

- **Asignar Rol** (`/api.php?action=asignar_rol`)
  - ✅ Implementado
  - ✅ Prevención auto-eliminación de privilegios

- **Faltante:**
  - ❌ Crear nuevo rol
  - ❌ Editar rol existente
  - ❌ Eliminar rol (con verificación de usuarios asignados)
  - ❌ Validación con sistema UTP (categoría empleado/estudiante)
  - ❌ Historial de cambios de permisos

---

## 2. PROBLEMAS DETECTADOS EN EL CÓDIGO ACTUAL

### 🔴 Seguridad (SonarQube Critical)
1. **Credenciales en código** (líneas 17-22)
   ```php
   $db_config = [
       'host' => 'localhost',
       'db' => 'bienestar_estudiantil',
       'user' => 'root',
       'pass' => ''  // ⚠️ Hardcoded, debería estar en archivo .env
   ];
   ```

2. **SQL Injection potencial** - Aunque se usan prepared statements, falta validación de entrada en varios lugares

3. **CORS permisivo** (línea 6)
   ```php
   header('Access-Control-Allow-Origin: *');  // ⚠️ Muy permisivo
   ```

4. **Falta validación de tipos** en datos de entrada

### 🟡 Code Smells (SonarQube Major)
1. **Archivo monolítico** - 740 líneas en un solo archivo
2. **Violación de Single Responsibility Principle** - Un archivo hace TODO
3. **Código duplicado** - Validaciones repetidas
4. **Sin separación de capas** - Lógica de negocio mezclada con acceso a datos
5. **Sin manejo centralizado de errores**
6. **Sin logging estructurado**
7. **Funciones muy largas** (algunas con 50+ líneas)

### 🔵 Mantenibilidad
1. **Sin namespaces ni autoloading**
2. **Sin documentación PHPDoc**
3. **Nombres de variables poco descriptivos** en algunos lugares
4. **Sin constantes para valores mágicos**
5. **Sin validadores reutilizables**

### 🟢 Testing
1. **Sin tests unitarios**
2. **Sin tests de integración**
3. **Sin cobertura de código**

---

## 3. FUNCIONALIDADES FALTANTES SEGÚN DOCUMENTO

### ❌ No Implementadas Completamente

1. **Sistema de Documentos Adjuntos** (CP-SOL-02, CP-SOL-04, CP-SOL-05)
   - Carga de archivos
   - Validación de formatos (PDF, JPG, PNG)
   - Validación de tamaño
   - Almacenamiento seguro
   - Prevención de archivos corruptos

2. **Sistema de Encuestas Completo** (CP-EVAL)
   - Múltiples tipos de preguntas
   - Preguntas obligatorias vs opcionales
   - Guardado de borradores
   - Modificación antes de envío
   - Validación de formatos

3. **Recordatorios y Notificaciones**
   - Email automático 24h antes de citas
   - Confirmación de asistencia
   - Notificaciones de cambios en solicitudes

4. **Recuperación de Contraseña** (CP-ING-10)
   - Sistema de reset de contraseña
   - Envío de enlaces por email
   - Tokens de recuperación

5. **Gestión Avanzada de Roles**
   - Creación de roles personalizados
   - Edición de permisos por rol
   - Historial de auditoría de cambios
   - Validación con directorio UTP

6. **Exportación Real de Reportes**
   - Generación de PDF con gráficos
   - Generación de Excel con datos
   - Diferentes formatos de gráficos (barras, líneas, circular)

7. **Reasignación de Citas** (CP-GAA-03)
   - Reasignación masiva entre trabajadoras sociales
   - Notificación automática a estudiantes

---

## 4. CASOS DE PRUEBA FALTANTES

### Casos de Prueba Sin Implementación Backend Completa:

1. **CP-ING-10**: Recuperación de contraseña
2. **CP-SOL-02**: Solicitud con múltiples documentos
3. **CP-SOL-04**: Documentación incompleta
4. **CP-SOL-05**: Formato de documento no permitido
5. **CP-CON-06**: Término con pocos caracteres (validación frontend)
6. **CP-CON-07**: Término con caracteres especiales (validación frontend)
7. **CP-EST-04**: Error generación PDF real
8. **CP-EST-05**: Error generación Excel real
9. **CP-GPR-04**: Eliminar rol con usuarios asignados
10. **CP-GPR-05**: Validación con sistema UTP

---

## 5. RECOMENDACIONES DE REFACTORIZACIÓN

### Estructura Propuesta:

```
GADBE/
├── config/
│   ├── database.php          # Configuración DB (desde .env)
│   └── config.php             # Configuraciones generales
├── src/
│   ├── Controllers/           # Controladores por módulo
│   │   ├── AuthController.php
│   │   ├── ServiciosController.php
│   │   ├── CitasController.php
│   │   ├── EvaluacionesController.php
│   │   ├── SolicitudesController.php
│   │   ├── EstadisticasController.php
│   │   └── RolesController.php
│   ├── Models/                # Modelos de datos
│   │   ├── Usuario.php
│   │   ├── Servicio.php
│   │   ├── Cita.php
│   │   ├── Evaluacion.php
│   │   └── Solicitud.php
│   ├── Validators/            # Validadores reutilizables
│   │   ├── EmailValidator.php
│   │   ├── PasswordValidator.php
│   │   ├── FileValidator.php
│   │   └── FormValidator.php
│   ├── Services/              # Lógica de negocio
│   │   ├── AuthService.php
│   │   ├── NotificationService.php
│   │   └── AuditoriaService.php
│   └── Utils/                 # Utilidades
│       ├── Response.php
│       ├── Logger.php
│       └── FileUploader.php
├── tests/                     # Tests unitarios y de integración
│   ├── AuthTest.php
│   ├── ServiciosTest.php
│   └── CitasTest.php
├── public/
│   ├── index.php              # Entry point
│   └── api.php                # Router principal
├── .env.example               # Ejemplo de variables de entorno
├── composer.json              # Dependencias
└── phpunit.xml                # Configuración tests
```

---

## 6. PRIORIDADES DE IMPLEMENTACIÓN

### 🔴 ALTA PRIORIDAD (Crítico para SonarQube y Seguridad)
1. Separar credenciales DB a archivo .env
2. Refactorizar api.php en múltiples controladores
3. Implementar validadores centralizados
4. Agregar manejo de excepciones
5. Implementar tests básicos

### 🟡 MEDIA PRIORIDAD (Funcionalidades Core)
6. Sistema de carga de documentos
7. Recuperación de contraseña
8. Recordatorios automáticos de citas
9. Exportación real de PDF/Excel
10. Gestión completa de roles

### 🟢 BAJA PRIORIDAD (Mejoras)
11. Sistema de encuestas completo
12. Reasignación masiva de citas
13. Dashboard con gráficos interactivos
14. Historial de auditoría completo

---

## RESUMEN EJECUTIVO

**Cobertura General:** ~75% de casos de uso implementados

**Puntos Críticos:**
- ✅ Autenticación y autorización: IMPLEMENTADO
- ✅ Gestión de servicios: IMPLEMENTADO
- ✅ Programación de citas: IMPLEMENTADO
- ⚠️ Sistema de documentos: FALTA IMPLEMENTAR
- ⚠️ Recuperación de contraseña: FALTA IMPLEMENTAR
- ⚠️ Exportación PDF/Excel: IMPLEMENTACIÓN BÁSICA
- ⚠️ Encuestas avanzadas: PARCIALMENTE IMPLEMENTADO

**Issues SonarQube Estimados:**
- 🔴 Critical: 3-5 (credenciales, CORS, SQL)
- 🟡 Major: 15-20 (code smells, complejidad)
- 🔵 Minor: 30-40 (nombres, documentación)
