<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap phsbot-module-wrap phsbot-stats-wrapper">
    <div class="phsbot-module-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 600; color: rgba(0, 0, 0, 0.8);">Estadísticas de Uso del Chatbot</h1>
        <div style="display: flex; gap: 12px; align-items: center;">
            <select id="stats-period" class="phsbot-period-select">
                <option value="current">Período actual de facturación</option>
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="60">Últimos 60 días</option>
                <option value="90">Últimos 90 días</option>
            </select>
            <button class="button button-primary" id="refresh-stats">Actualizar</button>
        </div>
    </div>

    <div class="phsbot-module-container">
        <div class="phsbot-module-content">
            <!-- Panel de Resumen: 3 Cards en fila -->
            <div class="phsbot-stats-grid-3">
                <!-- Card 1: Plan Actual (Fondo con gradient) -->
                <div class="phsbot-stat-card phsbot-card-plan-blue">
                    <div class="phsbot-card-body">
                        <div class="phsbot-plan-info" id="plan-info">
                            <div class="phsbot-loading">Cargando...</div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Gráfico de Evolución (Conversaciones + Créditos) -->
                <div class="phsbot-stat-card phsbot-card-chart">
                    <div class="phsbot-card-body">
                        <canvas id="timeline-chart"></canvas>
                    </div>
                </div>

                <!-- Card 3: Créditos Disponibles (Animación Líquido) -->
                <div class="phsbot-stat-card phsbot-card-tokens-liquid">
                    <div class="phsbot-card-body">
                        <div id="tokens-liquid-container">
                            <!-- Líquido con olas -->
                            <svg id="liquid-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="liquidGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:#000000;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#000000;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                <path id="liquid-wave" fill="url(#liquidGradient)"/>
                            </svg>
                            <!-- Texto negro (visible donde NO hay líquido) -->
                            <div id="tokens-available-blue">
                                <div class="tokens-number" id="tokens-number-blue">0</div>
                                <div class="tokens-label">créditos disponibles</div>
                            </div>
                            <!-- Texto blanco (visible donde SÍ hay líquido) -->
                            <div id="tokens-available-white">
                                <div class="tokens-number" id="tokens-number-white">0</div>
                                <div class="tokens-label">créditos disponibles</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Fin phsbot-stats-grid-3 -->

            <!-- Tabla: Detalle de Operaciones -->
            <div class="phsbot-stat-card phsbot-card-full">
                <div class="phsbot-card-header">
                    <h3>Detalle de Operaciones</h3>
                </div>
                <div class="phsbot-card-body">
                    <div id="operations-table">
                        <div class="phsbot-loading">Cargando operaciones...</div>
                    </div>
                </div>
            </div>
        </div> <!-- Fin phsbot-module-content -->
    </div> <!-- Fin phsbot-module-container -->
</div> <!-- Fin wrap phsbot-module-wrap -->
