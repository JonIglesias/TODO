<?php
if (!defined('ABSPATH')) exit;

$name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
$domain = isset($_GET['domain']) ? sanitize_text_field($_GET['domain']) : '';
$niche = isset($_GET['niche']) ? sanitize_text_field($_GET['niche']) : '';

if (empty($name) || empty($domain) || empty($niche)) {
    wp_redirect(admin_url('admin.php?page=autopost-autopilot'));
    exit;
}
?>


<div class="wrap ap-module-wrap ap-progress-wrapper">
    <h1 class="ap-module-header">⏳ Generando tu campaña...</h1>
    <p class="subtitle" style="text-align: center; margin: -10px 0 20px 0; color: #64748b;">El proceso tomará unos minutos</p>

    <div class="ap-module-container">
        <div class="ap-module-content">
            <div class="ap-progress-card">
        <div class="ap-progress-bar-container">
            <div class="ap-progress-bar" id="progress-bar"></div>
        </div>
        
        <div class="ap-progress-status" id="progress-status">
            Preparando...
        </div>
        
        <ul class="ap-steps" id="steps-list">
            <li class="ap-step pending" data-step="1">
                <div class="ap-step-icon">⏸️</div>
                <div class="ap-step-content">
                    <h3 class="ap-step-title">Descripción de Empresa</h3>
                    <p class="ap-step-desc">Analizando tu sitio web...</p>
                </div>
            </li>
            
            <li class="ap-step pending" data-step="2">
                <div class="ap-step-icon">⏸️</div>
                <div class="ap-step-content">
                    <h3 class="ap-step-title">Keywords SEO</h3>
                    <p class="ap-step-desc">Generando palabras clave...</p>
                </div>
            </li>
            
            <li class="ap-step pending" data-step="3">
                <div class="ap-step-icon">⏸️</div>
                <div class="ap-step-content">
                    <h3 class="ap-step-title">Prompt para Títulos</h3>
                    <p class="ap-step-desc">Creando plantilla de títulos...</p>
                </div>
            </li>
            
            <li class="ap-step pending" data-step="4">
                <div class="ap-step-icon">⏸️</div>
                <div class="ap-step-content">
                    <h3 class="ap-step-title">Prompt para Contenido</h3>
                    <p class="ap-step-desc">Definiendo estilo de escritura...</p>
                </div>
            </li>
            
            <li class="ap-step pending" data-step="5">
                <div class="ap-step-icon">⏸️</div>
                <div class="ap-step-content">
                    <h3 class="ap-step-title">Keywords para Imágenes</h3>
                    <p class="ap-step-desc">Preparando búsqueda de imágenes...</p>
                </div>
            </li>
        </ul>
        
        <div class="ap-success-message" id="success-message">
            <h2>✅ ¡Campaña generada!</h2>
            <p>Redirigiendo al formulario...</p>
        </div>
        
        <div class="ap-actions" id="cancel-action">
            <button type="button" class="ap-btn-cancel" onclick="cancelAutopilot()">
                Cancelar
            </button>
        </div>
            </div> <!-- Fin ap-progress-card -->
        </div> <!-- Fin ap-module-content -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->

<script>
const campaignName = <?php echo json_encode($name); ?>;
const domain = <?php echo json_encode($domain); ?>;
const niche = <?php echo json_encode($niche); ?>;
let cancelled = false;
let campaignId = null; // Se creará al inicio

function updateProgress(percent, message) {
    jQuery('#progress-bar').css('width', percent + '%');
    jQuery('#progress-status').text(message);
}

function updateStep(stepNum, status, icon) {
    const step = jQuery('.ap-step[data-step="' + stepNum + '"]');
    step.removeClass('pending processing completed error');
    step.addClass(status);
    step.find('.ap-step-icon').text(icon);
}

function cancelAutopilot() {
    if (confirm('¿Seguro que quieres cancelar?')) {
        cancelled = true;
        window.location.href = '<?php echo admin_url('admin.php?page=autopost-ia'); ?>';
    }
}

async function createCampaign() {
    const response = await fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'ap_autopilot_create_campaign',
            name: campaignName,
            domain: domain,
            niche: niche,
            nonce: '<?php echo wp_create_nonce('ap_autopilot'); ?>'
        })
    });
    
    const text = await response.text();
    const data = JSON.parse(text);
    
    if (!data.success) {
        throw new Error(data.data || 'Error al crear campaña');
    }
    
    return data.data.campaign_id;
}

