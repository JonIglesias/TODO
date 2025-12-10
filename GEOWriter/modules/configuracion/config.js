jQuery(document).ready(function($) {
    if (typeof apConfig === 'undefined') {
        alert('ERROR: Scripts no cargados correctamente. Revisar consola.');
        return;
    }
    
    // Verificar licencia
    $('#verify-license').on('click', function() {
        const btn = $(this);
        const licenseKey = $('#ap_license_key').val();
        const statusSpan = $('#license-status');

        if (!licenseKey) {
            statusSpan.html('<span style="color: red;">⚠ Ingrese una licencia</span>');
            return;
        }

        btn.prop('disabled', true).text('Verificando...');
        statusSpan.html('<span style="color: orange;">⏳ Verificando...</span>');

        const ajaxData = {
            action: 'ap_verify_license',
            nonce: apConfig.nonce,
            license_key: licenseKey
        };

        $.ajax({
            url: apConfig.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    statusSpan.html('<span style="color: green;">✓ Licencia válida (' + (response.data.plan || 'N/A') + ')</span>');
                    alert('✓ Licencia verificada correctamente');
                } else {
                    // Usar manejador centralizado de errores
                    AutoPost.handleApiError(response);
                    const message = AutoPost.extractErrorMessage(response);
                    statusSpan.html('<span style="color: red;">✗ ' + message + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                statusSpan.html('<span style="color: red;">✗ Error de conexión</span>');
                alert('Error de conexión: ' + textStatus);
            },
            complete: function() {
                btn.prop('disabled', false).text('Verificar');
            }
        });
    });
    
    // Cargar información de uso
    function loadUsageInfo() {
        $.ajax({
            url: apConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'ap_get_usage',
                nonce: apConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderStatsWidget(response.data);
                } else {
                    // Usar manejador centralizado de errores
                    const errorMsg = AutoPost.extractErrorMessage(response);
                    $('#usage-content').html('<p style="color: #ef4444; padding: 20px;">' + errorMsg + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#usage-content').html('<p style="color: #ef4444; padding: 20px;">Error de conexión: ' + textStatus + '</p>');
            }
        });
    }
    
    // Convertir tokens a créditos con decimales
    function tokensToCredits(tokens) {
        const credits = tokens / 10000;
        // Redondear a 1 decimal
        return Math.round(credits * 10) / 10;
    }

    // Renderizar widget de información del plan
    function renderStatsWidget(data) {
        // Extraer límites del plan
        const planLimits = data.plan_details && data.plan_details.limits ? data.plan_details.limits : {};
        const postsPerCampaign = planLimits.posts_per_campaign || data.posts_limit || 'No especificado';
        const tokensMonthly = planLimits.tokens_monthly || data.tokens_limit || 'No especificado';

        // Convertir tokens a créditos (1 crédito = 10,000 tokens) con decimales
        const creditsMonthly = typeof tokensMonthly === 'number' ? tokensToCredits(tokensMonthly) : tokensMonthly;

        // Calcular fecha de renovación (próximo mes, día 1)
        const today = new Date();
        const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
        const renewalDate = nextMonth.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });

        let html = `
            <div class="ap-plan-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <!-- Tarjeta Plan -->
                <div class="ap-stat-card ap-stat-plan">
                    <div class="ap-stat-icon">📋</div>
                    <div class="ap-stat-content">
                        <div class="ap-stat-label">Plan Contratado</div>
                        <div class="ap-stat-value">${data.plan || 'N/A'}</div>
                        <div class="ap-stat-detail">€${data.price || 0}/mes</div>
                    </div>
                </div>

                <!-- Tarjeta Límite Posts por Campaña -->
                <div class="ap-stat-card ap-stat-posts">
                    <div class="ap-stat-icon">📝</div>
                    <div class="ap-stat-content">
                        <div class="ap-stat-label">Límite Posts por Campaña</div>
                        <div class="ap-stat-value">${postsPerCampaign === -1 ? 'Ilimitado' : postsPerCampaign}</div>
                        <div class="ap-stat-detail">posts máximos</div>
                    </div>
                </div>

                <!-- Tarjeta Límite Créditos Mensual -->
                <div class="ap-stat-card ap-stat-tokens">
                    <div class="ap-stat-icon">⚡</div>
                    <div class="ap-stat-content">
                        <div class="ap-stat-label">Límite Créditos Mensual</div>
                        <div class="ap-stat-value">${typeof creditsMonthly === 'number' ? creditsMonthly.toLocaleString('es-ES', {minimumFractionDigits: 0, maximumFractionDigits: 1}) : creditsMonthly}</div>
                        <div class="ap-stat-detail">créditos/mes</div>
                    </div>
                </div>

                <!-- Tarjeta Fecha de Renovación -->
                <div class="ap-stat-card ap-stat-renewal">
                    <div class="ap-stat-icon">📅</div>
                    <div class="ap-stat-content">
                        <div class="ap-stat-label">Fecha de Renovación</div>
                        <div class="ap-stat-value" style="font-size: 16px;">${renewalDate}</div>
                        <div class="ap-stat-detail">próximo ciclo</div>
                    </div>
                </div>
            </div>
        `;

        // Alertas o info adicional
        if (data.is_snapshot || (data.plan_details && data.plan_details.is_snapshot)) {
            html += `
                <div class="ap-alert ap-alert-info">
                    <strong>ℹ️ Plan Congelado (Snapshot)</strong>
                    <p>Los cambios en tu plan se aplicarán en la próxima renovación</p>
                </div>
            `;
        }

        $('#usage-content').html(html);
    }
    
    if ($('#usage-content').length > 0) {
        loadUsageInfo();
    }
});
