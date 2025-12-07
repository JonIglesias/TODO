jQuery(document).ready(function($) {
    
    // ============================================
    // DRAG & DROP PARA REORDENAR
    // ============================================
    $('#queue-tbody').sortable({
        handle: '.drag-handle',
        axis: 'y',
        cursor: 'grabbing',
        placeholder: 'sortable-placeholder',
        helper: function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width());
            });
            $helper.css({
                'background': '#e3f2fd',
                'box-shadow': '0 5px 15px rgba(0,0,0,0.3)',
                'border-left': '4px solid #2271b1'
            });
            return $helper;
        },
        start: function(e, ui) {
            ui.placeholder.html('<td colspan="10" style="background:#f0f6fc; border:2px dashed #2271b1; height:60px;"></td>');
        },
        stop: function(e, ui) {
            // Restaurar opacidad
            ui.item.css('opacity', '1');
        },
        update: function(event, ui) {
            const order = [];
            const campaignId = apQueue.campaign_id;

            $('#queue-tbody tr').each(function(index) {
                order.push({
                    id: $(this).data('queue-id'),
                    position: index + 1
                });
                // Actualizar número visualmente
                $(this).find('td').eq(1).find('div').last().text('#' + (index + 1));
            });

            // Guardar en servidor Y recargar para actualizar fechas visualmente
            $.post(apQueue.ajax_url, {
                action: 'ap_update_queue_order',
                nonce: apQueue.nonce,
                campaign_id: campaignId,
                order: order
            }, function(response) {
                if (response.success) {
                    // Recargar página para mostrar las nuevas fechas
                    location.reload();
                } else {
                    alert('Error guardando orden');
                }
            });
        }
    });
    
    // Estilo CSS para placeholder
    $('<style>.sortable-placeholder { background: #f0f6fc !important; border: 2px dashed #2271b1 !important; visibility: visible !important; }</style>').appendTo('head');
    
    // Animación del spinner
    $('<style>@keyframes spin { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }</style>').appendTo('head');
    
    // ============================================
    // DATEPICKER PARA FECHAS
    // ============================================
    $(document).on('click', '.editable-date', function(e) {
        const elem = $(this);
        
        // Si ya hay un input abierto, no hacer nada
        if (elem.find('input').length > 0) {
            return;
        }
        
        const id = elem.data('id');
        const currentDate = elem.data('date'); // Formato: Y-m-d H:i:s
        
        // Crear input temporal
        const parts = currentDate.split(' ');
        const datePart = parts[0]; // Y-m-d
        const timePart = parts[1] ? parts[1].substring(0, 5) : '09:00'; // H:i
        
        const input = $('<input type="datetime-local" />')
            .val(datePart + 'T' + timePart)
            .css({
                'width': '100%',
                'padding': '6px',
                'border': '2px solid #2271b1',
                'border-radius': '3px',
                'font-size': '13px'
            });
        
        elem.html(input);
        input.focus();
        
        // Al cambiar o salir
        input.on('blur change', function() {
            const newValue = $(this).val();
            if (!newValue) {
                location.reload();
                return;
            }
            
            // Convertir datetime-local a MySQL format
            const mysqlFormat = newValue.replace('T', ' ') + ':00';
            
            $.post(apQueue.ajax_url, {
                action: 'ap_update_queue_field',
                nonce: apQueue.nonce,
                id: id,
                field: 'scheduled_date',
                value: mysqlFormat
            }, function(response) {
                if (response.success) {
                    // Actualizar display
                    const date = new Date(newValue);
                    const formatted = date.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit', 
                        year: 'numeric'
                    }) + '<br><small>' + 
                    date.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) + 'h</small>';
                    
                    elem.data('date', mysqlFormat);
                    elem.html(formatted);
                } else {
                    location.reload();
                }
            });
        });
        
        // Evitar que el click se propague
        e.stopPropagation();
    });
    
    // ============================================
    // GENERAR COLA
    // ============================================
    // GENERAR COLA CON PROGRESO REAL
    // ============================================
    let progressPolling = null;
    let lockRenewal = null;
    let noProgressCount = 0;
    let lastCount = 0;

    // Usar delegated event para que funcione con botones creados dinámicamente
    $(document).on('click', '#start-generate-queue, #start-generate-queue-header', function() {
        const btn = $(this);
        const campaignId = btn.data('campaign-id');
        const forceComplete = btn.data('force-complete') || '0';
        
        btn.prop('disabled', true).text('Generando...');
        $('#generate-progress').show();
        $('#progress-text').html('<svg class="ap-spinner" style="width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #000000; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24"></svg>Generando cola...');
        $('#progress-bar').css('width', '5%');
        $('#progress-log').html('');
        
        // Obtener num_posts esperados
        const expectedPosts = parseInt($('#expected-posts').val() || '10');
        
        // Iniciar polling de progreso
        startProgressPolling(campaignId, expectedPosts);
        
        // Iniciar renovación de bloqueo
        startLockRenewal();
        
        $.ajax({
            url: apQueue.ajax_url,
            type: 'POST',
            timeout: 300000,
            data: {
                action: 'ap_generate_queue',
                nonce: apQueue.nonce,
                campaign_id: campaignId,
                force_complete: forceComplete
            },
            success: function(response) {
                stopProgressPolling();
                stopLockRenewal();
                
                if (response.success) {
                    var msg = response.data && response.data.message ? response.data.message : 'Cola generada';
                    
                    // Verificar si la cola está incompleta
                    if (response.data && response.data.incomplete) {
                        $('#progress-text').html('<strong style="color:#f59e0b;">' + msg + '</strong>');
                    } else {
                        $('#progress-text').html('<strong style="color:#10b981;">' + msg + '</strong>');
                    }
                    
                    $('#progress-bar').css('width', '100%');
                    
                    if (response.data && response.data.created) {
                        var logMsg = 'Completado: ' + response.data.created + ' posts creados';
                        if (response.data.missing && response.data.missing > 0) {
                            logMsg += ' (Faltan ' + response.data.missing + ' posts)';
                        }
                        $('#progress-log').append('<div><strong>' + logMsg + '</strong></div>');
                    }
                    
                    setTimeout(function() {
                        location.href = location.href + (location.href.indexOf('?') !== -1 ? '&' : '?') + 'refresh=' + Date.now();
                    }, 2000);
                } else {
                    // Usar manejador centralizado de errores
                    var isTokenLimitError = AutoPost.isTokenLimitError(response);
                    var errorMsg = AutoPost.extractErrorMessage(response);

                    // Si es cola incompleta con permiso para completar, preguntar
                    if (response.data && response.data.allow_complete && !isTokenLimitError) {
                        if (confirm(errorMsg + '\n\n¿Quieres completar la cola ahora?')) {
                            // Marcar para forzar completado y volver a intentar
                            btn.data('force-complete', '1');
                            btn.prop('disabled', false).click();
                            return;
                        }
                    }

                    // Usar manejador centralizado para mostrar el error
                    if (isTokenLimitError) {
                        AutoPost.showTokenLimitError('progress-text', errorMsg);
                        $('#progress-log').html('');
                    } else {
                        $('#progress-text').html('<strong style="color:#dc2626;">' + errorMsg + '</strong>');

                        if (response.data && response.data.error_details) {
                            $('#progress-log').html('');
                            response.data.error_details.forEach(function(err) {
                                $('#progress-log').append('<div class="error">' + err + '</div>');
                            });
                        }
                    }

                    btn.prop('disabled', false).text('Generar Cola');
                    btn.data('force-complete', '0'); // Resetear
                }
            },
            error: function(xhr, status, error) {
                stopProgressPolling();
                stopLockRenewal();
                
                $('#progress-text').html('<strong style="color:red;">Error: ' + error + '</strong>');
                $('#progress-log').append('<div class="error">Status: ' + status + '</div>');
                if (xhr.responseText) {
                    $('#progress-log').append('<div class="error">Response: ' + xhr.responseText.substring(0, 500) + '</div>');
                }
                btn.prop('disabled', false).text('Generar Cola');
            }
        });
    });
    
    function startProgressPolling(campaignId, expectedPosts) {
        noProgressCount = 0;
        lastCount = 0;
        
        progressPolling = setInterval(function() {
            
            try {
                // Usar endpoint detallado
                $.post(apQueue.ajax_url, {
                    action: 'ap_check_queue_detailed_progress',
                    nonce: apQueue.nonce,
                    campaign_id: campaignId
                })
                .done(function(response) {
                    
                    if (response.success) {
                        const total = response.data.total || 0;
                        const expected = response.data.expected || expectedPosts;
                        
                        // Progreso simple: posts completos
                        const totalProgress = Math.min(100, (total / expected) * 100);
                        
                        
                        // Actualizar barra de progreso
                        $('#progress-bar').css('width', totalProgress + '%');
                        $('#progress-text').html('<svg class="ap-spinner" style="width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #000000; border-radius: 50%; animation: spin 0.8s linear infinite; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24"></svg>Generando cola... <span style="color: #64748b; font-weight: normal;">' + total + ' / ' + expected + ' posts</span>');
                        
                        // Actualizar contador en título
                        $('#queue-count').text('(' + total + ' / ' + expected + ')');
                        
                        // Actualizar mensaje único
                        let message = '';
                        if (total > 0) {
                            message = '<strong>' + total + '</strong> de <strong>' + expected + '</strong> posts generados';
                        } else {
                            message = 'Iniciando generación...';
                        }
                        $('#progress-message').html(message);
                        
                        // SIEMPRE cargar posts para mantener tabla actualizada
                        try {
                            loadAllPosts(campaignId);
                        } catch(e) {
                            console.error('❌ Error en loadAllPosts:', e); // DEBUG
                        }
                    
                    // Detectar si la generación terminó al 100%
                    if (total >= expected && expected > 0) {
                        stopProgressPolling();
                        stopLockRenewal();

                        $('#progress-text').html('<strong style="color:#10b981;">Cola generada correctamente</strong>');
                        $('#progress-message').html('<strong>' + total + '</strong> posts generados correctamente');
                        $('#progress-bar').css('width', '100%');

                        // Recargar página después de 2 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);

                        return; // Salir del polling
                    }

                    // Detectar cola atascada
                    if (total === lastCount && total > 0) {
                        noProgressCount++;

                        // 90 segundos sin progreso (18 iteraciones de 5s)
                        if (noProgressCount >= 18 && total < expected) {
                            stopProgressPolling();
                            stopLockRenewal();

                            $('#progress-text').html('<strong style="color:#f59e0b;">ATENCIÓN: La generación se detuvo</strong>');
                            $('#progress-message').html('Sin progreso durante 90 segundos. Se crearon <strong>' + total + '</strong> de <strong>' + expected + '</strong> posts.');

                            if (confirm('La generación se detuvo.\nSe crearon ' + total + ' posts de ' + expected + '.\n\n¿Quieres completar los posts restantes?')) {
                                $('#start-generate-queue').data('force-complete', '1');
                                $('#start-generate-queue').prop('disabled', false).text('Completar Cola').click();
                            } else {
                                $('#start-generate-queue').prop('disabled', false).text('Completar Cola');
                            }
                        }
                    } else {
                        noProgressCount = 0;
                    }

                    lastCount = total;
                }
            })
            .fail(function(xhr, status, error) {
                console.error('❌ AJAX ERROR:', {xhr, status, error}); // DEBUG
            });
            
            } catch(err) {
                console.error('❌ POLLING ERROR:', err); // DEBUG
            }
        }, 3000); // Cada 3 segundos
    }
    
    function loadAllPosts(campaignId) {
        $.post(apQueue.ajax_url, {
            action: 'ap_get_new_queue_posts',
            nonce: apQueue.nonce,
            campaign_id: campaignId,
            loaded_ids: [] // Array vacío - traer todos
        }, function(response) {
            if (response.success && response.data.posts) {
                const posts = response.data.posts;

                const $tbody = $('#queue-tbody');

                // Crear promesas para todos los posts que necesitan ser renderizados
                const renderPromises = [];
                const postsToRender = [];

                posts.forEach(function(post, index) {
                    const position = index + 1;
                    const postId = parseInt(post.id);

                    // Buscar si ya existe esta fila
                    let $existingRow = $('tr[data-queue-id="' + postId + '"]');

                    if ($existingRow.length > 0) {
                        // Ya existe - solo actualizar imágenes si cambiaron
                        updatePostRow($existingRow, post);
                    } else {
                        // No existe - necesita ser renderizado via AJAX
                        postsToRender.push({ post: post, position: position });
                        renderPromises.push(createPostRow(post, position));
                    }
                });

                // Esperar a que todas las filas se rendericen
                if (renderPromises.length > 0) {
                    Promise.all(renderPromises).then(function(newRows) {
                        // Insertar todas las filas nuevas
                        newRows.forEach(function($newRow, idx) {
                            const position = postsToRender[idx].position;
                            let $targetRow = $('.placeholder-row[data-position="' + position + '"]');

                            if ($targetRow.length === 0) {
                                $targetRow = $('.placeholder-row').first();
                            }

                            if ($targetRow.length > 0) {
                                $targetRow.fadeOut(200, function() {
                                    $(this).replaceWith($newRow);
                                    $newRow.css('opacity', '0').animate({opacity: 1}, 400);
                                });
                            } else {
                                $tbody.append($newRow);
                                $newRow.css('opacity', '0').animate({opacity: 1}, 400);
                            }
                        });
                    }).catch(function(error) {
                        console.error('Error renderizando filas:', error);
                    });
                }
            }
        }).fail(function(xhr, status, error) {
            console.error('Error cargando posts:', status, error); // DEBUG
        });
    }
    
    function updatePostRow($row, post) {
        // Actualizar solo imágenes si cambiaron
        const $featuredCell = $row.find('td').eq(3);
        const $innerCell = $row.find('td').eq(4);
        
        if (post.featured_image_thumb && $featuredCell.find('img').length === 0) {
            $featuredCell.html('<img src="' + post.featured_image_thumb + '" style="max-width:100px; height:auto; border-radius:4px;">');
            $featuredCell.hide().fadeIn(400);
        }
        
        if (post.inner_image_thumb && $innerCell.find('img').length === 0) {
            $innerCell.html('<img src="' + post.inner_image_thumb + '" style="max-width:100px; height:auto; border-radius:4px;">');
            $innerCell.hide().fadeIn(400);
        }
    }
    
    /**
     * Renderiza una fila de la cola usando el template PHP (fuente única de verdad)
     * @param {Object} post - Datos del post
     * @param {Number} position - Posición en la cola
     * @return {Promise} - Promesa que resuelve a un objeto jQuery con la fila renderizada
     */
    function createPostRow(post, position) {
        return new Promise(function(resolve, reject) {
            $.post(apQueue.ajax_url, {
                action: 'ap_render_queue_row',
                nonce: apQueue.nonce,
                post_id: post.id,
                position: position,
                campaign_id: apQueue.campaign_id
            }, function(response) {
                if (response.success && response.data.html) {
                    // Convertir el HTML a objeto jQuery
                    const $row = $(response.data.html);
                    resolve($row);
                } else {
                    console.error('Error renderizando fila:', response.data);
                    reject(new Error(response.data.message || 'Error desconocido'));
                }
            }).fail(function(xhr, status, error) {
                console.error('Error AJAX renderizando fila:', status, error);
                reject(new Error('Error de conexión: ' + error));
            });
        });
    }
    
    function stopProgressPolling() {
        if (progressPolling) {
            clearInterval(progressPolling);
            progressPolling = null;
        }
    }
    
    function startLockRenewal() {
        lockRenewal = setInterval(function() {
            $.post(apQueue.ajax_url, {
                action: 'ap_renew_lock',
                nonce: apQueue.nonce,
                type: 'generate'
            });
        }, 20000); // Cada 20 segundos
    }
    
    function stopLockRenewal() {
        if (lockRenewal) {
            clearInterval(lockRenewal);
            lockRenewal = null;
        }
    }
    
    // ============================================
    // SELECCIONAR TODOS
    // ============================================
    $('#select-all-queue').on('change', function() {
        $('.queue-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkDeleteButton();
    });
    
    $('.queue-checkbox').on('change', function() {
        updateBulkDeleteButton();
    });
    
    function updateBulkDeleteButton() {
        const checked = $('.queue-checkbox:checked').length;
        if (checked > 0) {
            $('#bulk-delete-queue').fadeIn(200);
        } else {
            $('#bulk-delete-queue').fadeOut(200);
        }
    }
    
    // ============================================
    // ELIMINACIÓN MASIVA
    // ============================================
    $('#bulk-delete-queue').on('click', function() {
        const checked = $('.queue-checkbox:checked');
        if (checked.length === 0) {
            alert('Selecciona al menos un post');
            return;
        }
        
        if (!confirm('¿Eliminar ' + checked.length + ' posts de la cola?')) {
            return;
        }
        
        const ids = [];
        checked.each(function() {
            ids.push($(this).val());
        });

        $(this).prop('disabled', true).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px; animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Eliminando...');
        
        $.post(apQueue.ajax_url, {
            action: 'ap_bulk_delete_queue',
            nonce: apQueue.nonce,
            ids: ids
        }, function(response) {
            if (response.success) {
                // Eliminar filas con animación
                checked.each(function() {
                    const row = $(this).closest('tr');
                    row.fadeOut(function() { $(this).remove(); });
                });
                
                // Actualizar UI después de eliminar
                setTimeout(function() {
                    updateQueueStatus();
                    $('#bulk-delete-queue').fadeOut(200);
                    $('#select-all-queue').prop('checked', false);
                }, 400);
            } else {
                alert('Error: ' + (response.data.message || 'Error desconocido'));
                $('#bulk-delete-queue').html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>Eliminar Seleccionados');
            }
        });
    });
    
    // ============================================
    // EDICIÓN INLINE
    // ============================================
    // ============================================
    // EDICIÓN DE TÍTULOS CON TEXTAREA
    // ============================================
    $(document).on('click', '.editable-title', function(e) {
        const elem = $(this);

        // No editar si está bloqueado
        if (elem.attr('contenteditable') === 'false') {
            return;
        }

        // Si ya hay un textarea, no hacer nada
        if (elem.find('textarea').length > 0) {
            return;
        }

        const id = elem.data('id');
        const currentTitle = elem.text().trim();

        // Crear textarea
        const textarea = $('<textarea>')
            .addClass('title-edit-textarea')
            .val(currentTitle)
            .css({
                'width': '100%',
                'min-height': '60px',
                'padding': '8px',
                'border': '2px solid #000000',
                'border-radius': '4px',
                'font-size': '14px',
                'font-weight': '600',
                'font-family': 'inherit',
                'resize': 'vertical',
                'box-sizing': 'border-box'
            });

        // Reemplazar contenido
        elem.html(textarea);
        textarea.focus().select();

        // Auto-resize del textarea
        function autoResize() {
            textarea.css('height', 'auto');
            textarea.css('height', textarea[0].scrollHeight + 'px');
        }
        autoResize();
        textarea.on('input', autoResize);

        // Guardar al perder foco
        function saveTitle() {
            const newValue = textarea.val().trim();

            if (!newValue) {
                elem.text(currentTitle);
                return;
            }

            if (newValue === currentTitle) {
                elem.text(currentTitle);
                return;
            }

            // Guardar en BD
            $.post(apQueue.ajax_url, {
                action: 'ap_update_queue_field',
                nonce: apQueue.nonce,
                id: id,
                field: 'title',
                value: newValue
            }, function(response) {
                if (response.success) {
                    elem.text(newValue);
                } else {
                    elem.text(currentTitle);
                    alert('Error guardando título');
                }
            }).fail(function() {
                elem.text(currentTitle);
                alert('Error de conexión');
            });
        }

        textarea.on('blur', saveTitle);

        // Ctrl/Cmd + Enter para guardar
        textarea.on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.which === 13) {
                e.preventDefault();
                textarea.blur();
            }
        });
    });
    
    // ============================================
    // EDICIÓN DE KEYWORDS CON TEXTAREA - CON DELEGACIÓN
    // ============================================
    $(document).on('click', '.keywords-display', function() {
        const elem = $(this);
        const id = elem.data('id');
        const currentKeywords = elem.data('keywords');
        
        // Crear overlay modal
        const overlay = $('<div>')
            .css({
                'position': 'fixed',
                'top': 0,
                'left': 0,
                'width': '100%',
                'height': '100%',
                'background': 'rgba(0,0,0,0.5)',
                'z-index': 9999,
                'display': 'flex',
                'align-items': 'center',
                'justify-content': 'center'
            });
        
        const modal = $('<div>')
            .css({
                'background': 'white',
                'padding': '25px',
                'border-radius': '8px',
                'box-shadow': '0 5px 20px rgba(0,0,0,0.3)',
                'max-width': '600px',
                'width': '90%'
            });
        
        const title = $('<h3>Editar Keywords de Imagen</h3>')
            .css({'margin-top': 0, 'color': '#2271b1'});
        
        const hint = $('<p>')
            .html('Separa términos con comas. <strong>Máximo 15 términos.</strong>')
            .css({'color': '#666', 'margin': '10px 0'});
        
        const textarea = $('<textarea>')
            .val(currentKeywords)
            .css({
                'width': '100%',
                'height': '150px',
                'padding': '10px',
                'border': '2px solid #ddd',
                'border-radius': '4px',
                'font-size': '14px',
                'resize': 'vertical',
                'font-family': 'inherit'
            });
        
        const counter = $('<div>')
            .css({
                'margin-top': '5px',
                'text-align': 'right',
                'color': '#666',
                'font-size': '13px'
            });
        
        // Actualizar contador
        function updateCounter() {
            const terms = textarea.val().split(',').filter(t => t.trim() !== '');
            const count = terms.length;
            counter.html('<strong>' + count + '/15</strong> términos');
            counter.css('color', count > 15 ? 'red' : '#666');
        }
        updateCounter();
        textarea.on('input', updateCounter);
        
        const buttons = $('<div>')
            .css({
                'margin-top': '20px',
                'display': 'flex',
                'gap': '10px',
                'justify-content': 'flex-end'
            });
        
        const cancelBtn = $('<button class="button">Cancelar</button>')
            .on('click', function() {
                overlay.remove();
            });
        
        const saveBtn = $('<button class="button button-primary">Guardar</button>')
            .on('click', function() {
                let value = textarea.val();
                
                // Limitar a 15 términos
                let terms = value.split(',').map(t => t.trim()).filter(t => t !== '');
                if (terms.length > 15) {
                    terms = terms.slice(0, 15);
                    value = terms.join(', ');
                    alert('Se han limitado a 15 términos');
                }
                
                $.post(apQueue.ajax_url, {
                    action: 'ap_update_queue_field',
                    nonce: apQueue.nonce,
                    id: id,
                    field: 'image_keywords',
                    value: value
                }, function(response) {
                    if (response.success) {
                        // Actualizar display
                        elem.data('keywords', value);
                        const display = value.length > 50 ? value.substring(0, 50) + '...' : value;
                        const count = terms.length;
                        elem.html(display + ' <span style="color:#999;">(' + count + '/15)</span>');
                        overlay.remove();
                    } else {
                        alert('Error al guardar');
                    }
                });
            });
        
        buttons.append(cancelBtn, saveBtn);
        modal.append(title, hint, textarea, counter, buttons);
        overlay.append(modal);
        $('body').append(overlay);
        
        // Focus en textarea
        setTimeout(function() {
            textarea.focus();
        }, 100);
        
        // Cerrar con ESC
        $(document).on('keydown.keywords-modal', function(e) {
            if (e.key === 'Escape') {
                overlay.remove();
                $(document).off('keydown.keywords-modal');
            }
        });
        
        // Cerrar al hacer click fuera
        overlay.on('click', function(e) {
            if (e.target === overlay[0]) {
                overlay.remove();
            }
        });
    });
    
    // ============================================
    // ELIMINAR INDIVIDUAL
    // ============================================
    // ============================================
    // HELPER: ACTUALIZAR CONTADOR Y BOTÓN GENERAR
    // ============================================
    function updateQueueStatus() {
        const currentCount = $('#queue-tbody tr:visible').length;
        const expectedCount = parseInt($('#expected-posts').val() || '0');
        const missingCount = Math.max(0, expectedCount - currentCount);
        const isComplete = currentCount >= expectedCount;

        // Actualizar contador
        $('#queue-count-display').text(currentCount + ' / ' + expectedCount + ' items');

        // Lógica: mostrar/ocultar botón "Generar N restantes" (naranja, antes de "Publicar Posts")
        // El botón ya existe en queue-ui.php, solo necesitamos actualizar su texto si cambia dinámicamente
        const btn = $('#start-generate-queue');
        if (btn.length > 0) {
            // Actualizar texto del botón dinámicamente
            const currentText = btn.text().trim();
            const newText = `Generar ${missingCount} restante${missingCount > 1 ? 's' : ''}`;
            if (!currentText.includes(newText)) {
                // Mantener el icono SVG
                const icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle; margin-right:6px;"><path d="M12 5v14M5 12h14"></path></svg>';
                btn.html(icon + newText);
            }
        }

        // Mostrar/ocultar según estado
        if (!isComplete && missingCount > 0) {
            btn.show();
        } else {
            btn.hide();
        }
    }
    
    // ============================================
    // ELIMINAR ITEM INDIVIDUAL
    // ============================================
    $(document).on('click', '.delete-queue-item', function() {
        if (!confirm('¿Eliminar este item?')) return;
        
        const id = $(this).data('id');
        const row = $(this).closest('tr');
        
        $.post(apQueue.ajax_url, {
            action: 'ap_delete_queue_item',
            nonce: apQueue.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                row.fadeOut(function() { 
                    $(this).remove();
                    updateQueueStatus();
                });
            }
        });
    });
    
    // ============================================
    // DRAG & DROP PARA REORDENAR
    // ============================================
    
    // ============================================
    // REGENERAR IMAGEN (DESDE PROVEEDOR ESPECÍFICO)
    // ============================================
    $('.regenerate-image-provider').on('click', function() {
        const btn = $(this);
        const id = btn.data('id');
        const type = btn.data('type');
        const provider = btn.data('provider');
        const originalLabel = btn.data('label');
        const cell = btn.closest('td');
        
        // Deshabilitar botón y mostrar spinner
        btn.prop('disabled', true).css('opacity', '0.5');
        cell.find('.image-spinner').show();
        
        $.post(apQueue.ajax_url, {
            action: 'ap_regenerate_image',
            nonce: apQueue.nonce,
            id: id,
            type: type,
            provider: provider
        }, function(response) {
            // Ocultar spinner
            cell.find('.image-spinner').hide();
            
            if (response.success) {
                // Actualizar la miniatura
                const imgTag = cell.find('img.queue-thumbnail');
                if (imgTag.length) {
                    imgTag.attr('src', response.data.thumb_url + '?t=' + Date.now());
                } else {
                    cell.find('span').first().replaceWith('<img src="' + response.data.thumb_url + '" class="queue-thumbnail" style="width:100%; height:90px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;">');
                }
                // Restaurar botón con su label original
                btn.prop('disabled', false).css('opacity', '1').html(originalLabel);
            } else {
                alert('Error: ' + (response.data.message || 'No se pudo regenerar'));
                // Restaurar botón con su label original
                btn.prop('disabled', false).css('opacity', '1').html(originalLabel);
            }
        }).fail(function() {
            // En caso de error de red
            cell.find('.image-spinner').hide();
            btn.prop('disabled', false).css('opacity', '1').html(originalLabel);
            alert('Error de conexión al regenerar imagen');
        });
    });
    
    // ============================================
    // ELEGIR DESDE BIBLIOTECA WP
    // ============================================
    $('.choose-from-library').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const id = btn.data('id');
        const type = btn.data('type');
        const cell = btn.closest('td');
        
        const frame = wp.media({
            title: 'Seleccionar imagen',
            button: { text: 'Usar esta imagen' },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            
            $.post(apQueue.ajax_url, {
                action: 'ap_set_library_image',
                nonce: apQueue.nonce,
                id: id,
                type: type,
                attachment_id: attachment.id,
                url: attachment.url
            }, function(response) {
                if (response.success) {
                    const imgTag = cell.find('img');
                    if (imgTag.length) {
                        imgTag.attr('src', attachment.url);
                    } else {
                        cell.find('span').replaceWith('<img src="' + attachment.url + '" class="queue-thumbnail">');
                    }
                }
            });
        });
        
        frame.open();
    });
    
    // ============================================
    // MODAL PARA VER/REGENERAR IMÁGENES
    // ============================================
    $(document).on('click', '.open-image-modal', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const el = $(this);
        const id = el.data('id');
        const type = el.data('type');
        const fullUrl = el.data('full-url');
        
        // Guardar datos en el modal
        $('#image-modal').data('id', id).data('type', type).data('full-url', fullUrl);
        
        // Mostrar imagen
        if (fullUrl) {
            $('#modal-image').attr('src', fullUrl).show();
        } else {
            $('#modal-image').hide();
        }
        
        // Cargar keywords del post
        $.post(apQueue.ajax_url, {
            action: 'ap_get_post_keywords',
            nonce: apQueue.nonce,
            id: id
        }, function(response) {
            if (response.success && response.data.keywords) {
                // Convertir keywords separadas por comas a una por línea
                const keywords = response.data.keywords.split(',').map(k => k.trim()).join('\n');
                $('#modal-keywords').val(keywords);
            }
        });
        
        // Mostrar modal
        $('#image-modal').css('display', 'flex');
    });
    
    // Cerrar modal
    $('#close-modal').on('click', function() {
        $('#image-modal').hide();
    });
    
    // Cerrar al clickear fuera
    $('#image-modal').on('click', function(e) {
        if (e.target.id === 'image-modal') {
            $(this).hide();
        }
    });
    
    // Regenerar desde modal
    $(document).on('click', '.modal-regenerate', function() {
        const btn = $(this);
        const id = $('#image-modal').data('id');
        const type = $('#image-modal').data('type');
        const provider = btn.data('provider');
        
        // Obtener keywords del textarea
        const keywordsText = $('#modal-keywords').val().trim();
        const keywords = keywordsText ? keywordsText.split('\n').map(k => k.trim()).filter(k => k).join(',') : '';
        
        if (!keywords) {
            alert('Por favor, añade al menos una palabra clave');
            return;
        }
        
        // Mostrar spinner
        $('#modal-spinner').show();
        btn.prop('disabled', true);
        
        $.post(apQueue.ajax_url, {
            action: 'ap_regenerate_image',
            nonce: apQueue.nonce,
            id: id,
            type: type,
            provider: provider,
            keywords: keywords
        }, function(response) {
            if (response.success) {
                const newUrl = response.data.thumb_url + '?t=' + Date.now();
                const fullUrl = response.data.large_url || response.data.thumb_url;
                
                // Crear imagen temporal para precargar
                const tempImg = new Image();
                tempImg.onload = function() {
                    // Imagen cargada, actualizar y ocultar spinner
                    $('#modal-image').attr('src', newUrl);
                    $('#modal-spinner').hide();
                    btn.prop('disabled', false);
                };
                tempImg.onerror = function() {
                    // Error al cargar
                    $('#modal-spinner').hide();
                    alert('Error al cargar la nueva imagen');
                    btn.prop('disabled', false);
                };
                tempImg.src = newUrl;
                
                // Actualizar data-full-url del modal
                $('#image-modal').data('full-url', fullUrl);
                
                // Actualizar miniatura en tabla
                const cell = $('tr[data-queue-id="' + id + '"]').find('.' + type + '-image-cell');
                const imgTag = cell.find('img.queue-thumbnail');
                if (imgTag.length) {
                    imgTag.attr('src', newUrl);
                    imgTag.data('full-url', fullUrl);
                } else {
                    // Si no existe imagen, crearla
                    const container = cell.find('div').first();
                    container.find('span').first().replaceWith(
                        '<img src="' + newUrl + '" class="queue-thumbnail open-image-modal" data-id="' + id + '" data-type="' + type + '" data-full-url="' + fullUrl + '" style="width:100%; height:90px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;">'
                    );
                }
            } else {
                $('#modal-spinner').hide();
                alert('Error: ' + (response.data.message || 'No se pudo regenerar'));
                btn.prop('disabled', false);
            }
        }).fail(function() {
            $('#modal-spinner').hide();
            alert('Error de conexión al regenerar imagen');
            btn.prop('disabled', false);
        });
    });
    
    // Biblioteca WP desde modal
    $(document).on('click', '.modal-library', function() {
        const id = $('#image-modal').data('id');
        const type = $('#image-modal').data('type');
        
        // Cerrar modal mientras se selecciona
        $('#image-modal').hide();
        
        const frame = wp.media({
            title: 'Seleccionar imagen',
            button: { text: 'Usar esta imagen' },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            const timestamp = Date.now();
            
            $.post(apQueue.ajax_url, {
                action: 'ap_set_library_image',
                nonce: apQueue.nonce,
                id: id,
                type: type,
                attachment_id: attachment.id,
                url: attachment.url
            }, function(response) {
                if (response.success) {
                    const newUrl = attachment.url + '?t=' + timestamp;
                    
                    // Precargar imagen antes de mostrar
                    const tempImg = new Image();
                    tempImg.onload = function() {
                        // Actualizar imagen en modal
                        $('#modal-image').attr('src', newUrl);
                        
                        // Actualizar data-full-url del modal
                        $('#image-modal').data('full-url', attachment.url);
                        
                        // Actualizar miniatura en tabla
                        const cell = $('tr[data-queue-id="' + id + '"]').find('.' + type + '-image-cell');
                        const imgTag = cell.find('img.queue-thumbnail');
                        if (imgTag.length) {
                            imgTag.attr('src', newUrl);
                            imgTag.data('full-url', attachment.url);
                        } else {
                            // Si no hay imagen, crearla
                            cell.find('span').first().replaceWith(
                                '<img src="' + newUrl + '" class="queue-thumbnail open-image-modal" data-id="' + id + '" data-type="' + type + '" data-full-url="' + attachment.url + '" style="width:100%; height:90px; object-fit:cover; border-radius:4px; cursor:pointer; display:block;">'
                            );
                        }
                        
                        // Reabrir modal
                        $('#image-modal').css('display', 'flex');
                    };
                    tempImg.onerror = function() {
                        alert('Error al cargar imagen de biblioteca');
                        $('#image-modal').css('display', 'flex');
                    };
                    tempImg.src = newUrl;
                } else {
                    alert('Error al actualizar imagen');
                    $('#image-modal').css('display', 'flex');
                }
            }).fail(function() {
                alert('Error de conexión');
                $('#image-modal').css('display', 'flex');
            });
        });
        
        // Si cierra sin seleccionar, reabrir modal
        frame.on('close', function() {
            setTimeout(function() {
                if (!frame.state().get('selection').first()) {
                    $('#image-modal').css('display', 'flex');
                }
            }, 100);
        });
        
        frame.open();
    });
    
    // Inicializar
    updateBulkDeleteButton();
    
    // ============================================
    // GENERAR PROMPT DE CONTENIDO CON IA
    // ============================================
    
    // Añadir estilos de spinner
    $('<style>' +
        '.queue-spinner {' +
        '    display: inline-block;' +
        '    width: 14px;' +
        '    height: 14px;' +
        '    border: 2px solid rgba(33, 113, 177, 0.3);' +
        '    border-top-color: #2271b1;' +
        '    border-radius: 50%;' +
        '    animation: queue-spin 0.6s linear infinite;' +
        '    margin-right: 6px;' +
        '    vertical-align: middle;' +
        '}' +
        '@keyframes queue-spin {' +
        '    to { transform: rotate(360deg); }' +
        '}' +
    '</style>').appendTo('head');
    
    $('#generate-content-prompt-btn').on('click', function() {
        const textarea = $('#queue-prompt-content');
        
        // Solo ejecutar si existe el elemento
        if (textarea.length === 0) {
            return;
        }
        
        const btn = $(this);
        const campaignId = $('#start-generate-queue').data('campaign-id');
        
        // Mostrar spinner CSS animado
        btn.prop('disabled', true);
        btn.find('.btn-text').html('<span class="queue-spinner"></span>Generando con IA...');
        btn.find('.btn-spinner').hide();
        
        $.post(apQueue.ajax_url, {
            action: 'ap_generate_queue_prompt',
            nonce: apQueue.nonce,
            campaign_id: campaignId
        }, function(response) {
            if (response.success) {
                textarea.val(response.data.prompt);
                
                // Animación de éxito
                textarea.css({
                    'border-color': '#10b981',
                    'background': '#f0fff4',
                    'transition': 'all 0.3s'
                });
                
                // Mostrar feedback visual
                btn.find('.btn-text').html('✅ Generado');
                setTimeout(() => {
                    textarea.css({
                        'border-color': '#10b981',
                        'background': 'white'
                    });
                    btn.find('.btn-text').html('Generar con IA');
                }, 2000);
            } else {
                alert('ERROR: ' + (response.data.message || 'No se pudo generar el prompt'));
            }
        }).fail(function() {
            alert('ERROR: Error de conexión al generar prompt');
        }).always(function() {
            // Restaurar botón
            btn.prop('disabled', false);
            btn.find('.btn-spinner').hide();
        });
    });
    
    // ============================================
    // GENERAR COLA CON PROMPT PERSONALIZADO
    // ============================================
    $(document).on('click', '#start-generate-queue', function() {
        // Solo ejecutar si existe el elemento de prompt personalizado
        if ($('#queue-prompt-content').length === 0) {
            return; // Dejar que el otro listener maneje el click
        }
        
        const btn = $(this);
        const campaignId = btn.data('campaign-id');
        const customPrompt = $('#queue-prompt-content').val().trim();
        
        if (!customPrompt) {
            alert('ATENCIÓN: Por favor, escribe o genera un prompt de contenido antes de generar la cola.');
            $('#queue-prompt-content').focus();
            return;
        }
        
        if (!confirm('¿Generar cola de posts con este prompt personalizado?')) {
            return;
        }
        
        // Mostrar spinner CSS animado en botón
        btn.prop('disabled', true);
        btn.find('.btn-text').html('<span class="queue-spinner"></span>Generando cola...');
        btn.find('.btn-spinner').hide();
        
        // Mostrar barra de progreso
        $('#generate-progress').show();
        $('#progress-log').html('');
        
        // Animación de progreso realista
        let progress = 0;
        const progressInterval = setInterval(() => {
            if (progress < 85) {
                progress += Math.random() * 5;
                $('#progress-bar').css('width', progress + '%');
            }
        }, 800);
        
        // Mensajes de progreso
        const logMessage = (msg) => {
            const time = new Date().toLocaleTimeString();
            $('#progress-log').append(`<div style="padding:5px; border-bottom:1px solid #eee;"><strong>${time}</strong> - ${msg}</div>`);
            $('#progress-log').scrollTop($('#progress-log')[0].scrollHeight);
        };
        
        $('#progress-text').html('Generando títulos con IA...');
        logMessage('Iniciando generación de cola');
        logMessage('Solicitando ' + (campaignId ? 'títulos' : 'datos') + ' a la IA');

        setTimeout(() => {
            logMessage('Generando keywords para búsqueda de imágenes...');
            $('#progress-text').html('Generando keywords para imágenes...');
        }, 2000);

        setTimeout(() => {
            logMessage('Buscando imágenes en proveedores externos...');
            $('#progress-text').html('Buscando imágenes...');
        }, 4000);

        setTimeout(() => {
            logMessage('Calculando fechas de publicación...');
            $('#progress-text').html('Calculando fechas...');
        }, 6000);
        
        $.post(apQueue.ajax_url, {
            action: 'ap_generate_queue_with_prompt',
            nonce: apQueue.nonce,
            campaign_id: campaignId,
            custom_prompt: customPrompt
        }, function(response) {
            clearInterval(progressInterval);
            
            if (response.success) {
                $('#progress-bar').css('width', '100%');
                logMessage('Títulos generados correctamente');
                logMessage('Keywords generadas correctamente');
                logMessage('Imágenes asignadas');
                logMessage('Cola creada con ' + (response.data.created || '0') + ' posts');
                $('#progress-text').html('<strong style="color:#059669;">Cola generada correctamente</strong>');
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                $('#progress-bar').css({
                    'width': '100%',
                    'background': '#dc2626'
                });
                const errorMsg = response.data.message || 'Error desconocido';
                logMessage('ERROR: ' + errorMsg);
                $('#progress-text').html('<strong style="color:#dc2626;">ERROR en la generación</strong>');

                // Solo mostrar alert si NO es error de bloqueo
                if (!errorMsg.includes('generándose')) {
                    alert('ERROR: ' + errorMsg);
                }
                
                setTimeout(() => {
                    $('#generate-progress').hide();
                    btn.prop('disabled', false);
                    btn.find('.btn-text').html('Generar Cola');
                    btn.find('.btn-spinner').hide();
                    
                    // Si es error de bloqueo, recargar para actualizar UI
                    if (errorMsg.includes('generándose')) {
                        location.reload();
                    }
                }, errorMsg.includes('generándose') ? 1000 : 3000);
            }
        }).fail(function(xhr, status, error) {
            clearInterval(progressInterval);
            
            $('#progress-bar').css({
                'width': '100%',
                'background': '#dc2626'
            });
            logMessage('ERROR: Error de conexión: ' + error);
            $('#progress-text').html('<strong style="color:#dc2626;">ERROR: Error de conexión</strong>');

            alert('ERROR: Error de conexión. Revisa tu conexión a internet.');
            
            setTimeout(() => {
                $('#generate-progress').hide();
                btn.prop('disabled', false);
                btn.find('.btn-text').html('🚀 Generar Cola');
                btn.find('.btn-spinner').hide();
            }, 3000);
        });
    });
    
    // ============================================
    // POLLING: ACTUALIZAR ESTADOS DE ITEMS
    // ============================================
    function updateItemStatuses() {
        // Solo hacer polling si hay items en la tabla
        const items = $('.status-cell[data-queue-id]');
        if (items.length === 0) {
            console.log('[Queue Status] No hay items para actualizar');
            return;
        }
        
        const queueIds = [];
        items.each(function() {
            queueIds.push($(this).data('queue-id'));
        });
        
        console.log('[Queue Status] Consultando estados de', queueIds.length, 'items');
        
        $.post(apQueue.ajax_url, {
            action: 'ap_get_queue_items_status',
            nonce: apQueue.nonce,
            queue_ids: queueIds
        }, function(response) {
            if (response.success && response.data) {
                let changesCount = 0;
                $.each(response.data, function(queueId, newStatus) {
                    const statusCell = $(`.status-cell[data-queue-id="${queueId}"]`);
                    const currentStatus = statusCell.data('status');
                    
                    // Solo actualizar si cambió el estado
                    if (currentStatus !== newStatus) {
                        console.log('[Queue Status] Item', queueId, 'cambió:', currentStatus, '→', newStatus);
                        updateStatusBadge(statusCell, newStatus);
                        statusCell.data('status', newStatus);
                        changesCount++;
                    }
                });
                
                if (changesCount > 0) {
                    console.log('[Queue Status] Se actualizaron', changesCount, 'items');
                } else {
                    console.log('[Queue Status] Sin cambios');
                }
            } else {
                console.error('[Queue Status] Error en respuesta:', response);
            }
        }).fail(function(xhr, status, error) {
            console.error('[Queue Status] Error AJAX:', error);
        });
    }
    
    /**
     * Actualizar el badge de estado con animación
     */
    function updateStatusBadge(statusCell, newStatus) {
        const statusConfig = {
            'pending': {
                label: 'Pendiente',
                color: '#f59e0b',
                bg: '#fef3c7',
                icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
            },
            'processing': {
                label: 'Ejecutando',
                color: '#000000',
                bg: '#000000',
                icon: '<svg class="spin-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>'
            },
            'completed': {
                label: 'Completado',
                color: '#10b981',
                bg: '#d1fae5',
                icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            },
            'error': {
                label: 'Error',
                color: '#ef4444',
                bg: '#fee2e2',
                icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
            }
        };
        
        const config = statusConfig[newStatus] || {
            label: newStatus,
            color: '#6b7280',
            bg: '#f3f4f6',
            icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>'
        };
        
        const badge = statusCell.find('.status-badge');
        
        // Crear el nuevo HTML completo del badge
        const newBadgeHtml = `
            <div class="status-badge" style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:${config.bg}; color:${config.color}; border-radius:6px; font-size:13px; font-weight:600;">
                ${config.icon}
                <span class="status-label">${config.label}</span>
            </div>
        `;
        
        // Reemplazar todo el contenido con fade
        badge.fadeOut(200, function() {
            statusCell.html(newBadgeHtml);
            statusCell.find('.status-badge').hide().fadeIn(200);
        });
    }
    
    // ============================================
    // INICIAR POLLING DE ESTADOS
    // ============================================
    // Iniciar polling si hay items en la tabla
    if ($('.status-cell[data-queue-id]').length > 0) {
        // Ejecutar inmediatamente al cargar
        setTimeout(updateItemStatuses, 500);
        
        // Continuar polling cada 3 segundos
        setInterval(updateItemStatuses, 3000);
    }
    
    // ============================================
    // AUTO-GENERACIÓN AL CARGAR PÁGINA
    // ============================================
    const urlParams = new URLSearchParams(window.location.search);
    const autoGenerate = urlParams.get('auto_generate');
    if (autoGenerate && autoGenerate !== '0') {
        // Esperar 500ms para que la página cargue completamente
        setTimeout(() => {
            const btn = $('#start-generate-queue');
            if (btn.length > 0) {
                btn.click();
                
                // Limpiar el parámetro de la URL sin recargar
                const newUrl = window.location.pathname + '?page=autopost-queue&campaign_id=' + apQueue.campaign_id;
                window.history.replaceState({}, '', newUrl);
            }
        }, 500);
    }
    
    // ============================================
    // DETECTAR GENERACIÓN EN CURSO AL CARGAR
    // ============================================
    // Verificar si hay un bloqueo activo al cargar la página
    setTimeout(() => {
        $.post(apQueue.ajax_url, {
            action: 'ap_check_lock_status',
            nonce: apQueue.nonce
        }, function(response) {
            if (response.success && response.data.generate_locked) {
                const lockInfo = response.data.generate_info;
                
                // Verificar si es esta campaña
                if (lockInfo && lockInfo.campaign_id == apQueue.campaign_id) {
                    // Mostrar barra de progreso
                    $('#generate-progress').show();
                    $('#progress-text').html('⏳ Generación en curso...');
                    
                    // Obtener el número esperado de posts
                    const expectedPosts = parseInt($('#expected-posts').val() || '10');
                    
                    // Iniciar polling de progreso
                    startProgressPolling(apQueue.campaign_id, expectedPosts);
                    startLockRenewal();
                }
            }
        });
    }, 1000);
});