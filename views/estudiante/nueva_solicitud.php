<?php
/**
 * Nueva Solicitud
 * Sistema de Gestión Automatizada para la Dirección de Bienestar Estudiantil - UTP
 */

// Definir la ruta base del proyecto
$base_path = dirname(dirname(dirname(__FILE__)));

// Incluir archivos necesarios
require_once $base_path . '/config/config.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté autenticado y sea estudiante
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Estudiante') {
    header("Location: ../auth/login.php");
    exit();
}

// Inicializar variables
$tipos_solicitud = [];
$servicios = [];
$error = null;
$success = null;

// ========== MODIFICACIÓN 1: Capturar servicio preseleccionado ==========
$id_servicio_preseleccionado = isset($_GET['id_servicio']) && is_numeric($_GET['id_servicio']) 
    ? (int)$_GET['id_servicio'] 
    : null;


try {
    $conn = getDBConnection();
    
    // Obtener tipos de solicitud activos
    $stmt_tipos = $conn->query("SELECT * FROM tipos_solicitud WHERE activo = 1 ORDER BY nombre_tipo");
    $tipos_solicitud = $stmt_tipos->fetchAll();
    
    // ========== MODIFICACIÓN 2: Cambiar query para obtener solo OFERTAS ==========
    // Obtener servicios activos (solo OFERTAS)
    $stmt_servicios = $conn->query("
        SELECT * FROM servicios_ofertas 
        WHERE activo = 1 
        AND tipo = 'oferta'
        ORDER BY nombre
    ");
    $servicios = $stmt_servicios->fetchAll();
    
    // Procesar el formulario si se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_tipo_solicitud = $_POST['id_tipo_solicitud'] ?? '';
        $id_servicio = $_POST['id_servicio'] ?? null;
        $motivo = trim($_POST['motivo'] ?? '');
        $informacion_adicional = trim($_POST['informacion_adicional'] ?? '');
        
        // Validaciones básicas
        if (empty($id_tipo_solicitud)) {
            throw new Exception("Debe seleccionar un tipo de solicitud");
        }
        
        if (empty($motivo)) {
            throw new Exception("Debe ingresar un motivo para la solicitud");
        }
        
        // Generar código de solicitud
        $codigo_solicitud = 'SOL-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insertar la solicitud
        $stmt_insert = $conn->prepare("
            INSERT INTO solicitudes (
                codigo_solicitud,
                id_estudiante,
                id_tipo_solicitud,
                id_servicio,
                estado,
                motivo,
                informacion_adicional,
                fecha_solicitud,
                hora_solicitud,
                prioridad
            ) VALUES (?, ?, ?, ?, 'pendiente', ?, ?, NOW(), CURTIME(), 'media')
        ");
        
        $stmt_insert->execute([
            $codigo_solicitud,
            $_SESSION['user_id'],
            $id_tipo_solicitud,
            $id_servicio,
            $motivo,
            $informacion_adicional
        ]);
        
        $id_solicitud = $conn->lastInsertId();
        
        // Procesar documentos adjuntos si existen
        if (isset($_FILES['documentos']) && is_array($_FILES['documentos']['name'])) {
            for ($i = 0; $i < count($_FILES['documentos']['name']); $i++) {
                if ($_FILES['documentos']['error'][$i] === UPLOAD_ERR_OK) {
                    $nombre_archivo = basename($_FILES['documentos']['name'][$i]);
                    $tipo_archivo = $_FILES['documentos']['type'][$i];
                    $tamaño = $_FILES['documentos']['size'][$i];
                    $tmp_name = $_FILES['documentos']['tmp_name'][$i];
                    
                    // Validar tipo de archivo
                    $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
                    
                    if (!in_array($extension, $extensiones_permitidas)) {
                        throw new Exception("Tipo de archivo no permitido: $nombre_archivo");
                    }
                    
                    // Validar tamaño (máximo 5MB)
                    if ($tamaño > 5 * 1024 * 1024) {
                        throw new Exception("Archivo demasiado grande: $nombre_archivo (máximo 5MB)");
                    }
                    
                    // Crear directorio si no existe
                    $upload_dir = $base_path . '/uploads/solicitudes/' . $id_solicitud . '/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generar nombre único
                    $nombre_unico = uniqid() . '_' . $nombre_archivo;
                    $ruta_completa = $upload_dir . $nombre_unico;
                    
                    // Mover el archivo
                    if (move_uploaded_file($tmp_name, $ruta_completa)) {
                        // Insertar en base de datos
                        $stmt_doc = $conn->prepare("
                            INSERT INTO documentos_solicitud (
                                id_solicitud,
                                nombre_archivo,
                                ruta_archivo,
                                tipo_archivo,
                                tamaño_bytes
                            ) VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $ruta_relativa = 'uploads/solicitudes/' . $id_solicitud . '/' . $nombre_unico;
                        $stmt_doc->execute([
                            $id_solicitud,
                            $nombre_archivo,
                            $ruta_relativa,
                            $tipo_archivo,
                            $tamaño
                        ]);
                    } else {
                        throw new Exception("Error al subir el archivo: $nombre_archivo");
                    }
                }
            }
        }
        
        $success = "Solicitud creada exitosamente con el código: $codigo_solicitud";
        
        // Redirigir a la lista de solicitudes después de 3 segundos
        header("refresh:3;url=solicitudes.php");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Variables para el layout
$page_title = "Nueva Solicitud";
$page_subtitle = "Crea una nueva solicitud de servicios";

// Capturar contenido
ob_start();
?>

<style>
    .content-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .form-label {
        font-weight: 600;
        color: #333;
    }
    
    .required::after {
        content: " *";
        color: #dc3545;
    }
    
    .file-upload {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        background: #f8f9fa;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .file-upload:hover {
        border-color: #2d8659;
        background: #e8f5e9;
    }
    
    .file-list {
        margin-top: 15px;
    }
    
    .file-item {
        background: #f1f3f4;
        border-radius: 5px;
        padding: 10px 15px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .file-item .file-name {
        flex-grow: 1;
        margin-right: 15px;
        word-break: break-all;
    }
    
    .file-item .file-size {
        color: #6c757d;
        font-size: 12px;
        margin-right: 15px;
    }
    
    .btn-utp {
        background: linear-gradient(135deg, #2d8659 0%, #1a5c3a 100%);
        color: white;
        border: none;
        padding: 10px 25px;
        font-weight: 600;
    }
    
    .btn-utp:hover {
        background: linear-gradient(135deg, #1a5c3a 0%, #2d8659 100%);
        color: white;
    }
</style>

<!-- Mostrar mensajes -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- CONTENIDO PRINCIPAL - SIN TÍTULO DUPLICADO -->
<div class="content-card">
    <form method="POST" action="" enctype="multipart/form-data">
        <!-- Tipo de Solicitud -->
        <div class="mb-4">
            <label for="id_tipo_solicitud" class="form-label required">Tipo de Solicitud</label>
            <select class="form-select" id="id_tipo_solicitud" name="id_tipo_solicitud" required>
                <option value="">Seleccione un tipo de solicitud</option>
                <?php foreach ($tipos_solicitud as $tipo): ?>
                    <option value="<?php echo $tipo['id_tipo_solicitud']; ?>">
                        <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Seleccione el tipo de solicitud que desea realizar.</div>
        </div>
        
        <!-- ========== MODIFICACIÓN 3: Servicio Relacionado con preselección ========== -->
        <div class="mb-4">
            <label for="id_servicio" class="form-label">Oferta/Beneficio Relacionado</label>
            <select class="form-select" id="id_servicio" name="id_servicio">
                <option value="">Seleccione una oferta (opcional)</option>
                <?php foreach ($servicios as $servicio): ?>
                    <option value="<?php echo $servicio['id_servicio']; ?>"
                            <?php echo ($id_servicio_preseleccionado == $servicio['id_servicio']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($servicio['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($id_servicio_preseleccionado): ?>
                <small class="text-success d-block mt-1">
                    <i class="bi bi-check-circle-fill"></i> Oferta preseleccionada desde el catálogo
                </small>
            <?php endif; ?>
            <div class="form-text">
                Seleccione la oferta o beneficio que desea solicitar. 
                Solo aparecen ofertas que requieren solicitud formal.
            </div>
        </div>
        
        <!-- Motivo -->
        <div class="mb-4">
            <label for="motivo" class="form-label required">Motivo de la Solicitud</label>
            <textarea class="form-control" id="motivo" name="motivo" rows="4" 
                      placeholder="Describa detalladamente el motivo de su solicitud..." required></textarea>
            <div class="form-text">Describa claramente por qué realiza esta solicitud.</div>
        </div>
        
        <!-- Información Adicional -->
        <div class="mb-4">
            <label for="informacion_adicional" class="form-label">Información Adicional (Opcional)</label>
            <textarea class="form-control" id="informacion_adicional" name="informacion_adicional" rows="3"
                      placeholder="Información adicional que considere relevante..."></textarea>
            <div class="form-text">Cualquier información adicional que ayude a evaluar su solicitud.</div>
        </div>
        
        <!-- Documentos Adjuntos -->
        <div class="mb-4">
            <label class="form-label">Documentos Adjuntos (Opcional)</label>
            <div class="file-upload" onclick="document.getElementById('documentos').click()">
                <i class="bi bi-cloud-upload" style="font-size: 48px; color: #6c757d;"></i>
                <h5>Arrastre y suelte archivos aquí o haga clic para seleccionar</h5>
                <p class="text-muted">Formatos permitidos: PDF, JPG, JPEG, PNG, DOC, DOCX (Máximo 5MB por archivo)</p>
                <input type="file" id="documentos" name="documentos[]" multiple 
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display: none;">
            </div>
            <div class="file-list" id="fileList"></div>
        </div>
        
        <!-- Botones -->
        <div class="d-flex justify-content-between">
            <a href="solicitudes.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Cancelar y Volver
            </a>
            <button type="submit" class="btn btn-utp">
                <i class="bi bi-send-fill"></i> Enviar Solicitud
            </button>
        </div>
    </form>
</div>

<script>
    // Manejar la visualización de archivos seleccionados
    document.getElementById('documentos').addEventListener('change', function(e) {
        const fileList = document.getElementById('fileList');
        fileList.innerHTML = '';
        
        Array.from(this.files).forEach(file => {
            const div = document.createElement('div');
            div.className = 'file-item';
            
            const fileName = document.createElement('span');
            fileName.className = 'file-name';
            fileName.textContent = file.name;
            
            const fileSize = document.createElement('span');
            fileSize.className = 'file-size';
            fileSize.textContent = formatFileSize(file.size);
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
            removeBtn.onclick = function() {
                div.remove();
            };
            
            div.appendChild(fileName);
            div.appendChild(fileSize);
            div.appendChild(removeBtn);
            fileList.appendChild(div);
        });
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
</script>

<?php
// Obtener contenido y limpiar buffer
$content = ob_get_clean();

// Incluir layout
require_once 'layout_estudiante.php';
?>