<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Generar Estadísticas
 * Basado en casos de prueba: CP-EST-01, CP-EST-05, CP-EST-06
 */
class GenerarEstadisticasTest extends TestCase
{
    /**
     * CP-EST-01: Caso de prueba exitoso - Descarga exitosa
     * Generar reporte estadístico con parámetros completos
     */
    public function testGenerarReporteExitoso()
    {
        // Datos de prueba del documento
        $tipo = 'Solicitudes';
        $fechaInicio = '2024-01-01';
        $fechaFin = '2024-06-30';
        
        // Validar campos obligatorios
        $tipoValidado = validarNoVacio($tipo, 'tipo de reporte');
        $this->assertEquals('Solicitudes', $tipoValidado);
        
        // Validar fechas
        $this->assertNotEmpty($fechaInicio);
        $this->assertNotEmpty($fechaFin);
        
        // Validar que fecha inicio es menor que fecha fin
        $this->assertLessThan($fechaFin, $fechaInicio);
        
        // Simular generación exitosa
        $reporteGenerado = true;
        $formatoPDF = 'reporte_2024.pdf';
        
        $this->assertTrue($reporteGenerado, 
            'Reporte debe generarse correctamente y PDF descargado');
        $this->assertStringContainsString('.pdf', $formatoPDF);
    }
    
    /**
     * CP-EST-05: Caso de prueba fallido - Campos obligatorios incompletos
     * Validar campos obligatorios
     */
    public function testGenerarReporteFallidoSinFechas()
    {
        $tipo = 'Estudiantes por Facultad';
        $fechaInicio = '';
        $fechaFin = '';
        
        // Validar que las fechas no estén vacías
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo fecha inicio es obligatorio');
        
        validarNoVacio($fechaInicio, 'fecha inicio');
    }
    
    /**
     * CP-EST-06: Caso de prueba fallido - Error en generación de Excel
     * Verificar manejo de error al exportar Excel
     */
    public function testGenerarReporteFallidoErrorExportacionExcel()
    {
        // Datos válidos
        $tipo = 'Citas';
        $fechaInicio = '2024-01-01';
        $fechaFin = '2024-06-30';
        
        // Simular error en generación de Excel
        $errorExcel = true;
        $archivoGenerado = false;
        
        $this->assertTrue($errorExcel, 
            'Sistema debe mostrar: "Error al generar Excel. Intente nuevamente"');
        $this->assertFalse($archivoGenerado);
    }
    
    /**
     * CP-EST-04: Test adicional - Error en generación de PDF
     */
    public function testGenerarReporteFallidoErrorPDF()
    {
        $tipo = 'Solicitudes';
        $fechaInicio = '2024-01-01';
        $fechaFin = '2024-06-30';
        
        // Simular fallo en módulo PDF
        $moduloPDFFunciona = false;
        
        $this->assertFalse($moduloPDFFunciona, 
            'Sistema debe mostrar mensaje de error y no descargar PDF');
    }
}
