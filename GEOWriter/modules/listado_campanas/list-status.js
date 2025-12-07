/**
 * Sistema de actualización de estados de campañas en tiempo real
 *
 * Este script se ejecuta al cargar la lista de campañas y actualiza
 * los estados y botones según la información real de las colas.
 */

jQuery(document).ready(function($) {
    console.log('[Campaign Status] Iniciando verificación de estados...');

    // Recopilar IDs de todas las campañas visibles
    const campaignIds = [];
    $('.ap-campaign-card').each(function() {
        const campaignId = $(this).find('[data-campaign-id]').first().data('campaign-id');
        if (campaignId) {
            campaignIds.push(campaignId);
        }
    });

    if (campaignIds.length === 0) {
        console.log('[Campaign Status] No hay campañas para verificar');
        return;
    }

    console.log('[Campaign Status] Verificando estados de', campaignIds.length, 'campañas');

    // Solicitar estados actualizados al servidor
    $.post(ajaxurl, {
        action: 'ap_get_campaigns_statuses',
        nonce: apList.nonce,
        campaign_ids: campaignIds
    }, function(response) {
        if (response.success && response.data) {
            console.log('[Campaign Status] Estados recibidos:', response.data);

            // Actualizar cada campaña con su estado real
            $.each(response.data, function(campaignId, status) {
                updateCampaignUI(campaignId, status);
            });

            console.log('[Campaign Status] UI actualizada correctamente');
        } else {
            console.error('[Campaign Status] Error al obtener estados:', response);
        }
    }).fail(function(xhr, status, error) {
        console.error('[Campaign Status] Error AJAX:', error);
    });

    /**
     * Actualiza la UI de una campaña según su estado
     */
    function updateCampaignUI(campaignId, status) {
        const $card = $('.ap-campaign-card').has(`[data-campaign-id="${campaignId}"]`);
        if ($card.length === 0) {
            console.warn('[Campaign Status] No se encontró card para campaña', campaignId);
            return;
        }

        const $statusCell = $card.find('.queue-status-cell');
        const $btnGroup = $card.find('.ap-btn-group');
        const $mainBtn = $btnGroup.find('.main-queue-btn, button[data-campaign-id="' + campaignId + '"]').first();

        // Actualizar estado visual (badge)
        updateStatusBadge($statusCell, status);

        // Actualizar botón principal
        updateMainButton($mainBtn, $btnGroup, status, campaignId);

        // Actualizar data-status para polling
        $statusCell.attr('data-status', status.state);

        console.log(`[Campaign Status] Campaña ${campaignId} → ${status.state}`);
    }

    /**
     * Actualiza el badge de estado
     */
    function updateStatusBadge($statusCell, status) {
        if (status.show_two_badges) {
            // Mostrar dos badges (cola + publicados)
            const html = `
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    ${status.queue_badge}
                    ${status.published_badge}
                </div>
            `;
            $statusCell.html(html);
        } else {
            // Mostrar un solo badge
            let icon = '';
            if (status.state === 'generating') {
                icon = '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid ' + status.status_color + '; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg> ';
            } else if (status.state === 'executing') {
                icon = '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid ' + status.status_color + '; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg> ';
            }

            const html = `
                <span style="background: ${status.status_bg}; color: ${status.status_color}; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                    ${icon}${status.status_label}
                </span>
            `;
            $statusCell.html(html);
        }
    }

    /**
     * Actualiza el botón principal
     */
    function updateMainButton($mainBtn, $btnGroup, status, campaignId) {
        // Eliminar botones secundarios dinámicos previos
        $btnGroup.find('.ap-btn-success, .ap-btn-warning:not(.clone-campaign)').remove();

        const queueUrl = apList.admin_url + 'admin.php?page=autopost-queue&campaign_id=' + campaignId;

        // Determinar qué botón mostrar según el estado
        let newBtnHtml = '';

        if (status.state === 'executing') {
            newBtnHtml = `<button type="button" class="ap-btn-primary main-queue-btn" disabled data-campaign-id="${campaignId}">Publicando...</button>`;
        } else if (status.state === 'generating') {
            newBtnHtml = `<button type="button" class="ap-btn-primary main-queue-btn" disabled data-campaign-id="${campaignId}">Generando Cola...</button>`;
        } else if (status.action === 'view_queue') {
            newBtnHtml = `<a href="${queueUrl}" class="ap-btn-primary main-queue-btn" data-campaign-id="${campaignId}">Ver Cola</a>`;
        } else if (status.action === 'generate_queue') {
            if (status.button_disabled) {
                newBtnHtml = `<button type="button" class="ap-btn-primary main-queue-btn" disabled style="opacity: 0.5; cursor: not-allowed;" data-campaign-id="${campaignId}">Generar Cola</button>`;
            } else {
                newBtnHtml = `<a href="${queueUrl}" class="ap-btn-primary main-queue-btn" data-campaign-id="${campaignId}">Generar Cola</a>`;
            }
        } else {
            // Otros estados
            newBtnHtml = `<a href="${queueUrl}" class="ap-btn-primary main-queue-btn" data-campaign-id="${campaignId}">${status.button_text}</a>`;
        }

        // Reemplazar o insertar botón
        if ($mainBtn.length > 0) {
            $mainBtn.replaceWith(newBtnHtml);
        } else {
            // Insertar antes de los botones de editar/clonar
            const $editBtn = $btnGroup.find('.ap-btn-edit');
            if ($editBtn.length > 0) {
                $editBtn.before(newBtnHtml);
            } else {
                $btnGroup.prepend(newBtnHtml);
            }
        }

        // Añadir botón "Publicar Posts" si es necesario
        if (status.state === 'ready_to_publish' && status.published_count < status.queue_count) {
            const postsToPublish = status.queue_count - status.published_count;
            const publishBtn = `
                <a href="${queueUrl}" class="ap-btn-success" title="Publicar ${postsToPublish} posts pendientes">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    Publicar Posts
                </a>
            `;
            $btnGroup.find('.main-queue-btn').after(publishBtn);
        }

        // Añadir botón "Completar (X)" si la cola está incompleta
        if (status.state === 'queue_incomplete' && status.missing_posts > 0) {
            const completeBtn = `
                <a href="${queueUrl}&auto_generate=${status.missing_posts}" class="ap-btn-warning" title="Ir a la cola y generar ${status.missing_posts} posts faltantes">
                    Completar (${status.missing_posts})
                </a>
            `;
            $btnGroup.find('.main-queue-btn').after(completeBtn);
        }
    }

    // Polling periódico cada 5 segundos para actualizar estados activos
    setInterval(function() {
        const activeCampaigns = [];
        $('.queue-status-cell[data-status="generating"], .queue-status-cell[data-status="executing"]').each(function() {
            const campaignId = $(this).data('campaign-id');
            if (campaignId) {
                activeCampaigns.push(campaignId);
            }
        });

        if (activeCampaigns.length > 0) {
            console.log('[Campaign Status] Actualizando', activeCampaigns.length, 'campañas activas');

            $.post(ajaxurl, {
                action: 'ap_get_campaigns_statuses',
                nonce: apList.nonce,
                campaign_ids: activeCampaigns
            }, function(response) {
                if (response.success && response.data) {
                    $.each(response.data, function(campaignId, status) {
                        updateCampaignUI(campaignId, status);
                    });
                }
            });
        }
    }, 5000);
});
