<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$queue_items = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d ORDER BY scheduled_date ASC", $campaign_id)
);

$queue_exists = !empty($queue_items);

// Verificar si hay bloqueos activos (ejecución o generación en marcha)
$is_queue_locked = AP_Bloqueo_System::is_locked('execute', $campaign_id) ||
                    AP_Bloqueo_System::is_locked('generate', $campaign_id);

/**
 * Determinar si un post debe estar bloqueado para edición
 * @param object $item Item de la cola
 * @param bool $is_queue_locked Si la cola completa está bloqueada
 * @return bool
 */
function ap_is_post_locked_ui($item, $is_queue_locked) {
    // Si la cola completa está bloqueada, todos los posts están bloqueados
    if ($is_queue_locked) {
        return true;
    }

    // Si el post ya fue completado/procesado/publicado, está bloqueado
    $locked_statuses = ['completed', 'processing', 'published'];
    if (in_array($item->status, $locked_statuses)) {
        return true;
    }

    return false;
}

// Contar estados
$pending_count = 0;
$processing_count = 0;
$completed_count = 0;

foreach ($queue_items as $item) {
    if ($item->status === 'pending') $pending_count++;
    if ($item->status === 'processing') $processing_count++;
    if ($item->status === 'completed') $completed_count++;
}

// Verificar si hay ejecución activa
$is_execution_active = AP_Bloqueo_System::is_locked('execute', $campaign_id);
$is_generation_active = AP_Bloqueo_System::is_locked('generate', $campaign_id);

// Solo mostrar "interrumpida" si hay posts en processing PERO NO hay lock activo
$has_interrupted = ($processing_count > 0) && !$is_execution_active;
$total_to_execute = $pending_count + $processing_count;
?>


