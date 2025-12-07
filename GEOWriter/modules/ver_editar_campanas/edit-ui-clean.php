<?php 
if (!defined('ABSPATH')) exit;

$is_edit = !empty($campaign);

// Detectar si viene de autopilot (cuando campaign_id está en URL)
$from_autopilot = isset($_GET['from_autopilot']) || (isset($_GET['id']) && !isset($_GET['action']));

$title = $is_edit ? ($from_autopilot ? 'Revisar Campaña y Generar Cola' : 'Editar Campaña') : 'Crear Campaña';

// Detectar si viene de autopilot
$autopilot_data = null;
if (isset($_GET['autopilot']) && $_GET['autopilot'] == '1') {
    $user_id = get_current_user_id();
    $autopilot_data = get_transient("autopilot_data_" . $user_id);
    
    if ($autopilot_data) {
        delete_transient("autopilot_data_" . $user_id);
    }
}

// Obtener nichos desde la base de datos
$nichos = ap_get_nichos();
$dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// Obtener límite de posts por campaña desde el plan activo
$api_client = new AP_API_Client();
$max_posts_per_campaign = $api_client->get_max_posts_per_campaign();
// Si es ilimitado (-1), usar 1000 como límite práctico en el formulario
$max_posts_form = ($max_posts_per_campaign === -1) ? 1000 : $max_posts_per_campaign;
?>


