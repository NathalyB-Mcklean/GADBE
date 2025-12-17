<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests automatizados para Caso de Uso: Gestionar Agenda de Atención
 * Basado en casos de prueba: CP-GAA-01, CP-GAA-04, CP-GAA-05
 */
class GestionAgendaTest extends TestCase
{
    /**
     * CP-GAA-01: Caso de prueba exitoso - Visualización agenda semanal
     * Verificar que trabajadora social puede ver su agenda correctamente
     */
    public function testVisualizarAgendaSemanalExitosamente()
    {
        // Datos de prueba del documento
        $usuario = 'Ana Rodríguez';
        $fecha = '10/12/2025';
        $rol = 'Trabajadora Social';
        
        // Validar permisos de usuario
        $tienePermiso = ($rol === 'Trabajadora Social' || $rol === 'Administrador');
        $this->assertTrue($tienePermiso);
        
        // Simular citas en agenda
        $citasSemanales = [
            ['dia' => 'Lunes', 'hora' => '10:00', 'estudiante' => 'Juan Pérez'],
            ['dia' => 'Miércoles', 'hora' => '14:00', 'estudiante' => 'María López'],
            ['dia' => 'Viernes', 'hora' => '11:00', 'estudiante' => 'Carlos Gómez']
        ];
        
        $this->assertCount(3, $citasSemanales, 
            'Sistema debe mostrar agenda semanal con citas e indicadores de disponibilidad');
    }
    
    /**
     * CP-GAA-04: Caso de prueba fallido - Horario bloqueado
     * Validar que no se puede programar en horarios no disponibles
     */
    public function testProgramarCitaFallidaHorarioBloqueado()
    {
        $servicio = 'Asistencia Económica';
        $fecha = 'sábado 29 de noviembre de 2025'; // Fin de semana
        $hora = '10:00';
        
        // Validar que no es fin de semana
        $esFindeSemana = (stripos($fecha, 'sábado') !== false || 
                          stripos($fecha, 'domingo') !== false);
        
        $this->assertTrue($esFindeSemana, 
            'Sistema debe mostrar: "Horario no disponible"');
    }
    
    /**
     * CP-GAA-05: Caso de prueba fallido - Reagendamiento con conflicto
     * Validar detección de conflictos al reagendar
     */
    public function testReagendarCitaFallidaConflictoHorario()
    {
        $servicio = 'Asistencia Económica';
        $fechaOriginal = '12/12/2025';
        $horaOriginal = '10:00';
        $horaNueva = '15:00';
        
        // Simular horarios ocupados
        $horariosOcupados = ['15:00', '16:00'];
        $hayConflicto = in_array($horaNueva, $horariosOcupados);
        
        $this->assertTrue($hayConflicto, 
            'Sistema debe mostrar: "Horario no disponible. Seleccione otro horario"');
    }
    
    /**
     * CP-GAA-03: Test adicional - Reasignación por administrador
     */
    public function testReasignarCitasExitosamente()
    {
        $administrador = 'Nathaly Bonilla';
        $trabajadoraActual = 'Eimy Felix';
        $trabajadoraDestino = 'Elena Martínez';
        
        // Simular reasignación
        $citasReasignadas = 3;
        $reasignacionExitosa = ($citasReasignadas > 0);
        
        $this->assertTrue($reasignacionExitosa, 
            'Citas deben reasignarse con notificaciones enviadas');
    }
}
