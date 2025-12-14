<?php
/**
 * Tests para PasswordValidator
 *
 * Casos de Prueba: CP-ING-02, CP-ING-07
 *
 * @package GADBE\Tests\Unit\Validators
 */

namespace GADBE\Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use GADBE\Validators\PasswordValidator;

class PasswordValidatorTest extends TestCase {
    /**
     * @test
     * CP-ING-01: Contraseña válida
     */
    public function debe_validar_password_correcto(): void {
        $password = 'MiPassword123';

        $this->assertTrue(PasswordValidator::isValid($password));
        $this->assertTrue(PasswordValidator::isStrong($password));
    }

    /**
     * @test
     * CP-ING-07: Contraseña muy corta
     */
    public function debe_rechazar_password_muy_corto(): void {
        $password = 'Ab1';

        $this->assertFalse(PasswordValidator::isValid($password));
    }

    /**
     * @test
     * Contraseña sin cumplir requisitos de fortaleza
     */
    public function debe_rechazar_password_debil(): void {
        $passwords = [
            'soloMinusculas',     // Sin mayúsculas ni números
            'SOLOMAYUSCULAS',      // Sin minúsculas ni números
            '12345678',            // Solo números
            'SinNumeros'           // Sin números
        ];

        foreach ($passwords as $password) {
            $this->assertFalse(PasswordValidator::isStrong($password), "Password '$password' debería ser débil");
        }
    }

    /**
     * @test
     * Hash y verificación de contraseña
     */
    public function debe_hashear_y_verificar_password(): void {
        $password = 'TestPassword123';

        $hash = PasswordValidator::hash($password);

        $this->assertTrue(PasswordValidator::verify($password, $hash));
        $this->assertFalse(PasswordValidator::verify('PasswordIncorrecto', $hash));
    }

    /**
     * @test
     * CP-ING-07: Contraseñas que coinciden
     */
    public function debe_verificar_coincidencia_de_passwords(): void {
        $password = 'MiPassword123';
        $confirmation = 'MiPassword123';

        $this->assertTrue(PasswordValidator::matches($password, $confirmation));
    }

    /**
     * @test
     * CP-ING-07: Contraseñas que no coinciden
     */
    public function debe_detectar_passwords_que_no_coinciden(): void {
        $password = 'MiPassword123';
        $confirmation = 'MiPassword124';

        $this->assertFalse(PasswordValidator::matches($password, $confirmation));
    }

    /**
     * @test
     * Mensajes de error
     */
    public function debe_generar_mensaje_de_error_apropiado(): void {
        $passwordCorto = 'Ab1';
        $mensaje = PasswordValidator::getErrorMessage($passwordCorto);
        $this->assertStringContainsString('al menos', strtolower($mensaje));

        $passwordDebil = 'soloMinusculas';
        $mensaje = PasswordValidator::getErrorMessage($passwordDebil, true);
        $this->assertStringContainsString('mayúscula', strtolower($mensaje));
    }
}
