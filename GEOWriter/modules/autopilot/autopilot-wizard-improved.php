<?php 
if (!defined('ABSPATH')) exit;

$site_url = get_site_url();
$domain = str_replace(['http://', 'https://'], '', $site_url);
$domain = rtrim($domain, '/');

$categories = get_categories([
    'taxonomy' => 'category',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
]);

$nichos = [
    'Medicina General', 'Nutrición y Dietética', 'Fitness y Gimnasio', 'Yoga', 'Pilates',
    'Marketing Digital', 'SEO', 'SEM', 'Redes Sociales', 'E-commerce',
    'Inteligencia Artificial', 'Blockchain', 'Ciberseguridad', 'Desarrollo Web',
    'Diseño Gráfico', 'Diseño Web', 'UX/UI Design', 'Fotografía', 'Video',
    'Gastronomía', 'Restaurantes', 'Moda', 'Belleza', 'Decoración',
    'Fútbol', 'Baloncesto', 'Tenis', 'Golf', 'Ciclismo', 'Running',
    'Abogacía', 'Derecho Penal', 'Derecho Civil', 'Contabilidad', 'Consultoría',
    'Arquitectura', 'Construcción', 'Reformas', 'Inmobiliaria'
];

// Obtener límite de posts por campaña desde el plan activo
$api_client = new AP_API_Client();
$max_posts_per_campaign = $api_client->get_max_posts_per_campaign();
// Si es ilimitado (-1), usar 1000 como límite práctico en el formulario
$max_posts_form = ($max_posts_per_campaign === -1) ? 1000 : $max_posts_per_campaign;
?>


