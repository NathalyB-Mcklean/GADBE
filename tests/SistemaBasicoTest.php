<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests básicos para el sistema GADBE
 */
class SistemaBasicoTest extends TestCase
{
    /**
     * Test: Verificar que PHP cumple versión mínima
     */
    public function testVersionPHPMinima()
    {
        $versionMinima = '8.0';
        $versionActual = PHP_VERSION;
        
        $this->assertGreaterThanOrEqual(
            $versionMinima,
            $versionActual,
            "PHP debe ser versión $versionMinima o superior. Versión actual: $versionActual"
        );
    }
    
    /**
     * Test: Validar formato de cédula panameña
     */
    public function testFormatoCedulaPanamena()
    {
        // Formato válido: X-XXX-XXXX o X-XXXX-XXXX
        $cedulasValidas = [
            '8-888-8888',
            '1-234-5678',
            'PE-123-4567'
        ];
        
        foreach ($cedulasValidas as $cedula) {
            $patron = '/^(PE-|E-|N-|[0-9]{1,2}-)?\d{3,4}-\d{4,5}$/';
            $this->assertMatchesRegularExpression(
                $patron,
                $cedula,
                "Cédula $cedula debe ser válida"
            );
        }
    }
    
    /**
     * Test: Cédulas con formato inválido
     */
    public function testFormatoCedulaInvalida()
    {
        $cedulasInvalidas = [
            '12345678',
            '8.888.8888',
            '8-888-888',
            'invalido'
        ];
        
        $patron = '/^(PE-|E-|N-|[0-9]{1,2}-)?\d{3,4}-\d{4,5}$/';
        
        foreach ($cedulasInvalidas as $cedula) {
            $this->assertDoesNotMatchRegularExpression(
                $patron,
                $cedula,
                "Cédula $cedula NO debe ser válida"
            );
        }
    }
    
    /**
     * Test: Validar que existan directorios críticos
     */
    public function testDirectoriosCriticosExisten()
    {
        $directoriosRequeridos = [
            __DIR__ . '/../config',
            __DIR__ . '/../validaciones',
            __DIR__ . '/../views',
            __DIR__ . '/../uploads'
        ];
        
        foreach ($directoriosRequeridos as $directorio) {
            $this->assertDirectoryExists(
                $directorio,
                "Directorio crítico debe existir: $directorio"
            );
        }
    }
}
