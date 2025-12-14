<?php
/**
 * Clase para manejo centralizado de respuestas HTTP
 *
 * @package GADBE\Utils
 */

namespace GADBE\Utils;

class Response {
    /**
     * Envía una respuesta JSON exitosa
     *
     * @param mixed $data Datos a devolver
     * @param string|null $message Mensaje opcional
     * @param int $httpCode Código HTTP (default: 200)
     * @return never
     */
    public static function success($data = null, ?string $message = null, int $httpCode = 200): never {
        http_response_code($httpCode);

        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envía una respuesta JSON de error
     *
     * @param string $message Mensaje de error
     * @param int $httpCode Código HTTP (default: 400)
     * @param array $errors Detalles de errores
     * @return never
     */
    public static function error(string $message, int $httpCode = 400, array $errors = []): never {
        http_response_code($httpCode);

        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        // Log de error en desarrollo
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log("API Error ($httpCode): $message");
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envía respuesta de error de validación
     *
     * @param array $errors Errores de validación
     * @return never
     */
    public static function validationError(array $errors): never {
        self::error('Errores de validación', 422, $errors);
    }

    /**
     * Envía respuesta de no autorizado
     *
     * @param string $message Mensaje
     * @return never
     */
    public static function unauthorized(string $message = 'No autorizado'): never {
        self::error($message, 401);
    }

    /**
     * Envía respuesta de prohibido
     *
     * @param string $message Mensaje
     * @return never
     */
    public static function forbidden(string $message = 'Sin permisos para esta acción'): never {
        self::error($message, 403);
    }

    /**
     * Envía respuesta de no encontrado
     *
     * @param string $message Mensaje
     * @return never
     */
    public static function notFound(string $message = 'Recurso no encontrado'): never {
        self::error($message, 404);
    }

    /**
     * Envía respuesta de error del servidor
     *
     * @param string $message Mensaje
     * @param \Exception|null $e Excepción para logging
     * @return never
     */
    public static function serverError(string $message = 'Error interno del servidor', ?\Exception $e = null): never {
        if ($e !== null) {
            error_log("Server Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        // No revelar detalles internos en producción
        $publicMessage = (defined('APP_DEBUG') && APP_DEBUG && $e !== null)
            ? $message . ': ' . $e->getMessage()
            : $message;

        self::error($publicMessage, 500);
    }

    /**
     * Envía respuesta JSON genérica
     *
     * @param array $data Datos de respuesta
     * @param int $httpCode Código HTTP
     * @return never
     */
    public static function json(array $data, int $httpCode = 200): never {
        http_response_code($httpCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
