/**
 * Shepherd.js Tours para GEOWriter
 * Tours guiados para configuración y uso del plugin
 */

(function($) {
    'use strict';

    // Configuración base de Shepherd
    const defaultOptions = {
        useModalOverlay: true,
        exitOnEsc: true,
        keyboardNavigation: true,
        defaultStepOptions: {
            scrollTo: { behavior: 'smooth', block: 'center' },
            cancelIcon: {
                enabled: true
            },
            classes: 'ap-shepherd-theme',
            modalOverlayOpeningPadding: 8,
            modalOverlayOpeningRadius: 8
        }
    };

    // ==========================================
    // TOUR 1: AUTOPILOT - CREAR CAMPAÑA
    // ==========================================
    window.AP_Tours = window.AP_Tours || {};

    AP_Tours.autopilot = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'welcome',
            title: '🚀 Bienvenido al Autopilot',
            text: 'Este asistente te guiará paso a paso para crear tu primera campaña de contenido automático con IA. ¡Vamos a empezar!',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'campaign-name',
            title: '📝 Nombre de la Campaña',
            text: 'Dale un nombre descriptivo a tu campaña. Por ejemplo: "Blog Medicina 2025" o "Marketing Digital Q1".',
            attachTo: {
                element: '#campaign_name',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'domain',
            title: '🌐 Dominio',
            text: 'Este es el dominio de tu sitio web. Se detecta automáticamente pero puedes modificarlo si es necesario.',
            attachTo: {
                element: '#domain',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'niche',
            title: '🎯 Nicho de Contenido',
            text: 'Selecciona el nicho o temática principal de tu contenido. Esto ayuda a la IA a generar contenido más relevante y especializado.',
            attachTo: {
                element: '#niche',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'num-posts',
            title: '📊 Número de Posts',
            text: 'Define cuántos artículos quieres generar en esta campaña. Puedes usar el slider o escribir el número directamente.',
            attachTo: {
                element: '.ap-slider-container',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'category',
            title: '📁 Categoría',
            text: 'Selecciona la categoría de WordPress donde se publicarán los posts. Si no existe, créala antes desde el menú de WordPress.',
            attachTo: {
                element: '#category',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'schedule',
            title: '📅 Fecha de Inicio',
            text: 'Define cuándo quieres que empiece a publicarse el primer artículo. Por defecto es el próximo lunes, pero puedes elegir cualquier fecha futura.',
            attachTo: {
                element: '#start_date',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'publish-time',
            title: '⏰ Hora de Publicación',
            text: 'Define a qué hora del día se publicarán los artículos. Por ejemplo, si eliges 09:00, todos los posts se programarán para las 9 de la mañana.',
            attachTo: {
                element: '#publish_time',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'publish-days',
            title: '📆 Días de Publicación',
            text: 'Marca los días de la semana en que quieres que se publiquen artículos. Por ejemplo, si marcas Lunes y Miércoles, los artículos se alternarán cada lunes y miércoles.',
            attachTo: {
                element: '.days-grid',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'start-autopilot',
            title: '✅ Iniciar Autopilot',
            text: '¡Perfecto! Cuando estés listo, haz clic en "Iniciar Autopilot" para que la IA analice tu negocio, genere keywords y cree la configuración completa automáticamente.',
            attachTo: {
                element: '#start-autopilot',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 2: COLA DE PROCESAMIENTO
    // ==========================================
    AP_Tours.queue = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'queue-intro',
            title: '⏱️ Cola de Procesamiento',
            text: 'Aquí se muestran todas las campañas que están siendo procesadas por la IA. Puedes monitorear el progreso en tiempo real.',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'queue-status',
            title: '📊 Estados de la Cola',
            text: 'Los posts pueden estar: Pendiente (esperando), Procesando (generándose), Completado (publicado) o Con Error. Los colores te ayudan a identificar rápidamente el estado de cada uno.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'queue-actions',
            title: '🎮 Acciones Disponibles',
            text: 'Puedes pausar, reanudar o cancelar campañas desde los botones de acción. También puedes ver detalles y errores si los hay.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 3: LISTADO CAMPAÑAS (CON CAMPAÑAS)
    // ==========================================
    AP_Tours.campaigns = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'campaigns-intro',
            title: '📋 Gestión de Campañas',
            text: 'Aquí puedes ver todas tus campañas creadas, editarlas, duplicarlas o eliminarlas.',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'campaigns-actions',
            title: '🎮 Acciones de Campaña',
            text: 'Cada campaña tiene botones de acción: Ver Cola (ir a la cola de posts), Editar (modificar configuración), Clonar (duplicar campaña) y Eliminar.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'campaigns-toolbar',
            title: '⚙️ Toolbar Superior',
            text: 'Desde aquí puedes crear nuevas campañas o eliminar varias a la vez seleccionándolas con el checkbox.',
            attachTo: {
                element: '.ap-campaigns-toolbar',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 3B: PRIMERA CAMPAÑA (SIN CAMPAÑAS)
    // ==========================================
    AP_Tours.firstCampaign = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'no-campaigns-intro',
            title: '🚀 ¡Bienvenido a GEOWriter!',
            text: 'Aún no tienes campañas creadas. Una campaña es un conjunto de artículos que se generarán automáticamente con IA sobre un tema específico.',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'create-manual',
            title: '✏️ Crear Primera Campaña (Manual)',
            text: 'Con este botón puedes crear una campaña manualmente, configurando todos los parámetros tú mismo. Es para usuarios avanzados que quieren control total.',
            attachTo: {
                element: 'a[href*="autopost-campaign-edit"]:not([style*="background"])',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'create-autopilot',
            title: '🤖 Crear con Autopilot (Recomendado)',
            text: '¡RECOMENDADO para empezar! El Autopilot analiza tu web automáticamente y configura todo por ti. Solo necesitas dar nombre, nicho y número de posts. ¡Es la forma más rápida!',
            attachTo: {
                element: 'a[href*="autopost-autopilot"]',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 4: CONFIGURACIÓN
    // ==========================================
    AP_Tours.config = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'config-intro',
            title: '⚙️ Configuración Inicial',
            text: 'Bienvenido a la configuración de GEOWriter. Aquí debes configurar los elementos esenciales para que el plugin funcione correctamente. ¡Vamos paso a paso!',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'company-desc',
            title: '🏢 Descripción de Empresa (Opcional)',
            text: 'Describe brevemente la temática de tu web o empresa. Esta información ayuda a personalizar el contenido generado, pero no es obligatoria.',
            attachTo: {
                element: '#ap_company_desc',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'license-key',
            title: '🔑 Licencia (OBLIGATORIO)',
            text: '⚠️ ¡IMPORTANTE! Sin una licencia válida, GEOWriter NO funcionará. Introduce tu clave de licencia aquí. Si no tienes una, contacta con soporte.',
            attachTo: {
                element: '#ap_license_key',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'verify-license',
            title: '✅ Verificar Licencia',
            text: 'Después de introducir tu licencia, haz clic en "Verificar Licencia" para activarla. Verás información sobre tu plan, límites y renovación.',
            attachTo: {
                element: '#verify-license',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'unsplash-api',
            title: '🖼️ Unsplash API (Casi Obligatorio)',
            text: 'Para obtener imágenes de calidad profesional, necesitas una API key de Unsplash. Es GRATIS y muy recomendado. Haz clic en el icono (?) para ver cómo obtenerla.',
            attachTo: {
                element: '#ap_unsplash_key',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'pixabay-api',
            title: '📸 Pixabay API (Recomendable)',
            text: 'Pixabay ofrece imágenes y videos gratuitos. Aunque opcional, te da más opciones de imágenes. También es GRATIS. Haz clic en (?) para instrucciones.',
            attachTo: {
                element: '#ap_pixabay_key',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'pexels-api',
            title: '🎥 Pexels API (Recomendable)',
            text: 'Pexels es otra fuente excelente de imágenes y videos profesionales. Tener las tres APIs maximiza tus opciones. ¡También es GRATIS!',
            attachTo: {
                element: '#ap_pexels_key',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'save-config',
            title: '💾 Guardar Configuración',
            text: '¡Último paso! Una vez que hayas configurado tu licencia y las APIs que desees, haz clic aquí para guardar. ¡Y listo para empezar a crear contenido!',
            attachTo: {
                element: '.ap-btn-save',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 5A: CREAR CAMPAÑA
    // ==========================================
    AP_Tours.campaignCreate = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'create-intro',
            title: '✨ Crear Nueva Campaña',
            text: 'Vamos a crear una campaña desde cero paso a paso. Tienes control total sobre todos los parámetros. Si prefieres algo más rápido y automático, usa Autopilot.',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'name-domain-niche',
            title: '📝 Datos Básicos',
            text: 'Primero lo esencial: Nombre de la campaña, tu dominio y el nicho temático. Estos datos son fundamentales para que la IA genere contenido relevante.',
            attachTo: {
                element: '.ap-field-row-triple',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'company-desc',
            title: '🏢 Descripción de Empresa',
            text: 'Puedes escribirla tú mismo o usar el botón "Generar con IA" para que se cree automáticamente. Luego puedes revisar, corregir o ampliar la información generada.',
            attachTo: {
                element: '#company_desc',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'num-posts-length',
            title: '📊 Cantidad y Extensión',
            text: 'Define cuántos posts quieres generar y su extensión (corto, medio o largo). Usa el slider o escribe el número directamente.',
            attachTo: {
                element: '.ap-field-row-slider',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'keywords-seo',
            title: '🔑 Keywords SEO',
            text: 'Las palabras clave son cruciales para SEO. Puedes escribirlas separadas por comas o usar "Generar con IA" para que se creen automáticamente según tu nicho.',
            attachTo: {
                element: '#keywords_seo',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'prompts',
            title: '✍️ Prompts de Generación',
            text: 'Los prompts definen CÓMO escribirá la IA (tono, estilo, estructura). Usa "Generar con IA" para crear prompts profesionales automáticamente.',
            attachTo: {
                element: '#prompt_titles',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'images',
            title: '🖼️ Configuración de Imágenes',
            text: 'Define las keywords para buscar imágenes y selecciona el proveedor (Unsplash, Pixabay o Pexels). También puedes generar keywords con IA.',
            attachTo: {
                element: '.ap-section[data-section="3"]',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'scheduling',
            title: '📅 Programación de Publicación',
            text: 'Define cuándo y cómo se publicarán tus posts: días de la semana, fecha de inicio, hora y categoría de WordPress.',
            attachTo: {
                element: '.ap-section[data-section="4"]',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'save-new-campaign',
            title: '💾 Guardar Nueva Campaña',
            text: '¡Último paso! Cuando termines de configurar todo, haz clic en "Guardar Campaña". Después podrás generar la cola de posts desde el listado.',
            attachTo: {
                element: 'button[form="campaign-form"]',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // TOUR 5B: EDITAR CAMPAÑA
    // ==========================================
    AP_Tours.campaignEdit = function() {
        const tour = new Shepherd.Tour(defaultOptions);

        tour.addStep({
            id: 'edit-intro',
            title: '✏️ Editar Campaña',
            text: 'Aquí puedes modificar cualquier parámetro de tu campaña existente. Los cambios se aplicarán a los nuevos posts que se generen.',
            buttons: [
                {
                    text: 'Saltar',
                    action: tour.cancel,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'edit-warning',
            title: '⚠️ Importante',
            text: 'Los posts que ya se generaron NO cambiarán. Solo los nuevos posts que se creen después de guardar tomarán la nueva configuración.',
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'basic-info-edit',
            title: '📝 Modificar Parámetros',
            text: 'Puedes cambiar nombre, nicho, configuración de contenido, SEO, imágenes, etc. Revisa cada sección según lo que necesites ajustar.',
            attachTo: {
                element: '.ap-section[data-section="1"]',
                on: 'top'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: 'Siguiente',
                    action: tour.next
                }
            ]
        });

        tour.addStep({
            id: 'save-changes',
            title: '💾 Guardar Cambios',
            text: 'Cuando termines de hacer las modificaciones, haz clic aquí para guardar. Los cambios se aplicarán inmediatamente.',
            attachTo: {
                element: 'button[form="campaign-form"]',
                on: 'bottom'
            },
            buttons: [
                {
                    text: 'Atrás',
                    action: tour.back,
                    classes: 'shepherd-button-secondary'
                },
                {
                    text: '¡Entendido!',
                    action: tour.complete
                }
            ]
        });

        return tour;
    };

    // ==========================================
    // INICIALIZACIÓN Y BOTONES DE AYUDA
    // ==========================================

    // Guardar estado del tour en localStorage
    function getTourStatus(tourName) {
        return localStorage.getItem(`ap_tour_${tourName}_completed`) === 'true';
    }

    function markTourCompleted(tourName) {
        localStorage.setItem(`ap_tour_${tourName}_completed`, 'true');
    }

    // Detectar módulo actual
    function detectCurrentModule() {
        if ($('#autopilot-form').length) return 'autopilot';
        if ($('#queue-table, .ap-queue-wrapper').length) return 'queue';
        if ($('#ap-config-form, .ap-config-wrapper').length) return 'config';

        // Detectar página de edición/creación de campaña
        if ($('.ap-campaign-wrapper, #campaign-form').length) {
            // Si el campo campaign_id tiene valor, es edición; si está vacío, es creación
            const campaignId = $('#campaign_id').val();
            if (campaignId && campaignId !== '') {
                return 'campaign-edit';
            } else {
                return 'campaign-create';
            }
        }

        // Detectar página de listado de campañas (con o sin campañas)
        if ($('.ap-campaigns-wrapper').length) {
            // Si hay tabla de campañas, es el listado normal
            if ($('#campaigns-form').length) return 'campaigns';
            // Si no hay tabla, es la vista vacía (primera campaña)
            return 'first-campaign';
        }

        return null;
    }

    // Agregar botones de ayuda
    function addHelpButtons() {
        const currentModule = detectCurrentModule();

        if (!currentModule) return;

        let buttonId, buttonText;

        switch(currentModule) {
            case 'autopilot':
                buttonId = 'start-autopilot-tour';
                buttonText = 'Tutorial Autopilot';
                break;
            case 'queue':
                buttonId = 'start-queue-tour';
                buttonText = 'Tutorial Cola';
                break;
            case 'campaigns':
                buttonId = 'start-campaigns-tour';
                buttonText = 'Tutorial Campañas';
                break;
            case 'first-campaign':
                buttonId = 'start-first-campaign-tour';
                buttonText = 'Tutorial Primera Campaña';
                break;
            case 'config':
                buttonId = 'start-config-tour';
                buttonText = 'Tutorial Configuración';
                break;
            case 'campaign-create':
                buttonId = 'start-campaign-create-tour';
                buttonText = 'Tutorial Crear Campaña';
                break;
            case 'campaign-edit':
                buttonId = 'start-campaign-edit-tour';
                buttonText = 'Tutorial Editar Campaña';
                break;
        }

        const helpBtn = $(`
            <button type="button" class="ap-help-tour-btn" id="${buttonId}" title="Ver tutorial guiado">
                <span class="dashicons dashicons-info"></span>
                ${buttonText}
            </button>
        `);

        // Para páginas de crear/editar campaña, insertar antes del botón "Guardar Campaña"
        if (currentModule === 'campaign-create' || currentModule === 'campaign-edit') {
            // Buscar el contenedor de botones dentro del header
            const $buttonContainer = $('.ap-module-header div[style*="display: flex"]').first();
            if ($buttonContainer.length) {
                // Insertar al principio del contenedor de botones
                $buttonContainer.prepend(helpBtn);
            } else {
                // Fallback: agregar al header
                $('.ap-module-header').first().append(helpBtn);
            }
        } else {
            // Para otros módulos, agregar al final del header
            $('.ap-module-header').first().append(helpBtn);
        }
    }

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        // Verificar que Shepherd esté cargado
        if (typeof Shepherd === 'undefined') {
            console.warn('Shepherd.js no está cargado');
            return;
        }

        // Agregar botones de ayuda
        addHelpButtons();

        // Event listeners para los botones
        $('#start-autopilot-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.autopilot();
            tour.on('complete', function() {
                markTourCompleted('autopilot');
            });
            tour.start();
        });

        $('#start-queue-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.queue();
            tour.on('complete', function() {
                markTourCompleted('queue');
            });
            tour.start();
        });

        $('#start-campaigns-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.campaigns();
            tour.on('complete', function() {
                markTourCompleted('campaigns');
            });
            tour.start();
        });

        $('#start-first-campaign-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.firstCampaign();
            tour.on('complete', function() {
                markTourCompleted('first-campaign');
            });
            tour.start();
        });

        $('#start-config-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.config();
            tour.on('complete', function() {
                markTourCompleted('config');
            });
            tour.start();
        });

        $('#start-campaign-create-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.campaignCreate();
            tour.on('complete', function() {
                markTourCompleted('campaign-create');
            });
            tour.start();
        });

        $('#start-campaign-edit-tour').on('click', function(e) {
            e.preventDefault();
            const tour = AP_Tours.campaignEdit();
            tour.on('complete', function() {
                markTourCompleted('campaign-edit');
            });
            tour.start();
        });

        // Auto-iniciar tours en primera visita
        const currentModule = detectCurrentModule();

        // Auto-iniciar tour de Primera Campaña si no hay campañas
        if (currentModule === 'first-campaign' && !getTourStatus('first-campaign')) {
            setTimeout(function() {
                const tour = AP_Tours.firstCampaign();
                tour.on('complete', function() {
                    markTourCompleted('first-campaign');
                });
                tour.start();
            }, 1500);
        }

        // Auto-iniciar tour de Configuración si es la primera vez
        if (currentModule === 'config' && !getTourStatus('config')) {
            setTimeout(function() {
                const tour = AP_Tours.config();
                tour.on('complete', function() {
                    markTourCompleted('config');
                });
                tour.start();
            }, 1500);
        }

        // Auto-iniciar tour de Autopilot si es la primera vez
        if (currentModule === 'autopilot' && !getTourStatus('autopilot')) {
            // Esperar 1.5 segundos para que el usuario vea la página primero
            setTimeout(function() {
                const tour = AP_Tours.autopilot();
                tour.on('complete', function() {
                    markTourCompleted('autopilot');
                });
                tour.start();
            }, 1500);
        }
    });

})(jQuery);
