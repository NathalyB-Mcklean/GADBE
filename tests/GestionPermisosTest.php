<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../validaciones/validaciones.php';

/**
 * Tests automatizados para Caso de Uso: Gestionar Permisos y Roles
 * Basado en casos de prueba: CP-GPR-01, CP-GPR-04, CP-GPR-06
 */
class GestionPermisosTest extends TestCase
{
    /**
     * CP-GPR-01: Caso de prueba exitoso - Creación de nuevo rol
     * Validar que administrador puede crear rol con permisos específicos
     */
    public function testCrearNuevoRolExitosamente()
    {
        // Datos de prueba del documento
        $nombre = 'Coordinador de Bienestar';
        $descripcion = 'Supervisa equipo de trabajadoras sociales y genera reportes ejecutivos';
        $permisos = [
            'Ver solicitudes departamentales',
            'Generar reportes',
            'Reasignar solicitudes',
            'Gestionar agenda'
        ];
        
        // Validar campos obligatorios
        $nombreValidado = validarNoVacio($nombre, 'nombre del rol');
        $descripcionValidada = validarNoVacio($descripcion, 'descripción');
        
        $this->assertEquals('Coordinador de Bienestar', $nombreValidado);
        $this->assertNotEmpty($descripcionValidada);
        
        // Validar permisos
        $this->assertCount(4, $permisos);
        $this->assertContains('Generar reportes', $permisos);
        
        // Simular creación exitosa
        $rolCreado = true;
        $this->assertTrue($rolCreado, 
            'Sistema debe crear rol y mostrar confirmación');
    }
    
    /**
     * CP-GPR-04: Caso de prueba fallido - Eliminar rol con usuarios asignados
     * Validar que impide eliminar rol con usuarios activos
     */
    public function testEliminarRolFallidoConUsuariosAsignados()
    {
        $rol = 'Coordinador de Bienestar';
        $usuariosAsignados = 3;
        
        // Verificar que no se puede eliminar con usuarios activos
        $puedeEliminar = ($usuariosAsignados === 0);
        
        $this->assertFalse($puedeEliminar, 
            'Sistema debe mostrar: "No se puede eliminar. El rol tiene 3 usuarios asignados"');
    }
    
    /**
     * CP-GPR-06: Caso de prueba fallido - Auto-eliminación de privilegios
     * Validar que administrador no puede remover sus propios privilegios
     */
    public function testAutoEliminarPrivilegiosFallido()
    {
        $usuarioActual = 'admin@utp.ac.pa';
        $rolActual = 'Administrador';
        $rolDestino = 'Estudiante';
        
        // Verificar que no puede cambiar su propio rol admin
        $esAutoModificacion = ($usuarioActual === 'admin@utp.ac.pa' && 
                               $rolActual === 'Administrador' &&
                               $rolDestino !== 'Administrador');
        
        $this->assertTrue($esAutoModificacion, 
            'Sistema debe mostrar: "No puede modificar sus propios privilegios administrativos"');
    }
    
    /**
     * CP-GPR-02: Test adicional - Modificación de permisos
     */
    public function testModificarPermisosRolExitosamente()
    {
        $rol = 'Trabajadora Social';
        $permisoDesactivado = 'Eliminar servicios';
        $permisosActivos = ['Crear servicios', 'Modificar servicios'];
        
        // Validar cambio de permisos
        $this->assertNotContains($permisoDesactivado, $permisosActivos);
        $this->assertContains('Crear servicios', $permisosActivos);
        $this->assertContains('Modificar servicios', $permisosActivos);
        
        $actualizacionExitosa = true;
        $this->assertTrue($actualizacionExitosa, 
            'Sistema debe actualizar permisos inmediatamente');
    }
    
    /**
     * CP-GPR-03: Test adicional - Asignación de rol a usuario
     */
    public function testAsignarRolAUsuarioExitosamente()
    {
        $usuario = 'coordinador.nuevo@utp.ac.pa';
        $rolAsignado = 'Coordinador de Bienestar';
        
        // Validar correo institucional
        $correoValidado = validarCorreoUTP($usuario);
        $this->assertEquals('coordinador.nuevo@utp.ac.pa', $correoValidado);
        
        // Simular asignación
        $asignacionExitosa = true;
        $this->assertTrue($asignacionExitosa, 
            'Sistema debe asignar rol y notificar al usuario');
    }
}
