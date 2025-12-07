<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap ap-module-wrap ap-stats-wrapper">
    <div class="ap-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600;">Estadísticas de Uso</h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <select id="stats-period" class="ap-period-select">
                <option value="current">Período actual de facturación</option>
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="60">Últimos 60 días</option>
                <option value="90">Últimos 90 días</option>
            </select>
            <button class="button button-primary" id="refresh-stats" >Actualizar</button>
        </div>
    </div>

    <div class="ap-module-container">
        <div class="ap-module-content">
            <!-- Panel de Resumen: 3 Cards en fila -->
    <div class="ap-stats-grid-3">
        <!-- Card 1: Plan Actual (Fondo Azul) -->
        <div class="ap-stat-card ap-card-plan-blue">
            <div class="ap-card-body">
                <div class="ap-plan-info" id="plan-info">
                    <div class="ap-loading">Cargando...</div>
                </div>
            </div>
        </div>

        <!-- Card 2: Gráfico de Evolución (Posts + Tokens) -->
        <div class="ap-stat-card ap-card-chart">
            <div class="ap-card-body">
                <canvas id="timeline-chart"></canvas>
            </div>
        </div>

        <!-- Card 3: Tokens Disponibles (Animación Líquido) -->
        <div class="ap-stat-card ap-card-tokens-liquid">
            <div class="ap-card-body">
                <div id="tokens-liquid-container">
                    <!-- Líquido azul -->
                    <svg id="liquid-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="liquidGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" style="stop-color:#000000;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#000000;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path id="liquid-wave" fill="url(#liquidGradient)"/>
                    </svg>
                    <!-- Texto azul (visible donde NO hay líquido) -->
                    <div id="tokens-available-blue">
                        <div class="tokens-number" id="tokens-number-blue">0</div>
                        <div class="tokens-label">tokens disponibles</div>
                    </div>
                    <!-- Texto blanco (visible donde SÍ hay líquido) -->
                    <div id="tokens-available-white">
                        <div class="tokens-number" id="tokens-number-white">0</div>
                        <div class="tokens-label">tokens disponibles</div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Tabla: Uso por Operación -->
    <div class="ap-stat-card ap-card-full">
        <div class="ap-card-header">
            <h3>Detalle de Operaciones</h3>
        </div>
        <div class="ap-card-body">
            <div id="operations-table">
                <div class="ap-loading">Cargando operaciones...</div>
            </div>
        </div>
    </div>
    </div> <!-- Fin ap-stats-grid-3 -->
        </div> <!-- Fin ap-module-content -->
    </div> <!-- Fin ap-module-container -->
</div> <!-- Fin wrap ap-module-wrap -->


