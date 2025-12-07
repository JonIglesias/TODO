/**
 * ========================================
 * SISTEMA UNIFICADO DE AUTOGUARDADO DE CAMPAÑAS
 * ========================================
 *
 * Sistema rediseñado desde cero para:
 * - UN SOLO punto de guardado automático
 * - Validación estricta de nombre (mínimo 3 caracteres)
 * - Prevención de duplicados
 * - Debounce inteligente (3 segundos)
 * - Detección de cambios reales
 * - Sistema de flags para evitar guardados simultáneos
 *
 * @version 2.0.0
 * @author AutoPost Team
 */

jQuery(document).ready(function($) {
    'use strict';

    // ========================================
    // PROTECCIÓN CONTRA INICIALIZACIÓN MÚLTIPLE
    // ========================================
    if (window.apAutosaveUnifiedInitialized) {
        console.warn('⚠️ Sistema de autoguardado ya inicializado, abortando');
        return;
    }
    window.apAutosaveUnifiedInitialized = true;

    // ========================================
    // VARIABLES DE CONTROL
    // ========================================

    let autosaveTimer = null;              // Timer de debounce
    let campaignId = parseInt($('#campaign_id').val()) || 0;  // ID de campaña actual
    let isSaving = false;                  // Flag para evitar guardados simultáneos
    let lastSavedData = null;              // Datos del último guardado exitoso
    let saveQueue = false;                 // Flag para indicar que hay cambios pendientes

    // Configuración
    const CONFIG = {
        DEBOUNCE_MS: 3000,                 // 3 segundos de espera
        MIN_NAME_LENGTH: 3,                // Mínimo 3 caracteres para nombre
        AUTOSAVE_NOTICE_DURATION: 2000     // Duración del mensaje de éxito
    };

    console.log('🚀 Sistema Unificado de Autoguardado inicializado');
    console.log('📋 Campaign ID inicial:', campaignId);

    // ========================================
    // OBTENER DATOS ACTUALES DEL FORMULARIO
    // ========================================

    function getFormData() {
        const formData = {
            campaign_id: campaignId,
            name: $('#name').val()?.trim() || '',
            domain: $('#domain').val()?.trim() || '',
            company_desc: $('#company_desc').val()?.trim() || '',
            niche: $('#niche').val() === 'Otro' ? $('#niche_custom').val()?.trim() : $('#niche').val(),
            num_posts: parseInt($('#num_posts').val()) || 0,
            post_length: $('#post_length').val() || 'medio',
            keywords_seo: $('#keywords_seo').val()?.trim() || '',
            prompt_titles: $('#prompt_titles').val()?.trim() || '',
            prompt_content: $('#prompt_content').val()?.trim() || '',
            keywords_images: $('#keywords_images').val()?.trim() || '',
            category_id: parseInt($('#category_id').val()) || 0,
            start_date: $('#start_date').val() || '',
            post_time: $('#publish_time').val() || '09:00',
            weekdays: getSelectedWeekdays(),
            image_provider: $('#image_provider').val() || 'pexels'
        };

        return formData;
    }

    // ========================================
    // OBTENER DÍAS DE PUBLICACIÓN SELECCIONADOS
    // ========================================

    function getSelectedWeekdays() {
        const days = [];
        $('input[name="publish_days[]"]:checked').each(function() {
            days.push($(this).val());
        });
        return days.join(',');
    }

    // ========================================
    // VALIDAR DATOS DEL FORMULARIO
    // ========================================

    function validateFormData(data) {
        const errors = [];

        // CRÍTICO: El nombre es OBLIGATORIO y debe tener al menos 3 caracteres
        if (!data.name || data.name.length < CONFIG.MIN_NAME_LENGTH) {
            errors.push(`El nombre debe tener al menos ${CONFIG.MIN_NAME_LENGTH} caracteres`);
        }

        // Validaciones opcionales pero recomendadas
        if (campaignId === 0 && !data.domain) {
            // Solo advertencia si es campaña nueva
            console.warn('⚠️ Se recomienda completar el dominio');
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    // ========================================
    // DETECTAR SI HAY CAMBIOS REALES
    // ========================================

    function hasRealChanges(newData) {
        if (!lastSavedData) {
            // Primera vez, siempre hay cambios
            return true;
        }

        // Comparar cada campo relevante (solo campos que existen en BD)
        const fieldsToCompare = [
            'name', 'domain', 'company_desc', 'niche', 'num_posts', 'post_length',
            'keywords_seo', 'prompt_titles', 'prompt_content', 'keywords_images',
            'category_id', 'start_date', 'post_time', 'weekdays', 'image_provider'
        ];

        for (const field of fieldsToCompare) {
            const oldValue = String(lastSavedData[field] || '');
            const newValue = String(newData[field] || '');

            if (oldValue !== newValue) {
                console.log(`🔄 Cambio detectado en "${field}":`, {
                    anterior: oldValue.substring(0, 50),
                    nuevo: newValue.substring(0, 50)
                });
                return true;
            }
        }

        console.log('ℹ️ No hay cambios reales, omitiendo guardado');
        return false;
    }

    // ========================================
    // FUNCIÓN PRINCIPAL DE AUTOGUARDADO
    // ========================================

    function performAutosave() {
        console.log('🔄 performAutosave() iniciado');

        // Verificar si ya hay un guardado en curso
        if (isSaving) {
            console.log('⏳ Guardado ya en curso, se volverá a intentar');
            saveQueue = true;  // Marcar que hay cambios pendientes
            return;
        }

        // Obtener datos actuales del formulario
        const formData = getFormData();

        // Validar datos
        const validation = validateFormData(formData);
        if (!validation.valid) {
            console.warn('⚠️ Validación fallida:', validation.errors);
            showNotice(validation.errors[0], 'warning');
            return;
        }

        // Verificar si hay cambios reales
        if (!hasRealChanges(formData)) {
            return;  // No hay cambios, no hacer nada
        }

        // Marcar como guardando
        isSaving = true;
        saveQueue = false;

        console.log('💾 Guardando campaña...', {
            campaign_id: campaignId,
            name: formData.name,
            es_nueva: campaignId === 0
        });

        // Preparar datos para AJAX
        const ajaxData = Object.assign({}, formData, {
            action: 'ap_autosave_campaign',
            nonce: apCampaignEdit.nonce
        });

        // Realizar petición AJAX
        $.ajax({
            url: apCampaignEdit.ajax_url,
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                showNotice('Guardando...', 'info');
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Guardado exitoso');

                    // Si es campaña nueva, actualizar el ID
                    if (campaignId === 0 && response.data.campaign_id) {
                        campaignId = parseInt(response.data.campaign_id);
                        $('#campaign_id').val(campaignId);
                        console.log('🆕 Campaña nueva creada con ID:', campaignId);

                        // Actualizar URL sin recargar la página
                        if (window.history.pushState) {
                            const newUrl = window.location.protocol + "//" + window.location.host +
                                          window.location.pathname + '?page=autopost-campaign-edit&id=' + campaignId;
                            window.history.pushState({path: newUrl}, '', newUrl);
                        }
                    }

                    // Guardar datos para próxima comparación
                    lastSavedData = Object.assign({}, formData);
                    lastSavedData.campaign_id = campaignId;

                    showNotice('✓ Cambios guardados automáticamente', 'success');

                    // Si había cambios en cola, ejecutar otro guardado
                    if (saveQueue) {
                        console.log('🔄 Hay cambios pendientes, programando nuevo guardado');
                        scheduleAutosave();
                    }

                } else {
                    console.error('❌ Error en guardado:', response.data?.message);
                    showNotice('Error: ' + (response.data?.message || 'No se pudo guardar'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error AJAX:', error);
                showNotice('Error de conexión al guardar', 'error');
            },
            complete: function() {
                isSaving = false;
            }
        });
    }

    // ========================================
    // PROGRAMAR AUTOGUARDADO (CON DEBOUNCE)
    // ========================================

    function scheduleAutosave() {
        // Cancelar timer anterior si existe
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }

        // Programar nuevo guardado después del debounce
        autosaveTimer = setTimeout(function() {
            performAutosave();
        }, CONFIG.DEBOUNCE_MS);

        console.log(`⏱️ Autoguardado programado en ${CONFIG.DEBOUNCE_MS}ms`);
    }

    // ========================================
    // MOSTRAR NOTIFICACIONES
    // ========================================

    function showNotice(message, type) {
        // Tipos: success, error, warning, info
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#000000'
        };

        const bgColor = colors[type] || colors.info;

        // Remover notificación anterior si existe
        $('.ap-autosave-notice').remove();

        const notice = $('<div class="ap-autosave-notice"></div>')
            .text(message)
            .css({
                'position': 'fixed',
                'top': '32px',
                'right': '20px',
                'background': bgColor,
                'color': 'white',
                'padding': '12px 24px',
                'border-radius': '6px',
                'z-index': '999999',
                'font-size': '14px',
                'font-weight': '500',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transition': 'opacity 0.3s ease',
                'max-width': '400px'
            });

        $('body').append(notice);

        // Fade in
        setTimeout(() => notice.css('opacity', '1'), 10);

        // Fade out y remover (excepto para mensajes de error que duran más)
        const duration = type === 'error' ? 4000 : CONFIG.AUTOSAVE_NOTICE_DURATION;
        setTimeout(() => {
            notice.css('opacity', '0');
            setTimeout(() => notice.remove(), 300);
        }, duration);
    }

    // ========================================
    // EVENTOS DEL FORMULARIO
    // ========================================

    // Detectar cambios en CUALQUIER campo del formulario
    $('#campaign-form').on('input change', 'input, textarea, select', function() {
        const fieldName = $(this).attr('name') || $(this).attr('id');
        console.log('📝 Campo modificado:', fieldName);

        // Programar autoguardado
        scheduleAutosave();
    });

    // Detectar cambios en checkboxes de días de publicación
    $('input[name="publish_days[]"]').on('change', function() {
        console.log('📅 Días de publicación modificados');
        scheduleAutosave();
    });

    // ========================================
    // GUARDADO MANUAL (BOTÓN "GUARDAR CAMPAÑA")
    // ========================================

    // Antes de enviar el formulario, guardar automáticamente si hay cambios pendientes
    $('#campaign-form').on('submit', function(e) {
        // Si hay timer pendiente, cancelarlo porque vamos a guardar manualmente
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }

        // Validar antes de enviar
        const formData = getFormData();
        const validation = validateFormData(formData);

        if (!validation.valid) {
            e.preventDefault();
            alert('ERROR: ' + validation.errors.join('\n'));
            return false;
        }

        console.log('📤 Formulario enviado manualmente');
    });

    // ========================================
    // GUARDADO AL SALIR DE LA PÁGINA
    // ========================================

    window.addEventListener('beforeunload', function(e) {
        // Si hay cambios pendientes, advertir al usuario
        if (saveQueue || (autosaveTimer !== null)) {
            const message = 'Hay cambios sin guardar. ¿Estás seguro de que quieres salir?';
            e.returnValue = message;
            return message;
        }
    });

    // ========================================
    // INICIALIZACIÓN: GUARDAR DATOS INICIALES
    // ========================================

    // Al cargar la página, guardar el estado inicial para comparaciones
    $(window).on('load', function() {
        lastSavedData = getFormData();
        console.log('📸 Estado inicial capturado');
    });

    console.log('✅ Sistema de autoguardado unificado listo');
});