<div class="wrap ap-module-wrap ap-campaign-wrapper">
    <!-- Título para WordPress (admin_notices se insertan después de este h1) -->
    <h1 class="ap-module-header" style="display: none;"><?php echo esc_html($title); ?></h1>

    <!-- Header visible -->
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;"><?php echo esc_html($title); ?></h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <button type="submit" form="campaign-form" class="button button-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Guardar Campaña
            </button>
            <?php if ($is_edit): ?>
                <button type="button" class="button" onclick="window.location.href='<?php echo admin_url('admin.php?page=autopost-ia'); ?>'">← Volver a Campañas</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid: formulario + sidebar -->
    <div class="ap-module-container ap-campaign-content has-sidebar">
        <div class="ap-module-content">
            <form method="post" id="campaign-form" class="ap-campaign-form">
        <?php wp_nonce_field('ap_save_campaign', 'ap_campaign_nonce'); ?>
        <input type="hidden" name="campaign_id" id="campaign_id" value="<?php echo $is_edit ? esc_attr($campaign->id) : ''; ?>">
        
        <!-- INFORMACIÓN BÁSICA -->
        <div class="ap-section" data-section="1">
            <h2 class="ap-section-title">
                <span class="section-number">1</span>
                Información Básica
            </h2>
            
            <!-- Nombre + Dominio + Nicho en la misma línea (25% + 25% + 50%) -->
            <div class="ap-field-row ap-field-row-triple">
                <div class="ap-field-group" style="flex: 1 1 0; min-width: 0;">
                    <label class="ap-field-label" for="name">
                        Nombre de Campaña<span class="required">*</span>
                        <button type="button" class="ap-tooltip" data-help="help-campaign-name">?</button>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="ap-field-input"
                           value="<?php echo $is_edit ? esc_attr($campaign->name) : ''; ?>"
                           required>
                </div>

                <div class="ap-field-group" style="flex: 1 1 0; min-width: 0;">
                    <label class="ap-field-label" for="domain">
                        Dominio
                        <button type="button" class="ap-tooltip" data-help="help-domain">?</button>
                    </label>
                    <input type="text"
                           id="domain"
                           name="domain"
                           class="ap-field-input"
                           value="<?php echo $autopilot_data ? esc_attr($autopilot_data['domain']) : ($is_edit ? esc_attr($campaign->domain) : ''); ?>"
                           placeholder="www.ejemplo.com">
                </div>

                <div class="ap-field-group" style="flex: 2 1 0; min-width: 0;">
                    <label class="ap-field-label" for="niche">
                        Nicho
                        <button type="button" class="ap-tooltip" data-help="help-niche">?</button>
                    </label>
                    <div class="ap-autocomplete-container">
                        <span id="niche-suggestion"></span>
                        <input type="text"
                               id="niche"
                               name="niche"
                               class="ap-field-input ap-autocomplete-input"
                               value="<?php echo $is_edit ? esc_attr($campaign->niche) : ''; ?>"
                               placeholder="Escribe para buscar..."
                               autocomplete="off">
                        <div class="ap-autocomplete-dropdown" id="niche-dropdown"></div>
                    </div>
                </div>
            </div>

            <div class="ap-field-group">
                <label class="ap-field-label" for="company_desc">
                    Descripción de Empresa
                    <button type="button" class="ap-tooltip" data-help="help-company-desc">?</button>
                </label>
                <textarea id="company_desc"
                          name="company_desc"
                          class="ap-field-textarea"><?php echo $is_edit ? esc_textarea($campaign->company_desc) : esc_textarea(get_option('ap_company_desc', '')); ?></textarea>
                <button type="button" class="ap-btn-ai ap-btn-inline" id="btn-generate-company" data-field="company_desc" data-source="domain">
                    <span class="spinner"></span>
                    <svg class="token-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9 7h4.5c1.5 0 2.5 1 2.5 2.5S15 12 13.5 12M9 7v10m0-10V7m4.5 5h-4.5m4.5 0h.5c1.5 0 2.5 1 2.5 2.5S15.5 17 14 17H9m0 0v0"/>
                    </svg>
                    <span class="text">Generar con IA</span>
                </button>
                <p class="ap-field-desc">Describe la empresa para generar contenido personalizado</p>
            </div>
        </div>
        
        <!-- CONFIGURACIÓN DE CONTENIDO -->
        <div class="ap-section" data-section="2">
            <h2 class="ap-section-title">
                <span class="section-number">2</span>
                Configuración de Contenido
            </h2>
            
            <!-- Número de Posts (flex) + Extensión (min-width) en la misma fila -->
            <div class="ap-field-row ap-field-row-slider">
                <div class="ap-field-group ap-slider-container" style="flex: 1;">
                    <label class="ap-field-label" for="num_posts_input">
                        Número de Posts:
                        <input type="number"
                               id="num_posts_input"
                               class="ap-num-posts-input"
                               min="1"
                               max="<?php echo $max_posts_form; ?>"
                               value="<?php echo $is_edit ? esc_attr($campaign->num_posts) : '10'; ?>"
                               style="width: 70px; margin-left: 8px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-weight: 600; color: #3D4A5C; text-align: center;">
                        <?php if ($max_posts_per_campaign !== -1): ?>
                            <span class="ap-limit-badge" style="font-size: 11px; background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 10px; margin-left: 6px;">
                                Máx: <?php echo $max_posts_per_campaign; ?> (plan activo)
                            </span>
                        <?php endif; ?>
                        <button type="button" class="ap-tooltip" data-help="help-num-posts">?</button>
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
                                   value="<?php echo $is_edit ? esc_attr($campaign->num_posts) : '10'; ?>">
                            <div id="slider_thumb_number" class="slider-thumb-number"></div>
                        </div>
                    </div>
                </div>

                <div class="ap-field-group ap-field-extension" style="flex: 0 0 auto; min-width: 220px; max-width: 250px;">
                    <label class="ap-field-label" for="post_length">
                        Extensión del Post
                        <button type="button" class="ap-tooltip" data-help="help-post-length">?</button>
                    </label>
                    <select id="post_length" name="post_length" class="ap-field-input">
                        <option value="corto" <?php echo ($is_edit && $campaign->post_length === 'corto') ? 'selected' : ''; ?>>Corto (300-500 palabras)</option>
                        <option value="medio" <?php echo ($is_edit && $campaign->post_length === 'medio') ? 'selected' : ''; ?> selected>Medio (500-800 palabras)</option>
                        <option value="largo" <?php echo ($is_edit && $campaign->post_length === 'largo') ? 'selected' : ''; ?>>Largo (800-1200 palabras)</option>
                    </select>
                </div>
            </div>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="keywords_seo">
                    Keywords SEO
                    <button type="button" class="ap-tooltip" data-help="help-keywords-seo">?</button>
                </label>
                <textarea id="keywords_seo"
                          name="keywords_seo"
                          class="ap-field-textarea"><?php echo $is_edit ? esc_textarea($campaign->keywords_seo) : ''; ?></textarea>
                <button type="button" class="ap-btn-ai ap-btn-inline" id="btn-generate-keywords-seo" data-field="keywords_seo" data-source="niche,company_desc">
                    <span class="spinner"></span>
                    <svg class="token-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9 7h4.5c1.5 0 2.5 1 2.5 2.5S15 12 13.5 12M9 7v10m0-10V7m4.5 5h-4.5m4.5 0h.5c1.5 0 2.5 1 2.5 2.5S15.5 17 14 17H9m0 0v0"/>
                    </svg>
                    <span class="text">Generar con IA</span>
                </button>
                <p class="ap-field-desc">Lista de palabras clave separadas por comas</p>
            </div>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="prompt_titles">
                    Prompt para Títulos
                    <button type="button" class="ap-tooltip" data-help="help-prompt-titles">?</button>
                    <button type="button" class="ap-btn-ai ap-btn-inline ia-generate" data-field="prompt_titles" data-source="niche,company_desc,keywords_seo">
                        <span class="spinner"></span>
                        <svg class="token-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9 7h4.5c1.5 0 2.5 1 2.5 2.5S15 12 13.5 12M9 7v10m0-10V7m4.5 5h-4.5m4.5 0h.5c1.5 0 2.5 1 2.5 2.5S15.5 17 14 17H9m0 0v0"/>
                        </svg>
                        <span class="text">Generar con IA</span>
                    </button>
                </label>
                <textarea id="prompt_titles"
                          name="prompt_titles"
                          class="ap-field-textarea"><?php echo $is_edit ? esc_textarea($campaign->prompt_titles) : ''; ?></textarea>
                <p class="ap-field-desc">Define cómo deben ser los títulos de los posts</p>
            </div>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="prompt_content">
                    Prompt para Contenido
                    <button type="button" class="ap-tooltip" data-help="help-prompt-content">?</button>
                    <button type="button" class="ap-btn-ai ap-btn-inline ia-generate" data-field="prompt_content" data-source="niche,company_desc,keywords_seo">
                        <span class="spinner"></span>
                        <svg class="token-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9 7h4.5c1.5 0 2.5 1 2.5 2.5S15 12 13.5 12M9 7v10m0-10V7m4.5 5h-4.5m4.5 0h.5c1.5 0 2.5 1 2.5 2.5S15.5 17 14 17H9m0 0v0"/>
                        </svg>
                        <span class="text">Generar con IA</span>
                    </button>
                </label>
                <textarea id="prompt_content"
                          name="prompt_content"
                          class="ap-field-textarea"
                          rows="6"
                          placeholder="Ejemplo: Escribe de forma cercana y práctica, con ejemplos reales. Usa un tono amigable."><?php echo $is_edit ? esc_textarea($campaign->prompt_content) : ''; ?></textarea>
                <p class="ap-field-desc">Define cómo debe escribir la IA</p>
            </div>
        </div>
        
        <!-- IMÁGENES -->
        <div class="ap-section" data-section="3">
            <h2 class="ap-section-title">
                <span class="section-number">3</span>
                Configuración de Imágenes
            </h2>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="keywords_images">
                    Keywords para Imágenes
                    <button type="button" class="ap-tooltip" data-help="help-keywords-images">?</button>
