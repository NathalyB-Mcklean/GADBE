<?php
/**
 * Validador de emails institucionales UTP
 *
 * @package GADBE\Validators
 */

namespace GADBE\Validators;

class EmailValidator {
    private const UTP_DOMAIN = '@utp.ac.pa';

    /**
     * Valida formato de email
     *
     * @param string $email Email a validar
     * @return bool
     */
    public static function isValid(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida que sea un email institucional UTP
     *
     * @param string $email Email a validar
     * @return bool
     */
    public static function isInstitutional(string $email): bool {
        return self::isValid($email) && str_ends_with(strtolower($email), self::UTP_DOMAIN);
    }

    /**
     * Obtiene mensaje de error para email inválido
     *
     * @param string $email Email que falló
     * @return string
     */
    public static function getErrorMessage(string $email): string {
        if (!self::isValid($email)) {
            return 'Formato de correo electrónico inválido';
        }

        if (!self::isInstitutional($email)) {
            return 'Solo se permiten correos institucionales UTP (@utp.ac.pa)';
        }

        return '';
    }

    /**
     * Normaliza un email (convierte a minúsculas y trim)
     *
     * @param string $email Email a normalizar
     * @return string
     */
    public static function normalize(string $email): string {
        return strtolower(trim($email));
    }
}
