<?php
/**
 * Tests para EmailValidator
 *
 * Casos de Prueba: CP-ING-04, CP-ING-06, CP-ING-09
 *
 * @package GADBE\Tests\Unit\Validators
 */

namespace GADBE\Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use GADBE\Validators\EmailValidator;

class EmailValidatorTest extends TestCase {
    /**
     * @test
     * CP-ING-01: Email institucional válido
     */
    public function debe_validar_email_institucional_correcto(): void {
        $email = 'juan.perez@utp.ac.pa';

        $this->assertTrue(EmailValidator::isValid($email));
        $this->assertTrue(EmailValidator::isInstitutional($email));
    }

    /**
     * @test
     * CP-ING-09: Email no institucional debe fallar
     */
    public function debe_rechazar_email_no_institucional(): void {
        $email = 'usuario@gmail.com';

        $this->assertTrue(EmailValidator::isValid($email)); // Es email válido
        $this->assertFalse(EmailValidator::isInstitutional($email)); // Pero no es UTP
    }

    /**
     * @test
     * CP-ING-04: Email con formato inválido
     */
    public function debe_rechazar_email_con_formato_invalido(): void {
        $emails = [
            'correo-invalido',
            '@utp.ac.pa',
            'usuario@',
            'usuario @utp.ac.pa',
            ''
        ];

        foreach ($emails as $email) {
            $this->assertFalse(EmailValidator::isValid($email), "Email '$email' debería ser inválido");
        }
    }

    /**
     * @test
     * Normalización de emails
     */
    public function debe_normalizar_emails_correctamente(): void {
        $this->assertEquals('usuario@utp.ac.pa', EmailValidator::normalize('  Usuario@UTP.AC.PA  '));
        $this->assertEquals('test@utp.ac.pa', EmailValidator::normalize('TEST@utp.ac.pa'));
    }

    /**
     * @test
     * Mensajes de error
     */
    public function debe_generar_mensaje_de_error_apropiado(): void {
        $emailInvalido = 'correo-invalido';
        $mensaje = EmailValidator::getErrorMessage($emailInvalido);
        $this->assertStringContainsString('formato', strtolower($mensaje));

        $emailNoInstitucional = 'usuario@gmail.com';
        $mensaje = EmailValidator::getErrorMessage($emailNoInstitucional);
        $this->assertStringContainsString('@utp.ac.pa', $mensaje);
    }
}