</label>
                <textarea id="keywords_images" 
                          name="keywords_images" 
                          class="ap-field-textarea"><?php echo $is_edit ? esc_textarea($campaign->keywords_images) : ''; ?></textarea>
                    <button type="button" class="ap-btn-ai ap-btn-inline" id="btn-generate-images" data-field="keywords_images" data-source="prompt_titles,niche,company_desc,keywords_seo">
                        <span class="spinner"></span>
                        <svg class="token-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9 7h4.5c1.5 0 2.5 1 2.5 2.5S15 12 13.5 12M9 7v10m0-10V7m4.5 5h-4.5m4.5 0h.5c1.5 0 2.5 1 2.5 2.5S15.5 17 14 17H9m0 0v0"/>
                        </svg>
                        <span class="text">Generar con IA</span>
                    </button>
                
                <p class="ap-field-desc">Rellena el prompt de títulos primero para generar con IA</p>
            </div>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="image_provider">
                    Proveedor de Imágenes
                </label>
                <select id="image_provider" name="image_provider" class="ap-field-input" style="max-width: 200px;">
                    <?php
                    $providers = [];
                    if (get_option('ap_unsplash_key')) $providers[] = 'unsplash';
                    if (get_option('ap_pixabay_key')) $providers[] = 'pixabay';
                    if (get_option('ap_pexels_key')) $providers[] = 'pexels';
                    
                    if (empty($providers)): ?>
                        <option value="">Configura APIs primero</option>
                    <?php else:
                        foreach ($providers as $provider): ?>
                            <option value="<?php echo esc_attr($provider); ?>"
                                <?php echo ($is_edit && $campaign->image_provider === $provider) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($provider); ?>
                            </option>
                        <?php endforeach;
                    endif; ?>
                </select>
            </div>
        </div>
        
        <!-- PROGRAMACIÓN -->
        <div class="ap-section" data-section="4">
            <h2 class="ap-section-title">
                <span class="section-number">4</span>
                Programación de Publicación
            </h2>
            
            <div class="ap-field-group">
                <label class="ap-field-label">
                    Días de Publicación
                    <button type="button" class="ap-tooltip" data-help="help-publish-days">?</button>
                </label>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <?php 
                    $selected_days = $is_edit ? explode(',', $campaign->publish_days) : [];
                    foreach ($dias_semana as $dia): 
                    ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" 
                                   name="publish_days[]" 
                                   value="<?php echo esc_attr($dia); ?>"
                                   <?php echo in_array($dia, $selected_days) ? 'checked' : ''; ?>>
                            <span style="font-size: 14px;"><?php echo esc_html($dia); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 600px;">
                <div class="ap-field-group">
                    <label class="ap-field-label" for="start_date">Fecha de Inicio</label>
                    <input type="date" 
                           id="start_date" 
                           name="start_date" 
                           class="ap-field-input"
                           value="<?php echo ($is_edit && $campaign->start_date) ? esc_attr(date('Y-m-d', strtotime($campaign->start_date))) : ''; ?>">
                </div>
                
                <div class="ap-field-group">
                    <label class="ap-field-label" for="publish_time">Hora de Publicación</label>
                    <input type="time" 
                           id="publish_time" 
                           name="publish_time" 
                           class="ap-field-input"
                           value="<?php echo $is_edit ? esc_attr($campaign->publish_time) : '09:00'; ?>">
                </div>
            </div>
            
            <div class="ap-field-group">
                <label class="ap-field-label" for="category_id">
                    Categoría
                    <button type="button" class="ap-tooltip" data-help="help-category">?</button>
                </label>
                <select id="category_id" name="category_id" class="ap-field-input" style="max-width: 400px;">
                    <option value="">Selecciona una categoría</option>
                    <?php
                    $categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
                    foreach ($categories as $category): 
                    ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>"
                            <?php echo ($is_edit && $campaign->category_id == $category->term_id) ? 'selected' : ''; ?>>
                            <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($categories)): ?>
                    <p class="ap-field-desc" style="color: #d1341f;">
                        No hay categorías. <a href="<?php echo admin_url('edit-tags.php?taxonomy=category'); ?>" target="_blank">Crea una aquí</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ACCIONES -->
        <div class="ap-actions">
            <button type="submit" class="ap-btn-primary">Guardar Campaña</button>

            <?php if ($is_edit): ?>
                <?php if ($campaign->queue_generated): ?>
                    <a href="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>" class="ap-btn-secondary">Ver Cola</a>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=autopost-queue&campaign_id=' . $campaign->id); ?>" class="ap-btn-primary" id="btn-generate-queue">Generar Cola</a>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?php echo admin_url('admin.php?page=autopost-ia'); ?>" class="ap-btn-secondary" id="btn-back-campaigns">Volver a Campañas</a>
        </div>
            </form>
        </div> <!-- Fin ap-module-content -->

        <!-- Sidebar de Ayuda (siempre visible) -->
        <div class="ap-module-sidebar ap-help-sidebar" id="help-sidebar">
        <h3>💡 Guía de Creación</h3>
        
        <div class="ap-help-item" id="help-default">
            <h4>Pasos para crear una campaña</h4>
            <p>1. Completa la información básica<br>
            2. Configura el contenido y prompts<br>
            3. Ajusta las imágenes<br>
            4. Programa la publicación<br>
            5. Genera la cola de posts</p>
        </div>
        
        <div class="ap-help-item" id="help-campaign-name" style="display:none;">
            <h4>Nombre de Campaña</h4>
            <p>Elige un nombre descriptivo que te ayude a identificar fácilmente esta campaña.</p>
        </div>
        
        <div class="ap-help-item" id="help-domain" style="display:none;">
            <h4>Dominio</h4>
            <p>Introduce la URL completa del sitio web donde se publicarán los posts.</p>
        </div>
        
        <div class="ap-help-item" id="help-company-desc" style="display:none;">
            <h4>Descripción de Empresa</h4>
            <p>Proporciona información sobre la empresa, sus servicios y valores. Esto personalizará el contenido generado.</p>
        </div>
        
        <!-- PANEL DE PROGRESO -->
        <div class="ap-help-item" id="progress-company-desc" style="display:none;">
            <h4>Generando descripción</h4>
            <div class="ap-progress-messages" id="progress-messages"></div>
        </div>
        
        <div class="ap-help-item" id="progress-keywords-seo" style="display:none;">
            <h4>Generando keywords SEO</h4>
            <div class="ap-progress-messages" id="progress-keywords-messages"></div>
        </div>
        
        <div class="ap-help-item" id="progress-prompts" style="display:none;">
            <h4>Generando prompts</h4>
            <div class="ap-progress-messages" id="progress-prompts-messages"></div>
        </div>
        
        <div class="ap-help-item" id="progress-keywords-images" style="display:none;">
            <h4>Generando keywords imágenes</h4>
            <div class="ap-progress-messages" id="progress-images-messages"></div>
        </div>
        
        <div class="ap-help-item" id="help-niche" style="display:none;">
            <h4>Nicho</h4>
            <p>Selecciona el sector principal. La IA generará contenido especializado para tu audiencia.</p>
        </div>
        
        <div class="ap-help-item" id="help-num-posts" style="display:none;">
            <h4>Número de Posts</h4>
            <p>Define cuántos posts se crearán en la cola (máximo 50 posts).</p>
        </div>
        
        <div class="ap-help-item" id="help-post-length" style="display:none;">
            <h4>Extensión del Post</h4>
            <p><strong>Corto:</strong> 300-500 palabras<br>
            <strong>Medio:</strong> 700-1000 palabras<br>
            <strong>Largo:</strong> 1500-2500 palabras</p>
        </div>
        
        <div class="ap-help-item" id="help-keywords-seo" style="display:none;">
            <h4>Keywords SEO</h4>
            <p>Lista las palabras clave principales para posicionar en buscadores. Sepáralas por comas.</p>
        </div>
        
        <div class="ap-help-item" id="help-prompt-titles" style="display:none;">
            <h4>Prompt para Títulos</h4>
            <p>Define cómo quieres que sean los títulos. Ejemplo: "Genera títulos llamativos y SEO-friendly"</p>
        </div>
        
        <div class="ap-help-item" id="help-prompt-content" style="display:none;">
            <h4>Prompt para Contenido</h4>
            <p>Describe el tono y estilo de escritura. Ejemplo: "Tono profesional pero cercano"</p>
        </div>
        
        <div class="ap-help-item" id="help-keywords-images" style="display:none;">
            <h4>Keywords para Imágenes</h4>
            <p>Palabras clave en <strong>inglés</strong> para buscar imágenes en bancos de fotos.</p>
        </div>
        
        <div class="ap-help-item" id="help-publish-days" style="display:none;">
            <h4>Días de Publicación</h4>
            <p>Selecciona los días en que se publicarán automáticamente los posts.</p>
        </div>
        
        <div class="ap-help-item" id="help-category" style="display:none;">
            <h4>Categoría</h4>
            <p>Selecciona la categoría de WordPress donde se publicarán los posts.</p>
        </div>
        </div> <!-- Fin ap-module-sidebar -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->

