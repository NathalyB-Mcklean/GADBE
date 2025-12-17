<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests para validación de campos obligatorios
 */
class ValidacionCamposTest extends TestCase
{
    /**
     * Test: Campo con valor no vacío debe pasar
     */
    public function testCampoNoVacioValido()
    {
        $valor = 'Juan Pérez';
        $resultado = validarNoVacio($valor, 'nombre');
        
        $this->assertEquals('Juan Pérez', $resultado);
    }
    
    /**
     * Test: Campo vacío debe lanzar excepción
     */
    public function testCampoVacioLanzaExcepcion()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo nombre es obligatorio');
        
        validarNoVacio('', 'nombre');
    }
    
    /**
     * Test: Campo solo con espacios debe lanzar excepción
     */
    public function testCampoSoloEspaciosLanzaExcepcion()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo apellido es obligatorio');
        
        validarNoVacio('   ', 'apellido');
    }
    
    /**
     * Test: Campo con espacios al inicio y final se limpia
     */
    public function testCampoSeRecorta()
    {
        $valor = '  María García  ';
        $resultado = validarNoVacio($valor, 'nombre');
        
        $this->assertEquals('María García', $resultado);
        $this->assertNotEquals('  María García  ', $resultado);
    }
}
