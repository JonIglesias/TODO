/**
 * UI BLOCKER - Bloqueo global
 */
(function($) {
    let checkInterval = null;
    let progressInterval = null;
    let isLocked = false;
    let activeCampaignId = null;
    
    const UIBlocker = {
        init: function() {
            this.checkStatus();
            checkInterval = setInterval(() => this.checkStatus(), 2000); // cada 2s
            
            // Solo interceptar clicks en el listado de campañas, NO en la cola
            // (en la cola, queue.js maneja el progreso)
            const isQueuePage = window.location.href.indexOf('autopost-queue') !== -1;
            
            if (!isQueuePage) {
                $(document).on('click', '#generate-queue-btn, #start-generate-queue', (e) => {
                    e.preventDefault();
                    
                    // Si es un enlace, extraer campaign_id de la URL
                    let campaignId = apQueue.campaign_id;
                    if (!campaignId) {
                        const href = $(e.currentTarget).attr('href');
                        const match = href ? href.match(/campaign_id=(\d+)/) : null;
                        campaignId = match ? match[1] : null;
                    }
                    
                    if (!campaignId) {
                        alert('Error: No se pudo obtener ID de campaña');
                        return;
                    }
                    
                    this.handleGenerate(campaignId);
                });
            }
            
            $(document).on('click', '#execute-selected-btn', (e) => {
                e.preventDefault();
                this.handleExecute();
            });
        },
        
        checkStatus: function() {
            $.post(apQueue.ajax_url, {
                action: 'ap_check_lock_status',
                nonce: apQueue.nonce
            }, (response) => {
                if (response.success) {
                    isLocked = response.data.any_locked;

                    if (isLocked) {
                        let info = response.data.generate_info || response.data.execute_info;
                        let operation = response.data.generate_locked ? 'Generando cola' : 'Ejecutando cola';
                        let operationType = response.data.generate_locked ? 'generate' : 'execute';
                        activeCampaignId = info ? info.campaign_id : null;

                        this.showBanner({
                            op: operation,
                            type: operationType,
                            time: info ? info.started_at : Date.now() / 1000,
                            campaign_id: activeCampaignId
                        });
                        this.disableAll();

                        // Si estamos en el listado y hay generación activa, iniciar polling de progreso
                        if (response.data.generate_locked && activeCampaignId && !progressInterval) {
                            this.startProgressPolling(activeCampaignId);
                        }
                    } else {
                        this.hideBanner();
                        this.enableAll();
                        this.hideAllProgress();
                        this.stopProgressPolling();
                        activeCampaignId = null;
                    }
                }
            });
        },
        
        startProgressPolling: function(campaignId) {
            // Solo en página de listado
            if (window.location.href.indexOf('page=autopost-ia') === -1) return;
            
            // Llamar inmediatamente la primera vez
            this.updateCampaignProgress(campaignId);
            
            // Luego cada 3 segundos
            progressInterval = setInterval(() => {
                this.updateCampaignProgress(campaignId);
            }, 3000);
        },
        
        stopProgressPolling: function() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        },
        
        updateCampaignProgress: function(campaignId) {
            $.post(apQueue.ajax_url, {
                action: 'ap_check_queue_progress',
                nonce: apQueue.nonce,
                campaign_id: campaignId
            }, (response) => {
                if (response.success) {
                    const current = response.data.count;
                    const expected = response.data.expected;
                    const progress = Math.min(100, (current / expected) * 100);
                    
                    const $row = $('tr[data-campaign-id="' + campaignId + '"]');
                    const $progressCell = $row.find('.progress-cell');
                    const $miniProgress = $progressCell.find('.mini-progress');
                    const $bar = $miniProgress.find('.mini-progress-bar');
                    const $text = $miniProgress.find('.mini-progress-text');
                    
                    $miniProgress.show();
                    $bar.css('width', progress + '%');
                    $text.text(current + ' / ' + expected + ' posts');
                    
                    // Si terminó, actualizar estado
                    if (current >= expected) {
                        const $statusCell = $row.find('.queue-status-cell');
                        $statusCell.html('<span style="color: green;">✓ Sí</span>');
                        
                        setTimeout(() => {
                            $miniProgress.fadeOut();
                        }, 2000);
                    }
                }
            });
        },
        
        hideAllProgress: function() {
            $('.mini-progress').fadeOut();
        },
        
        showBanner: function(info) {
            // Verificar si el botón ya existe
            let cancelBtn = $('#ap-cancel-process-btn');

            if (cancelBtn.length > 0) {
                // Ya existe, solo actualizar el tipo de operación si cambió
                const currentType = cancelBtn.attr('data-operation-type');
                if (currentType !== info.type) {
                    const btnText = info.type === 'generate' ? 'Cancelar Generación' : 'Cancelar Publicación';
                    cancelBtn.attr('data-operation-type', info.type)
                           .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>' + btnText);
                }
                return;
            }

            // Crear botón de cancelar
            const btnText = info.type === 'generate' ? 'Cancelar Generación' : 'Cancelar Publicación';
            cancelBtn = $('<button id="ap-cancel-process-btn"></button>')
                .attr('data-operation-type', info.type || 'generate')
                .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>' + btnText)
                .css({
                    'background': '#dc2626',
                    'color': 'white',
                    'border': 'none',
                    'padding': '10px 20px',
                    'border-radius': '8px',
                    'font-weight': '600',
                    'font-size': '14px',
                    'cursor': 'pointer',
                    'transition': 'all 0.2s',
                    'white-space': 'nowrap',
                    'box-shadow': '0 2px 8px rgba(220, 38, 38, 0.3)'
                })
                .on('mouseenter', function() {
                    $(this).css({'background': '#b91c1c', 'box-shadow': '0 4px 12px rgba(220, 38, 38, 0.4)'});
                })
                .on('mouseleave', function() {
                    $(this).css({'background': '#dc2626', 'box-shadow': '0 2px 8px rgba(220, 38, 38, 0.3)'});
                })
                .on('click', function(e) {
                    e.preventDefault();
                    UIBlocker.handleAbort();
                });

            // Insertar en el contenedor del header (si existe) o fallback al wrap
            const $container = $('#ap-cancel-process-container');
            if ($container.length > 0) {
                $container.html(cancelBtn);
            } else {
                // Fallback: insertar después del header de campaña
                const $header = $('.ap-campaign-header');
                if ($header.length > 0) {
                    cancelBtn.css('margin', '0 0 20px 0');
                    $header.after(cancelBtn);
                } else {
                    $('.wrap').prepend(cancelBtn);
                }
            }
        },

        handleAbort: function() {
            const btn = $('#ap-cancel-process-btn');
            const operationType = btn.attr('data-operation-type') || 'generate';
            const operationName = operationType === 'generate' ? 'generación' : 'publicación';
            const operationNameCap = operationType === 'generate' ? 'Generación' : 'Publicación';

            // SVG solo para uso en HTML (no en alerts)
            const checkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            const spinningSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px; animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
            const errorSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';

            // Usar emojis para alerts (no se puede usar HTML en confirm/alert)
            if (!confirm('⚠️ ¿Estás seguro de abortar la ' + operationName + '?\n\nEsto limpiará todos los bloqueos y detendrá el proceso actual.')) {
                return;
            }

            btn.prop('disabled', true).html(spinningSvg + 'Abortando...');

            $.post(apQueue.ajax_url, {
                action: 'ap_abort_generation',
                nonce: apQueue.nonce
            }, (response) => {
                if (response.success) {
                    btn.html(checkSvg + operationNameCap + ' abortada');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('❌ Error al abortar: ' + (response.data.message || 'Error desconocido'));
                    btn.prop('disabled', false).html(errorSvg + 'Abortar ' + operationNameCap);
                }
            }).fail(() => {
                alert('❌ Error de conexión al intentar abortar');
                btn.prop('disabled', false).html(errorSvg + 'Abortar ' + operationNameCap);
            });
        },
        
        hideBanner: function() {
            $('#ap-cancel-process-btn').fadeOut(200, function() {
                $(this).remove();
            });
            $('#ap-cancel-process-container').empty();
        },
        
        disableAll: function() {
            $('button[id*="generate"], button[id*="execute"], #start-generate-queue, #execute-selected-btn, a#generate-queue-btn')
                .prop('disabled', true)
                .css({'opacity': '0.5', 'cursor': 'not-allowed', 'pointer-events': 'none'});

            // SI hay ejecución activa, OCULTAR completamente los botones de publicar
            if (isLocked && $('#execute-queue').length > 0) {
                $('#execute-queue, #execute-queue-bottom').hide();
            }
        },
        
        enableAll: function() {
            $('button[id*="generate"], button[id*="execute"], #start-generate-queue, #execute-selected-btn, a#generate-queue-btn')
                .prop('disabled', false)
                .css({'opacity': '1', 'cursor': 'pointer', 'pointer-events': 'auto'});

            // Mostrar botones de publicar si hay posts pendientes
            const hasPending = $('#queue-tbody tr').filter(function() {
                return $(this).find('.status-badge').data('status') === 'pending' ||
                       $(this).find('.status-badge').data('status') === 'processing';
            }).length > 0;

            if (hasPending) {
                $('#execute-queue, #execute-queue-bottom').show();
            }
        },
        
        handleGenerate: function(campaignId) {
            if (isLocked) { alert('Sistema ocupado'); return; }
            if (!confirm('¿Generar cola?')) return;
            
            // Redirigir inmediatamente a la página de cola
            window.location.href = apQueue.ajax_url.replace('admin-ajax.php', 'admin.php?page=autopost-queue&campaign_id=' + campaignId + '&auto_generate=1');
        },
        
        handleExecute: function() {
            if (isLocked) { alert('Sistema ocupado'); return; }
            
            const checked = $('.queue-checkbox:checked');
            if (checked.length === 0) { alert('Selecciona posts'); return; }
            if (!confirm('¿Ejecutar ' + checked.length + ' posts?')) return;
            
            const btn = $('#execute-selected-btn');
            const ids = [];
            checked.each(function() { ids.push($(this).val()); });
            
            btn.prop('disabled', true).html('⏳ Ejecutando...');
            
            $.post(apQueue.ajax_url, {
                action: 'ap_execute_selected',
                nonce: apQueue.nonce,
                ids: ids
            }, (response) => {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload();
                } else {
                    alert('❌ ' + response.data.message);
                    btn.prop('disabled', false).html('Ejecutar');
                }
            }).fail(() => { alert('Error'); btn.prop('disabled', false); });
        },
        
        destroy: function() {
            if (checkInterval) clearInterval(checkInterval);
            if (progressInterval) clearInterval(progressInterval);
        }
    };
    
    $(document).ready(() => UIBlocker.init());
    $(window).on('beforeunload', () => UIBlocker.destroy());
    
})(jQuery);