<script>
// ⭐ Definir campaignId globalmente
const campaignId = <?php echo (int)($campaign->id ?? 0); ?>;

jQuery(document).ready(function($) {
    // ========================================
    // SISTEMA DE AYUDA - SIDEBAR
    // ========================================
    
    // Manejar clics en los tooltips
    $('.ap-tooltip').on('click', function(e) {
        e.preventDefault();
        const helpKey = $(this).data('help');
        
        // Ocultar todos los help-items
        $('.ap-help-item').hide();
        
        // Mostrar el help-item correspondiente
        const helpItem = $('#' + helpKey);
        if (helpItem.length) {
            helpItem.show();
        } else {
            // Si no existe, mostrar el default
            $('#help-default').show();
        }
    });
    
    // ========================================
    // FIN SISTEMA DE AYUDA
    // ========================================

    // ========================================
    // AUTOCOMPLETE FUNCIONAL PARA NICHO
    // ========================================

    const nichos = <?php echo json_encode($nichos); ?>;
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
        if (!query) return nichos;

        const queryLower = query.toLowerCase();
        return nichos.filter(nicho =>
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
            for (let i in nichos) {
                if (regex.test(nichos[i])) {
                    // Ajustar mayúsculas/minúsculas según lo que escribió el usuario
                    let suggestion = caseCheck(nichos[i]);
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
        if (!$nicheInput.val()) {
            clearSuggestion();
        }
    });

    // ========================================
    // FIN AUTOCOMPLETE FUNCIONAL
    // ========================================

    // ========================================
    // DESBLOQUEO PROGRESIVO DE SECCIONES
    // ========================================
    
    function checkSectionCompletion() {
        // Verificar si la campaña está completamente configurada para generar cola
        const campaignComplete =
            $('#name').val().trim() !== '' &&
            $('#domain').val().trim() !== '' &&
            $('#company_desc').val().trim() !== '' &&
            $('#niche').val().trim() !== '' &&
            $('#num_posts').val().trim() !== '' &&
            $('#post_length').val().trim() !== '' &&
            $('#keywords_seo').val().trim() !== '' &&
            $('#prompt_titles').val().trim() !== '' &&
            $('#prompt_content').val().trim() !== '' &&
            $('#keywords_images').val().trim() !== '' &&
            $('#image_provider').val().trim() !== '' &&
            $('#category_id').val() && $('#category_id').val().trim() !== '';

        // Habilitar/Deshabilitar botón "Generar Cola" (solo validación para este botón)
        const $btnGenerateQueue = $('#btn-generate-queue');
        if ($btnGenerateQueue.length) {
            if (campaignComplete) {
                $btnGenerateQueue
                    .removeClass('ap-btn-disabled')
                    .css({
                        'opacity': '1',
                        'pointer-events': 'auto',
                        'cursor': 'pointer'
                    })
                    .attr('title', '');
            } else {
                $btnGenerateQueue
                    .addClass('ap-btn-disabled')
                    .css({
                        'opacity': '0.5',
                        'pointer-events': 'none',
                        'cursor': 'not-allowed'
                    })
                    .attr('title', 'Completa todos los campos obligatorios antes de generar la cola');
            }
        }

        // NO bloqueamos ninguna sección - todas están desbloqueadas
    }
    
    // Verificar al cargar y al cambiar cualquier campo
    checkSectionCompletion();
    $('#campaign-form').on('input change', 'input, textarea, select', function() {
        checkSectionCompletion();
    });
    
    // ========================================
    // FIN DESBLOQUEO PROGRESIVO
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

    // Generar con IA
    $('.ap-btn-ai').on('click', function() {
        const $btn = $(this);
        const field = $btn.data('field');
        const sources = $btn.data('source').split(',');
        
        // Recopilar datos
        const sourceData = {};
        sources.forEach(function(src) {
            let value = $('#' + src).val();
            if (src === 'niche' && !value) {
                value = $('#niche_custom').val();
            }
            sourceData[src] = value || '';
        });
        
        // Mostrar loading
        $btn.addClass('loading').prop('disabled', true);
        $btn.find('.text').text('Generando...');
        
        // Guardar timeouts para poder cancelarlos
        let progressTimeouts = [];
        
        // Si es descripción de empresa, mostrar progreso
        if (field === 'company_desc') {
            showCompanyDescProgress();
            progressTimeouts.push(setTimeout(() => addProgressMsg('Analizando sitio web...'), 3000));
            progressTimeouts.push(setTimeout(() => addProgressMsg('Extrayendo información...'), 7000));
            progressTimeouts.push(setTimeout(() => addProgressMsg('Generando descripción...'), 11000));
        } else if (field === 'keywords_seo') {
            showProgress('keywords-seo', 'keywords');
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('keywords', 'Analizando nicho...'), 2000));
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('keywords', 'Generando keywords SEO...'), 5000));
        } else if (field === 'prompt_titles' || field === 'prompt_content') {
            showProgress('prompts', 'prompts');
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('prompts', 'Analizando contexto...'), 2000));
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('prompts', 'Generando prompt...'), 5000));
        } else if (field === 'keywords_images') {
            showProgress('keywords-images', 'images');
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('images', 'Analizando temática...'), 2000));
            progressTimeouts.push(setTimeout(() => addProgressMsgTo('images', 'Generando keywords...'), 5000));
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ap_generate_ia_field',
                field: field,
                sources: sourceData,
                campaign_id: campaignId,
                nonce: '<?php echo wp_create_nonce("ap_nonce"); ?>'
            },
            success: function(response) {
                // Cancelar timeouts pendientes
                progressTimeouts.forEach(t => clearTimeout(t));

                if (response.success && response.data.content) {
                    $('#' + field).val(response.data.content);

                    // Finalizar progreso según campo
                    if (field === 'company_desc') {
                        addProgressMsg('Descripción generada correctamente', 'success');
                        hideCompanyDescProgress();
                    } else if (field === 'keywords_seo') {
                        addProgressMsgTo('keywords', 'Keywords generadas', 'success');
                        setTimeout(() => { $('.ap-help-item').hide(); $('#help-default').show(); }, 2000);
                    } else if (field === 'prompt_titles' || field === 'prompt_content') {
                        addProgressMsgTo('prompts', 'Prompt generado', 'success');
                        setTimeout(() => { $('.ap-help-item').hide(); $('#help-default').show(); }, 2000);
                    } else if (field === 'keywords_images') {
                        addProgressMsgTo('images', 'Keywords generadas', 'success');
                        setTimeout(() => { $('.ap-help-item').hide(); $('#help-default').show(); }, 2000);
                    }

                    // Animación de éxito
                    $('#' + field).css({
                        'border': '2px solid #10b981',
                        'background': '#f0fff4',
                        'transition': 'all 0.3s'
                    });
                    setTimeout(function() {
                        $('#' + field).css({
                            'border': '1px solid #ddd',
                            'background': 'white'
                        });
                    }, 2000);

                    checkSectionCompletion();
                } else {
                    // Usar manejador centralizado de errores
                    AutoPost.handleApiError(response);

                    // Ocultar progreso si es company_desc
                    if (field === 'company_desc') {
                        hideCompanyDescProgress();
                    }
                }
            },
            error: function(xhr, status, error) {
                alert('ERROR: Error de conexión con la API');

                // Ocultar progreso si es company_desc
                if (field === 'company_desc') {
                    hideCompanyDescProgress();
                }
            },
            complete: function() {
                // Detener spinner
                $btn.removeClass('loading').prop('disabled', false);
                $btn.find('.text').text('Generar con IA');

                checkSectionCompletion();
            }
        });
    });
    
    // Validación obligatoria de nombre al enviar formulario
    $('#campaign-form').on('submit', function(e) {
        const campaignName = $('#name').val().trim();

        if (!campaignName || campaignName === '') {
            e.preventDefault();
            alert('ERROR: El nombre de campaña es obligatorio.\n\nPor favor, introduce un nombre antes de guardar.');
            $('#name').focus();
            $('#name').css({
                'border': '2px solid #ef4444',
                'background': '#fee2e2'
            });
            setTimeout(function() {
                $('#name').css({
                    'border': '2px solid #e2e8f0',
                    'background': 'white'
                });
            }, 3000);
            return false;
        }
    });
    
    // ========================================
    // ⭐ SISTEMA DE AUTOGUARDADO MOVIDO A campaign-autosave-unified.js
    // ========================================
    // El autoguardado ahora está manejado por el nuevo sistema unificado
    // que previene duplicados, valida nombres y optimiza el rendimiento
    
    // ========================================
    // SISTEMA DE PROGRESO PARA DESCRIPCIÓN DE EMPRESA
    // ========================================
    
    function showCompanyDescProgress() {
        $('.ap-help-item').hide();
        $('#progress-company-desc').show();
        $('#progress-messages').html('');
        addProgressMsg('Iniciando análisis...');
    }
    
    function showProgress(progressId, containerId) {
        $('.ap-help-item').hide();
        $('#progress-' + progressId).show();
        $('#progress-' + containerId + '-messages').html('');
        addProgressMsgTo(containerId, 'Iniciando...');
    }
    
    function addProgressMsg(text, type = 'info') {
        addProgressMsgTo('', text, type);
    }
    
    function addProgressMsgTo(containerId, text, type = 'info') {
        const container = containerId ? '#progress-' + containerId + '-messages' : '#progress-messages';
        const icons = {
            info: '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10" stroke="currentColor" opacity="0.3"/><line x1="12" y1="12" x2="12" y2="2" stroke="currentColor" stroke-linecap="round"/></svg>',
            success: '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>'
        };
        
        $(container).find('.progress-msg').removeClass('active');
        
        const $msg = $('<div class="progress-msg ' + type + ' ' + (type === 'info' ? 'active' : '') + '">' + 
            icons[type] + 
            '<span>' + text + '</span>' +
        '</div>');
        
        $(container).append($msg);
    }
    
    function hideCompanyDescProgress() {
        setTimeout(function() {
            $('#progress-company-desc').hide();
            $('.ap-help-item').first().show();
        }, 2000);
    }
});
</script>

