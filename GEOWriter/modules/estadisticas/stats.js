/**
 * Módulo de Estadísticas - Versión minimalista y profesional
 */
(function($) {
    'use strict';
    
    window.apStats = {
        data: null,
        
        init: function() {
            this.loadStats();
            this.attachEvents();
        },
        
        attachEvents: function() {
            const self = this;
            // Cambio de período
            const periodSelector = document.getElementById('ap-stats-period');
            if (periodSelector) {
                periodSelector.addEventListener('change', function(e) {
                    self.loadStats(e.target.value);
                });
            }
        },
        
        loadStats: function(days) {
            const self = this;
            days = days || 30;
            
            // Mostrar loading
            $('#ap-stats-loading').show();
            $('#ap-stats-content').hide();
            $('#ap-stats-error').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ap_get_stats',
                    days: days
                },
                success: function(response) {
                    console.log('Response:', response);
                    
                    if (response && response.success && response.data) {
                        self.data = response.data;
                        self.render();
                    } else {
                        const errorMsg = (response && response.data && response.data.message) 
                            ? response.data.message 
                            : 'Error al cargar estadísticas';
                        self.showError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Error de conexión: ' + error);
                }
            });
        },
        
        showError: function(message) {
            $('#ap-stats-loading').hide();
            $('#ap-stats-error').show();
            $('#ap-stats-error-message').text(message);
        },
        
        render: function() {
            $('#ap-stats-loading').hide();
            $('#ap-stats-content').show();
            
            this.renderSummary();
            this.renderChart();
            this.renderCampaigns();
        },
        
        renderSummary: function() {
            const summary = this.data.summary;

            // Convertir tokens a créditos (1 crédito = 10,000 tokens) con decimales
            const creditsUsed = this.tokensToCredits(summary.total_tokens);
            const creditsLimit = this.tokensToCredits(summary.tokens_limit);
            const creditsAvailable = this.tokensToCredits(summary.tokens_available);

            // Créditos
            $('#ap-tokens-used').text(this.formatNumber(creditsUsed));
            $('#ap-tokens-limit').text(this.formatNumber(creditsLimit));
            $('#ap-tokens-available').text(this.formatNumber(creditsAvailable));

            // Porcentaje
            const percentage = Math.min(100, summary.usage_percentage);
            $('#ap-usage-percentage').text(percentage.toFixed(1) + '%');
            $('#ap-progress-fill').css('width', percentage + '%');

            // Color del progress según uso
            const progressFill = $('#ap-progress-fill');
            if (percentage >= 90) {
                progressFill.css('background', '#ef4444');
            } else if (percentage >= 75) {
                progressFill.css('background', '#f59e0b');
            } else {
                progressFill.css('background', 'white');
            }

            // Operaciones
            $('#ap-total-operations').text(this.formatNumber(summary.total_operations));
        },
        
        renderChart: function() {
            const timeline = this.data.daily_timeline;
            const chartContainer = $('#ap-daily-chart');

            if (!timeline || timeline.length === 0) {
                chartContainer.html('<p style="text-align: center; color: #64748b; padding: 40px;">No hay datos disponibles</p>');
                return;
            }

            // Convertir tokens a créditos con decimales
            const self = this;
            const creditsTimeline = timeline.map(function(d) {
                return self.tokensToCredits(d.tokens);
            });

            // Encontrar máximo para escalar
            const maxCredits = Math.max.apply(null, creditsTimeline) || 1;

            // Crear barras
            const bars = timeline.map(function(day, index) {
                const dayCredits = creditsTimeline[index];
                const heightPercent = (dayCredits / maxCredits) * 100;

                return '<div class="ap-chart-bar">' +
                    '<div class="ap-chart-bar-fill" style="height: ' + heightPercent + '%">' +
                        '<span class="ap-chart-bar-value">' + self.formatNumber(dayCredits) + '</span>' +
                    '</div>' +
                    '<span class="ap-chart-bar-label">' + day.date_formatted + '</span>' +
                '</div>';
            }).join('');

            chartContainer.html('<div class="ap-chart-bars">' + bars + '</div>');
        },
        
        renderCampaigns: function() {
            const campaigns = this.data.campaigns;
            const container = $('#ap-campaigns-list');

            if (!campaigns || campaigns.length === 0) {
                container.html('<p style="text-align: center; color: #64748b; padding: 20px;">No hay campañas con actividad en este período</p>');
                return;
            }

            const self = this;
            const html = campaigns.map(function(campaign, index) {
                let queuesHtml = '';
                if (campaign.queues.length > 0) {
                    queuesHtml = '<div class="ap-campaign-section">' +
                        '<h4>📦 Colas Generadas (' + campaign.queues.length + ')</h4>' +
                        campaign.queues.map(function(queue) {
                            const queueCredits = self.tokensToCredits(queue.tokens);
                            return '<div class="ap-queue-item">' +
                                '<div class="ap-queue-header">' +
                                    '<span class="ap-queue-title">Cola ' + queue.number + ' - ' + queue.date + '</span>' +
                                    '<span class="ap-queue-tokens">' + self.formatNumber(queueCredits) + ' créditos</span>' +
                                '</div>' +
                                '<div class="ap-queue-items">' +
                                    queue.items.map(function(item) {
                                        return '<span>→ ' + item.display_name + ': ' + item.count + '</span>';
                                    }).join('') +
                                '</div>' +
                            '</div>';
                        }).join('') +
                    '</div>';
                }

                let operationsHtml = '';
                if (campaign.operations.length > 0) {
                    operationsHtml = '<div class="ap-campaign-section">' +
                        '<h4>📝 Operaciones Individuales</h4>' +
                        '<div class="ap-operation-grid">' +
                            campaign.operations.map(function(op) {
                                return '<div class="ap-operation-item">' +
                                    '<span class="ap-operation-name">' + op.name + '</span>' +
                                    '<span class="ap-operation-count">' + op.count + 'x</span>' +
                                '</div>';
                            }).join('') +
                        '</div>' +
                    '</div>';
                }

                const campaignCredits = self.tokensToCredits(campaign.total_tokens);

                return '<div class="ap-campaign-item" onclick="apStats.toggleCampaign(' + index + ')">' +
                    '<div class="ap-campaign-header">' +
                        '<p class="ap-campaign-name">🎯 ' + campaign.name + '</p>' +
                        '<div class="ap-campaign-stats">' +
                            '<span>' + campaign.total_operations + ' consultas</span>' +
                            '<span>' + self.formatNumber(campaignCredits) + ' créditos</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="ap-campaign-details" id="ap-campaign-' + index + '">' +
                        queuesHtml +
                        operationsHtml +
                    '</div>' +
                '</div>';
            }).join('');

            container.html(html);
        },
        
        toggleCampaign: function(index) {
            $('#ap-campaign-' + index).toggleClass('open');
        },

        tokensToCredits: function(tokens) {
            const credits = tokens / 10000;
            // Redondear a 1 decimal
            return Math.round(credits * 10) / 10;
        },

        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            // Para números con decimales, mostrar máximo 1 decimal
            if (num % 1 !== 0) {
                return num.toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };
    
    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        if (typeof apStats !== 'undefined' && apStats.init) {
            apStats.init();
        }
    });
    
})(jQuery);
