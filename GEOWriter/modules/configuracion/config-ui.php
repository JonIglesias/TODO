<?php if (!defined('ABSPATH')) exit; ?>


<div class="wrap ap-module-wrap">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Configuración</h1>
    </div>

    <div class="ap-module-container ap-config-wrapper">
        <!-- Configuración principal -->
        <div class="ap-module-content">
            <form method="post" action="" id="ap-config-form">
                <?php wp_nonce_field('ap_save_config', 'ap_config_nonce'); ?>
                
                <div class="ap-mega-card">
                    
                    <!-- Descripción de Empresa -->
                    <div class="ap-section">
                        <h2 class="ap-section-title">Descripción de Empresa</h2>
                        <div class="ap-field">
                            <label for="ap_company_desc" class="ap-label">Descripción</label>
                            <p style="color: #64748b; font-size: 13px; margin: 0 0 8px 0;">Describe brevemente la temática de tu web o empresa.</p>
                            <textarea id="ap_company_desc"
                                      name="ap_company_desc"
                                      class="ap-textarea-field"><?php echo esc_textarea(get_option('ap_company_desc', '')); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Grid 2 columnas: API + Buscadores -->
                    <div class="ap-grid-2">
                        
                        <!-- API OpenAI -->
                        <div class="ap-section">
                            <h2 class="ap-section-title">API de Generación</h2>
                            <div class="ap-card-inner">
                                <div class="ap-field" style="display:none;">
                                    <label for="ap_api_url" class="ap-label">URL de la API</label>
                                    <input type="url"
                                           id="ap_api_url"
                                           name="ap_api_url"
                                           value="<?php echo esc_attr(get_option('ap_api_url', AP_API_URL_DEFAULT)); ?>"
                                           class="ap-input-field"
                                           readonly
                                           required>
                                </div>

                                <div class="ap-field">
                                    <label for="ap_license_key" class="ap-label">Licencia</label>
                                    <input type="text"
                                           id="ap_license_key"
                                           name="ap_license_key"
                                           value="<?php echo esc_attr(AP_Encryption::get_encrypted_option('ap_license_key', '')); ?>"
                                           class="ap-input-field"
                                           placeholder="Introduce tu licencia">
                                    <span id="license-status" style="display: block; margin-top: 8px; font-size: 13px;"></span>

                                    <button type="button" id="verify-license" class="ap-btn-verify" style="margin-top: 12px;">Verificar Licencia</button>
                                </div>

                                <!-- Stats Display Section -->
                                <div class="ap-field" style="margin-top: 24px;">
                                    <div id="license-stats-info" style="background: #000000; color: #ffffff; padding: 20px; border-radius: 8px;">
                                        <div class="ap-loading" style="color: #ffffff;">Cargando información de la licencia...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Buscadores de Imágenes -->
                        <div class="ap-section">
                            <h2 class="ap-section-title">Buscadores de Imágenes</h2>
                            <div class="ap-card-inner">
                                <div class="ap-field">
                                    <label for="ap_unsplash_key" class="ap-label">
                                        Unsplash API Key
                                        <span class="ap-help-icon" onclick="showAPIHelp('unsplash')">?</span>
                                    </label>
                                    <input type="text" 
                                           id="ap_unsplash_key" 
                                           name="ap_unsplash_key" 
                                           value="<?php echo esc_attr(get_option('ap_unsplash_key', '')); ?>" 
                                           class="ap-input-field"
                                           placeholder="API Key de Unsplash">
                                </div>
                                
                                <div class="ap-field">
                                    <label for="ap_pixabay_key" class="ap-label">
                                        Pixabay API Key
                                        <span class="ap-help-icon" onclick="showAPIHelp('pixabay')">?</span>
                                    </label>
                                    <input type="text" 
                                           id="ap_pixabay_key" 
                                           name="ap_pixabay_key" 
                                           value="<?php echo esc_attr(get_option('ap_pixabay_key', '')); ?>" 
                                           class="ap-input-field"
                                           placeholder="API Key de Pixabay">
                                </div>
                                
                                <div class="ap-field">
                                    <label for="ap_pexels_key" class="ap-label">
                                        Pexels API Key
                                        <span class="ap-help-icon" onclick="showAPIHelp('pexels')">?</span>
                                    </label>
                                    <input type="text" 
                                           id="ap_pexels_key" 
                                           name="ap_pexels_key" 
                                           value="<?php echo esc_attr(get_option('ap_pexels_key', '')); ?>" 
                                           class="ap-input-field"
                                           placeholder="API Key de Pexels">
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- Botón Guardar -->
                    <div class="ap-section" style="margin-top: 32px;">
                        <button type="submit" class="ap-btn-save">Guardar Configuración</button>
                    </div>

                </div>
                
            </form>
        </div> <!-- Fin ap-module-content -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->

<script>
jQuery(document).ready(function($) {
    // Load license stats on page load
    loadLicenseStats();

    // Reload stats after verifying license
    $('#verify-license').on('click', function() {
        // Wait a bit for license verification to complete, then reload stats
        setTimeout(loadLicenseStats, 1000);
    });

    function loadLicenseStats() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ap_get_stats',
                period: 'current'
            },
            success: function(response) {
                if (response.success) {
                    renderLicenseStats(response.data.summary, response.data.plan);
                } else {
                    $('#license-stats-info').html('<div style="color: #ffffff; text-align: center; padding: 20px;">No se pudo cargar la información de la licencia</div>');
                }
            },
            error: function() {
                $('#license-stats-info').html('<div style="color: #ffffff; text-align: center; padding: 20px;">Error al cargar la información de la licencia</div>');
            }
        });
    }

    function renderLicenseStats(summary, plan) {
        if (!plan) {
            $('#license-stats-info').html('<div style="color: #ffffff; text-align: center; padding: 20px;">No se pudo cargar información del plan</div>');
            return;
        }

        const renewalText = plan.renewal_date || 'No disponible';

        let html = `
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <span style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Plan</span>
                    <span style="color: #ffffff; font-weight: 600; font-size: 16px;">${escapeHtml(plan.name)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <span style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Límite mensual</span>
                    <span style="color: #ffffff; font-weight: 600; font-size: 16px;">${formatNumber(tokensToCredits(summary.tokens_limit))} créditos</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <span style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Renovación</span>
                    <span style="color: #ffffff; font-weight: 600; font-size: 16px;">${renewalText}</span>
                </div>
        `;

        if (plan.days_remaining !== undefined) {
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
                    <span style="color: rgba(255, 255, 255, 0.7); font-size: 14px;">Días restantes</span>
                    <span style="color: #ffffff; font-weight: 600; font-size: 16px;">${plan.days_remaining}</span>
                </div>
            `;
        }

        html += '</div>';

        $('#license-stats-info').html(html);
    }

    // Convertir tokens a créditos con decimales
    function tokensToCredits(tokens) {
        const credits = tokens / 10000;
        // Redondear a 1 decimal
        return Math.round(credits * 10) / 10;
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 1
        }).format(num);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
