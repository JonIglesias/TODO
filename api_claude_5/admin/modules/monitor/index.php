<?php
/**
 * Monitor en Tiempo Real
 *
 * @version 1.0
 */
if (!defined('API_ACCESS')) die('Access denied');
?>

<style>
    .monitor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .live-indicator {
        width: 10px;
        height: 10px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
        display: inline-block;
        margin-right: 8px;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .monitor-status {
        font-size: 14px;
        color: #64748b;
    }

    .monitor-status.active {
        color: #10b981;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .metric-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .metric-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .metric-value {
        font-size: 28px;
        font-weight: 700;
        color: #2c3e50;
    }

    .metric-subvalue {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .monitor-table {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .monitor-table-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .monitor-table-header h2 {
        font-size: 18px;
        color: #2c3e50;
        margin: 0;
    }

    .time-selector {
        padding: 6px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 14px;
    }

    .operations-table {
        width: 100%;
        border-collapse: collapse;
    }

    .operations-table thead {
        background: #f8fafc;
    }

    .operations-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
    }

    .operations-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    .operations-table tbody tr:hover {
        background: #f8fafc;
    }

    .endpoint-cell {
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 13px;
        color: #0ea5e9;
        font-weight: 500;
    }

    .model-badge {
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 12px;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .tokens-cell {
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 12px;
    }

    .tokens-in {
        color: #059669;
    }

    .tokens-out {
        color: #dc2626;
    }

    .cost-cell {
        font-weight: 600;
        color: #7c3aed;
    }

    .license-cell {
        font-size: 12px;
        color: #64748b;
    }

    .timestamp-cell {
        font-size: 12px;
        color: #94a3b8;
    }

    .batch-badge {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 500;
        display: inline-block;
    }

    .batch-badge.setup {
        background: #dbeafe;
        color: #1e40af;
    }

    .batch-badge.cola {
        background: #fef3c7;
        color: #92400e;
    }

    .batch-badge.contenido {
        background: #dcfce7;
        color: #166534;
    }

    .loading-state {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
</style>

<div class="monitor-header">
    <h1>
        <span class="live-indicator"></span>
        Monitor en Tiempo Real
    </h1>
    <div class="monitor-status active" id="status">
        Actualizando cada 3s
    </div>
</div>

<!-- Metrics Cards -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-label">Requests (últimos 5min)</div>
        <div class="metric-value" id="metric-requests">-</div>
        <div class="metric-subvalue" id="metric-rpm">-</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Tokens Procesados</div>
        <div class="metric-value" id="metric-tokens">-</div>
        <div class="metric-subvalue" id="metric-tpm">-</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Coste Total (EUR)</div>
        <div class="metric-value" id="metric-cost">-</div>
        <div class="metric-subvalue" id="metric-cph">-</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Modelo Más Usado</div>
        <div class="metric-value" style="font-size: 16px; font-family: monospace;" id="metric-model">-</div>
        <div class="metric-subvalue" id="metric-licenses">-</div>
    </div>
</div>

<!-- Table -->
<div class="monitor-table">
    <div class="monitor-table-header">
        <h2>Operaciones Recientes</h2>
        <select id="time-range" class="time-selector">
            <option value="5">Últimos 5 min</option>
            <option value="10">Últimos 10 min</option>
            <option value="30">Últimos 30 min</option>
            <option value="60">Última hora</option>
        </select>
    </div>

    <div id="table-content">
        <div class="loading-state">
            Cargando datos...
        </div>
    </div>
</div>

<script>
    let pollingInterval = null;
    let currentMinutes = 5;

    // Iniciar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        fetchData();
        startPolling();

        // Listener para cambio de rango de tiempo
        document.getElementById('time-range').addEventListener('change', function(e) {
            currentMinutes = parseInt(e.target.value);
            fetchData();
        });
    });

    // Detener polling cuando se cierra/oculta la página
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    function startPolling() {
        if (pollingInterval) return;
        pollingInterval = setInterval(fetchData, 3000);
        updateStatus('Actualizando cada 3s', true);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
            updateStatus('Pausado', false);
        }
    }

    function updateStatus(text, active) {
        const statusEl = document.getElementById('status');
        statusEl.textContent = text;
        statusEl.className = 'monitor-status' + (active ? ' active' : '');
    }

    async function fetchData() {
        try {
            // Construir URL correctamente
            // Si estamos en /api_claude_5/admin/, necesitamos ir a /api_claude_5/?route=monitor/live
            const currentPath = window.location.pathname; // Ej: /api_claude_5/admin/
            const basePath = currentPath.replace(/\/admin.*$/, ''); // Ej: /api_claude_5
            const apiUrl = window.location.origin + basePath + '/?route=monitor/live&minutes=' + currentMinutes;

            console.log('Current path:', currentPath);
            console.log('Base path:', basePath);
            console.log('Fetching from:', apiUrl);

            const response = await fetch(apiUrl);
            const data = await response.json();

            console.log('Response:', data);

            if (data.success) {
                updateMetrics(data.data.metrics);
                updateTable(data.data.operations);
            } else {
                console.error('Error en respuesta:', data);
                updateStatus('Error: ' + (data.message || 'Unknown'), false);
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            updateStatus('Error de conexión', false);
        }
    }

    function updateMetrics(metrics) {
        document.getElementById('metric-requests').textContent = metrics.total_requests;
        document.getElementById('metric-rpm').textContent = metrics.requests_per_minute + ' req/min';

        document.getElementById('metric-tokens').textContent = formatNumber(metrics.total_tokens);
        document.getElementById('metric-tpm').textContent = formatNumber(metrics.tokens_per_minute) + ' tokens/min';

        document.getElementById('metric-cost').textContent = '€' + metrics.total_cost_eur.toFixed(4);
        document.getElementById('metric-cph').textContent = '€' + metrics.cost_per_hour_eur.toFixed(4) + '/hora';

        document.getElementById('metric-model').textContent = metrics.top_model;
        document.getElementById('metric-licenses').textContent = metrics.unique_licenses + ' licencias activas';
    }

    function updateTable(operations) {
        const tableContent = document.getElementById('table-content');

        if (operations.length === 0) {
            tableContent.innerHTML = `
                <div class="empty-state">
                    <p>No hay operaciones en los últimos ${currentMinutes} minutos</p>
                </div>
            `;
            return;
        }

        let html = `
            <table class="operations-table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Endpoint</th>
                        <th>Modelo</th>
                        <th>Tokens E/S</th>
                        <th>Coste E (EUR)</th>
                        <th>Coste S (EUR)</th>
                        <th>Coste Total (EUR)</th>
                        <th>Licencia</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
        `;

        operations.forEach(op => {
            const batchBadge = op.batch_type ?
                `<span class="batch-badge ${op.batch_type.toLowerCase()}">${op.batch_type}</span>` :
                '-';

            html += `
                <tr>
                    <td class="timestamp-cell">
                        ${formatTime(op.timestamp)}<br>
                        <small>${op.time_ago} ago</small>
                    </td>
                    <td><span class="endpoint-cell">${op.endpoint}</span></td>
                    <td><span class="model-badge">${op.model}</span></td>
                    <td class="tokens-cell">
                        <span class="tokens-in">${formatNumber(op.tokens.input)}</span> /
                        <span class="tokens-out">${formatNumber(op.tokens.output)}</span>
                    </td>
                    <td class="cost-cell">€${op.cost_eur.input.toFixed(4)}</td>
                    <td class="cost-cell">€${op.cost_eur.output.toFixed(4)}</td>
                    <td class="cost-cell">€${op.cost_eur.total.toFixed(4)}</td>
                    <td class="license-cell">
                        #${op.license.id}<br>
                        <small>${op.license.email}</small>
                    </td>
                    <td>${batchBadge}</td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        tableContent.innerHTML = html;
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
</script>
