<?php
/**
 * Controlador de Autenticación
 *
 * Maneja login, registro, logout y recuperación de contraseña
 *
 * @package GADBE\Controllers
 */

namespace GADBE\Controllers;

use PDO;
use GADBE\Utils\Response;
use GADBE\Validators\EmailValidator;
use GADBE\Validators\PasswordValidator;
use GADBE\Validators\FormValidator;
use GADBE\Services\AuditoriaService;

class AuthController {
    private PDO $db;
    private AuditoriaService $auditoria;

    /**
     * Constructor
     *
     * @param PDO $db Conexión a base de datos
     */
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->auditoria = new AuditoriaService($db);
    }

    /**
     * Inicia sesión de usuario
     *
     * Caso de Prueba: CP-ING-01 (Exitoso), CP-ING-02 (Fallido)
     *
     * @param array $data Datos del formulario
     * @return never
     */
    public function login(array $data): never {
        // Validar entrada
        $validator = new FormValidator($data);
        $validator
            ->required('correo', 'El correo es obligatorio')
            ->email('correo')
            ->required('password', 'La contraseña es obligatoria');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        $correo = EmailValidator::normalize($data['correo']);
        $password = $data['password'];

        // Obtener usuario
        $usuario = $this->obtenerUsuarioPorCorreo($correo);

        if (!$usuario) {
            $this->auditoria->registrarLoginFallido($correo, 'Usuario no encontrado');
            Response::error('Credenciales UTP inválidas');
        }

        // Verificar si la cuenta está bloqueada
        if ($this->estaBloqueada($usuario)) {
            $minutos = $this->minutosRestantesBloq ueo($usuario['bloqueado_hasta']);
            Response::error("Cuenta bloqueada por $minutos minutos. Intente más tarde.");
        }

        // Verificar contraseña
        if (!PasswordValidator::verify($password, $usuario['password_hash'])) {
            $this->manejarLoginFallido($usuario, $correo);
        }

        // Login exitoso
        $this->resetearIntentosFallidos($usuario['id']);
        $this->iniciarSesion($usuario);
        $this->auditoria->registrarLogin($usuario['id'], $usuario['correo']);

        Response::success([
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'correo' => $usuario['correo'],
                'rol' => $usuario['rol']
            ]
        ], 'Inicio de sesión exitoso');
    }

    /**
     * Registra un nuevo usuario
     *
     * Caso de Prueba: CP-ING-02 (Registro exitoso)
     *
     * @param array $data Datos del formulario
     * @return never
     */
    public function registrar(array $data): never {
        // Validar entrada
        $validator = new FormValidator($data);
        $validator
            ->required('nombre', 'El nombre es obligatorio')
            ->required('correo', 'El correo es obligatorio')
            ->email('correo')
            ->required('password', 'La contraseña es obligatoria')
            ->minLength('password', PASSWORD_MIN_LENGTH, 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres')
            ->required('password_confirm', 'La confirmación de contraseña es obligatoria')
            ->matches('password', 'password_confirm', 'Las contraseñas no coinciden');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        $correo = EmailValidator::normalize($data['correo']);

        // Verificar si el correo ya existe (CP-ING-08)
        if ($this->existeCorreo($correo)) {
            Response::error('Este correo ya está registrado. Use otro o recupere su contraseña', 400);
        }

        // Crear usuario
        try {
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (correo, password_hash, nombre, rol, activo)
                VALUES (?, ?, ?, 'Estudiante', 1)
            ");

            $hash = PasswordValidator::hash($data['password']);
            $stmt->execute([$correo, $hash, trim($data['nombre'])]);

            $usuarioId = (int)$this->db->lastInsertId();
            $this->auditoria->registrar($usuarioId, 'registro', 'usuarios', $usuarioId);

            Response::success([
                'id' => $usuarioId
            ], 'Cuenta creada exitosamente. Ya puede iniciar sesión.', 201);
        } catch (\PDOException $e) {
            error_log("Error al registrar usuario: " . $e->getMessage());
            Response::serverError('Error al crear la cuenta. Intente nuevamente.');
        }
    }

    /**
     * Cierra sesión del usuario
     *
     * @return never
     */
    public function logout(): never {
        if (isset($_SESSION['user_id'])) {
            $this->auditoria->registrar($_SESSION['user_id'], 'logout', 'usuarios', $_SESSION['user_id']);
        }

        session_destroy();
        Response::success(null, 'Sesión cerrada exitosamente');
    }

    /**
     * Verifica si hay una sesión activa
     *
     * @return never
     */
    public function verificarSesion(): never {
        if (!isset($_SESSION['user_id'])) {
            Response::json(['authenticated' => false]);
        }

        Response::success([
            'authenticated' => true,
            'usuario' => [
                'id' => $_SESSION['user_id'],
                'nombre' => $_SESSION['user_nombre'],
                'correo' => $_SESSION['user_correo'],
                'rol' => $_SESSION['user_rol']
            ]
        ]);
    }

    /**
     * Solicita recuperación de contraseña
     *
     * Caso de Prueba: CP-ING-10
     *
     * @param array $data Datos del formulario
     * @return never
     */
    public function solicitarRecuperacion(array $data): never {
        $validator = new FormValidator($data);
        $validator->required('correo')->email('correo');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        $correo = EmailValidator::normalize($data['correo']);
        $usuario = $this->obtenerUsuarioPorCorreo($correo);

        // Por seguridad, siempre devolvemos el mismo mensaje
        // Esto previene enumerar usuarios válidos
        if (!$usuario) {
            Response::success(null, 'Si el correo existe, recibirá un enlace de recuperación');
        }

        // Generar token de recuperación
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        try {
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (usuario_id, token, expira_en)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = ?, expira_en = ?
            ");

            $stmt->execute([$usuario['id'], $token, $expira, $token, $expira]);

            // TODO: Enviar email con enlace de recuperación
            // Para implementar en NotificationService
            $enlaceRecuperacion = APP_URL . "/recuperar-password?token=" . $token;

            // Log temporal (en producción se enviaría por email)
            if (APP_DEBUG) {
                logInfo("Enlace de recuperación para {$usuario['correo']}: $enlaceRecuperacion");
            }

            $this->auditoria->registrar($usuario['id'], 'solicitar_recuperacion_password', 'usuarios', $usuario['id']);

            Response::success(null, 'Si el correo existe, recibirá un enlace de recuperación');
        } catch (\PDOException $e) {
            error_log("Error al generar token de recuperación: " . $e->getMessage());
            Response::serverError('Error al procesar la solicitud');
        }
    }

    /**
     * Restablece la contraseña usando un token
     *
     * @param array $data Datos del formulario
     * @return never
     */
    public function restablecerPassword(array $data): never {
        $validator = new FormValidator($data);
        $validator
            ->required('token', 'Token de recuperación requerido')
            ->required('password', 'La nueva contraseña es obligatoria')
            ->minLength('password', PASSWORD_MIN_LENGTH)
            ->required('password_confirm')
            ->matches('password', 'password_confirm');

        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }

        // Verificar token
        $stmt = $this->db->prepare("
            SELECT pr.*, u.id as usuario_id, u.correo
            FROM password_resets pr
            JOIN usuarios u ON pr.usuario_id = u.id
            WHERE pr.token = ? AND pr.expira_en > NOW() AND pr.usado = 0
        ");

        $stmt->execute([$data['token']]);
        $reset = $stmt->fetch();

        if (!$reset) {
            Response::error('Token inválido o expirado', 400);
        }

        // Actualizar contraseña
        try {
            $hash = PasswordValidator::hash($data['password']);

            $this->db->beginTransaction();

            // Actualizar password
            $stmt = $this->db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $reset['usuario_id']]);

            // Marcar token como usado
            $stmt = $this->db->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?");
            $stmt->execute([$reset['id']]);

            $this->db->commit();

            $this->auditoria->registrar($reset['usuario_id'], 'restablecer_password', 'usuarios', $reset['usuario_id']);

            Response::success(null, 'Contraseña restablecida exitosamente. Ya puede iniciar sesión');
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("Error al restablecer contraseña: " . $e->getMessage());
            Response::serverError('Error al restablecer la contraseña');
        }
    }

    // ========== MÉTODOS PRIVADOS ==========

    /**
     * Obtiene usuario por correo
     *
     * @param string $correo Correo del usuario
     * @return array|false
     */
    private function obtenerUsuarioPorCorreo(string $correo) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1");
        $stmt->execute([$correo]);
        return $stmt->fetch();
    }

    /**
     * Verifica si un correo ya existe
     *
     * @param string $correo Correo a verificar
     * @return bool
     */
    private function existeCorreo(string $correo): bool {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        return $stmt->fetch() !== false;
    }

    /**
     * Verifica si una cuenta está bloqueada
     *
     * @param array $usuario Datos del usuario
     * @return bool
     */
    private function estaBloqueada(array $usuario): bool {
        return $usuario['bloqueado_hasta'] && strtotime($usuario['bloqueado_hasta']) > time();
    }

    /**
     * Calcula minutos restantes de bloqueo
     *
     * @param string $bloqueadoHasta Timestamp de fin de bloqueo
     * @return int
     */
    private function minutosRestantesBloqueo(string $bloqueadoHasta): int {
        return (int)ceil((strtotime($bloqueadoHasta) - time()) / 60);
    }

    /**
     * Maneja un intento de login fallido
     *
     * Caso de Prueba: CP-ING-04 (Bloqueo por intentos)
     *
     * @param array $usuario Datos del usuario
     * @param string $correo Correo usado
     * @return never
     */
    private function manejarLoginFallido(array $usuario, string $correo): never {
        $intentos = $usuario['intentos_fallidos'] + 1;

        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
            // Bloquear cuenta
            $bloqueadoHasta = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);

            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET intentos_fallidos = ?, bloqueado_hasta = ?
                WHERE id = ?
            ");

            $stmt->execute([$intentos, $bloqueadoHasta, $usuario['id']]);

            $this->auditoria->registrarLoginFallido($correo, 'Cuenta bloqueada por múltiples intentos');

            Response::error('Cuenta bloqueada por 30 minutos por seguridad', 403);
        }

        // Incrementar intentos
        $stmt = $this->db->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?");
        $stmt->execute([$intentos, $usuario['id']]);

        $intentosRestantes = MAX_LOGIN_ATTEMPTS - $intentos;
        $this->auditoria->registrarLoginFallido($correo, 'Contraseña incorrecta');

        Response::error("Credenciales incorrectas. Intentos restantes: $intentosRestantes", 401);
    }

    /**
     * Resetea los intentos fallidos de login
     *
     * @param int $usuarioId ID del usuario
     * @return void
     */
    private function resetearIntentosFallidos(int $usuarioId): void {
        $stmt = $this->db->prepare("
            UPDATE usuarios
            SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultima_sesion = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$usuarioId]);
    }

    /**
     * Inicia la sesión del usuario
     *
     * @param array $usuario Datos del usuario
     * @return void
     */
    private function iniciarSesion(array $usuario): void {
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_rol'] = $usuario['rol'];
        $_SESSION['user_nombre'] = $usuario['nombre'];
        $_SESSION['user_correo'] = $usuario['correo'];
        $_SESSION['last_activity'] = time();
    }
}
