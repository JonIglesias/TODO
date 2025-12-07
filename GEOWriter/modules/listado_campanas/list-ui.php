<?php 
if (!defined('ABSPATH')) exit;

global $wpdb;
$campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ap_campaigns WHERE deleted_at IS NULL ORDER BY created_at DESC");

// Contar posts en cola por campaña
$queue_counts = [];
$published_counts = [];
if (!empty($campaigns)) {
    $campaign_ids = array_map(function($c) { return $c->id; }, $campaigns);
    $placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));

    // Total de posts en cola
    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT campaign_id, COUNT(*) as count FROM {$wpdb->prefix}ap_queue WHERE deleted_at IS NULL AND campaign_id IN ($placeholders) GROUP BY campaign_id",
        ...$campaign_ids
    ), OBJECT_K);
    foreach ($counts as $campaign_id => $row) {
        $queue_counts[$campaign_id] = $row->count;
    }

    // Posts publicados (status = 'completed')
    $published = $wpdb->get_results($wpdb->prepare(
        "SELECT campaign_id, COUNT(*) as count FROM {$wpdb->prefix}ap_queue WHERE deleted_at IS NULL AND status = 'completed' AND campaign_id IN ($placeholders) GROUP BY campaign_id",
        ...$campaign_ids
    ), OBJECT_K);
    foreach ($published as $campaign_id => $row) {
        $published_counts[$campaign_id] = $row->count;
    }
}

// Verificar si hay alguna generación activa para mostrar/ocultar columna de progreso
$has_active_generation = false;
if (!empty($campaigns)) {
    foreach ($campaigns as $campaign) {
        if (AP_Bloqueo_System::is_locked('generate', $campaign->id)) {
            $has_active_generation = true;
            break;
        }
    }
}
?>


