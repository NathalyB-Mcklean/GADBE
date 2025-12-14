<?php
/**
 * Validador genérico de formularios
 *
 * @package GADBE\Validators
 */

namespace GADBE\Validators;

class FormValidator {
    private array $errors = [];
    private array $data;

    /**
     * Constructor
     *
     * @param array $data Datos a validar
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Valida que un campo sea obligatorio
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function required(string $field, ?string $message = null): self {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field][] = $message ?? "El campo $field es obligatorio";
        }
        return $this;
    }

    /**
     * Valida email institucional
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function email(string $field, ?string $message = null): self {
        if (isset($this->data[$field])) {
            $email = EmailValidator::normalize($this->data[$field]);
            if (!EmailValidator::isInstitutional($email)) {
                $this->errors[$field][] = $message ?? EmailValidator::getErrorMessage($email);
            }
        }
        return $this;
    }

    /**
     * Valida longitud mínima
     *
     * @param string $field Nombre del campo
     * @param int $min Longitud mínima
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function minLength(string $field, int $min, ?string $message = null): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field][] = $message ?? "El campo $field debe tener al menos $min caracteres";
        }
        return $this;
    }

    /**
     * Valida longitud máxima
     *
     * @param string $field Nombre del campo
     * @param int $max Longitud máxima
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function maxLength(string $field, int $max, ?string $message = null): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field][] = $message ?? "El campo $field no puede exceder $max caracteres";
        }
        return $this;
    }

    /**
     * Valida que un campo sea numérico
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function numeric(string $field, ?string $message = null): self {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = $message ?? "El campo $field debe ser numérico";
        }
        return $this;
    }

    /**
     * Valida que un campo sea una fecha válida
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function date(string $field, ?string $message = null): self {
        if (isset($this->data[$field])) {
            $date = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$date || $date->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field][] = $message ?? "El campo $field no es una fecha válida (formato: YYYY-MM-DD)";
            }
        }
        return $this;
    }

    /**
     * Valida que una fecha no sea pasada
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function dateNotPast(string $field, ?string $message = null): self {
        if (isset($this->data[$field])) {
            $date = strtotime($this->data[$field]);
            $today = strtotime(date('Y-m-d'));

            if ($date < $today) {
                $this->errors[$field][] = $message ?? "No se pueden seleccionar fechas pasadas";
            }
        }
        return $this;
    }

    /**
     * Valida que un valor esté en una lista de opciones
     *
     * @param string $field Nombre del campo
     * @param array $options Opciones válidas
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function in(string $field, array $options, ?string $message = null): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $options, true)) {
            $this->errors[$field][] = $message ?? "El campo $field contiene un valor no válido";
        }
        return $this;
    }

    /**
     * Valida que dos campos coincidan
     *
     * @param string $field1 Primer campo
     * @param string $field2 Segundo campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function matches(string $field1, string $field2, ?string $message = null): self {
        if (isset($this->data[$field1], $this->data[$field2]) && $this->data[$field1] !== $this->data[$field2]) {
            $this->errors[$field1][] = $message ?? "Los campos no coinciden";
        }
        return $this;
    }

    /**
     * Valida con una función personalizada
     *
     * @param string $field Nombre del campo
     * @param callable $callback Función de validación
     * @param string $message Mensaje de error
     * @return self
     */
    public function custom(string $field, callable $callback, string $message): self {
        if (isset($this->data[$field]) && !$callback($this->data[$field])) {
            $this->errors[$field][] = $message;
        }
        return $this;
    }

    /**
     * Valida expresión regular
     *
     * @param string $field Nombre del campo
     * @param string $pattern Patrón regex
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function regex(string $field, string $pattern, ?string $message = null): self {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field][] = $message ?? "El campo $field no cumple con el formato requerido";
        }
        return $this;
    }

    /**
     * Valida que solo contenga letras, números y espacios
     *
     * @param string $field Nombre del campo
     * @param string|null $message Mensaje personalizado
     * @return self
     */
    public function alphanumericSpace(string $field, ?string $message = null): self {
        return $this->regex(
            $field,
            '/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ]+$/u',
            $message ?? "El campo $field solo puede contener letras, números y espacios"
        );
    }

    /**
     * Verifica si hay errores
     *
     * @return bool
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Verifica si la validación pasó
     *
     * @return bool
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Obtiene todos los errores
     *
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Obtiene errores de un campo específico
     *
     * @param string $field Nombre del campo
     * @return array
     */
    public function getFieldErrors(string $field): array {
        return $this->errors[$field] ?? [];
    }

    /**
     * Obtiene el primer error de todos los campos
     *
     * @return string
     */
    public function getFirstError(): string {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return '';
    }

    /**
     * Limpia y obtiene los datos validados
     *
     * @return array
     */
    public function validated(): array {
        return array_map(function($value) {
            return is_string($value) ? trim($value) : $value;
        }, $this->data);
    }

    /**
     * Sanitiza entrada de texto
     *
     * @param string $text Texto a sanitizar
     * @return string
     */
    public static function sanitize(string $text): string {
        return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitiza todos los datos de entrada
     *
     * @param array $data Datos a sanitizar
     * @return array
     */
    public static function sanitizeAll(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = self::sanitize($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeAll($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