<?php if ($autopilot_data): ?>
<script>
jQuery(document).ready(function($) {
    // Pre-rellenar campos con datos de autopilot
    const autopilotData = <?php echo json_encode($autopilot_data); ?>;
    
    $('#domain').val(autopilotData.domain);
    $('#niche').val(autopilotData.niche);
    $('#company_desc').val(autopilotData.company_desc);
    $('#keywords_seo').val(autopilotData.keywords_seo);
    $('#prompt_titles').val(autopilotData.prompt_titles);
    $('#prompt_content').val(autopilotData.prompt_content);
    $('#keywords_images').val(autopilotData.keywords_images);
    
    // Generar nombre de campaña automático
    const today = new Date();
    const dateStr = today.getFullYear() + '-' + 
                   String(today.getMonth() + 1).padStart(2, '0') + '-' +
                   String(today.getDate()).padStart(2, '0');
    const campaignName = autopilotData.domain.replace(/^www\./, '').split('.')[0] + '_Campaña_' + dateStr;
    $('#name').val(campaignName);
    
    // Actualizar estado de secciones
    if (typeof checkSectionCompletion === 'function') {
        checkSectionCompletion();
    }
    
    // Mostrar notificación de éxito
    const toast = $('<div class="ap-toast-success">' +
        '<strong>✅ Campaña generada por Autopilot</strong><br>' +
        'Revisa los campos y ajusta lo que necesites antes de guardar.' +
        '</div>');
    
    $('body').append(toast);
    
    toast.css({
        'position': 'fixed',
        'top': '32px',
        'right': '20px',
        'background': 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'color': 'white',
        'padding': '16px 20px',
        'border-radius': '8px',
        'box-shadow': '0 4px 12px rgba(16, 185, 129, 0.3)',
        'z-index': 9999,
        'max-width': '400px',
        'font-size': '14px',
        'line-height': '1.5',
        'animation': 'slideInRight 0.3s ease'
    });
    
    // Añadir animación
    $('<style>').text(`
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `).appendTo('head');
    
    // Auto-ocultar después de 5 segundos
    setTimeout(function() {
        toast.fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
});
</script>
<?php endif; ?>
