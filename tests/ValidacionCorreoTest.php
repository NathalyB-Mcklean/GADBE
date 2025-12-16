<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

// Cargar las funciones de validación
require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests para validaciones de correos UTP
 */
class ValidacionCorreoTest extends TestCase
{
    /**
     * Test: Correo UTP válido debe pasar la validación
     */
    public function testCorreoUTPValido()
    {
        $correo = 'estudiante.prueba@utp.ac.pa';
        
        try {
            $resultado = validarCorreoUTP($correo);
            $this->assertEquals('estudiante.prueba@utp.ac.pa', $resultado);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('No debería lanzar excepción con correo válido: ' . $e->getMessage());
        }
    }
    
    /**
     * Test: Correo no UTP debe fallar
     */
    public function testCorreoNoUTPInvalido()
    {
        $correo = 'estudiante@gmail.com';
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo se permiten correos institucionales UTP');
        
        validarCorreoUTP($correo);
    }
    
    /**
     * Test: Correo con formato inválido debe fallar
     */
    public function testCorreoFormatoInvalido()
    {
        $correo = 'correo-invalido-sin-arroba';
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('correo electrónico no es válido');
        
        validarCorreoUTP($correo);
    }
    
    /**
     * Test: Correo UTP debe convertirse a minúsculas
     */
    public function testCorreoUTPConvierteMinusculas()
    {
        $correo = 'ESTUDIANTE.PRUEBA@UTP.AC.PA';
        
        $resultado = validarCorreoUTP($correo);
        $this->assertEquals('estudiante.prueba@utp.ac.pa', $resultado);
    }
}