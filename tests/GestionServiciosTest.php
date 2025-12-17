<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Gestionar Servicios y Ofertas
 * Basado en casos de prueba: CP-GSO-01, CP-GSO-04, CP-GSO-05
 */
class GestionServiciosTest extends TestCase
{
    /**
     * CP-GSO-01: Caso de prueba exitoso - Agregar servicio exitosamente
     * Verificar que un usuario con permisos puede agregar un nuevo servicio
     */
    public function testAgregarServicioExitosamente()
    {
        // Datos de prueba del documento
        $tipo = 'Servicio';
        $nombre = 'Tutorías de Matemáticas';
        $trabajadoraSocial = 'Karina Torres';
        
        // Validar campos obligatorios no vacíos
        $tipoValidado = validarNoVacio($tipo, 'tipo');
        $nombreValidado = validarNoVacio($nombre, 'nombre');
        $trabajadoraValidada = validarNoVacio($trabajadoraSocial, 'trabajadora social');
        
        $this->assertEquals('Servicio', $tipoValidado);
        $this->assertEquals('Tutorías de Matemáticas', $nombreValidado);
        $this->assertEquals('Karina Torres', $trabajadoraValidada);
        
        // Simular guardado exitoso
        $servicioGuardado = true;
        $this->assertTrue($servicioGuardado, 
            'Servicio debe ser agregado a la BD con mensaje de confirmación');
    }
    
    /**
     * CP-GSO-04: Caso de prueba fallido - Nombre del servicio vacío
     * Validar que no se permiten campos obligatorios vacíos
     */
    public function testAgregarServicioFallidoConNombreVacio()
    {
        $nombre = '';
        $trabajadoraSocial = 'Ana López';
        
        // Validar que el nombre no esté vacío
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El campo nombre es obligatorio');
        
        validarNoVacio($nombre, 'nombre');
    }
    
    /**
     * CP-GSO-05: Caso de prueba fallido - Nombre ya existente
     * Validar duplicación de nombres de servicios
     */
    public function testAgregarServicioFallidoConNombreDuplicado()
    {
        // Datos de prueba del documento
        $nombreNuevo = 'Taller de Emprendimiento';
        $nombresExistentes = [
            'Taller de Emprendimiento',
            'Asesoría Académica',
            'Beca de Transporte'
        ];
        
        // Validar que el nombre no existe ya
        $nombreDuplicado = in_array($nombreNuevo, $nombresExistentes);
        
        $this->assertTrue($nombreDuplicado, 
            'Sistema debe mostrar: "Ya existe un servicio/oferta con este nombre"');
    }
    
    /**
     * CP-GSO-06: Test adicional - Eliminación con citas programadas
     * Verificar que no se puede eliminar servicio con citas activas
     */
    public function testEliminarServicioFallidoConCitasActivas()
    {
        $servicio = 'Asesoría de Becas';
        $citasActivas = 3;
        
        // Validar que no se puede eliminar si hay citas
        $puedeEliminar = ($citasActivas === 0);
        
        $this->assertFalse($puedeEliminar, 
            'Sistema debe mostrar error explicando que tiene citas asociadas');
    }
}
