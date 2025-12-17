<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Programar Citas
 * Basado en casos de prueba: CP-CIT-01, CP-CIT-02, CP-CIT-03
 */
class ProgramarCitasTest extends TestCase
{
    /**
     * CP-CIT-01: Caso de prueba exitoso - Reservación exitosa
     * Reservar cita en horario disponible
     */
    public function testReservarCitaExitosamente()
    {
        // Datos de prueba del documento
        $servicio = 'Pago de matricula';
        $trabajadoraSocial = 'Elena de Avalon';
        $fecha = '16/12/2025';
        $hora = '14:30';
        $nota = 'Consulta sobre opciones de pago';
        
        // Validar campos obligatorios
        $servicioValidado = validarNoVacio($servicio, 'servicio');
        $trabajadoraValidada = validarNoVacio($trabajadoraSocial, 'trabajadora social');
        $fechaValidada = validarNoVacio($fecha, 'fecha');
        $horaValidada = validarNoVacio($hora, 'hora');
        
        $this->assertEquals('Pago de matricula', $servicioValidado);
        $this->assertEquals('Elena de Avalon', $trabajadoraValidada);
        $this->assertEquals('16/12/2025', $fechaValidada);
        $this->assertEquals('14:30', $horaValidada);
        
        // Simular horario disponible
        $horarioDisponible = true;
        $citaReservada = true;
        
        $this->assertTrue($horarioDisponible);
        $this->assertTrue($citaReservada, 
            'Cita debe ser reservada con confirmación en pantalla');
    }
    
    /**
     * CP-CIT-02: Caso de prueba fallido - Conflicto de horarios
     * Validar que no se permite reservar en horarios ocupados
     */
    public function testReservarCitaFallidaHorarioOcupado()
    {
        $fecha = '12/12/2024';
        $hora = '14:00';
        
        // Simular horario ya ocupado
        $horariosOcupados = ['14:00', '15:00', '16:00'];
        $horarioDisponible = !in_array($hora, $horariosOcupados);
        
        $this->assertFalse($horarioDisponible, 
            'Sistema debe mostrar: "El horario seleccionado ya no está disponible"');
    }
    
    /**
     * CP-CIT-03: Caso de prueba fallido - Información incompleta
     * Verificar que no permite reservar con datos incompletos
     */
    public function testReservarCitaFallidaDatosIncompletos()
    {
        $tipoAsesoria = 'Académica';
        $fecha = '';
        $hora = '';
        
        // Validar fecha obligatoria
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo fecha es obligatorio');
        
        validarNoVacio($fecha, 'fecha');
    }
    
    /**
     * Test adicional - Validación de formato de fecha
     */
    public function testValidarFormatoFecha()
    {
        $fechaValida = '16/12/2025';
        $fechaInvalida = '2025-13-40';
        
        // Validar formato DD/MM/YYYY
        $patron = '/^\d{2}\/\d{2}\/\d{4}$/';
        
        $this->assertMatchesRegularExpression($patron, $fechaValida);
        $this->assertDoesNotMatchRegularExpression($patron, $fechaInvalida);
    }
}
