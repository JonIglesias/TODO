<?php if (!defined('ABSPATH')) exit;

global $wpdb;

// Obtener datos de la campaña
$campaign = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE id = %d",
    $campaign_id
));

$queue_items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ap_queue WHERE campaign_id = %d ORDER BY scheduled_date ASC",
    $campaign_id
));

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

// Detectar ejecución interrumpida
$has_interrupted = $processing_count > 0;
?>


<div class="wrap ap-module-wrap ap-execute-wrapper">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Ejecutar Cola - <?php echo esc_html($campaign ? $campaign->name : 'Campaña #' . $campaign_id); ?></h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div id="ap-cancel-process-container" style="flex-shrink: 0;"></div>
            <a href="<?php echo admin_url('admin.php?page=autopost-ia'); ?>" class="button" style="background: white; color: #3D4A5C; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; text-decoration: none;">
                Volver a Campañas
            </a>
        </div>
    </div>

    <div class="ap-module-container ap-execute-content">
        <div class="ap-module-content ap-main-content">
            <?php if ($campaign_id && ($pending_count > 0 || $processing_count > 0)): ?>

                <?php
                // Definir $total_to_execute ANTES de cualquier condicional para que esté disponible en JavaScript
                $total_to_execute = $pending_count + $processing_count;
                ?>

                <!-- Info section: SOLO mostrar si NO hay ejecución activa -->
                <?php if (!$is_execution_active): ?>
                <div class="ap-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #1e293b;">Ejecutar Cola</h2>
                        <span style="background: #000000; color: #000000; padding: 6px 12px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                            <?php echo $total_to_execute; ?> posts pendientes
                        </span>
                    </div>

                    <p style="margin: 0 0 16px 0; color: #64748b; font-size: 14px;">
                        Se generará el contenido con IA y se programarán los posts.
                        <?php if ($completed_count > 0): ?>
                            <strong style="color: #10b981;"><?php echo $completed_count; ?> posts ya completados.</strong>
                        <?php endif; ?>
                        Tiempo estimado: <strong><?php echo $total_to_execute; ?> minutos</strong>.
                    </p>

                    <?php
                    // Determinar texto del botón
                    if ($completed_count > 0) {
                        // Ya hay posts publicados → "Ejecutar N restantes"
                        $btn_text = "Ejecutar $total_to_execute restante" . ($total_to_execute > 1 ? 's' : '');
                    } else {
                        // Primera vez → "Ejecutar Cola"
                        $btn_text = "Ejecutar Cola ($total_to_execute posts)";
                    }
                    ?>
                    <button id="start-execution"
                            class="ap-btn-primary"
                            data-campaign-id="<?php echo $campaign_id; ?>">
                        <?php echo $btn_text; ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Barra de progreso global: mostrar si hay ejecución activa -->
                <div id="global-progress" class="ap-section" style="display:<?php echo $is_execution_active ? 'block' : 'none'; ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="margin:0; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center;">
                            <svg class="ap-spinner" style="width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #000000; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 8px;" viewBox="0 0 24 24"></svg>
                            Ejecutando cola
                        </h3>
                        <span id="progress-count" style="font-size:15px; font-weight:600; color:#64748b;">
                            <?php echo $completed_count; ?> / <?php echo count($queue_items); ?>
                        </span>
                    </div>
                    <div style="background:#f1f5f9; height:6px; border-radius:3px; overflow:hidden;">
                        <div id="global-progress-bar" style="background:#000000; height:100%; width:<?php echo count($queue_items) > 0 ? round(($completed_count / count($queue_items)) * 100) : 0; ?>%; transition:width 0.3s;"></div>
                    </div>
                    <div style="text-align: center; margin-top: 10px;">
                        <span id="progress-percentage" style="color:#64748b; font-size:13px; font-weight:600;">
                            <?php echo count($queue_items) > 0 ? round(($completed_count / count($queue_items)) * 100) : 0; ?>%
                        </span>
                    </div>
                </div>
                
                <!-- Tabla de cola -->
                <div class="ap-section">
                    <h2 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1e293b;">Posts en Cola</h2>
                    
                    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                        <table class="wp-list-table widefat" style="border: none;">
                            <thead>
                                <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                    <th style="width:50px; padding: 12px; font-size: 13px; font-weight: 600; color: #6b7280;">#</th>
                                    <th style="padding: 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Título</th>
                                    <th style="width:150px; padding: 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Estado</th>
                                    <th style="width:130px; padding: 12px; font-size: 13px; font-weight: 600; color: #6b7280;">Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="queue-table">
                                <?php foreach ($queue_items as $index => $item): ?>
                                    <tr data-queue-id="<?php echo $item->id; ?>" class="queue-row status-<?php echo $item->status; ?>" style="border-bottom: 1px solid #f3f4f6;">
                                        <td style="text-align:center; padding: 12px; font-weight:600; color:#9ca3af;"><?php echo $index + 1; ?></td>
                                        <td style="padding: 12px;">
                                            <strong style="color: #1e293b; font-size: 14px;"><?php echo esc_html($item->title); ?></strong>
                                            <div class="row-spinner" style="display:<?php echo $item->status === 'processing' ? 'block' : 'none'; ?>; margin-top:8px;">
                                                <div style="display:inline-flex; align-items:center; gap:8px; color:#64748b; font-size: 13px;">
                                                    <div style="width:14px; height:14px; border:2px solid #e5e7eb; border-top:2px solid #000000; border-radius:50%; animation:spin 1s linear infinite;"></div>
                                                    <span class="spinner-text">Generando contenido...</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 12px;">
                                            <?php
                                            $status_config = [
                                                'pending' => [
                                                    'label' => 'Pendiente', 
                                                    'color' => '#f59e0b', 
                                                    'bg' => '#fef3c7',
                                                    'icon' => '⏳'
                                                ],
                                                'processing' => [
                                                    'label' => 'Ejecutando', 
                                                    'color' => '#000000', 
                                                    'bg' => '#000000',
                                                    'icon' => '⚙️'
                                                ],
                                                'completed' => [
                                                    'label' => 'Completado', 
                                                    'color' => '#10b981', 
                                                    'bg' => '#d1fae5',
                                                    'icon' => '✅'
                                                ],
                                                'error' => [
                                                    'label' => 'Error', 
                                                    'color' => '#ef4444', 
                                                    'bg' => '#fee2e2',
                                                    'icon' => '❌'
                                                ]
                                            ];
                                            $config = $status_config[$item->status] ?? $status_config['pending'];
                                            ?>
                                            <span class="status-badge" 
                                                  data-status="<?php echo $item->status; ?>"
                                                  style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:<?php echo $config['bg']; ?>; color:<?php echo $config['color']; ?>; border-radius:6px; font-size:13px; font-weight:600;">
                                                <span class="status-icon"><?php echo $config['icon']; ?></span>
                                                <span class="status-text"><?php echo $config['label']; ?></span>
                                            </span>
                                        </td>
                                        <td style="padding: 12px; font-size: 13px; color: #64748b;">
                                            <?php 
                                            $date = new DateTime($item->scheduled_date);
                                            echo $date->format('d/m/Y H:i');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    let totalPosts = <?php echo count($queue_items); ?>;
                    let processedPosts = <?php echo $completed_count; ?>;
                    let isExecuting = false;
                    let pollingInterval;
                    
                    // Configuración de estados con colores, iconos y labels completos
                    const statusConfig = {
                        'pending': {
                            label: 'Pendiente',
                            color: '#f59e0b',
                            bg: '#fef3c7',
                            icon: '⏳'
                        },
                        'processing': {
                            label: 'Ejecutando',
                            color: '#000000',
                            bg: '#000000',
                            icon: '⚙️'
                        },
                        'completed': {
                            label: 'Completado',
                            color: '#10b981',
                            bg: '#d1fae5',
                            icon: '✅'
                        },
                        'error': {
                            label: 'Error',
                            color: '#ef4444',
                            bg: '#fee2e2',
                            icon: '❌'
                        }
                    };

                    // Función para actualizar estados desde la base de datos
                    function pollQueueStatus() {
                        console.log('[Polling] Iniciando pollQueueStatus...');

                        $.post(ajaxurl, {
                            action: 'ap_get_queue_status',
                            nonce: '<?php echo wp_create_nonce('ap_nonce'); ?>',
                            campaign_id: <?php echo $campaign_id; ?>
                        })
                        .done(function(response) {
                            console.log('[Polling] Respuesta recibida:', response);

                            if (response.success && response.data.items) {
                                console.log('[Polling] Actualizando ' + response.data.items.length + ' posts');

                                $.each(response.data.items, function(i, item) {
                                    const row = $('tr[data-queue-id="' + item.id + '"]');
                                    if (row.length) {
                                        updateRowStatus(row, item.status);
                                    }
                                });

                                // Contar completados
                                const completed = response.data.items.filter(item => item.status === 'completed').length;
                                const pending = response.data.items.filter(item => item.status === 'pending' || item.status === 'processing').length;
                                processedPosts = completed;

                                const percentage = Math.round((completed / totalPosts) * 100);
                                $('#global-progress-bar').css('width', percentage + '%');
                                $('#progress-percentage').text(percentage + '%');
                                $('#progress-count').text(completed + ' / ' + totalPosts);

                                console.log('[Polling] Progreso: ' + completed + '/' + totalPosts + ' (' + percentage + '%)');

                                // DETECTAR SI TODO ESTÁ COMPLETADO AL 100%
                                if (completed === totalPosts && pending === 0 && percentage === 100) {
                                    console.log('[Polling] ✅ Ejecución completada, deteniendo polling');

                                    // Detener polling
                                    if (pollingInterval) {
                                        clearInterval(pollingInterval);
                                        pollingInterval = null;
                                    }

                                    // Mostrar mensaje de completado
                                    $('#progress-count').html('<strong style="color:#10b981;">Ejecución Completada</strong>');

                                    // Ocultar barra de progreso después de 2 segundos
                                    setTimeout(function() {
                                        $('#global-progress').fadeOut(500);
                                    }, 2000);
                                }
                            } else {
                                console.warn('[Polling] Respuesta sin datos válidos:', response);
                            }
                        })
                        .fail(function(xhr, status, error) {
                            console.error('[Polling] ❌ Error en AJAX:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                        });
                    }
                    
                    // Función mejorada para actualizar estado de fila
                    function updateRowStatus(row, status) {
                        const config = statusConfig[status] || statusConfig['pending'];
                        const badge = row.find('.status-badge');
                        
                        // Actualizar clases de la fila
                        row.removeClass('status-pending status-processing status-completed status-error')
                           .addClass('status-' + status);
                        
                        // Actualizar badge completo (color, background, icono y texto)
                        badge.css({
                            'background': config.bg,
                            'color': config.color
                        }).attr('data-status', status);
                        
                        badge.find('.status-icon').text(config.icon);
                        badge.find('.status-text').text(config.label);
                        
                        // Mostrar/ocultar spinner
                        if (status === 'processing') {
                            row.find('.row-spinner').show();
                        } else {
                            row.find('.row-spinner').hide();
                        }
                    }
                    
                    // Si hay ejecución activa O items en processing, iniciar polling automáticamente
                    <?php if ($is_execution_active || $has_interrupted): ?>
                    console.log('[Init] 🔄 Ejecución activa detectada - Iniciando polling automático');
                    console.log('[Init] is_execution_active: <?php echo $is_execution_active ? "true" : "false"; ?>');
                    console.log('[Init] has_interrupted: <?php echo $has_interrupted ? "true" : "false"; ?>');

                    // Mostrar progreso y activar polling
                    $('#global-progress').show();
                    isExecuting = true;

                    // Llamar al polling INMEDIATAMENTE para actualizar estado actual
                    console.log('[Init] Llamando a pollQueueStatus() inmediatamente...');
                    pollQueueStatus();

                    // Luego iniciar intervalo para seguir actualizando cada 3 segundos
                    pollingInterval = setInterval(pollQueueStatus, 3000);
                    console.log('[Init] Intervalo de polling iniciado (cada 3 segundos)');

                    // Auto-cerrar alerta después de que el usuario vea el mensaje
                    <?php if ($has_interrupted): ?>
                    setTimeout(function() {
                        $('.interrupted-alert').fadeOut(300);
                    }, 8000);
                    <?php endif; ?>
                    <?php else: ?>
                    console.log('[Init] ⚠️ NO hay ejecución activa - Polling NO iniciado');
                    console.log('[Init] is_execution_active: <?php echo $is_execution_active ? "true" : "false"; ?>');
                    console.log('[Init] has_interrupted: <?php echo $has_interrupted ? "true" : "false"; ?>');
                    <?php endif; ?>
                    
                    $('#start-execution').on('click', function() {
                        const totalToExecute = <?php echo $total_to_execute; ?>;
                        const isInterrupted = <?php echo $has_interrupted ? 'true' : 'false'; ?>;
                        
                        let confirmMsg = 'Se procesarán ' + totalToExecute + ' posts.\n\nTiempo estimado: ' + totalToExecute + ' minutos.';

                        if (isInterrupted) {
                            confirmMsg = 'ATENCIÓN: Se retomará la ejecución interrumpida.\n\n' + confirmMsg;
                        }
                        
                        if (!confirm('¿Ejecutar cola ahora?\n\n' + confirmMsg)) return;
                        
                        const btn = $(this);
                        const campaignId = btn.data('campaign-id');
                        
                        btn.prop('disabled', true).html('Ejecutando...');
                        $('#global-progress').show();
                        isExecuting = true;
                        
                        // Ocultar alerta si existe
                        $('.interrupted-alert').fadeOut(300);
                        
                        // Iniciar polling cada 3 segundos
                        if (pollingInterval) clearInterval(pollingInterval);
                        pollingInterval = setInterval(pollQueueStatus, 3000);
                        
                        const eventSource = new EventSource(
                            ajaxurl + '?action=ap_execute_queue&campaign_id=' + campaignId + '&nonce=<?php echo wp_create_nonce('ap_nonce'); ?>'
                        );
                        
                        let lastEventTime = Date.now();
                        
                        // Detectar si SSE se congela
                        const heartbeatCheck = setInterval(function() {
                            if (isExecuting && (Date.now() - lastEventTime) > 70000) {
                                console.log('SSE parece congelado, confiando en polling...');
                            }
                        }, 10000);
                        
                        eventSource.onmessage = function(event) {
                            lastEventTime = Date.now();
                            
                            try {
                                const data = JSON.parse(event.data);
                                
                                // Actualizar fila específica
                                if (data.queue_id) {
                                    const row = $('tr[data-queue-id="' + data.queue_id + '"]');
                                    updateRowStatus(row, data.item_status);
                                    
                                    if (data.item_status === 'completed') {
                                        processedPosts++;
                                    }
                                    
                                    // Actualizar texto del spinner
                                    if (data.spinner_text) {
                                        row.find('.spinner-text').text(data.spinner_text);
                                    }
                                    
                                    // Actualizar progreso global
                                    const percentage = Math.round((processedPosts / totalPosts) * 100);
                                    $('#global-progress-bar').css('width', percentage + '%');
                                    $('#progress-percentage').text(percentage + '%');
                                    $('#progress-count').text(processedPosts + ' / ' + totalPosts);
                                }
                                
                                // Completado
                                if (data.status === 'done') {
                                    isExecuting = false;
                                    clearInterval(pollingInterval);
                                    clearInterval(heartbeatCheck);
                                    eventSource.close();

                                    $('#global-progress-bar').css('width', '100%');
                                    $('#progress-percentage').text('100%');

                                    // Mensaje diferente si hay pendientes
                                    if (data.remaining && data.remaining > 0) {
                                        $('#progress-count').html(
                                            '<strong style="color:#f59e0b;">ATENCIÓN: ' + data.message + '</strong>'
                                        );
                                        btn.html('Ejecución Incompleta').css('background', '#f59e0b');

                                        // Ocultar barra de progreso después de 3 segundos
                                        setTimeout(function() {
                                            $('#global-progress').fadeOut(500);
                                        }, 3000);

                                        alert(data.message + '\n\nPuedes volver a ejecutar para completar los posts restantes.');
                                    } else {
                                        $('#progress-count').html(
                                            '<strong style="color:#10b981;">Completado: ' + processedPosts + ' / ' + totalPosts + '</strong>'
                                        );
                                        btn.html('Ejecución Completada').css('background', '#10b981');

                                        // Ocultar barra y redirigir cuando TODO está completado
                                        setTimeout(function() {
                                            $('#global-progress').fadeOut(500, function() {
                                                window.location.href = '<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign_id); ?>';
                                            });
                                        }, 3000);
                                    }
                                }
                                
                                // Error general
                                if (data.status === 'error') {
                                    isExecuting = false;
                                    clearInterval(pollingInterval);
                                    clearInterval(heartbeatCheck);
                                    eventSource.close();
                                    alert('ERROR: ' + data.message);
                                    btn.prop('disabled', false).html('Ejecutar Cola');
                                }
                            } catch (e) {
                                console.error('Error parsing SSE data:', e, event.data);
                            }
                        };
                        
                        eventSource.onerror = function(error) {
                            console.warn('SSE Error - Continuando con polling', error);
                            // NO cerrar la conexión inmediatamente
                            // El polling seguirá funcionando
                            
                            setTimeout(function() {
                                if (isExecuting) {
                                    console.log('SSE perdido pero polling activo');
                                }
                            }, 180000);
                        };
                    });
                    
                    // Guardar estado de ejecución en localStorage
                    window.addEventListener('beforeunload', function() {
                        if (isExecuting) {
                            localStorage.setItem('ap_executing_campaign', <?php echo $campaign_id; ?>);
                        }
                    });
                    
                    // Restaurar polling si se estaba ejecutando antes de recargar
                    const wasExecuting = localStorage.getItem('ap_executing_campaign');
                    if (wasExecuting == <?php echo $campaign_id; ?> && !isExecuting && <?php echo $has_interrupted ? 'true' : 'false'; ?>) {
                        console.log('Restaurando polling de ejecución interrumpida...');
                        isExecuting = false; // No marcar como ejecutando para no bloquear botón
                        if (!pollingInterval) {
                            pollingInterval = setInterval(pollQueueStatus, 3000);
                        }
                    }
                });
                </script>
                
                <?php else: ?>
                    <div class="ap-section">
                        <p style="color: #64748b; text-align: center; padding: 40px 20px;">No hay posts pendientes en esta campaña.</p>
                    </div>
                <?php endif; ?>


        </div> <!-- Fin ap-module-content -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->