<div class="wrap ap-module-wrap ap-queue-wrapper">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Cola de Posts - <?php echo esc_html($campaign->name); ?></h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div id="ap-cancel-process-container" style="flex-shrink: 0;"></div>
            <?php
            // Botón GENERAR COLA en el header (duplicado del que aparece en el contenido)
            $current_count_header = count($queue_items);
            $expected_count_header = intval($campaign->num_posts);
            $missing_count_header = max(0, $expected_count_header - $current_count_header);
            $is_complete_header = ($current_count_header >= $expected_count_header);
            $is_generation_active_header = AP_Bloqueo_System::is_locked('generate', $campaign->id);

            if ($queue_exists && !$is_complete_header && !$is_generation_active_header):
            ?>
            <button id="start-generate-queue-header"
                    data-campaign-id="<?php echo $campaign->id; ?>"
                    class="button button-primary"
                    style="background: #f97316; border-color: #f97316; color: white;"
                    onmouseover="this.style.background='#ea580c'; this.style.borderColor='#ea580c'"
                    onmouseout="this.style.background='#f97316'; this.style.borderColor='#f97316'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;">
                    <path d="M12 5v14M5 12h14"></path>
                </svg>
                Generar <?php echo $missing_count_header; ?> restante<?php echo $missing_count_header > 1 ? 's' : ''; ?>
            </button>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=autopost-campaign-edit&id=' . $campaign->id); ?>" class="button">
                Editar Campaña
            </a>
            <a href="<?php echo admin_url('admin.php?page=autopost-ia'); ?>" class="button">
                Volver a Campañas
            </a>
        </div>
    </div>

    <div class="ap-module-container ap-queue-content has-sidebar">
        <div class="ap-module-content ap-main-content">
    
    <!-- Contenedor de progreso -->
    <input type="hidden" id="expected-posts" value="<?php echo $campaign->num_posts; ?>">
    <div id="generate-progress" class="ap-section" style="display:none; background: white; border: 1px solid #e5e7eb; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 id="progress-text" style="margin:0; color:#1e293b; font-size: 15px; font-weight: 600; display: flex; align-items: center;">
                <svg class="ap-spinner" style="width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #000000; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 8px;" viewBox="0 0 24 24"></svg>
                Generando cola...
            </h3>
        </div>
        
        <div style="background: #f1f5f9; height: 6px; border-radius: 3px; overflow: hidden; margin-bottom: 10px;">
            <div id="progress-bar" style="width:0%; height:100%; background:#000000; transition:width 0.3s;"></div>
        </div>
        
        <div id="progress-message" style="font-size:13px; color:#64748b; text-align:center;">
            <span>Iniciando...</span>
        </div>
        
        <!-- Log detallado (usado por el polling) -->
        <div id="progress-log" style="margin-top:10px; max-height:150px; overflow-y:auto; font-size:12px; color:#64748b;"></div>
    </div>
    
    <?php if (!$queue_exists): ?>
        <div id="generate-queue-section" class="ap-section">
            <h2 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 600; color: #1e293b;">
                Generar Cola de Posts
            </h2>
            
            <p style="margin-bottom: 12px; color: #475569;">
                Esta campaña generará <strong><?php echo $campaign->num_posts; ?> posts</strong>.
            </p>
            <p class="description" style="margin-bottom: 24px; color: #64748b;">
                El prompt de contenido se configura en <a href="<?php echo admin_url('admin.php?page=autopost-campaign-edit&id=' . $campaign->id); ?>">Configuración de Campaña</a>.
            </p>
            
            <button id="start-generate-queue" class="ap-btn-primary" data-campaign-id="<?php echo $campaign->id; ?>">
                Generar Cola
            </button>
        </div>
        
        <!-- Tabla placeholder -->
        <div id="queue-list" class="ap-section" style="margin-top:0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #1e293b;">Cola de Posts</h2>
                <span id="queue-count" style="color: #64748b; font-size: 14px;">(0 / <?php echo $campaign->num_posts; ?>)</span>
            </div>
            
            <div class="table-wrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="wp-list-table widefat fixed striped" style="table-layout:fixed; width:100%;">
                <thead>
                    <tr>
                        <th class="check-column" style="width:40px;"></th>
                        <th style="width:70px;">Orden</th>
                        <th style="width:25%;">Título</th>
                        <th style="width:150px;">Imagen Destacada</th>
                        <th style="width:150px;">Imagen Interior</th>
                        <th style="width:100px;">Estado</th>
                        <th style="width:130px;">Fecha Programada</th>
                    </tr>
                </thead>
                <tbody id="queue-tbody">
                    <?php for ($i = 0; $i < $campaign->num_posts; $i++): ?>
                    <tr class="placeholder-row" data-position="<?php echo $i + 1; ?>" style="opacity:0.3;">
                        <td></td>
                        <td style="text-align:center;">#<?php echo $i + 1; ?></td>
                        <td colspan="5" style="color:#999; font-style:italic;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:4px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Esperando generación...
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($queue_exists): ?>
        <?php
        // Calcular si falta generar items
        $current_count = count($queue_items);
        $expected_count = intval($campaign->num_posts);
        $missing_count = max(0, $expected_count - $current_count);
        $is_complete = ($current_count >= $expected_count);
        ?>
        

        <div id="queue-list" class="ap-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #1e293b;">Cola de Posts</h2>
                    <span id="queue-count-display" style="color: #64748b; font-size: 14px;"><?php echo $current_count; ?> / <?php echo $expected_count; ?> items</span>
                </div>
                <div id="ap-cancel-process-container"></div>
            </div>

            <div style="margin: 0 0 20px 0; display: flex; gap: 12px; align-items: center;">
                <?php
                // LÓGICA BOTÓN GENERAR RESTANTES:
                // - Si cola incompleta Y NO está generando → mostrar botón "Generar N restantes" (NARANJA)
                if (!$is_complete && !$is_generation_active):
                ?>
                <button id="start-generate-queue"
                        data-campaign-id="<?php echo $campaign->id; ?>"
                        style="background: #f97316; color: white; border: none; border-radius: 10px; padding: 12px 24px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(249,115,22,0.25);"
                        onmouseover="this.style.background='#ea580c'; this.style.boxShadow='0 4px 12px rgba(249,115,22,0.35)'"
                        onmouseout="this.style.background='#f97316'; this.style.boxShadow='0 2px 8px rgba(249,115,22,0.25)'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;">
                        <path d="M12 5v14M5 12h14"></path>
                    </svg>
                    Generar <?php echo $missing_count; ?> restante<?php echo $missing_count > 1 ? 's' : ''; ?>
                </button>
                <?php endif; ?>

                <?php
                // LÓGICA BOTÓN PUBLICAR:
                // - Si está ejecutando → NO mostrar botón
                // - Si todos están completados → NO mostrar botón
                // - Si hay posts por ejecutar (pending o processing) → mostrar botón
                if (!$is_execution_active && $total_to_execute > 0):
                    // Determinar texto del botón
                    if ($completed_count > 0) {
                        // Ya hay posts publicados → "Publicar N restantes"
                        $btn_text = "Publicar $total_to_execute restante" . ($total_to_execute > 1 ? 's' : '');
                    } else {
                        // Primera vez → "Publicar Posts"
                        $btn_text = "Publicar Posts";
                    }
                ?>
                <button id="execute-queue" class="ap-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <?php echo $btn_text; ?>
                </button>
                <?php endif; ?>

                <button id="bulk-delete-queue" style="display: none; background: #dc2626; color: white; border: none; border-radius: 8px; padding: 8px 16px; font-size: 14px; cursor: pointer; font-weight: 500; transition: all 0.2s;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    Eliminar Seleccionados
                </button>
            </div>
            
            <div class="table-wrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <table class="wp-list-table widefat" style="border: none; border-radius: 12px; overflow: hidden;">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <th class="check-column" style="width:40px; padding: 16px 12px;"><input type="checkbox" id="select-all-queue"></th>
                        <th style="width:70px; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Orden</th>
                        <th style="width:30%; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Título / Keywords</th>
                        <th style="width:150px; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Imagen Destacada</th>
                        <th style="width:150px; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Imagen Interior</th>
                        <th style="width:140px; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Estado / Fecha</th>
                        <th style="width:50px; padding: 16px 12px; font-size: 13px; font-weight: 600; color: #6b7280;"></th>
                    </tr>
                </thead>
                <tbody id="queue-tbody">
                    <?php foreach ($queue_items as $index => $item):
                        // Usar template único para renderizar la fila
                        echo ap_render_queue_row($item, $index + 1, $is_queue_locked);
                    endforeach; ?>
                </tbody>
            </table>
            </div>
            </div>
            
            <?php
            // Botón inferior: misma lógica que el superior
            if (!$is_execution_active && $total_to_execute > 0):
                if ($completed_count > 0) {
                    $btn_text = "Publicar $total_to_execute restante" . ($total_to_execute > 1 ? 's' : '');
                } else {
                    $btn_text = "Publicar Posts";
                }
            ?>
            <div style="margin: 20px 0 0 0;">
                <button id="execute-queue-bottom" class="ap-btn-primary"
                        style="background: #000000 !important; color: white; border: none; border-radius: 10px; padding: 12px 24px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(59,130,246,0.25);"
                        onmouseover="this.style.background='#000000'; this.style.boxShadow='0 4px 12px rgba(59,130,246,0.35)'"
                        onmouseout="this.style.background='#000000'; this.style.boxShadow='0 2px 8px rgba(59,130,246,0.25)'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <?php echo $btn_text; ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Hacer que ambos botones ejecutar cola funcionen