<div class="wrap ap-module-wrap ap-autopilot-container">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Autopilot - Generación Automática</h1>
    </div>

    <div class="ap-module-container ap-autopilot-grid has-sidebar">
        <div class="ap-module-content ap-main-content">

            <form id="autopilot-form">
                <div class="ap-section">
                    <div class="ap-section-header">
                        <div class="ap-section-number">1</div>
                        <h2 class="ap-section-title">Configuración de la Campaña</h2>
                    </div>
                    
                    <div class="ap-compact-grid">
                        <div class="ap-field-group ap-field-full">
                            <label class="ap-field-label">Nombre de la campaña <span class="required">*</span></label>
                            <input type="text" id="campaign_name" class="ap-field-input" placeholder="Ej: Blog Medicina 2025" required>
                        </div>
                        
                        <div class="ap-field-group">
                            <label class="ap-field-label">Dominio <span class="required">*</span></label>
                            <input type="text" id="domain" class="ap-field-input" value="<?php echo esc_attr($domain); ?>" required>
                        </div>
                        
                        <div class="ap-field-group">
                            <label class="ap-field-label">Nicho <span class="required">*</span></label>
                            <div class="ap-autocomplete-container">
                                <span id="niche-suggestion"></span>
                                <input type="text" id="niche" class="ap-field-input ap-autocomplete-input" placeholder="Escribe para buscar..." autocomplete="off" required>
                                <div class="ap-autocomplete-dropdown" id="niche-dropdown"></div>
                            </div>
                        </div>
                        
                        <div class="ap-field-group ap-slider-container ap-field-full">
                            <label class="ap-field-label" for="num_posts_input">
                                Número de Posts:
                                <input type="number"
                                       id="num_posts_input"
                                       class="ap-num-posts-input"
                                       min="1"
                                       max="<?php echo $max_posts_form; ?>"
                                       value="5">
                                <?php if ($max_posts_per_campaign !== -1): ?>
                                    <span class="ap-limit-badge">
                                        Máx: <?php echo $max_posts_per_campaign; ?> (plan activo)
                                    </span>
                                <?php endif; ?>
                            </label>
                            <div class="ap-slider-wrapper">
                                <div class="ap-slider-track-container">
                                    <input type="range"
                                           id="num_posts"
                                           name="num_posts"
                                           class="ap-slider"
                                           min="1"
                                           max="<?php echo $max_posts_form; ?>"
                                           step="1"
                                           data-plan-limit="<?php echo $max_posts_per_campaign; ?>"
                                           value="5"
                                           required>
                                    <div id="slider_thumb_number" class="slider-thumb-number"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ap-field-group">
                            <label class="ap-field-label">Categoría <span class="required">*</span></label>
                            <select id="category" class="ap-field-select" required>
                                <?php 
                                $first = true;
                                foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php echo $first ? 'selected' : ''; ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php 
                                $first = false;
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="ap-field-group">
                            <label class="ap-field-label">Fecha de inicio de las publicaciones <span class="required">*</span></label>
                            <input type="date" id="start_date" class="ap-field-input" value="<?php echo date('Y-m-d', strtotime('next monday')); ?>" required>
                        </div>
                        
                        <div class="ap-field-group">
                            <label class="ap-field-label">Hora de publicación <span class="required">*</span></label>
                            <input type="time" id="publish_time" class="ap-field-input" value="09:00" required>
                        </div>
                        
                        <div class="ap-field-group ap-field-full">
                            <label class="ap-field-label">Selecciona qué días quieres que se programe la publicación <span class="required">*</span></label>
                            <div class="days-grid">
                                <div><input type="checkbox" id="day-1" name="publish_days[]" value="Lunes" class="day-checkbox" checked><label for="day-1" class="day-label">Lunes</label></div>
                                <div><input type="checkbox" id="day-2" name="publish_days[]" value="Martes" class="day-checkbox"><label for="day-2" class="day-label">Martes</label></div>
                                <div><input type="checkbox" id="day-3" name="publish_days[]" value="Miércoles" class="day-checkbox"><label for="day-3" class="day-label">Miércoles</label></div>
                                <div><input type="checkbox" id="day-4" name="publish_days[]" value="Jueves" class="day-checkbox"><label for="day-4" class="day-label">Jueves</label></div>
                                <div><input type="checkbox" id="day-5" name="publish_days[]" value="Viernes" class="day-checkbox"><label for="day-5" class="day-label">Viernes</label></div>
                                <div><input type="checkbox" id="day-6" name="publish_days[]" value="Sábado" class="day-checkbox"><label for="day-6" class="day-label">Sábado</label></div>
                                <div><input type="checkbox" id="day-7" name="publish_days[]" value="Domingo" class="day-checkbox"><label for="day-7" class="day-label">Domingo</label></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="ap-summary-card" id="summary-card">
                    <h3 class="ap-summary-title">📋 Resumen de Configuración</h3>
                    <div class="ap-summary-items">
                        <strong>Campaña:</strong> <span id="summary-campaign">-</span> | 
                        <strong>Posts:</strong> <span id="summary-posts">-</span> | 
                        <strong>Días:</strong> <span id="summary-days">-</span> | 
                        <strong>Inicio:</strong> <span id="summary-date">-</span> a las <span id="summary-time">-</span>
                    </div>
                </div>
                
                <div class="ap-progress-container" id="progress-container">
                    <div class="ap-progress-header">
                        <span class="ap-progress-title">Progreso del proceso</span>
                    </div>
                    <div class="ap-progress-bar-wrapper">
                        <div class="ap-progress-bar" id="progress-bar"></div>
                        <div class="ap-progress-percent" id="progress-percent">0%</div>
                    </div>
                    <div class="ap-steps-list" id="steps-list"></div>
                </div>
                
                <div class="ap-actions">
                    <a href="<?php echo admin_url('admin.php?page=autopost-ia'); ?>" class="ap-btn ap-btn-secondary" id="cancel-btn">Cancelar</a>
                    <button type="submit" class="ap-btn ap-btn-primary" id="start-autopilot">Iniciar Autopilot</button>
                </div>
            </form>
        </div> <!-- Fin ap-module-content -->

        <div class="ap-module-sidebar ap-sidebar">
            <div class="ap-sidebar-panel">
                <h3>💡 Guía Rápida</h3>
                
                <div class="ap-help-text" id="help-text">
                    <strong>📝 Nombre de campaña</strong>
                    Dale un nombre que identifique claramente tu proyecto. Por ejemplo: "Blog Medicina General", "Tienda Online Moda", o "Consultoría Legal Madrid". Este nombre solo lo verás tú en el panel de administración.
                    
                    <br><br><strong>🌐 Dominio</strong>
                    Es la dirección web donde se publicarán los artículos. Se detecta automáticamente desde tu WordPress. Asegúrate de que sea correcto antes de continuar.
                    
                    <br><br><strong>🎯 Nicho</strong>
                    Empieza a escribir la temática principal de tu negocio y aparecerán sugerencias. Por ejemplo: si escribes "marketing", verás opciones como "Marketing Digital", "Email Marketing", etc. Si tu nicho no aparece, escribe uno personalizado.
                    
                    <br><br><strong>📊 Número de posts</strong>
                    ¿Cuántos artículos quieres crear? Si es tu primera vez, recomendamos empezar con 5-10 artículos para probar el sistema. Luego puedes crear campañas más grandes.
                    
                    <br><br><strong>📁 Categoría</strong>
                    Selecciona en qué categoría de WordPress se publicarán los artículos. Puedes crear categorías nuevas desde el menú "Entradas > Categorías" de WordPress.
                    
                    <br><br><strong>📅 Fecha de inicio</strong>
                    ¿Cuándo quieres que empiece a publicarse el primer artículo? Por defecto es el próximo lunes, pero puedes elegir cualquier fecha futura.
                    
                    <br><br><strong>⏰ Hora de publicación</strong>
                    A qué hora del día se publicarán los artículos. Por ejemplo, si eliges 09:00, todos los posts se programarán para las 9 de la mañana.
                    
                    <br><br><strong>📆 Días de publicación</strong>
                    Marca los días de la semana en que quieres que se publiquen artículos. Por ejemplo, si marcas Lunes y Miércoles, los artículos se alternarán cada lunes y miércoles. Si marcas todos los días, se publicará un artículo diario.
                    
                    <br><br><strong>✨ ¿Qué hace AutoPilot?</strong>
                    Una vez que hagas clic en "Iniciar AutoPilot":
                    <br>1️⃣ Analiza tu web y tu negocio automáticamente
                    <br>2️⃣ Genera palabras clave SEO optimizadas
                    <br>3️⃣ Crea prompts profesionales para el contenido
                    <br>4️⃣ Guarda todo y te lleva directamente a editar la campaña
                    <br><br>⏱️ El proceso tarda aproximadamente 2-3 minutos.
                </div>
                
                <div class="ap-progress-messages" id="sidebar-messages" style="display:none;">
                    <div style="font-size:11px;font-weight:600;color:white;margin-bottom:8px;">AVANCES:</div>
                </div>
            </div>
        </div> <!-- Fin ap-module-sidebar -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->

