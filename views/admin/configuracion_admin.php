<?php
/**
 * Configuración del Sistema
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header("Location: ../../auth/login.php");
    exit();
}

$page_title = "Configuración del Sistema";
$page_subtitle = "Configuración general del sistema";

ob_start();
?>

<div class="content-card">
    <h2 class="card-title">Configuración del Sistema</h2>
    <p class="text-muted">Esta sección permite configurar los parámetros generales del sistema.</p>
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Esta funcionalidad está en desarrollo.
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout_admin.php';
?>