jQuery(document).ready(function($) {
    $('#execute-queue, #execute-queue-bottom').on('click', function() {
        window.location.href = '<?php echo admin_url('admin.php?page=autopost-execute&campaign_id=' . $campaign->id); ?>';
    });
});
</script>

<!-- MODAL PARA VER/REGENERAR IMÁGENES -->
<div id="image-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background:#fff; border-radius:16px; max-width:1200px; width:90%; max-height:90vh; overflow:hidden; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.4); display:flex; flex-direction:row;">
        
        <!-- Botón cerrar -->
        <button id="close-modal" style="position:absolute; top:16px; right:16px; background:rgba(0,0,0,0.1); color:#333; border:none; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:24px; line-height:1; z-index:2; transition:all 0.2s; font-weight:300;" onmouseover="this.style.background='rgba(0,0,0,0.2)'" onmouseout="this.style.background='rgba(0,0,0,0.1)'">×</button>
        
        <!-- Columna izquierda: Imagen -->
        <div style="flex:1; padding:32px; display:flex; align-items:center; justify-content:center; background:#f8f9fa; position:relative;">
            <div style="position:relative; width:100%; max-width:700px;">
                <img id="modal-image" src="" style="width:100%; height:500px; object-fit:cover; display:block; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                
                <div id="modal-spinner" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);">
                    <div style="width:60px; height:60px; border:4px solid rgba(255,255,255,0.2); border-top:4px solid #2271b1; border-radius:50%; animation:spin 1s linear infinite;"></div>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha: Keywords y botones -->
        <div style="width:320px; padding:32px; background:#fff; display:flex; flex-direction:column; gap:24px; overflow-y:auto;">
            
            <div>
                <h3 style="margin:0 0 8px 0; font-size:18px; font-weight:600; color:#1a1a1a;">Palabras clave</h3>
                <p style="margin:0 0 12px 0; font-size:13px; color:#666;">Una por línea. Se usarán para buscar imágenes.</p>
                <textarea id="modal-keywords" rows="10" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:13px; font-family:inherit; resize:vertical; line-height:1.6;" placeholder="keyword1&#10;keyword2&#10;keyword3"></textarea>
            </div>
            
            <div>
                <h3 style="margin:0 0 12px 0; font-size:18px; font-weight:600; color:#1a1a1a;">Regenerar desde:</h3>
                <div id="modal-buttons" style="display:flex; flex-direction:column; gap:10px;">
                    <?php
                    // Botones de proveedores
                    $modal_providers = [];
                    if (get_option('ap_unsplash_key')) $modal_providers['unsplash'] = 'Unsplash';
                    if (get_option('ap_pixabay_key')) $modal_providers['pixabay'] = 'Pixabay';
                    if (get_option('ap_pexels_key')) $modal_providers['pexels'] = 'Pexels';
                    
                    foreach ($modal_providers as $provider => $name):
                    ?>
                        <button class="button button-primary modal-regenerate" 
                                data-provider="<?php echo $provider; ?>"
                                style="padding:14px 20px; font-size:14px; font-weight:500; border-radius:8px; border:none; background:#2271b1; color:#fff; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 8px rgba(34,113,177,0.2);" 
                                onmouseover="this.style.background='#135e96'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(34,113,177,0.3)'" 
                                onmouseout="this.style.background='#2271b1'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(34,113,177,0.2)'">
                            <?php echo $name; ?>
                        </button>
                    <?php endforeach; ?>
                    
                    <button class="button modal-library"
                            style="padding:14px 20px; font-size:14px; font-weight:500; border-radius:8px; border:1px solid #ddd; background:#fff; color:#333; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:8px; justify-content:center;"
                            onmouseover="this.style.background='#f8f9fa'; this.style.borderColor='#999'"
                            onmouseout="this.style.background='#fff'; this.style.borderColor='#ddd'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Biblioteca WordPress
                    </button>
                </div>
            </div>
            
        </div>
    </div>
        
        </div> <!-- Fin ap-module-content -->

        <!-- Sidebar de Ayuda -->
        <aside class="ap-module-sidebar ap-help-sidebar" style="background: #000000; padding: 20px; border-radius: 8px;">
            <!-- Card: ¿Qué es la Cola? -->
            <div style="margin-bottom: 20px;">
                <h3 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">¿Qué es la Cola?</h3>
                <p style="color: rgba(255,255,255,0.95); font-size: 13px; line-height: 1.6; margin: 0;">
                    La cola almacena todos los posts que se publicarán automáticamente según el calendario de tu campaña.
                </p>
            </div>
            
            <!-- Card: Proceso -->
            <div style="margin-bottom: 20px;">
                <h3 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">Proceso</h3>
                <ul style="margin: 0; padding-left: 20px; color: rgba(255,255,255,0.95); font-size: 13px; line-height: 1.8;">
                    <li><strong style="color: white;">Generar Cola:</strong> Crea títulos e imágenes para todos los posts</li>
                    <li><strong style="color: white;">Revisar:</strong> Edita títulos, keywords o regenera imágenes si necesitas</li>
                    <li><strong style="color: white;">Ejecutar:</strong> El sistema genera el contenido y publica automáticamente</li>
                </ul>
            </div>
            
            <!-- Card: Funciones -->
            <div style="margin-bottom: 20px;">
                <h3 style="color: white; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">Funciones</h3>
                <ul style="margin: 0; padding-left: 20px; color: rgba(255,255,255,0.95); font-size: 13px; line-height: 1.8;">
                    <li><strong style="color: white;">Editar Título:</strong> Click en el título para editarlo</li>
                    <li><strong style="color: white;">Editar Keywords:</strong> Click en las keywords para modificarlas</li>
                    <li><strong style="color: white;">Reordenar:</strong> Arrastra el icono para cambiar el orden</li>
                    <li><strong style="color: white;">Regenerar Imagen:</strong> Click en "Regenerar" bajo cada imagen</li>
                </ul>
            </div>
            
            <!-- Tip -->
            <div style="background: #3D4A5C; color: white; border: none; border-left: 3px solid white; padding: 12px 16px; border-radius: 6px;">
                <strong style="color: white;">Tip:</strong> Genera la cola primero para ver todos los títulos. Luego revísalos antes de ejecutar la campaña.
            </div>
        </aside> <!-- Fin ap-module-sidebar -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->

