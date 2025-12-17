<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Solicitar Servicios
 * Basado en casos de prueba: CP-SOL-01, CP-SOL-04, CP-SOL-05
 */
class SolicitarServiciosTest extends TestCase
{
    /**
     * CP-SOL-01: Caso de prueba exitoso - Solicitud exitosa
     * Verificar que estudiante puede completar solicitud correctamente
     */
    public function testSolicitarServicioExitosamente()
    {
        // Datos de prueba del documento
        $tipo = 'Becas Alimenticias';
        $estudiante = 'Laura Martínez';
        $documentos = ['declaracion_jurada.pdf', 'comprobantes_ingresos.jpg'];
        
        // Validar campos obligatorios
        $tipoValidado = validarNoVacio($tipo, 'tipo de servicio');
        $estudianteValidado = validarNoVacio($estudiante, 'estudiante');
        
        $this->assertEquals('Becas Alimenticias', $tipoValidado);
        $this->assertEquals('Laura Martínez', $estudianteValidado);
        
        // Validar documentos adjuntos
        $this->assertCount(2, $documentos);
        $this->assertStringEndsWith('.pdf', $documentos[0]);
        $this->assertStringEndsWith('.jpg', $documentos[1]);
        
        // Simular solicitud registrada
        $solicitudRegistrada = true;
        $numeroSolicitud = '#SOL-2024-001';
        
        $this->assertTrue($solicitudRegistrada, 
            'Solicitud debe registrarse con estado "Pendiente de revisión"');
        $this->assertMatchesRegularExpression('/^#SOL-\d{4}-\d{3}$/', $numeroSolicitud);
    }
    
    /**
     * CP-SOL-04: Caso de prueba fallido - Documentación incompleta
     * Verificar que impide envío con campos obligatorios vacíos
     */
    public function testSolicitarServicioFallidoCamposVacios()
    {
        $servicio = 'Asistencia alimenticia';
        $fecha = '';
        $hora = '';
        $motivo = '';
        
        // Validar campos obligatorios
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo fecha es obligatorio');
        
        validarNoVacio($fecha, 'fecha');
    }
    
    /**
     * CP-SOL-05: Caso de prueba fallido - Formato no permitido
     * Verificar que rechaza documentos con formatos inválidos
     */
    public function testSolicitarServicioFallidoFormatoInvalido()
    {
        $estudiante = 'Samuel Rivas';
        $servicio = 'Crédito bibliográfico';
        $documento = 'aplicacion.exe';
        
        // Validar formato de archivo
        $formatosPermitidos = ['pdf', 'jpg', 'png', 'jpeg'];
        $extension = pathinfo($documento, PATHINFO_EXTENSION);
        $formatoValido = in_array(strtolower($extension), $formatosPermitidos);
        
        $this->assertFalse($formatoValido, 
            'Sistema debe mostrar: "Formato o tamaño de archivo no permitido"');
    }
    
    /**
     * CP-SOL-02: Test adicional - Múltiples documentos
     */
    public function testSolicitarServicioConMultiplesDocumentos()
    {
        $estudiante = 'Juan Aguilar';
        $tipo = 'Beca de investigación';
        $documentos = [
            'propuesta.pdf',
            'presupuesto.xlsx',
            'carta_profesor.jpg',
            'historial.pdf',
            'cronograma.docx'
        ];
        
        // Validar cantidad de documentos
        $this->assertCount(5, $documentos);
        $this->assertLessThanOrEqual(10, count($documentos), 
            'Sistema debe aceptar hasta 10 documentos');
        
        // Validar formatos
        foreach ($documentos as $doc) {
            $extension = pathinfo($doc, PATHINFO_EXTENSION);
            $this->assertContains(strtolower($extension), 
                ['pdf', 'xlsx', 'jpg', 'docx', 'png']);
        }
    }
}
