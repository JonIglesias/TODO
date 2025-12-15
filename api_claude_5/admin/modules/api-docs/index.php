<?php
if (!defined('API_ACCESS')) exit;

// Definir todos los endpoints con sus especificaciones
$endpoints = [
    [
        'category' => 'Geowriter - Generación de Campañas',
        'endpoints' => [
            [
                'name' => 'Descripción de Empresa',
                'method' => 'POST',
                'path' => '/geowriter/descripcion-empresa',
                'description' => 'Genera una descripción de empresa analizando el sitio web mediante scraping inteligente. Analiza la página principal y hasta 2 páginas adicionales si es necesario.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'domain' => ['type' => 'string', 'required' => true, 'description' => 'Dominio del sitio web a analizar (ej: example.com)'],
                    'additional_info' => ['type' => 'string', 'required' => false, 'description' => 'Información adicional manual sobre la empresa']
                ],
                'response_success' => [
                    'success' => true,
                    'description' => 'Descripción de la empresa generada por IA basada en el análisis del sitio web',
                    'pages_analyzed' => ['https://example.com', 'https://example.com/servicios'],
                    'tokens_used' => 850
                ],
                'response_error' => [
                    'success' => false,
                    'error' => 'Domain is required',
                    'code' => 'VALIDATION_ERROR'
                ]
            ],
            [
                'name' => 'Generar Prompt de Títulos',
                'method' => 'POST',
                'path' => '/geowriter/prompt-titulos',
                'description' => 'Genera un prompt optimizado para la creación de títulos SEO basado en nicho, keywords y descripción de empresa.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'niche' => ['type' => 'string', 'required' => true, 'description' => 'Nicho o temática de la campaña'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción de la empresa'],
                    'keywords_seo' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO separadas por comas']
                ],
                'response_success' => [
                    'success' => true,
                    'prompt' => 'Prompt optimizado generado para títulos SEO...',
                    'tokens_used' => 320
                ]
            ],
            [
                'name' => 'Generar Prompt de Contenido',
                'method' => 'POST',
                'path' => '/geowriter/prompt-contenido',
                'description' => 'Genera un prompt optimizado para la creación de contenido basado en nicho, keywords y descripción de empresa.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'niche' => ['type' => 'string', 'required' => true, 'description' => 'Nicho o temática de la campaña'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción de la empresa'],
                    'keywords_seo' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO separadas por comas']
                ],
                'response_success' => [
                    'success' => true,
                    'prompt' => 'Prompt optimizado generado para contenido...',
                    'tokens_used' => 340
                ]
            ],
            [
                'name' => 'Keywords SEO',
                'method' => 'POST',
                'path' => '/geowriter/keywords-seo',
                'description' => 'Genera keywords SEO optimizadas para un nicho específico.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'niche' => ['type' => 'string', 'required' => true, 'description' => 'Nicho o temática'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción de la empresa para contexto']
                ],
                'response_success' => [
                    'success' => true,
                    'keywords' => 'keyword1, keyword2, keyword3, keyword4, keyword5',
                    'tokens_used' => 180
                ]
            ],
            [
                'name' => 'Keywords de Campaña',
                'method' => 'POST',
                'path' => '/geowriter/keywords-campana',
                'description' => 'Genera keywords específicas para búsqueda de imágenes en campañas.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'niche' => ['type' => 'string', 'required' => true, 'description' => 'Nicho o temática']
                ],
                'response_success' => [
                    'success' => true,
                    'keywords' => 'keyword_imagen1, keyword_imagen2, keyword_imagen3',
                    'tokens_used' => 120
                ]
            ],
            [
                'name' => 'Keywords para Imágenes',
                'method' => 'POST',
                'path' => '/geowriter/keywords-imagenes',
                'description' => 'Genera keywords optimizadas para búsqueda de imágenes en Pixabay basado en título del post.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'Título del post'],
                    'niche' => ['type' => 'string', 'required' => false, 'description' => 'Nicho para contexto adicional']
                ],
                'response_success' => [
                    'success' => true,
                    'keywords' => 'keyword_pixabay_1, keyword_pixabay_2',
                    'tokens_used' => 95
                ]
            ]
        ]
    ],
    [
        'category' => 'Geowriter - Generación de Contenido',
        'endpoints' => [
            [
                'name' => 'Generar Título Individual',
                'method' => 'POST',
                'path' => '/geowriter/generar-titulo',
                'description' => 'Genera un título SEO optimizado único. Incluye sistema anti-duplicados mediante Levenshtein y tracking de títulos previos por campaña.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'prompt' => ['type' => 'string', 'required' => false, 'description' => 'Prompt personalizado para generar el título'],
                    'topic' => ['type' => 'string', 'required' => false, 'description' => 'Tema del post (alternativo a prompt)'],
                    'domain' => ['type' => 'string', 'required' => false, 'description' => 'Dominio de la web'],
                    'company_description' => ['type' => 'string', 'required' => false, 'description' => 'Descripción de la empresa'],
                    'keywords_seo' => ['type' => 'array', 'required' => false, 'description' => 'Array de keywords SEO'],
                    'keywords' => ['type' => 'array', 'required' => false, 'description' => 'Array de keywords generales'],
                    'campaign_id' => ['type' => 'integer', 'required' => false, 'description' => 'ID de campaña para tracking y anti-duplicados']
                ],
                'response_success' => [
                    'success' => true,
                    'title' => 'Título SEO generado optimizado para posicionamiento',
                    'tokens_used' => 145,
                    'campaign_id' => 23,
                    'attempt_number' => 1,
                    'similarity_score' => 0
                ],
                'notes' => 'Nota: Si se proporciona campaign_id, el sistema automáticamente evita títulos similares a los ya generados en esa campaña (umbral: 75% similitud).'
            ],
            [
                'name' => 'Generar Contenido de Post',
                'method' => 'POST',
                'path' => '/geowriter/generar-contenido',
                'description' => 'Genera el contenido HTML completo para un post basado en título y contexto.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'Título del post'],
                    'prompt' => ['type' => 'string', 'required' => false, 'description' => 'Prompt personalizado'],
                    'keywords' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO'],
                    'min_words' => ['type' => 'integer', 'required' => false, 'description' => 'Mínimo de palabras (default: 800)'],
                    'max_words' => ['type' => 'integer', 'required' => false, 'description' => 'Máximo de palabras (default: 1200)']
                ],
                'response_success' => [
                    'success' => true,
                    'content' => '<p>Contenido HTML generado...</p>',
                    'usage' => [
                        'total_tokens' => 2350,
                        'prompt_tokens' => 450,
                        'completion_tokens' => 1900
                    ]
                ]
            ],
            [
                'name' => 'Generar Post Completo',
                'method' => 'POST',
                'path' => '/geowriter/post-completo',
                'description' => 'Genera un post completo con título y contenido en una sola llamada.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'topic' => ['type' => 'string', 'required' => true, 'description' => 'Tema del post'],
                    'niche' => ['type' => 'string', 'required' => false, 'description' => 'Nicho o industria'],
                    'keywords' => ['type' => 'string', 'required' => false, 'description' => 'Keywords SEO']
                ],
                'response_success' => [
                    'success' => true,
                    'title' => 'Título generado',
                    'content' => '<p>Contenido HTML completo...</p>',
                    'usage' => [
                        'total_tokens' => 2680
                    ]
                ]
            ]
        ]
    ],
    [
        'category' => 'Licencias y Autenticación',
        'endpoints' => [
            [
                'name' => 'Verificar Licencia',
                'method' => 'POST',
                'path' => '/verify',
                'description' => 'Verifica la validez de una licencia y dominio.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia'],
                    'domain' => ['type' => 'string', 'required' => true, 'description' => 'Dominio a validar']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'valid' => true,
                        'license' => [
                            'key' => 'DEMO-PRO-2025-ABCD1234',
                            'status' => 'active',
                            'plan_id' => 'pro',
                            'plan_name' => 'Plan Pro',
                            'expires_at' => '2025-12-05',
                            'tokens_available' => 45000
                        ]
                    ]
                ],
                'response_error' => [
                    'success' => false,
                    'error' => 'License key is required',
                    'code' => 'VALIDATION_ERROR'
                ]
            ],
            [
                'name' => 'Obtener Plan Activo',
                'method' => 'GET',
                'path' => '/get-active-plan?license_key=XXX',
                'description' => 'Obtiene información del plan activo de una licencia.',
                'headers' => [],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia (query param)']
                ],
                'response_success' => [
                    'success' => true,
                    'plan' => [
                        'name' => 'Plan Pro',
                        'tokens_limit' => 200000,
                        'billing_cycle' => 'monthly'
                    ]
                ]
            ]
        ]
    ],
    [
        'category' => 'Estadísticas y Uso',
        'endpoints' => [
            [
                'name' => 'Obtener Uso de Licencia',
                'method' => 'GET',
                'path' => '/usage?license_key=XXX',
                'description' => 'Obtiene información de uso y límites de tokens de una licencia.',
                'headers' => [],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia (query param)']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'plan_name' => 'Plan Pro',
                        'tokens_limit' => 200000,
                        'tokens_used' => 155000,
                        'tokens_remaining' => 45000,
                        'period_ends_at' => '2025-12-05'
                    ]
                ]
            ],
            [
                'name' => 'Obtener Estadísticas Detalladas',
                'method' => 'POST',
                'path' => '/get-stats',
                'description' => 'Obtiene estadísticas detalladas de uso por tipo de operación.',
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'params' => [
                    'license_key' => ['type' => 'string', 'required' => true, 'description' => 'Clave de licencia']
                ],
                'response_success' => [
                    'success' => true,
                    'data' => [
                        'total_operations' => 150,
                        'total_tokens' => 155000,
                        'by_operation' => [
                            'content' => ['count' => 50, 'tokens' => 100000],
                            'title' => ['count' => 80, 'tokens' => 40000],
                            'keywords_seo' => ['count' => 20, 'tokens' => 15000]
                        ]
                    ]
                ]
            ]
        ]
    ]
];
?>