<script>
jQuery(document).ready(function($) {
    let currentPeriod = 'current';
    let billingPeriod = null;
    let timelineChart = null; // Guardar instancia del gráfico

    // Cargar datos iniciales
    loadStats();
    
    // Evento: cambiar período
    $('#stats-period').on('change', function() {
        currentPeriod = $(this).val();
        loadStats();
    });
    
    // Evento: refresh manual
    $('#refresh-stats').on('click', function() {
        loadStats();
    });
    
    function loadStats() {
        // Mostrar loading
        $('#plan-info').html('<div class="ap-loading">Cargando...</div>');
        $('#operations-table').html('<div class="ap-loading">Cargando...</div>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ap_get_stats',
                period: currentPeriod
            },
            success: function(response) {
                if (response.success) {
                    billingPeriod = response.data.billing_period;
                    renderPlanInfo(response.data.summary, response.data.plan);
                    renderLiquidAnimation(response.data.summary);
                    renderTimelineChart(response.data.daily_timeline, response.data.daily_posts);
                    renderOperationsTable(response.data.campaigns);
                } else {
                    $('#plan-info').html('<div class="ap-empty-state"><div class="ap-empty-state-text">Error: ' + (response.data.message || 'No se pudieron cargar las estadísticas') + '</div></div>');
                    $('#operations-table').html('<div class="ap-empty-state"><div class="ap-empty-state-text">No hay datos disponibles</div></div>');
                }
            },
            error: function() {
                $('#plan-info').html('<div class="ap-empty-state"><div class="ap-empty-state-text">Error al cargar las estadísticas</div></div>');
                $('#operations-table').html('<div class="ap-empty-state"><div class="ap-empty-state-text">Error al cargar las operaciones</div></div>');
            }
        });
    }
    
    function renderPlanInfo(summary, plan) {
        if (!plan) {
            $('#plan-info').html('<div class="ap-empty-state-text">No se pudo cargar información del plan</div>');
            return;
        }
        
        const renewalText = plan.renewal_date || 'No disponible';
        
        let html = `
            <div class="ap-plan-row">
                <span class="ap-plan-label">Plan</span>
                <span class="ap-plan-value ap-plan-name">${escapeHtml(plan.name)}</span>
            </div>
            <div class="ap-plan-row">
                <span class="ap-plan-label">Límite mensual</span>
                <span class="ap-plan-value ap-number">${formatNumber(summary.tokens_limit)}</span>
            </div>
            <div class="ap-plan-row">
                <span class="ap-plan-label">Renovación</span>
                <span class="ap-plan-value">${renewalText}</span>
            </div>
        `;
        
        if (plan.days_remaining !== undefined) {
            html += `<div class="ap-plan-row">
                <span class="ap-plan-label">Días restantes</span>
                <span class="ap-plan-value">${plan.days_remaining}</span>
            </div>`;
        }
        
        $('#plan-info').html(html);
    }
    
    function renderLiquidAnimation(summary) {
        const available = summary.tokens_available;
        const total = summary.tokens_limit;
        const percentage = summary.usage_percentage || 0;
        
        // Porcentaje de tokens DISPONIBLES (lo que queda)
        const availablePercentage = 100 - percentage;
        
        // Mostrar número en AMBAS capas
        const formattedNumber = formatNumber(available);
        $('#tokens-number-blue').text(formattedNumber);
        $('#tokens-number-white').text(formattedNumber);
        
        // Animar líquido
        animateLiquid(availablePercentage);
    }
    
    function animateLiquid(targetPercentage) {
        const wave = document.getElementById('liquid-wave');
        if (!wave) return;
        
        // Empezar lleno (100%) y bajar hasta el targetPercentage
        let currentLevel = 100;
        const targetLevel = targetPercentage;
        
        // Calcular duración de animación según el volumen a vaciar
        const volumeToEmpty = 100 - targetPercentage;
        const duration = Math.max(2000, Math.min(5000, volumeToEmpty * 50)); // Entre 2 y 5 segundos
        const frameDuration = 1000 / 60; // 60 FPS
        const totalFrames = duration / frameDuration;
        const decrementPerFrame = volumeToEmpty / totalFrames;
        
        let animating = true;
        
        // Sistema de puntos de onda: cada punto oscila independientemente
        const numPoints = 50; // 50 puntos = doble de ancho de onda (antes 100)
        const wavePoints = [];
        
        // Inicializar cada punto con su estado de oscilación
        for (let i = 0; i < numPoints; i++) {
            wavePoints.push({
                height: 0, // Altura actual de la onda en este punto
                targetHeight: (Math.random() - 0.5) * 7.2, // Altura objetivo ±3.6px (antes ±3, ahora +20%)
                velocity: 0, // Velocidad actual de cambio
                changeInterval: Math.floor(Math.random() * 40) + 20, // Frames hasta cambiar objetivo
                frameCount: 0
            });
        }
        
        function animate() {
            // Bajar el nivel si estamos animando
            if (animating && currentLevel > targetLevel) {
                currentLevel -= decrementPerFrame;
                if (currentLevel <= targetLevel) {
                    currentLevel = targetLevel;
                    animating = false;
                }
            }
            
            // Calcular Y del nivel del líquido (0 = arriba, 100 = abajo)
            const liquidY = 100 - currentLevel;
            
            // Actualizar cada punto de onda
            wavePoints.forEach((point, index) => {
                point.frameCount++;
                
                // Cambiar objetivo aleatoriamente
                if (point.frameCount >= point.changeInterval) {
                    point.frameCount = 0;
                    point.changeInterval = Math.floor(Math.random() * 40) + 20;
                    point.targetHeight = (Math.random() - 0.5) * 7.2; // ±3.6 píxeles
                }
                
                // Física simple: mover hacia el objetivo con amortiguación
                const diff = point.targetHeight - point.height;
                point.velocity += diff * 0.02; // Aceleración hacia objetivo
                point.velocity *= 0.85; // Amortiguación (fricción)
                point.height += point.velocity;
                
                // Influencia de puntos vecinos (para crear continuidad)
                if (index > 0 && index < numPoints - 1) {
                    const avgNeighbor = (wavePoints[index - 1].height + wavePoints[index + 1].height) / 2;
                    point.height = point.height * 0.7 + avgNeighbor * 0.3;
                }
            });
            
            // Crear el path SVG con curvas cúbicas suaves (spline)
            let pathData = `M 0,100 L 0,${liquidY + wavePoints[0].height}`;
            
            // Usar curvas Bézier cúbicas para máxima suavidad
            for (let i = 0; i < numPoints - 1; i++) {
                const x0 = i > 0 ? ((i - 1) / (numPoints - 1)) * 100 : 0;
                const y0 = i > 0 ? liquidY + wavePoints[i - 1].height : liquidY + wavePoints[0].height;
                
                const x1 = (i / (numPoints - 1)) * 100;
                const y1 = liquidY + wavePoints[i].height;
                
                const x2 = ((i + 1) / (numPoints - 1)) * 100;
                const y2 = liquidY + wavePoints[i + 1].height;
                
                const x3 = i < numPoints - 2 ? ((i + 2) / (numPoints - 1)) * 100 : 100;
                const y3 = i < numPoints - 2 ? liquidY + wavePoints[i + 2].height : liquidY + wavePoints[numPoints - 1].height;
                
                // Calcular puntos de control para curva cúbica suave
                // Usando aproximación Catmull-Rom
                const tension = 0.3; // Menor = más suave
                
                const cp1x = x1 + (x2 - x0) * tension;
                const cp1y = y1 + (y2 - y0) * tension;
                
                const cp2x = x2 - (x3 - x1) * tension;
                const cp2y = y2 - (y3 - y1) * tension;
                
                // Curva cúbica Bézier: C cp1x,cp1y cp2x,cp2y x2,y2
                pathData += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${x2},${y2}`;
            }
            
            // Cerrar el path
            pathData += ` L 100,100 Z`;
            
            wave.setAttribute('d', pathData);
            
            // Aplicar clip-path al texto blanco para que solo se vea donde hay líquido
            const whiteText = document.getElementById('tokens-available-white');
            if (whiteText) {
                const liquidPercentage = currentLevel;
                whiteText.style.clipPath = `inset(0 0 ${liquidPercentage}% 0)`;
            }
            
            requestAnimationFrame(animate);
        }
        
        animate();
    }
    
    function renderTimelineChart(timeline, postsData) {
        if (!timeline || timeline.length === 0) {
            return;
        }

        const ctx = document.getElementById('timeline-chart');
        if (!ctx) return;

        // Destruir gráfico anterior si existe
        if (timelineChart) {
            timelineChart.destroy();
        }

        const labels = timeline.map(d => d.date_formatted);
        const tokensData = timeline.map(d => d.tokens);
        const posts = postsData || timeline.map(() => 0);

        timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Posts generados',
                        data: posts,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y-posts'
                    },
                    {
                        label: 'Tokens utilizados',
                        data: tokensData,
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y-tokens'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                },
                scales: {
                    'y-posts': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Posts'
                        },
                        beginAtZero: true
                    },
                    'y-tokens': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Tokens'
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return formatNumber(value);
                            }
                        }
                    }
                }
            }
        });
    }
    
    function renderOperationsTable(campaigns) {
        if (!campaigns || campaigns.length === 0) {
            $('#operations-table').html('<div class="ap-empty-state"><div class="ap-empty-state-text">No hay operaciones en este período</div></div>');
            return;
        }
        
        let html = '<table class="ap-operations-table"><thead><tr>';
        html += '<th></th>'; // Columna para el icono
        html += '<th>Operación</th>';
        html += '<th class="ap-text-right">Posts Publicados</th>';
        html += '<th class="ap-text-right">Tokens</th>';
        html += '<th></th>'; // Columna para "Ver detalle"
        html += '</tr></thead><tbody>';
        
        campaigns.forEach((campaign, index) => {
            const hasQueues = campaign.queues && campaign.queues.length > 0;
            const hasOperations = campaign.operations && campaign.operations.length > 0;
            const hasDetails = hasQueues || hasOperations;
            
            html += '<tr>';
            
            // Columna 1: Icono desplegable
            html += '<td class="ap-toggle-cell">';
            if (hasDetails) {
                html += '<span class="ap-toggle-icon" data-campaign-index="' + index + '">▶</span>';
            }
            html += '</td>';
            
            // Columna 2: Pastilla azul con nombre de campaña (clicable)
            html += '<td>';
            html += '<div class="ap-op-campaign">';
            html += '<span class="ap-campaign-pill" data-campaign-index="' + index + '">';
            html += 'Campaña: ' + escapeHtml(campaign.name);
            html += '</span>';
            html += '</div>';
            html += '</td>';
            
            // Columna 3: Cantidad
            html += '<td class="ap-text-right ap-number">' + formatNumber(campaign.total_operations) + '</td>';
            
            // Columna 4: Tokens
            html += '<td class="ap-text-right ap-number">' + formatNumber(campaign.total_tokens) + '</td>';
            
            // Columna 5: Ver detalle (solo texto)
            html += '<td class="ap-text-right">';
            if (hasDetails) {
                html += '<span class="ap-op-toggle" data-campaign-index="' + index + '">Ver detalle</span>';
            }
            html += '</td>';
            
            html += '</tr>';
            
            // Fila de detalles (oculta por defecto)
            if (hasDetails) {
                html += '<tr class="ap-op-details-row" data-campaign-index="' + index + '" style="display: none;">';
                html += '<td colspan="5" class="ap-op-details">';
                
                // Mostrar colas si existen
                if (hasQueues) {
                    html += '<div style="margin-bottom: 16px;"><strong>Colas generadas (' + campaign.queues.length + '):</strong></div>';
                    campaign.queues.forEach((queue, qidx) => {
                        html += '<div class="ap-queue-item">';
                        html += '<div class="ap-queue-header">Cola ' + (qidx + 1) + ' - ' + queue.date + ' (' + formatNumber(queue.tokens) + ' tokens)</div>';
                        if (queue.items && queue.items.length > 0) {
                            html += '<div class="ap-queue-subitems">';
                            queue.items.forEach(item => {
                                html += '<div class="ap-subitem">';
                                html += '<span>' + escapeHtml(item.display_name || item.type) + '</span>';
                                html += '<span>' + item.count + ' ops, ' + formatNumber(item.tokens) + ' tokens</span>';
                                html += '</div>';
                            });
                            html += '</div>';
                        }
                        html += '</div>';
                    });
                }
                
                // Mostrar operaciones individuales
                if (hasOperations) {
                    html += '<div style="margin-top: 16px;"><strong>Operaciones individuales:</strong></div>';
                    html += '<table style="margin-top: 8px; width: 100%;"><tbody>';
                    campaign.operations.forEach(op => {
                        html += '<tr>';
                        html += '<td style="padding: 6px 12px;">' + escapeHtml(op.name) + '</td>';
                        html += '<td style="padding: 6px 12px; text-align: right;">' + op.count + ' ops</td>';
                        html += '<td style="padding: 6px 12px; text-align: right;" class="ap-number">' + formatNumber(op.tokens) + ' tokens</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                }
                
                html += '</td></tr>';
            }
        });
        
        html += '</tbody></table>';
        $('#operations-table').html(html);
        
        // Event: toggle con icono
        $('.ap-toggle-icon').on('click', function() {
            toggleCampaignDetails($(this).data('campaign-index'));
        });
        
        // Event: toggle con pastilla azul
        $('.ap-campaign-pill').on('click', function() {
            toggleCampaignDetails($(this).data('campaign-index'));
        });
        
        // Event: toggle con "Ver detalle"
        $('.ap-op-toggle').on('click', function() {
            toggleCampaignDetails($(this).data('campaign-index'));
        });
    }
    
    function toggleCampaignDetails(index) {
        const $row = $('.ap-op-details-row[data-campaign-index="' + index + '"]');
        const $icon = $('.ap-toggle-icon[data-campaign-index="' + index + '"]');
        const $toggle = $('.ap-op-toggle[data-campaign-index="' + index + '"]');
        
        if ($row.is(':visible')) {
            $row.hide();
            $icon.removeClass('expanded');
            $toggle.html('Ver detalle');
        } else {
            $row.show();
            $icon.addClass('expanded');
            $toggle.html('Ocultar detalle');
        }
    }
    
    function formatNumber(num) {
        return new Intl.NumberFormat('es-ES').format(num);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