<!-- Manejador centralizado de errores de API -->
<script src="<?php echo AP_PLUGIN_URL; ?>core/assets/ap-error-handler.js?v=<?php echo time(); ?>"></script>

<script>
jQuery(document).ready(function($) {
    let processInProgress = false;
    let savedData = {};
    let progressInterval = null;
    let currentProgress = 0;
    let targetProgress = 0;
    let totalSteps = 0;
    let completedRealSteps = 0;
    
    // Lista de nichos para autocomplete
    const nichosList = <?php echo json_encode($nichos); ?>;

    // ========================================
    // AUTOCOMPLETE FUNCIONAL PARA NICHO
    // ========================================

    const $nicheInput = $('#niche');
    const $nicheSuggestion = $('#niche-suggestion');
    const $nicheDropdown = $('#niche-dropdown');
    let filteredNichos = [];
    let selectedIndex = -1;

    // Función para limpiar sugerencia
    const clearSuggestion = () => {
        $nicheSuggestion.text('');
    };

    // Función para ajustar mayúsculas/minúsculas según input del usuario (del ejemplo CodePen)
    const caseCheck = (word) => {
        word = word.split('');
        let inp = $nicheInput.val();
        for (let i in inp) {
            if (inp[i] == word[i]) {
                continue;
            } else if (inp[i].toUpperCase() == word[i]) {
                word.splice(i, 1, word[i].toLowerCase());
            } else {
                word.splice(i, 1, word[i].toUpperCase());
            }
        }
        return word.join('');
    };

    // Función para filtrar nichos
    function filterNichos(query) {
        if (!query) return nichosList;

        const queryLower = query.toLowerCase();
        return nichosList.filter(nicho =>
            nicho.toLowerCase().includes(queryLower)
        ).sort((a, b) => {
            // Priorizar los que empiezan con la query
            const aStarts = a.toLowerCase().startsWith(queryLower);
            const bStarts = b.toLowerCase().startsWith(queryLower);
            if (aStarts && !bStarts) return -1;
            if (!aStarts && bStarts) return 1;
            return a.localeCompare(b);
        });
    }

    // Función para actualizar el dropdown
    function updateDropdown() {
        $nicheDropdown.empty();

        if (filteredNichos.length === 0) {
            $nicheDropdown.removeClass('active');
            return;
        }

        filteredNichos.slice(0, 10).forEach((nicho, index) => {
            const $item = $('<div>')
                .addClass('ap-autocomplete-item')
                .text(nicho)
                .on('click', function() {
                    $nicheInput.val(nicho);
                    clearSuggestion();
                    $nicheDropdown.removeClass('active');
                    $nicheInput.focus();
                });

            if (index === selectedIndex) {
                $item.addClass('selected');
            }

            $nicheDropdown.append($item);
        });

        $nicheDropdown.addClass('active');
    }

    // Evento input - actualizar mientras escribe (siguiendo ejemplo CodePen)
    $nicheInput.on('input', function() {
        const query = $(this).val();

        clearSuggestion();

        // Buscar palabra que coincida (case insensitive)
        if (query) {
            let regex = new RegExp('^' + query, 'i');
            for (let i in nichosList) {
                if (regex.test(nichosList[i])) {
                    // Ajustar mayúsculas/minúsculas según lo que escribió el usuario
                    let suggestion = caseCheck(nichosList[i]);
                    // Mostrar la palabra COMPLETA en el span
                    $nicheSuggestion.text(suggestion);
                    break;
                }
            }
        }

        // Actualizar dropdown
        filteredNichos = filterNichos(query);
        selectedIndex = -1;
        updateDropdown();
    });

    // Evento focus - mostrar dropdown
    $nicheInput.on('focus', function() {
        const query = $(this).val();
        filteredNichos = filterNichos(query);
        updateDropdown();
    });

    // Navegación con teclado
    $nicheInput.on('keydown', function(e) {
        const suggestion = $nicheSuggestion.text();

        // Enter: aceptar sugerencia (siguiendo ejemplo CodePen)
        if (e.keyCode === 13 && suggestion !== '') {
            e.preventDefault();
            if (selectedIndex >= 0) {
                // Si hay item seleccionado en dropdown, usar ese
                $(this).val(filteredNichos[selectedIndex]);
            } else {
                // Si no, usar la sugerencia
                $(this).val(suggestion);
            }
            clearSuggestion();
            $nicheDropdown.removeClass('active');
            return;
        }

        // Tab o flecha derecha: aceptar sugerencia
        if ((e.keyCode === 9 || e.keyCode === 39) && suggestion !== '') {
            e.preventDefault();
            $(this).val(suggestion);
            clearSuggestion();
        }

        // Flecha abajo
        if (e.keyCode === 40 && filteredNichos.length > 0) {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, filteredNichos.length - 1);
            updateDropdown();
        }

        // Flecha arriba
        if (e.keyCode === 38 && filteredNichos.length > 0) {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateDropdown();
        }

        // Escape: cerrar dropdown
        if (e.keyCode === 27) {
            $nicheDropdown.removeClass('active');
        }
    });

    // Cerrar dropdown al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ap-autocomplete-container').length) {
            $nicheDropdown.removeClass('active');
        }
    });

    // Limpiar sugerencia al blur
    $nicheInput.on('blur', function() {
        setTimeout(function() {
            clearSuggestion();
        }, 200);
    });

    // Inicialización (siguiendo ejemplo CodePen)
    window.addEventListener('load', () => {
        $nicheInput.val('');
        clearSuggestion();
    });

    // ========================================
    // FIN AUTOCOMPLETE FUNCIONAL
    // ========================================

    // ========================================
    // SLIDER DE NÚMERO DE POSTS OPTIMIZADO CON INPUT EDITABLE
    // ========================================

    const $numPostsSlider = $('#num_posts');
    const $numPostsInput = $('#num_posts_input');
    const $sliderWrapper = $('.ap-slider-wrapper');
    const $thumbNumber = $('#slider_thumb_number');
    const planLimit = parseInt($numPostsSlider.attr('data-plan-limit'));
    const maxValue = parseInt($numPostsSlider.attr('max'));
    const minValue = parseInt($numPostsSlider.attr('min'));

    // Función para generar divisiones blancas dentro del slider
    function generateSliderDivisions() {
        // Determinar cuántas divisiones mostrar
        let divisions = 10; // Por defecto 10 divisiones
        if (maxValue > 100) divisions = 20;
        else if (maxValue > 50) divisions = 10;
        else if (maxValue <= 20) divisions = maxValue - 1;

        // Crear gradiente con líneas blancas
        const divisionWidth = 100 / divisions; // Porcentaje de cada división
        let gradientParts = [];

        for (let i = 1; i < divisions; i++) {
            const position = (i * divisionWidth).toFixed(2);
            gradientParts.push(`transparent ${position}%`);
            gradientParts.push(`rgba(255, 255, 255, 0.4) ${position}%`);
            gradientParts.push(`rgba(255, 255, 255, 0.4) calc(${position}% + 1px)`);
            gradientParts.push(`transparent calc(${position}% + 1px)`);
        }

        const gradient = `linear-gradient(to right, ${gradientParts.join(', ')})`;
        $numPostsSlider.css('--slider-divisions', gradient);
    }

    // Función para actualizar posición del número dentro del thumb
    function updateThumbNumber(value) {
        const currentValue = parseInt(value);
        const percent = ((currentValue - minValue) / (maxValue - minValue)) * 100;

        // Calcular posición exacta del thumb teniendo en cuenta su tamaño
        const sliderWidth = $numPostsSlider.width();
        const thumbSize = 24; // Ancho total del thumb en px
        // El thumb se mueve en un rango de (thumbSize/2) a (sliderWidth - thumbSize/2)
        const thumbPosition = (percent / 100) * (sliderWidth - thumbSize) + (thumbSize / 2);

        $thumbNumber.css('left', thumbPosition + 'px');
        $thumbNumber.text(currentValue);
    }

    // Función para validar y ajustar al límite del plan
    function validatePlanLimit(value) {
        let validValue = parseInt(value) || minValue;

        // Asegurar que esté dentro del rango
        if (validValue < minValue) validValue = minValue;
        if (validValue > maxValue) validValue = maxValue;

        // Si el plan tiene límite (no es -1) y se supera
        if (planLimit !== -1 && validValue > planLimit) {
            validValue = planLimit;

            // Mostrar mensaje de advertencia
            if (!$('#num-posts-warning').length) {
                $sliderWrapper.after(
                    '<p id="num-posts-warning" class="ap-field-desc" style="color: #d32f2f; margin-top: 4px;">' +
                    '⚠️ Tu plan permite un máximo de ' + planLimit + ' posts por campaña.' +
                    '</p>'
                );

                // Eliminar mensaje después de 5 segundos
                setTimeout(function() {
                    $('#num-posts-warning').fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }

        return validValue;
    }

    // Función para actualizar todo (slider, input, thumb)
    function updateAllFromValue(value) {
        const validValue = validatePlanLimit(value);

        // Actualizar slider
        $numPostsSlider.val(validValue);

        // Actualizar input numérico
        $numPostsInput.val(validValue);

        // Actualizar color del slider
        const percent = ((validValue - minValue) / (maxValue - minValue)) * 100;
        $numPostsSlider.css('--slider-percent', percent + '%');

        // Actualizar posición del número dentro del thumb
        updateThumbNumber(validValue);
    }

    // Cuando cambia el slider, actualizar el input
    $numPostsSlider.on('input change', function() {
        updateAllFromValue($(this).val());
    });

    // Cuando cambia el input manualmente, actualizar el slider
    $numPostsInput.on('input change', function() {
        updateAllFromValue($(this).val());
    });

    // Inicializar
    generateSliderDivisions();
    updateAllFromValue($numPostsSlider.val());

    // Actualizar al redimensionar ventana
    $(window).on('resize', function() {
        updateThumbNumber($numPostsSlider.val());
    });

    // ========================================
    // FIN SLIDER DE NÚMERO DE POSTS
    // ========================================

    $(window).on('beforeunload', function(e) {
        if (processInProgress) {
            return '¡Proceso en curso!';
        }
    });
    
    $('#autopilot-form').on('submit', function(e) {
        e.preventDefault();
        
        const selectedDays = [];
        $('input[name="publish_days[]"]:checked').each(function() {
            selectedDays.push($(this).val());
        });
        
        if (selectedDays.length === 0) {
            alert('Selecciona al menos un día de publicación');
            return;
        }
        
        const formData = {
            campaign_name: $('#campaign_name').val().trim(),
            domain: $('#domain').val().trim(),
            niche: $('#niche').val().trim(),
            num_posts: parseInt($('#num_posts').val()),
            category: $('#category').val(),
            start_date: $('#start_date').val(),
            publish_time: $('#publish_time').val(),
            publish_days: selectedDays.join(',')
        };
        
        if (!formData.campaign_name || !formData.domain || !formData.niche || 
            !formData.num_posts || !formData.category) {
            alert('Por favor completa todos los campos obligatorios');
            return;
        }
        
        startProcess(formData);
    });
    
    function startProcess(formData) {
        processInProgress = true;
        savedData = formData;

        // Advertir al usuario si intenta cerrar la ventana
        $(window).on('beforeunload', function(e) {
            if (processInProgress) {
                e.preventDefault();
                return '';
            }
        });

        $('#start-autopilot').prop('disabled', true).text('Procesando...');
        $('#cancel-btn').hide();
        $('.ap-section').fadeOut(300);
        
        // Ocultar ayuda, mostrar avances
        $('#help-text').hide();
        $('#sidebar-messages').show();
        
        startProgressAnimation();
        
        setTimeout(function() {
            updateSummary(formData);
            $('#summary-card').addClass('active').hide().fadeIn(300);
        }, 300);
        
        setTimeout(function() {
            $('#progress-container').addClass('active').hide().fadeIn(300);
            executeProcess(formData);
        }, 600);
    }
    
    function startProgressAnimation() {
        progressInterval = setInterval(function() {
            if (currentProgress < targetProgress) {
                currentProgress += 0.2; // 2-3 píxeles por segundo
                updateProgressBar(currentProgress);
            }
        }, 100);
    }
    
    function updateSummary(formData) {
        $('#summary-campaign').text(formData.campaign_name);
        $('#summary-posts').text(formData.num_posts);
        $('#summary-date').text(formData.start_date);
        $('#summary-time').text(formData.publish_time);
        
        const days = formData.publish_days.split(',').join(', ');
        $('#summary-days').text(days);
    }
    
    function executeProcess(formData) {
        const steps = [
            {
                id: 'save_initial',
                text: 'Creando campaña...',
                action: 'save_campaign_initial',
                weight: 8
            },
            {
                id: 'company_desc',
                text: 'Generando descripción de empresa...',
                action: 'generate_company_description',
                weight: 15
            },
            { id: 'keywords_seo', text: 'Generando keywords SEO...', action: 'generate_keywords_seo', weight: 15 },
            { id: 'prompt_title', text: 'Creando prompt para títulos...', action: 'generate_title_prompt', weight: 15 },
            { id: 'prompt_content', text: 'Creando prompt para contenido...', action: 'generate_content_prompt', weight: 15 },
            { id: 'keywords_img', text: 'Generando keywords de imágenes...', action: 'generate_image_keywords', weight: 15 },
            {
                id: 'update_final',
                text: 'Guardando campaña completa...',
                action: 'update_campaign_final',
                weight: 17
            }
        ];

        // Total weight = 100
        totalSteps = steps.length;
        
        renderAllSteps(steps);
        
        let currentStep = 0;
        const results = {};
        let processStopped = false;
        let accumulatedProgress = 0;
        
        function processNextStep() {
            if (processStopped) return;

            if (currentStep >= steps.length) {
                processCompleted();
                return;
            }

            const step = steps[currentStep];

            updateStepStatus(step.id, 'active');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ap_autopilot_step',
                    step_action: step.action,
                    form_data: formData,
                    results: results
                },
                success: function(response) {
                    if (response.success) {
                        updateStepStatus(step.id, 'completed');
                        results[step.action] = response.data;
                        savedData[step.action] = response.data;

                        // Guardar campaign_id cuando se crea la campaña
                        if (step.action === 'save_campaign' || step.action === 'save_campaign_initial') {
                            formData.campaign_id = response.data.campaign_id;
                            savedData.campaign_id = response.data.campaign_id;
                        }

                        accumulatedProgress += step.weight;
                        targetProgress = accumulatedProgress;
                        currentStep++;
                        setTimeout(processNextStep, 300);
                    } else {
                        processStopped = true;
                        updateStepStatus(step.id, 'error');

                        // Usar manejador centralizado de errores
                        var isTokenLimitError = AutoPost.isTokenLimitError(response);
                        var errorMsg = AutoPost.extractErrorMessage(response);

                        handleError(errorMsg, isTokenLimitError);
                    }
                },
                error: function() {
                    processStopped = true;
                    updateStepStatus(step.id, 'error');
                    handleError('Error de conexión', false);
                }
            });
        }

        processNextStep();
    }
    
    function renderAllSteps(steps) {
        let html = '';
        steps.forEach(step => {
            html += `
                <div class="ap-step-item" id="step-${step.id}">
                    <svg class="ap-step-icon" viewBox="0 0 24 24" fill="#cbd5e1">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    <span class="ap-step-text">${step.text}</span>
                </div>
            `;
        });
        $('#steps-list').html(html);
    }
    
    function updateProgressBar(percent) {
        const $bar = $('#progress-bar');
        $bar.css('width', Math.min(percent, 100) + '%');
        $('#progress-percent').text(Math.round(Math.min(percent, 100)) + '%');
        
        // Activar animación si está en progreso
        if (percent > 0 && percent < 100) {
            $bar.addClass('active');
        } else {
            $bar.removeClass('active');
        }
    }
    
    function updateStepStatus(stepId, status) {
        const $step = $('#step-' + stepId);
        $step.removeClass('active completed error').addClass(status);
        
        let icon = '';
        if (status === 'active') {
            icon = `<svg class="ap-step-icon icon-active" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" fill="none" stroke="#cbd5e1" stroke-width="2"/>
                <circle cx="12" cy="12" r="10" fill="none" stroke="#000000" stroke-width="3" 
                        stroke-dasharray="50 15" stroke-linecap="round" 
                        style="transform-origin: center; animation: spin 1s linear infinite;"/>
            </svg>`;
        } else if (status === 'completed') {
            icon = '<svg class="ap-step-icon icon-success" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none"/></svg>';
        } else if (status === 'error') {
            icon = '<svg class="ap-step-icon icon-error" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 9l6 6M15 9l-6 6" stroke="white" stroke-width="2"/></svg>';
        }
        $step.find('.ap-step-icon').replaceWith(icon);
    }
    
    function updateStepText(stepId, text) {
        $('#step-' + stepId).find('.ap-step-text').text(text);
    }
    
    function addSidebarMessage(text) {
        $('#sidebar-messages').append('<div class="progress-msg active">' + text + '</div>');
        $('#sidebar-messages').scrollTop($('#sidebar-messages')[0].scrollHeight);
    }
    
    function processCompleted() {
        processInProgress = false;
        clearInterval(progressInterval);
        targetProgress = 100;
        currentProgress = 100;
        updateProgressBar(100);
        
        $('#steps-list').append(`
            <div class="ap-step-item completed">
                <svg class="ap-step-icon icon-success" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none"/></svg>
                <span class="ap-step-text">Campaña creada correctamente</span>
            </div>
        `);

        // Desactivar advertencia de cierre
        processInProgress = false;
        $(window).off('beforeunload');

        setTimeout(function() {
            window.location.href = '<?php echo admin_url('admin.php?page=autopost-campaign-edit&id='); ?>' + savedData.campaign_id;
        }, 1500);
    }
    
    function handleError(message, isTokenLimitError) {
        processInProgress = false;
        $(window).off('beforeunload');
        clearInterval(progressInterval);

        $('#steps-list').append(`
            <div class="ap-step-item error">
                <svg class="ap-step-icon icon-error" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 9l6 6M15 9l-6 6" stroke="white" stroke-width="2"/></svg>
                <span class="ap-step-text">ERROR: ${message}</span>
            </div>
        `);

        // Mostrar alert con formato especial para error de tokens
        if (isTokenLimitError) {
            alert('ERROR: ' + message);
        } else {
            alert('ERROR: ' + message);
        }

        $('#start-autopilot').prop('disabled', false).text('Reintentar').show();
        $('#cancel-btn').show();
    }
});
</script>