<style>
.api-docs-wrapper {
    max-width: 1400px;
}

.api-category {
    background: white;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.category-header {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #ecf0f1;
}

.endpoint-card {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 16px;
    border-left: 4px solid #3498db;
}

.endpoint-card:last-child {
    margin-bottom: 0;
}

.endpoint-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.method-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
}

.method-post {
    background: #2ecc71;
    color: white;
}

.method-get {
    background: #3498db;
    color: white;
}

.endpoint-name {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.endpoint-path {
    background: #34495e;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    margin-bottom: 12px;
    display: inline-block;
}

.endpoint-description {
    color: #7f8c8d;
    margin-bottom: 16px;
    line-height: 1.6;
}

.endpoint-notes {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 12px;
    margin-top: 16px;
    border-radius: 4px;
    font-size: 13px;
    color: #856404;
}

.params-section, .response-section {
    margin-top: 16px;
}

.section-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 14px;
}

.params-table {
    width: 100%;
    background: white;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ecf0f1;
}

.params-table th {
    background: #ecf0f1;
    padding: 10px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
}

.params-table td {
    padding: 10px;
    border-top: 1px solid #ecf0f1;
    font-size: 13px;
}

.required-badge {
    background: #e74c3c;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.optional-badge {
    background: #95a5a6;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.code-block {
    background: #2c3e50;
    color: #ecf0f1;
    padding: 16px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin-top: 8px;
}

