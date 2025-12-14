/**
 * Conversa - Shepherd.js Tours
 * Sistema de tutoriales interactivos para Conversa (PHSBOT)
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Objeto global para almacenar los tours
    window.PHSBOT_Tours = window.PHSBOT_Tours || {};

    // Configuración por defecto de Shepherd
    const defaultOptions = {
        useModalOverlay: true,
        exitOnEsc: true,
        keyboardNavigation: true,
        defaultStepOptions: {
            scrollTo: { behavior: 'smooth', block: 'center' },
            cancelIcon: { enabled: true },
            classes: 'phsbot-shepherd-theme',
            modalOverlayOpeningPadding: 8,
            modalOverlayOpeningRadius: 8
        }
    };

    // ===========================================
    // TOUR: CONFIGURACIÓN - TAB CONEXIONES
    // ===========================================
    PHSBOT_Tours.configConexiones = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '👋 Bienvenido - Conexiones',
            text: 'Te guiaremos por la configuración de las conexiones del chatbot. Esta es la parte más importante!',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'license',
            title: '🔑 Licencia BOT (OBLIGATORIO)',
            text: '⚠️ <strong>¡CRÍTICO!</strong> Sin una licencia válida, el chatbot NO funcionará. Introduce tu clave que empieza por BOT- y haz clic en "Validar Licencia".',
            attachTo: { element: '#bot_license_key', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'telegram-token',
            title: '📱 Token de Telegram (Opcional)',
            text: 'Configura un bot de Telegram para recibir notificaciones cuando lleguen leads importantes. Primero necesitas crear un bot con @BotFather y copiar el token aquí.',
            attachTo: { element: '#telegram_bot_token', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'telegram-chat-id',
            title: '💬 Chat ID de Telegram (Opcional)',
            text: 'Introduce el ID del chat, usuario o canal donde quieres recibir las notificaciones. Puedes obtenerlo usando @userinfobot en Telegram.',
            attachTo: { element: '#telegram_chat_id', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'whatsapp',
            title: '💬 WhatsApp (Opcional)',
            text: 'Número de WhatsApp en formato internacional. El chatbot puede mostrar un botón para contactar por WhatsApp.',
            attachTo: { element: '#whatsapp_phone', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // TOUR: CONFIGURACIÓN - TAB CHAT (IA)
    // ===========================================
    PHSBOT_Tours.configChat = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '💬 Configuración del Chat',
            text: 'Aquí configuras los mensajes y el comportamiento de la inteligencia artificial del chatbot.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'welcome-message',
            title: '👋 Mensaje de Bienvenida',
            text: 'Personaliza el primer mensaje que verán tus visitantes cuando abran el chat. Hazlo amigable y acogedor.',
            attachTo: { element: '#chat_welcome', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'system-prompt',
            title: '🤖 System Prompt (IMPORTANTE)',
            text: 'Define la personalidad y comportamiento de tu chatbot. Este prompt instruye a la IA sobre cómo debe responder, su tono, estilo y conocimientos.',
            attachTo: { element: '#chat_system_prompt', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'advanced-options',
            title: '⚙️ Opciones Avanzadas',
            text: 'Opciones como permitir HTML en respuestas, integración con Elementor y live fetch para obtener la URL actual de la página.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // TOUR: CONFIGURACIÓN - TAB ASPECTO
    // ===========================================
    PHSBOT_Tours.configAspecto = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '🎨 Aspecto Visual',
            text: 'Personaliza completamente la apariencia del chatbot para que combine con tu marca. Verás una vista previa en tiempo real!',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'position',
            title: '📍 Posición del Chat',
            text: 'Elige dónde aparecerá el botón del chatbot en tu web: abajo derecha, abajo izquierda, arriba, etc.',
            attachTo: { element: '#chat_position', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'title-and-font',
            title: '✏️ Título y Fuente',
            text: 'Personaliza el título que aparece en la cabecera del chat y el tamaño de fuente de los mensajes (12-22px).',
            attachTo: { element: '#chat_title', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'size',
            title: '📐 Ancho y Alto',
            text: 'Ajusta las dimensiones del chat. Ancho: 260-920px, Alto: 420-960px. Usa los sliders para ver los cambios en tiempo real.',
            attachTo: { element: '#chat_width_slider', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'color-primary',
            title: '🎨 Color Primario',
            text: 'Color principal del chat (cabecera y elementos destacados). Este color define la identidad visual del chatbot.',
            attachTo: { element: 'input[name="color_primary"]', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'color-bubbles',
            title: '💬 Colores de Burbujas',
            text: 'Define los colores de las burbujas de mensajes: una para los mensajes del bot y otra para los del usuario.',
            attachTo: { element: 'input[name="color_bot_bubble"]', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'color-launcher',
            title: '🚀 Colores del Botón Flotante',
            text: 'Personaliza el botón flotante que abre el chat: fondo, icono y texto. Estos colores se aplican al botón que verán tus visitantes.',
            attachTo: { element: 'input[name="color_launcher_bg"]', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'preview',
            title: '👀 Vista Previa',
            text: '¡Fíjate en la vista previa de la derecha! Todos los cambios que hagas se reflejan en tiempo real. Cuando estés satisfecho, guarda la configuración.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // TOUR: BASE DE CONOCIMIENTO
    // ===========================================
    PHSBOT_Tours.kb = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '📚 Base de Conocimiento',
            text: 'La Base de Conocimiento es el cerebro de tu chatbot. Aquí se almacena toda la información que el bot usará para responder a tus clientes.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'generate',
            title: '🔄 Generar Documento',
            text: 'Haz clic en "Generar documento" para que el sistema analice automáticamente tu sitio web y cree la base de conocimiento. Este proceso puede tardar varios minutos.',
            attachTo: { element: '#phsbot-kb-generate', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'document',
            title: '📝 Revisar y Editar',
            text: 'Una vez generado, puedes revisar y editar el documento para añadir información importante como precios actualizados, horarios, políticas, etc.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'save',
            title: '💾 Guardar Cambios',
            text: 'Recuerda guardar tus cambios cuando termines de editar. El chatbot usará esta información actualizada para responder a los clientes.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // TOUR: INYECCIONES (TRIGGERS)
    // ===========================================
    PHSBOT_Tours.inject = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '💉 Inyecciones (Triggers)',
            text: 'Las inyecciones te permiten inyectar contenido automático (HTML, shortcodes, vídeos o redirecciones) cuando el usuario escribe determinadas palabras clave en el chat.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'add-rule',
            title: '➕ Añadir Regla',
            text: 'Haz clic en "Añadir regla" para crear un nuevo trigger. Podrás configurar las palabras clave que activarán el contenido inyectado.',
            attachTo: { element: '#phsbot-add-row-top', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'keywords',
            title: '🔑 Palabras Clave',
            text: 'Define las palabras o frases que activarán el trigger. Por ejemplo: "precio, precios, cuánto cuesta". Puedes usar múltiples palabras separadas por comas.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'types',
            title: '🎯 Tipos de Contenido',
            text: '<strong>Tipos disponibles:</strong><br/>• <strong>HTML</strong>: Código HTML personalizado<br/>• <strong>Shortcode</strong>: Shortcodes de WordPress/Elementor<br/>• <strong>Vídeo YouTube</strong>: URL de vídeo con autoplay opcional<br/>• <strong>Redirect</strong>: Redirigir a otra página<br/>• <strong>Producto WooCommerce</strong>: Mostrar ficha de producto',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'positions',
            title: '📍 Posiciones',
            text: '<strong>Dónde se muestra el contenido:</strong><br/>• <strong>Antes</strong>: Se inserta justo después del mensaje del usuario<br/>• <strong>Después</strong>: Se espera a la respuesta del bot y se inserta debajo<br/>• <strong>Sólo trigger</strong>: Solo se muestra el contenido inyectado (sin respuesta del bot)',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'match-mode',
            title: '🎲 Modo de Coincidencia',
            text: '<strong>ANY</strong>: Se activa si el usuario escribe <em>cualquiera</em> de las palabras clave.<br/><strong>ALL</strong>: Se activa solo si el usuario escribe <em>todas</em> las palabras clave.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // TOUR: LEADS & SCORING
    // ===========================================
    PHSBOT_Tours.leads = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '🎯 Leads & Scoring',
            text: 'Este módulo te permite gestionar los leads capturados por el chatbot y ver su puntuación de calidad. Los leads con puntuación alta (≥8) son notificados automáticamente vía Telegram.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'table',
            title: '📊 Tabla de Leads',
            text: 'Aquí se muestran todos los leads capturados con su información: nombre, email, teléfono, puntuación (score), estado y página de origen. Puedes ordenar y filtrar los resultados.',
            attachTo: { element: '#phsbot-leads-table', on: 'top' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'search',
            title: '🔍 Buscar Leads',
            text: 'Usa este campo para buscar leads por nombre, email, teléfono o página. La búsqueda filtra la tabla en tiempo real.',
            attachTo: { element: '#phsbot-leads-search', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'filter-open',
            title: '✅ Filtrar Solo Abiertos',
            text: 'Activa este interruptor para mostrar únicamente los leads que aún están abiertos (no cerrados). Útil para enfocarte en leads activos.',
            attachTo: { element: '#phsbot-leads-open-only', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'actions',
            title: '⚙️ Acciones Disponibles',
            text: '<strong>Ver</strong>: Muestra el detalle completo del lead con historial de conversación.<br/><strong>Cerrar</strong>: Marca el lead como cerrado cuando ya lo hayas contactado.<br/><strong>Borrar</strong>: Elimina el lead permanentemente.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'purge',
            title: '🧹 Purgar Cerrados',
            text: 'Haz clic en "Purgar cerrados (>30d)" para eliminar automáticamente todos los leads cerrados hace más de 30 días. Útil para mantener la base de datos limpia.',
            attachTo: { element: '#phsbot-leads-purge', on: 'bottom' },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'settings',
            title: '⚙️ Configuración',
            text: 'En la pestaña "Configuración" puedes ajustar el umbral de puntuación para notificaciones de Telegram, activar/desactivar la captura de leads y configurar los campos opcionales.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Finalizar',
                    action: tour.complete,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        return tour;
    };

    // ===========================================
    // DETECCIÓN DE MÓDULO Y TAB ACTUAL
    // ===========================================
    function detectCurrentModule() {
        const page = new URLSearchParams(window.location.search).get('page');

        // Para la página de configuración, detectar el tab activo
        if (page === 'phsbot' || page === 'phsbot_config') {
            const activeTab = detectActiveTab();
            if (activeTab) {
                return 'config-' + activeTab;
            }
            return 'config-conexiones'; // Default
        }

        if (page === 'phsbot-kb' || page === 'phsbot_kb') return 'kb';
        if (page === 'phsbot-inject') return 'inject';
        if (page === 'phsbot-leads') return 'leads';
        if (page === 'phsbot-chat') return 'chat';
        if (page === 'phsbot-estadisticas') return 'stats';

        return null;
    }

    function detectActiveTab() {
        // Detectar cuál tab está visible
        const tabs = {
            'conexiones': $('#tab-conexiones'),
            'chat': $('#tab-chat'),
            'aspecto': $('#tab-aspecto')
        };

        for (let tabName in tabs) {
            const $tab = tabs[tabName];
            if ($tab.length && $tab.attr('aria-hidden') === 'false') {
                return tabName;
            }
        }

        return 'conexiones'; // Default
    }

    // ===========================================
    // GESTIÓN DE ESTADO DE TOURS
    // ===========================================
    function getTourStatus(tourId) {
        return localStorage.getItem('phsbot_tour_' + tourId) === 'completed';
    }

    function markTourCompleted(tourId) {
        localStorage.setItem('phsbot_tour_' + tourId, 'completed');
    }

    // ===========================================
    // AÑADIR BOTONES DE AYUDA
    // ===========================================
    function addHelpButtons() {
        const currentModule = detectCurrentModule();
        if (!currentModule) return;

        // Determinar el módulo base (sin el sufijo de tab)
        const moduleBase = currentModule.split('-')[0];

        // No añadir botón si no hay tour para este módulo
        const validModules = ['config', 'kb', 'inject', 'leads'];
        if (!validModules.includes(moduleBase)) return;

        // Para config, añadir botón en cada tab
        if (moduleBase === 'config') {
            addConfigTabButtons();
        } else {
            // Para otros módulos, añadir botón en el header principal
            addMainHeaderButton(currentModule);
        }
    }

    function addConfigTabButtons() {
        // Añadir un botón dinámico en el header principal que cambia según el tab activo
        const $header = $('.phsbot-module-header').first();
        if (!$header.length) return;

        // No añadir si ya existe
        if ($header.find('.phsbot-help-tour-btn').length > 0) return;

        const tabs = {
            'conexiones': { module: 'configConexiones', title: 'Conexiones' },
            'chat': { module: 'configChat', title: 'Chat (IA)' },
            'aspecto': { module: 'configAspecto', title: 'Aspecto' }
        };

        // Crear botón dinámico
        const helpBtn = $(`
            <button type="button" class="phsbot-help-tour-btn" id="phsbot-config-tour-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span id="phsbot-tour-btn-text">Tutorial</span>
            </button>
        `);

        // Insertar en el header a la derecha (como último elemento)
        $header.append(helpBtn);

        // Actualizar texto del botón según tab activo
        function updateButtonText() {
            const activeTab = detectActiveTab();
            if (tabs[activeTab]) {
                $('#phsbot-tour-btn-text').text('Tutorial: ' + tabs[activeTab].title);
                $('#phsbot-config-tour-btn').data('tour', tabs[activeTab].module);
            }
        }

        // Event listener
        helpBtn.on('click', function() {
            const tourName = $(this).data('tour');
            if (tourName && PHSBOT_Tours[tourName]) {
                startTour(tourName);
            }
        });

        // Actualizar texto inicial
        updateButtonText();

        // Actualizar cuando cambie el tab
        $('.phsbot-config-tabs .nav-tab').on('click', function() {
            setTimeout(updateButtonText, 50);
        });
    }

    function addMainHeaderButton(currentModule) {
        // No añadir si ya existe
        if ($('.phsbot-help-tour-btn').length > 0) return;

        const $header = $('.phsbot-module-header').first();
        if (!$header.length) return;

        const helpBtn = $(`
            <button type="button" class="phsbot-help-tour-btn" id="phsbot-tour-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>Tutorial</span>
            </button>
        `);

        // Insertar botón en el header a la derecha (como último elemento)
        $header.append(helpBtn);

        // Event listener para el botón
        helpBtn.on('click', function() {
            startTour(currentModule);
        });
    }

    // ===========================================
    // INICIAR TOUR
    // ===========================================
    function startTour(moduleId) {
        if (!PHSBOT_Tours[moduleId]) {
            console.warn('No hay tour definido para el módulo:', moduleId);
            return;
        }

        const tour = PHSBOT_Tours[moduleId]();

        tour.on('complete', function() {
            markTourCompleted(moduleId);
        });

        tour.on('cancel', function() {
            // No marcar como completado si se cancela
        });

        tour.start();
    }

    // Normalizar nombre de módulo para detección de tab
    function normalizeModuleName(moduleName) {
        // Convertir "config-conexiones" a "configConexiones"
        if (moduleName.startsWith('config-')) {
            const tabName = moduleName.replace('config-', '');
            return 'config' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
        }
        return moduleName;
    }

    // ===========================================
    // AUTO-INICIO DE TOURS
    // ===========================================
    $(document).ready(function() {
        const currentModule = detectCurrentModule();
        if (!currentModule) return;

        // Añadir botones de ayuda
        setTimeout(addHelpButtons, 500);

        // Auto-start solo para el tab de Conexiones en primera visita
        if (currentModule === 'config-conexiones' && !getTourStatus('configConexiones')) {
            setTimeout(function() {
                startTour('configConexiones');
            }, 1500);
        }

        // Observar cambios de tab para actualizar botones
        observeTabChanges();
    });

    // ===========================================
    // OBSERVAR CAMBIOS DE TAB
    // ===========================================
    function observeTabChanges() {
        // Escuchar clicks en los botones de tab
        $('.phsbot-tab-button').on('click', function() {
            setTimeout(function() {
                // Re-detectar módulo y añadir botones si es necesario
                addHelpButtons();
            }, 100);
        });

        // También observar cambios en aria-hidden para detectar cambios de tab
        const tabs = document.querySelectorAll('[id^="tab-"]');
        if (tabs.length > 0 && window.MutationObserver) {
            const observer = new MutationObserver(function() {
                addHelpButtons();
            });

            tabs.forEach(tab => {
                observer.observe(tab, {
                    attributes: true,
                    attributeFilter: ['aria-hidden']
                });
            });
        }
    }

})(jQuery);
