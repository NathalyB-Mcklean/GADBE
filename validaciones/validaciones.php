<?php
/**
 * Archivo de Validaciones
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 * 
 * Contiene todas las funciones de validación para formularios y datos del sistema
 */

// ==============================================
// VALIDACIONES BÁSICAS
// ==============================================

/**
 * Validar que un campo no esté vacío
 * 
 * @param mixed $valor Valor a validar
 * @param string $campo Nombre del campo para mensaje de error
 * @return string Valor limpio
 * @throws Exception Si el valor está vacío
 */
function validarNoVacio($valor, $campo) {
    if (trim($valor) === '') {
        throw new Exception("El campo $campo es obligatorio.");
    }
    return trim($valor);
}

/**
 * Validar correo institucional UTP
 * 
 * @param string $correo Correo a validar
 * @return string Correo validado
 * @throws Exception Si el correo no es válido o no es institucional
 */
function validarCorreoUTP($correo) {
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo electrónico no es válido.");
    }
    if (!preg_match('/@utp\.ac\.pa$/', $correo)) {
        throw new Exception("Solo se permiten correos institucionales UTP (@utp.ac.pa).");
    }
    return strtolower(trim($correo));
}

/**
 * Validar contraseña segura
 * 
 * @param string $password Contraseña a validar
 * @return string Contraseña validada
 * @throws Exception Si la contraseña no cumple requisitos
 */
function validarPassword($password) {
    if (strlen($password) < 8) {
        throw new Exception("La contraseña debe tener al menos 8 caracteres.");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception("La contraseña debe tener al menos una letra mayúscula.");
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception("La contraseña debe tener al menos una letra minúscula.");
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception("La contraseña debe tener al menos un número.");
    }
    return $password;
}

/**
 * Validar coincidencia de contraseñas
 * 
 * @param string $password Primera contraseña
 * @param string $password2 Confirmación de contraseña
 * @throws Exception Si las contraseñas no coinciden
 */
function validarCoincidenciaPassword($password, $password2) {
    if ($password !== $password2) {
        throw new Exception("Las contraseñas no coinciden.");
    }
}

// ==============================================
// VALIDACIONES ESPECÍFICAS DEL SISTEMA
// ==============================================

/**
 * Validar formato de cédula panameña
 * 
 * @param string $cedula Cédula a validar
 * @return string Cédula validada o vacío si es opcional
 * @throws Exception Si el formato no es válido
 */
function validarFormatoCedula($cedula) {
    if (empty(trim($cedula))) {
        return ''; // La cédula es opcional
    }
    
    if (!preg_match('/^[0-9EP]{1,2}-[0-9]{1,4}-[0-9]{1,6}$/', $cedula)) {
        throw new Exception("La cédula debe tener el formato: 8-XXX-XXX o PE-XXX-XXXX");
    }
    return strtoupper(trim($cedula));
}

/**
 * Validar formato de teléfono panameño
 * 
 * @param string $telefono Teléfono a validar
 * @return string Teléfono validado o vacío si es opcional
 * @throws Exception Si el formato no es válido
 */
function validarFormatoTelefono($telefono) {
    if (empty(trim($telefono))) {
        return ''; // El teléfono es opcional
    }
    
    // Permitir formatos: XXXX-XXXX o +507-XXXX-XXXX
    if (!preg_match('/^(\+507-)?[0-9]{4}-[0-9]{4}$/', $telefono)) {
        throw new Exception("El teléfono debe tener el formato: XXXX-XXXX o +507-XXXX-XXXX");
    }
    return trim($telefono);
}

/**
 * Validar nombre (solo letras y espacios)
 * 
 * @param string $nombre Nombre a validar
 * @param string $campo Nombre del campo
 * @return string Nombre validado
 * @throws Exception Si contiene caracteres no válidos
 */
function validarNombre($nombre, $campo = "nombre") {
    $nombre = trim($nombre);
    if (empty($nombre)) {
        throw new Exception("El campo $campo es obligatorio.");
    }
    
    if (!preg_match('/^[a-záéíóúñA-ZÁÉÍÓÚÑ\s]+$/u', $nombre)) {
        throw new Exception("El $campo solo puede contener letras y espacios.");
    }
    
    if (strlen($nombre) < 2) {
        throw new Exception("El $campo debe tener al menos 2 caracteres.");
    }
    
    if (strlen($nombre) > 150) {
        throw new Exception("El $campo no puede exceder 150 caracteres.");
    }
    
    return ucwords(strtolower($nombre));
}

