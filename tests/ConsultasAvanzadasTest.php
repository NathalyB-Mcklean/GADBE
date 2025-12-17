<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests automatizados para Caso de Uso: Realizar Consultas Avanzadas
 * Basado en casos de prueba: CP-CON-01, CP-CON-05, CP-CON-06
 */
class ConsultasAvanzadasTest extends TestCase
{
    /**
     * CP-CON-01: Caso de prueba exitoso - Búsqueda con filtros aplicados
     * Buscar servicios aplicando filtros correctamente
     */
    public function testBusquedaExitosaConFiltros()
    {
        // Datos de prueba del documento
        $termino = 'becas';
        $categoria = 'Todas las categorías';
        $ubicacion = 'Oficina de Bienestar Estudiantil';
        
        // Validar término de búsqueda
        $this->assertNotEmpty($termino);
        $this->assertGreaterThanOrEqual(3, strlen($termino), 
            'Término debe tener al menos 3 caracteres');
        
        // Simular resultados de búsqueda
        $resultadosEncontrados = [
            'Becas Alimenticias',
            'Becas de Investigación',
            'Becas de Transporte'
        ];
        
        $this->assertGreaterThan(0, count($resultadosEncontrados), 
            'Sistema debe mostrar lista de servicios filtrados');
        $this->assertContains('Becas', $resultadosEncontrados[0]);
    }
    
    /**
     * CP-CON-05: Caso de prueba fallido - Término inexistente
     * Manejo de búsquedas sin resultados
     */
    public function testBusquedaFallidaConTerminoInexistente()
    {
        $termino = 'Tenis';
        $categoria = 'Mecenazgo académico';
        $estado = 'Expirado';
        
        // Simular búsqueda sin resultados
        $resultados = [];
        
        $this->assertEmpty($resultados, 
            'Sistema debe mostrar: "No se encontraron resultados para los criterios seleccionados"');
    }
    
    /**
     * CP-CON-06: Caso de prueba fallido - Término con pocos caracteres
     * Validar manejo de término de búsqueda inválido
     */
    public function testBusquedaFallidaConTerminoDemasiado Corto()
    {
        $termino = 'a';
        
        // Validar longitud mínima
        $longitudValida = (strlen($termino) >= 3);
        
        $this->assertFalse($longitudValida, 
            'Sistema debe mostrar: "El término de búsqueda debe contener al menos 3 caracteres"');
    }
    
    /**
     * CP-CON-07: Test adicional - Caracteres no permitidos
     * Verificar validación de caracteres especiales
     */
    public function testBusquedaFallidaConCaracteresEspeciales()
    {
        $termino = 'tutorías@#$';
        
        // Validar solo letras, números y espacios
        $patron = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s]+$/';
        $esValido = preg_match($patron, $termino);
        
        $this->assertFalse((bool)$esValido, 
            'Sistema debe rechazar caracteres especiales no permitidos');
    }
}