.code-block pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.toggle-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-left: 8px;
}

.toggle-btn:hover {
    background: #2980b9;
}

.response-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.response-tab {
    padding: 6px 16px;
    background: #ecf0f1;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
}

.response-tab.active {
    background: #3498db;
    color: white;
}

.response-content {
    display: none;
}

.response-content.active {
    display: block;
}

.search-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #ecf0f1;
    border-radius: 6px;
    font-size: 15px;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
}

.info-box {
    background: #e8f4f8;
    border-left: 4px solid #3498db;
    padding: 16px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info-box h3 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 16px;
}

.info-box p {
    margin: 0;
    color: #5a6c7d;
    line-height: 1.6;
    font-size: 14px;
}

.info-box ul {
    margin: 8px 0;
    padding-left: 20px;
}

.info-box li {
    color: #5a6c7d;
    line-height: 1.8;
}
</style>

<div class="api-docs-wrapper">
    <div class="search-box">
        <input type="text"
               class="search-input"
               id="endpoint-search"
               placeholder="Buscar endpoint por nombre o ruta...">
    </div>

    <div class="info-box">
        <h3>Información General</h3>
        <p><strong>Base URL:</strong> <?= htmlspecialchars(API_BASE_URL) ?></p>
        <p><strong>Autenticación:</strong> Todos los endpoints requieren un parámetro <code>license_key</code> válido.</p>
        <p><strong>Formato de respuesta:</strong> JSON</p>
        <p><strong>Modelo de IA configurado:</strong> Los endpoints de Geowriter utilizan el modelo configurado en la base de datos (setting_key: geowrite_ai_model).</p>
    </div>

    <?php foreach ($endpoints as $category): ?>
    <div class="api-category">
        <h2 class="category-header"><?= htmlspecialchars($category['category']) ?></h2>

        <?php foreach ($category['endpoints'] as $endpoint): ?>
        <div class="endpoint-card" data-search="<?= strtolower($endpoint['name'] . ' ' . $endpoint['path']) ?>">
            <div class="endpoint-header">
                <span class="method-badge method-<?= strtolower($endpoint['method']) ?>">
                    <?= $endpoint['method'] ?>
                </span>
                <span class="endpoint-name"><?= htmlspecialchars($endpoint['name']) ?></span>
            </div>

            <code class="endpoint-path"><?= htmlspecialchars(API_BASE_URL . $endpoint['path']) ?></code>

            <p class="endpoint-description"><?= htmlspecialchars($endpoint['description']) ?></p>

            <?php if (isset($endpoint['notes'])): ?>
            <div class="endpoint-notes">
                <?= htmlspecialchars($endpoint['notes']) ?>
            </div>
            <?php endif; ?>

            <!-- Headers -->
            <?php if (!empty($endpoint['headers'])): ?>
            <div class="params-section">
                <div class="section-title">Headers</div>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoint['headers'] as $header => $value): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($header) ?></code></td>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Parámetros -->
            <?php if (!empty($endpoint['params'])): ?>
            <div class="params-section">
                <div class="section-title">Parámetros</div>
                <table class="params-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Requerido</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoint['params'] as $param => $details): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($param) ?></code></td>
                            <td><?= htmlspecialchars($details['type']) ?></td>
                            <td>
                                <?php if ($details['required']): ?>
                                    <span class="required-badge">REQUERIDO</span>
                                <?php else: ?>
                                    <span class="optional-badge">OPCIONAL</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($details['description']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Respuesta -->
            <div class="response-section">
                <div class="section-title">Ejemplo de Respuesta</div>

                <div class="response-tabs">
                    <button class="response-tab active" onclick="showResponse(this, 'success')">Success</button>
                    <?php if (isset($endpoint['response_error'])): ?>
                    <button class="response-tab" onclick="showResponse(this, 'error')">Error</button>
                    <?php endif; ?>
                </div>

                <div class="response-content active" data-type="success">
                    <div class="code-block">
                        <pre><?= json_encode($endpoint['response_success'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </div>
                </div>

                <?php if (isset($endpoint['response_error'])): ?>
                <div class="response-content" data-type="error">
                    <div class="code-block">
                        <pre><?= json_encode($endpoint['response_error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ejemplo de petición cURL -->
            <div class="params-section" style="margin-top: 16px;">
                <div class="section-title">Ejemplo cURL</div>
                <div class="code-block">
                    <pre><?= generateCurlExample($endpoint) ?></pre>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
// Búsqueda de endpoints
document.getElementById('endpoint-search').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.endpoint-card');

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search');
        if (searchText.includes(search)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Cambiar entre respuestas success/error
function showResponse(button, type) {
    const parent = button.closest('.response-section');

    // Actualizar tabs
    parent.querySelectorAll('.response-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    button.classList.add('active');

    // Actualizar contenido
    parent.querySelectorAll('.response-content').forEach(content => {
        content.classList.remove('active');
        if (content.getAttribute('data-type') === type) {
            content.classList.add('active');
        }
    });
}
</script>

<?php
function generateCurlExample($endpoint) {
    $curl = "curl -X {$endpoint['method']} '" . API_BASE_URL . "{$endpoint['path']}'";

    // Headers
    if (!empty($endpoint['headers'])) {
        foreach ($endpoint['headers'] as $header => $value) {
            $curl .= " \\\n  -H '{$header}: {$value}'";
        }
    }

    // Body (solo para POST)
    if ($endpoint['method'] === 'POST' && !empty($endpoint['params'])) {
        $body = [];
        foreach ($endpoint['params'] as $param => $details) {
            if ($details['type'] === 'array') {
                $body[$param] = ['ejemplo1', 'ejemplo2'];
            } else {
                $body[$param] = 'valor_ejemplo';
            }
        }
        $curl .= " \\\n  -d '" . json_encode($body, JSON_UNESCAPED_UNICODE) . "'";
    }

    return $curl;
}
?>