/**
 * Validar facultad
 * 
 * @param string $facultad Facultad a validar
 * @return string Facultad validada
 * @throws Exception Si está vacía
 */
function validarFacultad($facultad) {
    $facultad = trim($facultad);
    if (empty($facultad)) {
        throw new Exception("Debe seleccionar una facultad.");
    }
    return $facultad;
}

/**
 * Validar carrera
 * 
 * @param string $carrera Carrera a validar
 * @return string Carrera validada
 * @throws Exception Si está vacía
 */
function validarCarrera($carrera) {
    $carrera = trim($carrera);
    if (empty($carrera)) {
        throw new Exception("Debe especificar una carrera.");
    }
    
    if (strlen($carrera) < 3) {
        throw new Exception("La carrera debe tener al menos 3 caracteres.");
    }
    
    if (strlen($carrera) > 100) {
        throw new Exception("La carrera no puede exceder 100 caracteres.");
    }
    
    return ucwords(strtolower($carrera));
}

/**
 * Validar año de ingreso
 * 
 * @param int $año Año a validar
 * @return int Año validado
 * @throws Exception Si el año no es válido
 */
function validarAñoIngreso($año) {
    $año_actual = date('Y');
    $año = intval($año);
    
    if ($año < 1990) {
        throw new Exception("El año de ingreso no puede ser anterior a 1990.");
    }
    
    if ($año > $año_actual) {
        throw new Exception("El año de ingreso no puede ser futuro.");
    }
    
    return $año;
}

// ==============================================
// VALIDACIONES CON BASE DE DATOS
// ==============================================

/**
 * Validar unicidad de correo
 * 
 * @param string $correo Correo a verificar
 * @param PDO $conexion Conexión a la base de datos
 * @throws Exception Si el correo ya existe
 */
function validarUnicidadCorreo($correo, $conexion) {
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE correo_institucional = ?");
    $stmt->execute([strtolower(trim($correo))]);
    
    if ($stmt->fetch()) {
        throw new Exception("Ya existe una cuenta con ese correo institucional.");
    }
}

/**
 * Validar que el usuario existe y está activo
 * 
 * @param string $correo Correo del usuario
 * @param PDO $conexion Conexión a la base de datos
 * @return array|false Usuario encontrado o false
 */
function validarUsuarioExiste($correo, $conexion) {
    $stmt = $conexion->prepare("
        SELECT u.*, r.nombre_rol 
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE u.correo_institucional = ? AND u.activo = 1
    ");
    $stmt->execute([strtolower(trim($correo))]);
    return $stmt->fetch();
}

/**
 * Validar contraseña contra hash almacenado
 * 
 * @param string $password Contraseña ingresada
 * @param string $hash Hash almacenado en BD
 * @return bool True si coincide
 */
function validarPasswordHash($password, $hash) {
    return password_verify($password, $hash);
}

// ==============================================
// VALIDACIONES DE ARCHIVOS
// ==============================================

/**
 * Validar archivo subido
 * 
 * @param array $archivo Array $_FILES de un archivo
 * @param array $extensiones_permitidas Extensiones permitidas
 * @param int $tamaño_max Tamaño máximo en bytes
 * @return bool True si es válido
 * @throws Exception Si el archivo no es válido
 */
function validarArchivo($archivo, $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'], $tamaño_max = 5242880) {
    // Verificar que se subió un archivo
    if (!isset($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al subir el archivo.");
    }
    
    // Verificar tamaño
    if ($archivo['size'] > $tamaño_max) {
        $tamaño_mb = $tamaño_max / 1048576;
        throw new Exception("El archivo no puede superar los {$tamaño_mb}MB.");
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensiones_permitidas)) {
        $extensiones_str = implode(', ', $extensiones_permitidas);
        throw new Exception("Solo se permiten archivos: {$extensiones_str}");
    }
    
    // Verificar que es un archivo real (no manipulado)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png'
    ];
    
    if (!in_array($mime, $mimes_permitidos)) {
        throw new Exception("Tipo de archivo no permitido.");
    }
    
    return true;
}

