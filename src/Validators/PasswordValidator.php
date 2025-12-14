<?php
/**
 * Validador de contraseñas
 *
 * @package GADBE\Validators
 */

namespace GADBE\Validators;

class PasswordValidator {
    private const MIN_LENGTH = PASSWORD_MIN_LENGTH ?? 8;
    private const MAX_LENGTH = 72; // Límite de bcrypt

    /**
     * Valida que una contraseña cumpla con los requisitos
     *
     * @param string $password Contraseña a validar
     * @return bool
     */
    public static function isValid(string $password): bool {
        $length = strlen($password);
        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    /**
     * Valida que una contraseña sea fuerte
     *
     * @param string $password Contraseña a validar
     * @return bool
     */
    public static function isStrong(string $password): bool {
        if (!self::isValid($password)) {
            return false;
        }

        // Al menos una mayúscula, una minúscula y un número
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);

        return $hasUppercase && $hasLowercase && $hasNumber;
    }

    /**
     * Genera un hash seguro de la contraseña
     *
     * @param string $password Contraseña a hashear
     * @return string
     */
    public static function hash(string $password): string {
        $cost = defined('BCRYPT_COST') ? BCRYPT_COST : 12;
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);
    }

    /**
     * Verifica una contraseña contra un hash
     *
     * @param string $password Contraseña en texto plano
     * @param string $hash Hash almacenado
     * @return bool
     */
    public static function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Verifica si un hash necesita ser rehashedo
     *
     * @param string $hash Hash a verificar
     * @return bool
     */
    public static function needsRehash(string $hash): bool {
        $cost = defined('BCRYPT_COST') ? BCRYPT_COST : 12;
        return password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => $cost]);
    }

    /**
     * Obtiene mensaje de error para contraseña inválida
     *
     * @param string $password Contraseña que falló
     * @param bool $requireStrong Si requiere contraseña fuerte
     * @return string
     */
    public static function getErrorMessage(string $password, bool $requireStrong = false): string {
        if (strlen($password) < self::MIN_LENGTH) {
            return "La contraseña debe tener al menos " . self::MIN_LENGTH . " caracteres";
        }

        if (strlen($password) > self::MAX_LENGTH) {
            return "La contraseña no puede exceder " . self::MAX_LENGTH . " caracteres";
        }

        if ($requireStrong && !self::isStrong($password)) {
            return "La contraseña debe contener al menos una mayúscula, una minúscula y un número";
        }

        return '';
    }

    /**
     * Valida que dos contraseñas coincidan
     *
     * @param string $password Primera contraseña
     * @param string $confirmation Confirmación de contraseña
     * @return bool
     */
    public static function matches(string $password, string $confirmation): bool {
        return $password === $confirmation;
    }
}