async function runAutopilot() {
    try {
        // Paso 0: Crear campaña primero
        updateProgress(0, 'Creando campaña...');
        campaignId = await createCampaign();
        console.log('Campaña creada:', campaignId);
        
        if (cancelled) return;
        // Paso 1: Descripción de Empresa
        updateProgress(0, 'Analizando tu sitio web...');
        updateStep(1, 'processing', '⏳');
        
        const companyDesc = await generateField('company_desc', {domain: domain});
        if (cancelled) return;
        
        updateStep(1, 'completed', '✅');
        updateProgress(20, 'Descripción generada ✓');
        
        await sleep(500);
        
        // Paso 2: Keywords SEO
        updateProgress(20, 'Generando keywords SEO...');
        updateStep(2, 'processing', '⏳');
        
        const keywordsSeo = await generateField('keywords_seo', {
            niche: niche,
            company_desc: companyDesc
        });
        if (cancelled) return;
        
        updateStep(2, 'completed', '✅');
        updateProgress(40, 'Keywords generadas ✓');
        
        await sleep(500);
        
        // Paso 3: Prompt Títulos
        updateProgress(40, 'Creando prompt de títulos...');
        updateStep(3, 'processing', '⏳');
        
        const promptTitles = await generateField('prompt_titles', {
            niche: niche,
            company_desc: companyDesc,
            keywords_seo: keywordsSeo
        });
        if (cancelled) return;
        
        updateStep(3, 'completed', '✅');
        updateProgress(60, 'Prompt de títulos creado ✓');
        
        await sleep(500);
        
        // Paso 4: Prompt Contenido
        updateProgress(60, 'Definiendo estilo de escritura...');
        updateStep(4, 'processing', '⏳');
        
        const promptContent = await generateField('prompt_content', {
            niche: niche,
            company_desc: companyDesc,
            keywords_seo: keywordsSeo
        });
        if (cancelled) return;
        
        updateStep(4, 'completed', '✅');
        updateProgress(80, 'Prompt de contenido definido ✓');
        
        await sleep(500);
        
        // Paso 5: Keywords Imágenes
        updateProgress(80, 'Preparando keywords de imágenes...');
        updateStep(5, 'processing', '⏳');
        
        const keywordsImages = await generateField('keywords_images', {
            prompt_titles: promptTitles,
            niche: niche,
            company_desc: companyDesc,
            keywords_seo: keywordsSeo
        });
        if (cancelled) return;
        
        updateStep(5, 'completed', '✅');
        updateProgress(100, '¡Completado! ✓');
        
        // Actualizar campaña con todos los datos generados
        await updateCampaignWithData({
            campaign_id: campaignId,
            company_desc: companyDesc,
            keywords_seo: keywordsSeo,
            prompt_titles: promptTitles,
            prompt_content: promptContent,
            keywords_images: keywordsImages
        });
        
        jQuery('#cancel-action').hide();
        jQuery('#success-message').show();
        
        setTimeout(() => {
            // Redirigir a EDITAR la campaña creada
            window.location.href = '<?php echo admin_url('admin.php?page=autopost-campaign-edit'); ?>&id=' + campaignId;
        }, 2000);
        
    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    }
}

async function generateField(field, sources) {
    try {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ap_autopilot_generate',
                field: field,
                campaign_id: campaignId,
                sources: JSON.stringify(sources),
                nonce: '<?php echo wp_create_nonce('ap_autopilot'); ?>'
            })
        });
        
        // Primero obtener el texto para debug
        const text = await response.text();
        console.log('Response for ' + field + ':', text);
        
        // Intentar parsear como JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            throw new Error('Respuesta inválida del servidor para ' + field);
        }
        
        if (!data.success) {
            throw new Error(data.data || 'Error al generar ' + field);
        }
        
        return data.data.content;
        
    } catch (error) {
        console.error('Error en generateField(' + field + '):', error);
        throw error;
    }
}

async function updateCampaignWithData(data) {
    const response = await fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'ap_autopilot_update_campaign',
            data: JSON.stringify(data),
            nonce: '<?php echo wp_create_nonce('ap_autopilot'); ?>'
        })
    });
    
    return await response.json();
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// Iniciar al cargar
jQuery(document).ready(function() {
    runAutopilot();
});
</script>