// ==============================================
// VALIDACIONES DE FECHAS
// ==============================================

/**
 * Validar formato de fecha
 * 
 * @param string $fecha Fecha a validar
 * @param string $formato Formato esperado (por defecto Y-m-d)
 * @return string Fecha validada
 * @throws Exception Si la fecha no es válida
 */
function validarFecha($fecha, $formato = 'Y-m-d') {
    $d = DateTime::createFromFormat($formato, $fecha);
    if (!$d || $d->format($formato) !== $fecha) {
        throw new Exception("La fecha no es válida. Use el formato $formato.");
    }
    return $fecha;
}

/**
 * Validar que fecha de fin sea posterior a fecha de inicio
 * 
 * @param string $inicio Fecha de inicio
 * @param string $fin Fecha de fin
 * @throws Exception Si el rango es inválido
 */
function validarRangoFechas($inicio, $fin) {
    if (strtotime($fin) <= strtotime($inicio)) {
        throw new Exception("La fecha de fin debe ser posterior a la fecha de inicio.");
    }
}

/**
 * Validar que una fecha no sea pasada
 * 
 * @param string $fecha Fecha a validar
 * @throws Exception Si la fecha es pasada
 */
function validarFechaFutura($fecha) {
    if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
        throw new Exception("No se pueden programar citas en fechas pasadas.");
    }
}

// ==============================================
// SANITIZACIÓN
// ==============================================

/**
 * Sanitizar texto general
 * 
 * @param string $texto Texto a sanitizar
 * @return string Texto sanitizado
 */
function sanitizarTexto($texto) {
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizar número
 * 
 * @param mixed $numero Número a sanitizar
 * @return float Número sanitizado
 */
function sanitizarNumero($numero) {
    return filter_var($numero, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Sanitizar entero
 * 
 * @param mixed $entero Entero a sanitizar
 * @return int Entero sanitizado
 */
function sanitizarEntero($entero) {
    return filter_var($entero, FILTER_SANITIZE_NUMBER_INT);
}

// ==============================================
// VALIDACIONES DE CITAS
// ==============================================

/**
 * Validar horario dentro de rango laboral
 * 
 * @param string $hora Hora a validar (formato H:i)
 * @throws Exception Si está fuera del horario laboral
 */
function validarHorarioLaboral($hora) {
    $hora_obj = DateTime::createFromFormat('H:i', $hora);
    $inicio = DateTime::createFromFormat('H:i', '08:00');
    $fin = DateTime::createFromFormat('H:i', '17:00');
    
    if ($hora_obj < $inicio || $hora_obj > $fin) {
        throw new Exception("El horario debe estar entre 8:00 AM y 5:00 PM.");
    }
}

/**
 * Validar día laboral (lunes a viernes)
 * 
 * @param string $fecha Fecha a validar
 * @throws Exception Si es fin de semana
 */
function validarDiaLaboral($fecha) {
    $dia = date('N', strtotime($fecha)); // 1=lunes, 7=domingo
    
    if ($dia >= 6) {
        throw new Exception("No se pueden programar citas en fines de semana.");
    }
}

// ==============================================
// CLASE PARA NUEVOS DESARROLLOS (OPCIONAL)
// ==============================================

class Validaciones {
    
    // Métodos estáticos que encapsulan las funciones
    public static function noVacio($valor, $campo) {
        return validarNoVacio($valor, $campo);
    }
    
    public static function correoUTP($correo) {
        return validarCorreoUTP($correo);
    }
    
    public static function password($password) {
        return validarPassword($password);
    }
    
    public static function coincidenciaPassword($password, $password2) {
        return validarCoincidenciaPassword($password, $password2);
    }
    
    public static function cedula($cedula) {
        return validarFormatoCedula($cedula);
    }
    
    public static function telefono($telefono) {
        return validarFormatoTelefono($telefono);
    }
    
    public static function nombre($nombre, $campo = "nombre") {
        return validarNombre($nombre, $campo);
    }
    
    public static function sanitizar($texto) {
        return sanitizarTexto($texto);
    }
}

?>
