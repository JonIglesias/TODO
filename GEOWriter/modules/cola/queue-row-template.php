<?php
/**
 * Template para renderizar una fila de la cola
 * FUENTE ÚNICA DE VERDAD para el HTML de las filas
 *
 * @param object $item Post de la cola desde BD
 * @param int $position Posición en la cola (1-indexed)
 * @param bool $is_queue_locked Si la cola está bloqueada por ejecución
 * @return string HTML de la fila completa
 */
function ap_render_queue_row($item, $position, $is_queue_locked = false) {
    // Determinar si este post específico está bloqueado
    $locked_statuses = ['completed', 'processing', 'published'];
    $is_post_locked = $is_queue_locked || in_array($item->status, $locked_statuses);
    $locked_class = $is_post_locked ? 'post-locked' : '';

    // Configuración de estados con SVG
    $status_config = [
        'pending' => [
            'label' => 'Pendiente',
            'color' => '#f59e0b',
            'bg' => '#fef3c7',
            'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
        ],
        'processing' => [
            'label' => 'Generando',
            'color' => '#000000',
            'bg' => '#000000',
            'icon' => '<svg class="spin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>'
        ],
        'completed' => [
            'label' => 'Completado',
            'color' => '#10b981',
            'bg' => '#d1fae5',
            'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
        ],
        'error' => [
            'label' => 'Error',
            'color' => '#ef4444',
            'bg' => '#fee2e2',
            'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
        ]
    ];

    $config = $status_config[$item->status] ?? [
        'label' => ucfirst($item->status),
        'color' => '#6b7280',
        'bg' => '#f3f4f6',
        'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>'
    ];

    // Keywords
    $keywords = $item->image_keywords ?? '';
    $terms = array_filter(array_map('trim', explode(',', $keywords)));
    $keywords_short = strlen($keywords) > 50 ? substr($keywords, 0, 50) . '...' : $keywords;

    // URLs de imágenes
    $featured_thumb = !empty($item->featured_image_thumb) ? $item->featured_image_thumb : $item->featured_image_url;
    $featured_full = $item->featured_image_url ?? '';
    $inner_thumb = !empty($item->inner_image_thumb) ? $item->inner_image_thumb : $item->inner_image_url;
    $inner_full = $item->inner_image_url ?? '';

    // Fecha
    $date = new DateTime($item->scheduled_date);

    ob_start();
    ?>
    <tr data-queue-id="<?php echo esc_attr($item->id); ?>"
        class="<?php echo esc_attr($locked_class); ?>"
        data-locked="<?php echo $is_post_locked ? '1' : '0'; ?>"
        style="border-bottom: 1px solid #f3f4f6; transition: background 0.15s;"
        onmouseover="this.style.background='#f9fafb'"
        onmouseout="this.style.background='white'">

        <!-- Checkbox -->
        <td style="padding: 16px 12px;">
            <input type="checkbox"
                   class="queue-checkbox"
                   value="<?php echo esc_attr($item->id); ?>"
                   <?php echo ($item->status === 'completed') ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : ''; ?>>
        </td>

        <!-- Orden -->
        <td style="text-align:center; padding: 16px 12px;">
            <div class="drag-handle"
                 style="cursor:grab; padding:6px; display:inline-flex; align-items:center; justify-content:center; transition:opacity 0.2s; border-radius: 6px;"
                 title="Arrastra para reordenar"
                 onmouseover="this.style.background='#f3f4f6'"
                 onmouseout="this.style.background='transparent'">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:0.3;">
                    <circle cx="5" cy="4" r="1.2" fill="currentColor"/>
                    <circle cx="11" cy="4" r="1.2" fill="currentColor"/>
                    <circle cx="5" cy="8" r="1.2" fill="currentColor"/>
                    <circle cx="11" cy="8" r="1.2" fill="currentColor"/>
                    <circle cx="5" cy="12" r="1.2" fill="currentColor"/>
                    <circle cx="11" cy="12" r="1.2" fill="currentColor"/>
                </svg>
            </div>
            <div style="margin-top:4px; color:#9ca3af; font-size:12px; font-weight:500;">#<?php echo esc_html($position); ?></div>
        </td>

        <!-- Título / Keywords -->
        <td>
            <strong class="editable-title"
                    contenteditable="<?php echo $is_post_locked ? 'false' : 'true'; ?>"
                    data-id="<?php echo esc_attr($item->id); ?>"
                    style="display: block; padding: 4px; border-radius: 4px; transition: background 0.2s;"
                    title="<?php echo $is_post_locked ? 'Post bloqueado' : 'Click para editar'; ?>">
                <?php echo esc_html($item->title); ?>
            </strong>
            <small class="keywords-display<?php echo $is_post_locked ? '' : ' editable'; ?>"
                   data-id="<?php echo esc_attr($item->id); ?>"
                   data-keywords="<?php echo esc_attr($keywords); ?>"
                   style="<?php echo $is_post_locked ? '' : 'cursor:pointer;'; ?> color:#666; display:block; margin-top:5px; padding:4px; border-radius:3px; transition:background 0.2s;"
                   <?php if (!$is_post_locked): ?>
                   onmouseover="this.style.background='#f0f0f0';"
                   onmouseout="this.style.background='transparent';"
                   <?php endif; ?>
                   title="<?php echo $is_post_locked ? 'Post bloqueado' : 'Click para editar keywords (máx. 15 términos)'; ?>">
                <?php echo esc_html($keywords_short); ?> <span style="color:#999;">(<?php echo count($terms); ?>/15)</span>
            </small>
        </td>

        <!-- Imagen Destacada -->
        <td class="featured-image-cell" style="min-width:150px;">
            <div style="position:relative;">
                <?php if (!empty($featured_thumb)): ?>
                    <img src="<?php echo esc_url($featured_thumb); ?>"
                         class="queue-thumbnail open-image-modal"
                         data-id="<?php echo esc_attr($item->id); ?>"
                         data-type="featured"
                         data-full-url="<?php echo esc_url($featured_full); ?>"
                         style="width:100%; height:90px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="color:#999; display:none; width:100%; height:90px; background:#f5f5f5; align-items:center; justify-content:center; border-radius:4px; font-size:11px;">Error al cargar</span>
                <?php else: ?>
                    <span style="color:#999; display:flex; width:100%; height:90px; background:#f5f5f5; align-items:center; justify-content:center; border-radius:4px; font-size:11px;">Sin imagen</span>
                <?php endif; ?>

                <?php if (!$is_post_locked): ?>
                <button class="open-image-modal"
                        data-id="<?php echo esc_attr($item->id); ?>"
                        data-type="featured"
                        data-full-url="<?php echo esc_url($featured_full); ?>"
                        style="position:absolute; bottom:4px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; border:none; padding:4px 12px; border-radius:3px; font-size:11px; cursor:pointer; font-weight:600;">
                    Regenerar
                </button>
                <?php endif; ?>

                <div class="image-spinner" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:30px; height:30px; border:3px solid #f3f3f3; border-top:3px solid #2271b1; border-radius:50%; animation:spin 1s linear infinite;"></div>
            </div>
        </td>

        <!-- Imagen Interior -->
        <td class="inner-image-cell" style="min-width:150px;">
            <div style="position:relative;">
                <?php if (!empty($inner_thumb)): ?>
                    <img src="<?php echo esc_url($inner_thumb); ?>"
                         class="queue-thumbnail open-image-modal"
                         data-id="<?php echo esc_attr($item->id); ?>"
                         data-type="inner"
                         data-full-url="<?php echo esc_url($inner_full); ?>"
                         style="width:100%; height:90px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="color:#999; display:none; width:100%; height:90px; background:#f5f5f5; align-items:center; justify-content:center; border-radius:4px; font-size:11px;">Error al cargar</span>
                <?php else: ?>
                    <span style="color:#999; display:flex; width:100%; height:90px; background:#f5f5f5; align-items:center; justify-content:center; border-radius:4px; font-size:11px;">Sin imagen</span>
                <?php endif; ?>

                <?php if (!$is_post_locked): ?>
                <button class="open-image-modal"
                        data-id="<?php echo esc_attr($item->id); ?>"
                        data-type="inner"
                        data-full-url="<?php echo esc_url($inner_full); ?>"
                        style="position:absolute; bottom:4px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; border:none; padding:4px 12px; border-radius:3px; font-size:11px; cursor:pointer; font-weight:600;">
                    Regenerar
                </button>
                <?php endif; ?>

                <div class="image-spinner" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:30px; height:30px; border:3px solid #f3f3f3; border-top:3px solid #2271b1; border-radius:50%; animation:spin 1s linear infinite;"></div>
            </div>
        </td>

        <!-- Columna combinada: Estado / Fecha -->
        <td class="status-cell status-date-cell" data-queue-id="<?php echo esc_attr($item->id); ?>" data-status="<?php echo esc_attr($item->status); ?>" style="padding: 16px 12px;">
            <!-- Badge de estado -->
            <div class="status-badge" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:<?php echo esc_attr($config['bg']); ?>; color:<?php echo esc_attr($config['color']); ?>; border-radius:6px; font-size:12px; font-weight:600; width: 100%;">
                <?php echo $config['icon']; ?>
                <span class="status-label"><?php echo esc_html($config['label']); ?></span>
            </div>
            <!-- Fecha programada con icono calendario SVG -->
            <div class="scheduled-date editable-date"
                 data-id="<?php echo esc_attr($item->id); ?>"
                 data-date="<?php echo esc_attr($item->scheduled_date); ?>"
                 style="display:flex; align-items:center; gap:6px; justify-content:center; font-size:12px; color:#64748b; font-weight:500; cursor:pointer; padding:4px 8px; border-radius:4px; transition:background 0.15s;"
                 onmouseover="this.style.background='#f3f4f6'"
                 onmouseout="this.style.background='transparent'"
                 title="Click para cambiar fecha">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span><?php echo $date->format('d/m/Y H:i'); ?></span>
            </div>
        </td>

        <!-- Columna de eliminar: círculo rojo con papelera SVG -->
        <td style="padding: 16px 12px; text-align:center;">
            <?php if ($is_post_locked): ?>
                <span style="color: #9ca3af; opacity: 0.5;" title="Post bloqueado">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
            <?php else: ?>
                <button class="delete-queue-item" data-id="<?php echo esc_attr($item->id); ?>" title="Eliminar post">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}
