<?php
/**
 * Servicio para registro de auditoría
 *
 * @package GADBE\Services
 */

namespace GADBE\Services;

use PDO;

class AuditoriaService {
    private PDO $db;

    /**
     * Constructor
     *
     * @param PDO $db Conexión a base de datos
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Registra una acción de auditoría
     *
     * @param int $usuarioId ID del usuario que realiza la acción
     * @param string $accion Descripción de la acción
     * @param string|null $tabla Tabla afectada
     * @param int|null $registroId ID del registro afectado
     * @param array $detalles Detalles adicionales
     * @return bool
     */
    public function registrar(
        int $usuarioId,
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        array $detalles = []
    ): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, ip_address, detalles, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $usuarioId,
                $accion,
                $tabla,
                $registroId,
                $this->getClientIP(),
                !empty($detalles) ? json_encode($detalles) : null,
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            return true;
        } catch (\PDOException $e) {
            error_log("Error en auditoría: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra un login exitoso
     *
     * @param int $usuarioId ID del usuario
     * @param string $correo Correo del usuario
     * @return bool
     */
    public function registrarLogin(int $usuarioId, string $correo): bool {
        return $this->registrar($usuarioId, 'login', 'usuarios', $usuarioId, [
            'correo' => $correo,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Registra un intento de login fallido
     *
     * @param string $correo Correo usado en el intento
     * @param string $motivo Motivo del fallo
     * @return bool
     */
    public function registrarLoginFallido(string $correo, string $motivo): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO auditoria (usuario_id, accion, tabla_afectada, ip_address, detalles)
                VALUES (NULL, ?, ?, ?, ?)
            ");

            $stmt->execute([
                'login_fallido',
                'usuarios',
                $this->getClientIP(),
                json_encode([
                    'correo' => $correo,
                    'motivo' => $motivo,
                    'timestamp' => date('Y-m-d H:i:s')
                ])
            ]);

            return true;
        } catch (\PDOException $e) {
            error_log("Error registrando login fallido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el historial de auditoría de un usuario
     *
     * @param int $usuarioId ID del usuario
     * @param int $limit Límite de registros
     * @return array
     */
    public function obtenerHistorial(int $usuarioId, int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT * FROM auditoria
            WHERE usuario_id = ?
            ORDER BY fecha_hora DESC
            LIMIT ?
        ");

        $stmt->execute([$usuarioId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene IP del cliente de forma segura
     *
     * @return string
     */
    private function getClientIP(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
