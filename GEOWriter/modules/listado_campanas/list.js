jQuery(document).ready(function($) {
    
    // ============================================
    // HELPERS
    // ============================================
    function toggleBulkDelete() {
        const selected = $('input[name="campaign_ids[]"]:checked').length;
        if (selected > 0) {
            $('#bulk-delete').addClass('has-selection').css('display', 'block').fadeIn(200);
        } else {
            $('#bulk-delete').removeClass('has-selection').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
        }
    }
    
    /**
     * Actualizar UI de una campaña
     */
    function updateCampaignUI(campaignId, data) {
        const row = $(`.ap-campaign-card[data-campaign-id="${campaignId}"]`);
        const statusCell = row.find('.queue-status-cell');
        const progressCell = row.find('.progress-cell');
        const miniProgress = progressCell.find('.mini-progress');
        const miniBar = miniProgress.find('.mini-progress-bar');
        const miniText = miniProgress.find('.mini-progress-text');
        const btnGroup = row.find('.ap-btn-group');
        
        const current = data.current || 0;
        const expected = data.expected || 10;
        const missing = Math.max(0, expected - current);
        const isComplete = (current >= expected);
        const progress = Math.min(100, (current / expected) * 100);
        
        // Actualizar barra de progreso
        miniBar.css('width', progress + '%');
        miniText.text(`Generando... ${current} / ${expected} posts`);
        
        // Actualizar estado
        if (isComplete) {
            statusCell.html('<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Cola completa</span>');
            statusCell.data('status', 'complete');

            // Ocultar barra de progreso cuando termina
            setTimeout(function() {
                miniProgress.fadeOut();
            }, 1000);
        } else if (current > 0) {
            statusCell.html(`<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">⚠ ${current} de ${expected} en cola</span>`);
            statusCell.data('status', 'incomplete');
        }
        
        // Actualizar botones
        const queueUrl = apCampaigns.admin_url + 'admin.php?page=autopost-queue&campaign_id=' + campaignId;
        
        if (isComplete) {
            // Cola completa: solo "Ver Cola"
            btnGroup.find('.main-queue-btn').replaceWith(
                `<a href="${queueUrl}" class="ap-action-btn main-queue-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    <span class="btn-text">Ver Cola</span>
                </a>`
            );
            btnGroup.find('.ap-action-btn.warning').remove();
        } else if (current > 0) {
            // Cola incompleta: "Ver Cola" + "Completar (X)"
            const mainBtn = btnGroup.find('.main-queue-btn');
            if (mainBtn.length === 0 || mainBtn.is('button')) {
                btnGroup.prepend(`<a href="${queueUrl}" class="ap-action-btn main-queue-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    <span class="btn-text">Ver Cola</span>
                </a>`);
                mainBtn.remove();
            }

            let completeBtn = btnGroup.find('.ap-action-btn.warning');
            if (completeBtn.length === 0) {
                $(`<a href="${queueUrl}&auto_generate=${missing}" class="ap-action-btn warning">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span class="btn-text">Completar (${missing})</span>
                </a>`)
                    .insertAfter(btnGroup.find('.main-queue-btn'));
            } else {
                completeBtn.attr('href', `${queueUrl}&auto_generate=${missing}`)
                    .html(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span class="btn-text">Completar (${missing})</span>`);
            }
        }
    }
    
    /**
     * Iniciar proceso de generación de cola
     */
    function startQueueGeneration(campaignId, isComplete = false) {
        const row = $(`.ap-campaign-card[data-campaign-id="${campaignId}"]`);
        const statusCell = row.find('.queue-status-cell');
        const progressCell = row.find('.progress-cell');
        const miniProgress = progressCell.find('.mini-progress');
        const miniBar = miniProgress.find('.mini-progress-bar');
        const miniText = miniProgress.find('.mini-progress-text');
        const btnGroup = row.find('.ap-btn-group');
        
        // Actualizar estado a "Generando..."
        statusCell.html('<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">' +
            '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid #92400E; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg>' +
            'Generando...' +
            '</span>');
        statusCell.data('status', 'generating');
        
        // Mostrar barra de progreso
        miniProgress.show();
        miniBar.css('width', '5%');
        miniText.text('Iniciando generación...');
        
        // Deshabilitar botón principal
        const mainBtn = btnGroup.find('.main-queue-btn');
        if (mainBtn.is('button')) {
            mainBtn.prop('disabled', true).attr('title', 'Generando cola...').html(`<svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2">
                <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                <path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
            </svg>`);
        } else {
            mainBtn.replaceWith(`<button type="button" class="ap-action-btn main-queue-btn" disabled title="Generando cola...">
                <svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <circle cx="12" cy="12" r="10" opacity="0.25"></circle>
                    <path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                </svg>
            </button>`);
        }
        
        // Ocultar botón "Completar" si existe
        btnGroup.find('.complete-queue-btn').hide();
        
        // Iniciar AJAX
        $.ajax({
            url: apCampaigns.ajax_url,
            type: 'POST',
            timeout: 300000, // 5 minutos
            data: {
                action: 'ap_generate_queue',
                nonce: apCampaigns.nonce,
                campaign_id: campaignId,
                force_complete: isComplete ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    // Éxito: actualizar a cola completa
                    miniBar.css('width', '100%');
                    miniText.text('✅ Cola generada');
                    
                    statusCell.html('<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Cola completa</span>');
                    statusCell.data('status', 'complete');
                    
                    const queueUrl = apCampaigns.admin_url + 'admin.php?page=autopost-queue&campaign_id=' + campaignId;
                    btnGroup.find('.main-queue-btn').replaceWith(`<a href="${queueUrl}" class="ap-action-btn main-queue-btn" title="Ver Cola">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                        <span class="btn-text">Ver Cola</span>
                    </a>`);
                    btnGroup.find('.complete-queue-btn').remove();
                    
                    setTimeout(function() {
                        miniProgress.fadeOut();
                    }, 2000);
                } else {
                    // Error
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Error desconocido';
                    
                    miniText.html('<span style="color:red;">❌ ' + errorMsg + '</span>');
                    statusCell.html('<span style="color: red;">✗ Error</span>');
                    
                    // Restaurar botón
                    btnGroup.find('.main-queue-btn').replaceWith(
                        '<button type="button" class="ap-action-btn main-queue-btn generate-queue-btn" data-campaign-id="' + campaignId + '" title="Generar Cola">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<circle cx="12" cy="12" r="10"></circle>' +
                        '<line x1="12" y1="8" x2="12" y2="16"></line>' +
                        '<line x1="8" y1="12" x2="16" y2="12"></line>' +
                        '</svg>' +
                        '<span class="btn-text">Generar Cola</span>' +
                        '</button>'
                    );
                    
                    // Si permite completar, preguntar
                    if (response.data && response.data.allow_complete) {
                        if (confirm(errorMsg + '\n\n¿Quieres completar la cola ahora?')) {
                            startQueueGeneration(campaignId, true);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                miniText.html('<span style="color:red;">❌ Error: ' + error + '</span>');
                statusCell.html('<span style="color: red;">✗ Error</span>');
                btnGroup.find('.main-queue-btn').replaceWith(
                    '<button type="button" class="ap-action-btn main-queue-btn generate-queue-btn" data-campaign-id="' + campaignId + '" title="Generar Cola">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<circle cx="12" cy="12" r="10"></circle>' +
                    '<line x1="12" y1="8" x2="12" y2="16"></line>' +
                    '<line x1="8" y1="12" x2="16" y2="12"></line>' +
                    '</svg>' +
                    '<span class="btn-text">Generar Cola</span>' +
                    '</button>'
                );
            }
        });
        
        // Iniciar polling de progreso
        let pollingInterval = setInterval(function() {
            $.post(apCampaigns.ajax_url, {
                action: 'ap_check_queue_detailed_progress',
                nonce: apCampaigns.nonce,
                campaign_id: campaignId
            }).done(function(response) {
                if (response.success) {
                    updateCampaignUI(campaignId, response.data);
                    
                    // Si terminó, detener polling
                    if (response.data.current >= response.data.expected) {
                        clearInterval(pollingInterval);
                    }
                }
            });
        }, 3000);
        
        // Detener polling después de 5 minutos
        setTimeout(function() {
            clearInterval(pollingInterval);
        }, 300000);
    }
    
    // ============================================
    // EVENT HANDLERS
    // ============================================
    
    // Selector todos
    $('#cb-select-all').on('change', function() {
        $('input[name="campaign_ids[]"]').prop('checked', $(this).prop('checked'));
        toggleBulkDelete();
    });
    
    // Checkboxes individuales
    $(document).on('change', 'input[name="campaign_ids[]"]', function() {
        toggleBulkDelete();
    });
    
    // Generar Cola (botón principal cuando no hay cola)
    $(document).on('click', '.generate-queue-btn', function() {
        const campaignId = $(this).data('campaign-id');
        startQueueGeneration(campaignId, false);
    });
    
    // Eliminar individual
    $('.delete-campaign').on('click', function() {
        if (!confirm(apCampaigns.confirm_delete)) return;
        
        const btn = $(this);
        const campaignId = btn.data('id');
        const row = btn.closest('.ap-campaign-card');
        
        btn.prop('disabled', true).text('Eliminando...');
        
        $.ajax({
            url: apCampaigns.ajax_url,
            type: 'POST',
            data: {
                action: 'ap_delete_campaign',
                nonce: apCampaigns.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() { $(this).remove(); });
                } else {
                    const errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Error al eliminar la campaña';
                    alert(errorMsg);
                    btn.prop('disabled', false).text('Eliminar');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error eliminando campaña:', error);
                alert('Error de conexión al eliminar la campaña');
                btn.prop('disabled', false).text('Eliminar');
            }
        });
    });
    
    // Eliminar múltiples
    $('#bulk-delete').on('click', function() {
        const selected = $('input[name="campaign_ids[]"]:checked');
        
        if (selected.length === 0) {
            alert('Selecciona al menos una campaña');
            return;
        }
        
        if (!confirm(apCampaigns.confirm_delete_multiple)) return;
        
        const ids = selected.map(function() { return $(this).val(); }).get();
        
        $.ajax({
            url: apCampaigns.ajax_url,
            type: 'POST',
            data: {
                action: 'ap_delete_campaigns',
                nonce: apCampaigns.nonce,
                campaign_ids: ids
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    const errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Error al eliminar las campañas';
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error eliminando campañas:', error);
                alert('Error de conexión al eliminar las campañas');
            }
        });
    });
    
    // Clonar
    $('.clone-campaign').on('click', function() {
        const btn = $(this);
        const campaignId = btn.data('id');
        
        btn.prop('disabled', true).text('Clonando...');
        
        $.ajax({
            url: apCampaigns.ajax_url,
            type: 'POST',
            data: {
                action: 'ap_clone_campaign',
                nonce: apCampaigns.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    const errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Error al clonar la campaña';
                    alert(errorMsg);
                    btn.prop('disabled', false).text('Clonar');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error clonando campaña:', error);
                alert('Error de conexión al clonar la campaña');
                btn.prop('disabled', false).text('Clonar');
            }
        });
    });
    
    // ============================================
    // POLLING GLOBAL - CONSULTAR ESTADO DE TODAS LAS CAMPAÑAS
    // ============================================
    /**
     * Consulta el estado de TODAS las campañas visibles cada 3 segundos
     * para detectar cambios (abortos, completados, etc.) en tiempo real
     */
    function pollAllCampaignsStatus() {
        // Primero verificar bloqueos globales
        $.post(apCampaigns.ajax_url, {
            action: 'ap_check_lock_status',
            nonce: apCampaigns.nonce
        }).done(function(lockResponse) {
            if (!lockResponse.success) return;

            const generateLocked = lockResponse.data.generate_locked;
            const executeLocked = lockResponse.data.execute_locked;
            const generateCampaignId = lockResponse.data.generate_info ? lockResponse.data.generate_info.campaign_id : null;
            const executeCampaignId = lockResponse.data.execute_info ? lockResponse.data.execute_info.campaign_id : null;

            // Consultar estado de TODAS las campañas visibles
            $('.ap-campaign-card').each(function() {
                const $card = $(this);
                const campaignId = $card.data('campaign-id');

                if (!campaignId) return;

                // Determinar si esta campaña tiene bloqueo activo
                const hasGenerateLock = (generateLocked && generateCampaignId == campaignId);
                const hasExecuteLock = (executeLocked && executeCampaignId == campaignId);

                // Consultar estado detallado de esta campaña
                $.post(apCampaigns.ajax_url, {
                    action: 'ap_check_queue_detailed_progress',
                    nonce: apCampaigns.nonce,
                    campaign_id: campaignId
                }).done(function(response) {
                    if (!response.success) return;

                    const data = response.data;
                    const current = data.current || 0;
                    const expected = data.expected || 0;
                    const completed = data.completed || 0;
                    const isComplete = (current >= expected && expected > 0);

                    // Actualizar UI según estado real
                    updateCampaignUIComplete($card, {
                        current: current,
                        expected: expected,
                        completed: completed,
                        isComplete: isComplete,
                        hasGenerateLock: hasGenerateLock,
                        hasExecuteLock: hasExecuteLock
                    });
                });
            });
        });
    }

    /**
     * Actualizar UI COMPLETA de una campaña según su estado real
     */
    function updateCampaignUIComplete($card, state) {
        const campaignId = $card.data('campaign-id');
        const statusCell = $card.find('.queue-status-cell');
        const progressCell = $card.find('.progress-cell');
        const miniProgress = progressCell.find('.mini-progress');
        const miniBar = miniProgress.find('.mini-progress-bar');
        const miniText = miniProgress.find('.mini-progress-text');
        const btnGroup = $card.find('.ap-btn-group');
        const queueUrl = apCampaigns.admin_url + 'admin.php?page=autopost-queue&campaign_id=' + campaignId;

        const current = state.current;
        const expected = state.expected;
        const completed = state.completed;
        const isComplete = state.isComplete;
        const missing = Math.max(0, expected - current);
        const hasGenerateLock = state.hasGenerateLock;
        const hasExecuteLock = state.hasExecuteLock;

        // === ACTUALIZAR ESTADO ===
        // IMPORTANTE: El orden de las condiciones importa
        if (hasGenerateLock) {
            // GENERANDO (bloqueo activo) - SIEMPRE mostrar "Generando..." sin números
            statusCell.html('<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">' +
                '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid #92400E; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg>' +
                'Generando cola' +
                '</span>');
            statusCell.data('status', 'generating');

            // Mostrar barra de progreso
            const progress = Math.min(100, (current / expected) * 100);
            miniBar.css('width', progress + '%');
            miniText.text(`Generando... ${current} / ${expected} posts`);
            miniProgress.show();
        } else if (hasExecuteLock) {
            // EJECUTANDO (bloqueo activo)
            statusCell.html('<span style="background: #DBEAFE; color: #1E40AF; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">' +
                '<svg class="ap-spinner" style="width: 14px; height: 14px; border: 2px solid #1E40AF; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;" viewBox="0 0 24 24"></svg>' +
                'Publicando...' +
                '</span>');
            statusCell.data('status', 'executing');
            miniProgress.hide();
        } else if (completed >= expected && expected > 0) {
            // TODOS PUBLICADOS (completados >= esperados)
            statusCell.html('<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Todos publicados</span>');
            statusCell.data('status', 'all-published');
            miniProgress.hide();
        } else if (completed > 0 && completed < expected) {
            // PUBLICACIÓN PARCIAL (algunos publicados, pero no todos)
            if (isComplete) {
                // Cola completa pero solo algunos publicados
                statusCell.html(`<span style="background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">Cola completa | ${completed} de ${expected} publicados</span>`);
            } else {
                // Cola incompleta con algunos publicados
                statusCell.html(`<span style="background: #E0E7FF; color: #3730A3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">${current} de ${expected} en cola | ${completed} publicados</span>`);
            }
            statusCell.data('status', 'partial-published');
            miniProgress.hide();
        } else if (isComplete && current >= expected) {
            // COLA COMPLETA (sin publicar todavía)
            statusCell.html('<span style="background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Cola completa</span>');
            statusCell.data('status', 'complete');
            miniProgress.hide();
        } else if (current > 0 && current < expected) {
            // COLA INCOMPLETA (hay posts pero no todos) - Solo cuando NO está generando
            statusCell.html(`<span style="background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">⚠ ${current} de ${expected} en cola</span>`);
            statusCell.data('status', 'incomplete');
            miniProgress.hide();
        } else if (current === 0 && expected > 0) {
            // SIN COLA (no hay posts generados)
            statusCell.html('<span style="background: #FEE2E2; color: #991B1B; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">Sin cola</span>');
            statusCell.data('status', 'empty');
            miniProgress.hide();
        }

        // === ACTUALIZAR BOTONES ===
        // IMPORTANTE: Desde que hay 1+ post, SIEMPRE "Ver Cola" (incluso si está generando)
        let mainBtnHtml = '';
        let executeBtnHtml = '';
        let secondaryBtnHtml = '';

        if (current > 0) {
            // HAY POSTS EN COLA (1 o más): SIEMPRE "Ver Cola"
            // Esto incluye cuando está generando, ejecutando, o completado
            mainBtnHtml = `<a href="${queueUrl}" class="ap-action-btn main-queue-btn" title="Ver Cola">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span class="btn-text">Ver Cola</span>
            </a>`;

            // Calcular posts pendientes de publicar (current - completed)
            const pendingToPublish = Math.max(0, current - completed);

            // Si hay posts pendientes de publicar Y NO está ejecutando → agregar botón "Publicar N"
            if (pendingToPublish > 0 && !hasExecuteLock) {
                const executeUrl = apCampaigns.admin_url + 'admin.php?page=autopost-execute&campaign_id=' + campaignId;
                executeBtnHtml = `<a href="${executeUrl}" class="ap-action-btn execute" title="Publicar ${pendingToPublish} posts pendientes">
                    <svg viewBox="0 0 24 24">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <span class="btn-text">Publicar ${pendingToPublish}</span>
                </a>`;
            }

            // Si cola incompleta Y NO está generando → agregar botón "Completar (X)"
            if (!isComplete && missing > 0 && !hasGenerateLock) {
                secondaryBtnHtml = `<a href="${queueUrl}&auto_generate=${missing}" class="ap-action-btn warning" title="Completar cola (generar ${missing} posts faltantes)">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span class="btn-text">Completar (${missing})</span>
                </a>`;
            }
        } else if (hasGenerateLock) {
            // SIN POSTS todavía PERO generando: "Generando..."
            mainBtnHtml = '<button type="button" class="ap-action-btn main-queue-btn" disabled title="Generando cola..."><svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"></circle><path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path></svg></button>';
        } else if (hasExecuteLock) {
            // SIN POSTS todavía PERO ejecutando: "Publicando..."
            mainBtnHtml = '<button type="button" class="ap-action-btn main-queue-btn" disabled title="Publicando posts..."><svg class="ap-spinner" viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"></circle><path d="M4 12a8 8 0 018-8" stroke-linecap="round"></path></svg></button>';
        } else if (current === 0 && expected > 0) {
            // SIN COLA: "Generar Cola"
            mainBtnHtml = `<button type="button" class="ap-action-btn main-queue-btn generate-queue-btn" data-campaign-id="${campaignId}" data-queue-url="${queueUrl}" title="Generar Cola">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span class="btn-text">Generar Cola</span>
            </button>`;
        }

        // Reemplazar botones (mantener botones de editar, clonar, eliminar)
        const $mainBtn = btnGroup.find('.main-queue-btn');
        if ($mainBtn.length > 0) {
            $mainBtn.replaceWith(mainBtnHtml);
        } else {
            btnGroup.prepend(mainBtnHtml);
        }

        // Actualizar o eliminar botón de ejecutar (VERDE)
        const $executeBtn = btnGroup.find('.ap-action-btn.execute');
        if (executeBtnHtml) {
            if ($executeBtn.length > 0) {
                $executeBtn.replaceWith(executeBtnHtml);
            } else {
                btnGroup.find('.main-queue-btn').after(executeBtnHtml);
            }
        } else {
            $executeBtn.remove();
        }

        // Actualizar o eliminar botón secundario (NARANJA - Completar)
        const $secondaryBtn = btnGroup.find('.ap-action-btn.warning');
        if (secondaryBtnHtml) {
            if ($secondaryBtn.length > 0) {
                $secondaryBtn.replaceWith(secondaryBtnHtml);
            } else {
                // Insertar después del botón de ejecutar si existe, sino después del main
                if (btnGroup.find('.ap-action-btn.execute').length > 0) {
                    btnGroup.find('.ap-action-btn.execute').after(secondaryBtnHtml);
                } else {
                    btnGroup.find('.main-queue-btn').after(secondaryBtnHtml);
                }
            }
        } else {
            $secondaryBtn.remove();
        }
    }

    // Funciones antiguas eliminadas - ahora se usa updateCampaignUIComplete() que es más completa

    // ============================================
    // ANIMACIÓN DE BOTONES - Expansión hacia la derecha
    // ============================================
    $(document).on('mouseenter', '.ap-action-btn:not(:disabled)', function() {
        $(this).addClass('expanded');
    });

    $(document).on('mouseleave', '.ap-action-btn', function() {
        $(this).removeClass('expanded');
    });

    // Ejecutar polling cada 3 segundos
    setInterval(pollAllCampaignsStatus, 3000);

    // Ejecutar una vez al cargar
    setTimeout(pollAllCampaignsStatus, 500);
});
