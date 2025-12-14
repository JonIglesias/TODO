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
            id: 'api-url',
            title: '🌐 API URL',
            text: 'Esta es la URL donde está alojada la API del chatbot. Normalmente no necesitas cambiarla.',
            attachTo: { element: '#bot_api_url', on: 'bottom' },
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
            id: 'telegram',
            title: '📱 Notificaciones Telegram (Opcional)',
            text: 'Configura un bot de Telegram para recibir notificaciones cuando lleguen leads importantes. Es opcional pero muy útil.',
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
            text: 'Personaliza completamente la apariencia del chatbot para que combine con tu marca.',
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
            text: 'Elige dónde aparecerá el botón del chatbot en tu web: abajo derecha, abajo izquierda, etc.',
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
            id: 'colors',
            title: '🎨 Colores Personalizados',
            text: 'Ajusta todos los colores: primario, secundario, fondo, burbujas, etc. Usa los selectores para visualizar los cambios en tiempo real.',
            attachTo: { element: '#color_primary', on: 'bottom' },
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
            id: 'launcher',
            title: '🚀 Botón Launcher',
            text: 'Personaliza el botón que abre el chat: color de fondo, icono y texto. Estos colores se aplican al botón flotante.',
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
            text: 'Aquí configuras el conocimiento que tu chatbot usará para responder preguntas sobre tu negocio.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'crawl',
            title: '🕷️ Escanear Sitio Web',
            text: 'El sistema puede escanear automáticamente tu web y extraer información para la base de conocimiento.',
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
            id: 'manual',
            title: '✍️ Añadir Manualmente',
            text: 'También puedes añadir documentos manualmente con información específica que quieres que el bot conozca.',
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
    // TOUR: INYECCIONES
    // ===========================================
    PHSBOT_Tours.inject = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '💉 Inyecciones',
            text: 'Las inyecciones te permiten añadir contenido o scripts personalizados a tu chatbot.',
            buttons: [
                {
                    text: 'Siguiente',
                    action: tour.next,
                    classes: 'shepherd-button-primary'
                }
            ]
        });

        tour.addStep({
            id: 'create',
            title: '➕ Crear Inyección',
            text: 'Puedes añadir JavaScript, CSS o HTML personalizado que se ejecutará en el contexto del chatbot.',
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
        const validModules = ['config', 'kb', 'inject'];
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
        // Añadir un botón en cada tab de configuración
        const tabs = [
            { id: 'tab-conexiones', module: 'configConexiones', title: 'Conexiones' },
            { id: 'tab-chat', module: 'configChat', title: 'Chat (IA)' },
            { id: 'tab-aspecto', module: 'configAspecto', title: 'Aspecto' }
        ];

        tabs.forEach(tab => {
            const $tab = $('#' + tab.id);
            if (!$tab.length) return;

            // No añadir si ya existe
            if ($tab.find('.phsbot-help-tour-btn').length > 0) return;

            const helpBtn = $(`
                <button type="button" class="phsbot-help-tour-btn" data-tour="${tab.module}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span>Tutorial de ${tab.title}</span>
                </button>
            `);

            // Añadir al inicio del contenido del tab
            $tab.find('.phsbot-module-content').first().prepend(
                $('<div>').css({
                    'margin-bottom': '20px',
                    'text-align': 'right'
                }).append(helpBtn)
            );

            // Event listener
            helpBtn.on('click', function() {
                const tourName = $(this).data('tour');
                if (PHSBOT_Tours[tourName]) {
                    startTour(tourName);
                }
            });
        });
    }

    function addMainHeaderButton(currentModule) {
        // No añadir si ya existe
        if ($('.phsbot-help-tour-btn').length > 0) return;

        const helpBtn = `
            <button type="button" class="phsbot-help-tour-btn" id="phsbot-tour-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>Tutorial</span>
            </button>
        `;

        // Insertar botón en el header
        $('.phsbot-config-header h1').first().after(helpBtn);

        // Event listener para el botón
        $('#phsbot-tour-btn').on('click', function() {
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
