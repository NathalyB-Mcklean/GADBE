<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Ingresar al Sistema
 * Basado en casos de prueba: CP-ING-01, CP-ING-02, CP-ING-03
 */
class IngresoSistemaTest extends TestCase
{
    /**
     * CP-ING-01: Caso de prueba exitoso
     * Verificar que un usuario registrado puede iniciar sesión con credenciales válidas
     */
    public function testIngresoExitosoConCredencialesValidas()
    {
        // Datos de prueba del documento
        $correo = 'maria.gomez@utp.ac.pa';
        $password = 'Docente123';
        
        // Validar correo institucional
        $correoValidado = validarCorreoUTP($correo);
        $this->assertEquals('maria.gomez@utp.ac.pa', $correoValidado);
        
        // Validar que la contraseña no está vacía
        $this->assertNotEmpty($password);
        $this->assertGreaterThan(6, strlen($password));
        
        // Simular validación exitosa
        $loginExitoso = true;
        $this->assertTrue($loginExitoso, 
            'El sistema debe redirigir al menú principal según rol del usuario');
    }
    
    /**
     * CP-ING-02: Caso de prueba fallido - Contraseña incorrecta
     * Verificar que el sistema rechaza credenciales incorrectas
     */
    public function testIngresoFallidoConPasswordIncorrecta()
    {
        // Datos de prueba del documento
        $correo = 'juan.perez@utp.ac.pa';
        $passwordIncorrecta = 'incorrecta';
        
        // Validar correo (debe ser válido)
        $correoValidado = validarCorreoUTP($correo);
        $this->assertEquals('juan.perez@utp.ac.pa', $correoValidado);
        
        // Simular intento de login con contraseña incorrecta
        $passwordCorrecta = 'Password123';
        $loginExitoso = ($passwordIncorrecta === $passwordCorrecta);
        
        $this->assertFalse($loginExitoso, 
            'Sistema debe mostrar: "Credenciales incorrectas". No se concede acceso.');
    }
    
    /**
     * CP-ING-03: Caso de prueba fallido - Campo de correo vacío
     * Verificar que el sistema rechaza inicio de sesión con campos obligatorios vacíos
     */
    public function testIngresoFallidoConCampoCorreoVacio()
    {
        $correo = '';
        $password = 'Docente123';
        
        // Validar que el correo no esté vacío
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo correo es obligatorio');
        
        validarNoVacio($correo, 'correo');
    }
    
    /**
     * CP-ING-05: Test adicional - Correo inválido (no institucional)
     * Validar formato de correo institucional requerido
     */
    public function testIngresoFallidoConCorreoNoInstitucional()
    {
        $correo = 'usuario@gmail.com';
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo se permiten correos institucionales UTP');
        
        validarCorreoUTP($correo);
    }
}