<div class="wrap ap-module-wrap">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Campañas</h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <div id="ap-cancel-process-container" style="flex-shrink: 0;"></div>
        </div>
    </div>

    <div class="ap-module-container">
        <div class="ap-module-content">
            <?php if (!empty($campaigns)): ?>
            <div class="ap-campaigns-wrapper">
        <form method="post" id="campaigns-form">
            <div class="ap-campaigns-toolbar">
                <a href="<?php echo admin_url('admin.php?page=autopost-campaign-edit'); ?>" class="ap-btn-primary" style="text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 6px;"><path d="M12 5v14M5 12h14"/></svg>
                    Nueva Campaña
                </a>
                <button type="button" id="bulk-delete">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    Eliminar Seleccionados
                </button>
                <div style="margin-left: auto; color: #6B7280; font-size: 14px;">
                    <?php echo count($campaigns); ?> campañas totales
                </div>
            </div>
            
            <div class="ap-table-header">
                <div><input type="checkbox" id="cb-select-all"></div>
                <div>CAMPAÑA</div>
                <div>INICIO</div>
                <div>UDS</div>
                <div>ESTADO</div>
                <div class="progress-header">PROGRESO</div>
                <div>ACCIONES</div>
            </div>
            
            <div>
                <?php foreach ($campaigns as $campaign):
                $queue_count = isset($queue_counts[$campaign->id]) ? intval($queue_counts[$campaign->id]) : 0;
                $published_count = isset($published_counts[$campaign->id]) ? intval($published_counts[$campaign->id]) : 0;
                $expected_posts = intval($campaign->num_posts);
                $missing_posts = max(0, $expected_posts - $queue_count);
                $has_posts = $queue_count > 0;
                // Solo marcar como completa si tiene al menos 1 post Y cumple con el número esperado
                $is_queue_complete = ($queue_count > 0 && $queue_count >= $expected_posts);
                $is_generating = ($campaign->queue_generated == 2);

                // Verificar si la campaña está completa
                $is_campaign_complete = ap_is_campaign_complete($campaign);
                $missing_fields = ap_get_campaign_missing_fields($campaign);

                // Verificar si hay bloqueos activos para esta campaña
                $is_generating_locked = AP_Bloqueo_System::is_locked('generate', $campaign->id);
                $is_executing_locked = AP_Bloqueo_System::is_locked('execute', $campaign->id);

                // Determinar estado para la etiqueta
                $status_parts = [];
                if ($is_generating_locked) {
                    // ESTADO: GENERANDO COLA (bloqueo activo)
                    $status_label = 'Generando...';
                    $status_class = 'generating';
                    $status_bg = '#FEF3C7';
                    $status_color = '#92400E';
                    $status_icon = '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid #92400E; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg>';
                } elseif ($is_executing_locked) {
                    // ESTADO: EJECUTANDO/PUBLICANDO (bloqueo activo)
                    $status_label = 'Publicando...';
                    $status_class = 'executing';
                    $status_bg = '#DBEAFE';
                    $status_color = '#1E40AF';
                    $status_icon = '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid #1E40AF; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg>';
                } elseif ($published_count === $expected_posts && $is_queue_complete) {
                    // Todos publicados
                    $status_label = '✓ Todos publicados';
                    $status_class = 'all-published';
                    $status_bg = '#D1FAE5';
                    $status_color = '#065F46';
                    $status_icon = '';
                } elseif ($published_count > 0) {
                    // Hay posts publicados
                    if (!$is_queue_complete) {
                        // Cola incompleta + posts publicados
                        $status_label = $queue_count . ' de ' . $expected_posts . ' en cola | ' . $published_count . ' de ' . $expected_posts . ' publicados';
                    } else {
                        // Cola completa + posts publicados (pero no todos)
                        $status_label = $published_count . ' de ' . $expected_posts . ' publicados';
                    }
                    $status_class = 'partial-published';
                    $status_bg = '#E0E7FF';
                    $status_color = '#3730A3';
                    $status_icon = '';
                } elseif ($is_queue_complete) {
                    $status_label = '✓ Cola completa';
                    $status_class = 'complete';
                    $status_bg = '#D1FAE5';
                    $status_color = '#065F46';
                    $status_icon = '';
                } elseif ($has_posts) {
                    $status_label = $queue_count . ' de ' . $expected_posts . ' en cola';
                    $status_class = 'incomplete';
                    $status_bg = '#FEF3C7';
                    $status_color = '#92400E';
                    $status_icon = '⚠';
                } else {
                    $status_label = 'Sin cola';
                    $status_class = 'empty';
                    $status_bg = '#FEE2E2';
                    $status_color = '#991B1B';
                    $status_icon = '';
                }

                // Determinar texto del botón principal
                if ($is_generating_locked) {
                    // ESTADO: GENERANDO - Deshabilitar botón
                    $btn_text = 'Generando...';
                    $btn_class = 'ap-btn-primary';
                    $btn_disabled = true;
                    $btn_type = 'generating';
                } elseif ($is_executing_locked) {
                    // ESTADO: EJECUTANDO - Deshabilitar botón
                    $btn_text = 'Publicando...';
                    $btn_class = 'ap-btn-primary';
                    $btn_disabled = true;
                    $btn_type = 'executing';
                } elseif ($is_queue_complete) {
                    // ESTADO: COLA COMPLETA - Mostrar "Ver Cola"
                    $btn_text = 'Ver Cola';
                    $btn_class = 'ap-btn-primary';
                    $btn_disabled = false;
                    $btn_type = 'view';
                } elseif ($has_posts) {
                    // ESTADO: COLA PARCIAL - Mostrar "Ver Cola"
                    $btn_text = 'Ver Cola';
                    $btn_class = 'ap-btn-primary';
                    $btn_disabled = false;
                    $btn_type = 'view';
                } else {
                    // ESTADO: SIN COLA - Mostrar "Generar Cola"
                    $btn_text = 'Generar Cola';
                    $btn_class = 'ap-btn-primary';
                    $btn_disabled = !$is_campaign_complete; // Deshabilitar si campaña no está completa
                    $btn_type = 'generate';
                }
                ?>
                <div class="ap-campaign-card" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                    <div>
                        <input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr($campaign->id); ?>">
                    </div>
                    
                    <div>
                        <div class="ap-campaign-name"><?php echo esc_html($campaign->name); ?></div>
                    </div>
                    
                    <div style="font-size: 13px; color: #6B7280;">
                        <?php echo date('d/m/Y', strtotime($campaign->start_date)); ?>
                    </div>
                    
                    <div>
                        <span style="background: #DBEAFE; color: #1E40AF; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                            <?php echo esc_html($expected_posts); ?>
                        </span>
                    </div>

                    <div class="queue-status-cell" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" data-status="<?php echo $status_class; ?>">
                        <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                            <?php echo $status_icon . ' ' . $status_label; ?>
                        </span>
                    </div>
                    
                    <div class="progress-cell" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                        <div class="mini-progress" style="<?php echo $is_generating ? 'display:block;' : 'display:none;'; ?>">
                            <div style="background: #E5E7EB; height: 6px; border-radius: 3px; overflow: hidden;">
                                <div class="mini-progress-bar" style="width:<?php echo $is_generating ? '5' : '0'; ?>%; height:100%; background:#4A9EFF; transition: width 0.3s;"></div>
                            </div>
                            <small class="mini-progress-text" style="display:block; margin-top:4px; font-size:11px; color:#666;">
                                <?php echo $is_generating ? 'Iniciando...' : ''; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="ap-btn-group" style="min-height: 32px;">
                        <?php if ($btn_type === 'view'): ?>
                            <!-- Botón principal: Ver Cola -->
                            <a href="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>"
                               class="ap-action-btn main-queue-btn"
                               data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                               data-queue-url="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                                <span class="btn-text">Ver Cola</span>
                            </a>

                            <?php if (!$is_queue_complete): ?>
                                <!-- Botón secundario: Completar (X) - Redirige a cola con auto-generación -->
                                <a href="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id . '&auto_generate=' . $missing_posts); ?>"
                                   class="ap-action-btn warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span class="btn-text">Completar (<?php echo $missing_posts; ?>)</span>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($btn_type === 'generate'): ?>
                            <!-- Botón principal: Generar Cola -->
                            <button type="button"
                                    class="ap-action-btn main-queue-btn generate-queue-btn"
                                    data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                                    data-queue-url="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>"
                                    <?php if ($btn_disabled): ?>
                                        disabled
                                        style="opacity: 0.5; cursor: not-allowed;"
                                        title="Completa: <?php echo esc_attr(implode(', ', $missing_fields)); ?>"
                                    <?php endif; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="16"></line>
                                    <line x1="8" y1="12" x2="16" y2="12"></line>
                                </svg>
                                <span class="btn-text">Generar Cola</span>
                            </button>
                        <?php elseif ($btn_type === 'executing'): ?>
                            <!-- Estado ejecutando/publicando -->
                            <button type="button"
                                    class="ap-action-btn main-queue-btn"
                                    disabled
                                    data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                                    data-queue-url="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>">
                                <svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                                    <path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                                </svg>
                                <span class="btn-text">Publicando...</span>
                            </button>
                        <?php else: ?>
                            <!-- Estado generando -->
                            <button type="button"
                                    class="ap-action-btn main-queue-btn"
                                    disabled
                                    data-campaign-id="<?php echo esc_attr($campaign->id); ?>"
                                    data-queue-url="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>">
                                <svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                                    <path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                                </svg>
                                <span class="btn-text">Generando...</span>
                            </button>
                        <?php endif; ?>

                        <a href="<?php echo admin_url('admin.php?page=autopost-campaign-edit&id=' . $campaign->id); ?>"
                           class="ap-action-btn edit">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            <span class="btn-text">Editar</span>
                        </a>

                        <button type="button"
                                class="ap-action-btn clone clone-campaign"
                                data-id="<?php echo esc_attr($campaign->id); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span class="btn-text">Clonar</span>
                        </button>

                        <button type="button" class="ap-btn-delete-round delete-campaign" data-id="<?php echo esc_attr($campaign->id); ?>" title="Eliminar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
            <?php else: ?>
            <div class="ap-campaigns-wrapper" style="text-align: center; padding: 60px 24px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 24px; color: #9CA3AF;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <p style="color: #6B7280; margin-bottom: 20px;">No hay campañas creadas todavía</p>
                <div style="display: flex; gap: 12px; justify-content: center; align-items: center;">
                    <a href="<?php echo admin_url('admin.php?page=autopost-campaign-edit'); ?>" class="ap-btn-primary">Crear Primera Campaña</a>
                    <a href="<?php echo admin_url('admin.php?page=autopost-autopilot'); ?>" class="ap-btn-primary" style="background: #10b981; border-color: #10b981;">Crear Primera Campaña con Autopilot</a>
                </div>
            </div>
            <?php endif; ?>
        </div> <!-- Fin ap-module-content -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->
