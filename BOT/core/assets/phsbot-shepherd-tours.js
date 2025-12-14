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
    // TOUR: CONFIGURACIÓN
    // ===========================================
    PHSBOT_Tours.config = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '👋 Bienvenido a Conversa',
            text: 'Te guiaremos por la configuración del chatbot paso a paso. ¡Empecemos!',
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
            text: '⚠️ Sin una licencia válida, el chatbot NO funcionará. Introduce tu clave que empieza por BOT- y valídala.',
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
            id: 'telegram',
            title: '📱 Notificaciones Telegram (Opcional)',
            text: 'Configura un bot de Telegram para recibir notificaciones cuando lleguen leads importantes.',
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
            id: 'chat-tab',
            title: '💬 Configuración del Chat',
            text: 'Ahora ve a la pestaña "Chat (IA)" para configurar los mensajes y comportamiento del chatbot.',
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
            id: 'welcome-message',
            title: '👋 Mensaje de Bienvenida',
            text: 'Personaliza el primer mensaje que verán tus visitantes cuando abran el chat.',
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
            title: '🤖 System Prompt',
            text: 'Define la personalidad y comportamiento de tu chatbot. Este prompt instruye a la IA sobre cómo debe responder.',
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
            id: 'appearance-tab',
            title: '🎨 Aspecto Visual',
            text: 'Ve a la pestaña "Aspecto" para personalizar los colores y apariencia del chatbot.',
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
            text: 'Ajusta los colores para que el chatbot combine con tu marca. Usa los selectores de color para visualizar los cambios en tiempo real.',
            attachTo: { element: '#color_primary', on: 'bottom' },
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
    // DETECCIÓN DE MÓDULO ACTUAL
    // ===========================================
    function detectCurrentModule() {
        const page = new URLSearchParams(window.location.search).get('page');

        if (page === 'phsbot' || page === 'phsbot_config') return 'config';
        if (page === 'phsbot-kb' || page === 'phsbot_kb') return 'kb';
        if (page === 'phsbot-inject') return 'inject';
        if (page === 'phsbot-leads') return 'leads';
        if (page === 'phsbot-chat') return 'chat';
        if (page === 'phsbot-estadisticas') return 'stats';

        return null;
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

        // No añadir botón si no hay tour para este módulo
        const validModules = ['config', 'kb', 'inject'];
        if (!validModules.includes(currentModule)) return;

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

    // ===========================================
    // AUTO-INICIO DE TOURS
    // ===========================================
    $(document).ready(function() {
        const currentModule = detectCurrentModule();
        if (!currentModule) return;

        // Añadir botones de ayuda
        setTimeout(addHelpButtons, 500);

        // Auto-start solo para configuración en primera visita
        if (currentModule === 'config' && !getTourStatus('config')) {
            setTimeout(function() {
                startTour('config');
            }, 1500);
        }
    });

})(jQuery);
