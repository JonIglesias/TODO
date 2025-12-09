<?php
/**
 * Settings Module
 */

// Helper function para sanitización - DEBE estar ANTES de usarse
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
    }
}

$success = '';
$error = '';

// Obtener settings actuales de BD
try {
    $db = Database::getInstance();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Guardar OpenAI API Key en BD
        $apiKey = $_POST['openai_api_key'] ?? '';

        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value, setting_type)
                              VALUES ('openai_api_key', ?, 'string')
                              ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$apiKey, $apiKey]);

        // Guardar configuración de IA para GeoWrite
        $geoSettings = [
            'geowrite_ai_model' => $_POST['geowrite_ai_model'] ?? 'gpt-4o',
            'geowrite_ai_temperature' => floatval($_POST['geowrite_ai_temperature'] ?? 0.7),
            'geowrite_ai_max_tokens' => intval($_POST['geowrite_ai_max_tokens'] ?? 2000),
            'geowrite_ai_tone' => sanitize_text_field($_POST['geowrite_ai_tone'] ?? 'profesional')
        ];

        // Guardar configuración de IA para Chatbot (BOT)
        $botSettings = [
            'bot_ai_model' => $_POST['bot_ai_model'] ?? 'gpt-4o',
            'bot_ai_temperature' => floatval($_POST['bot_ai_temperature'] ?? 0.7),
            'bot_ai_max_tokens' => intval($_POST['bot_ai_max_tokens'] ?? 1000),
            'bot_ai_tone' => sanitize_text_field($_POST['bot_ai_tone'] ?? 'profesional'),
            'bot_ai_max_history' => intval($_POST['bot_ai_max_history'] ?? 10)
        ];

        // Guardar configuración de IA para Knowledge Base (BOT KB)
        $botKBSettings = [
            'bot_kb_ai_model' => $_POST['bot_kb_ai_model'] ?? 'gpt-4o'
        ];

        // Validaciones GeoWrite
        if ($geoSettings['geowrite_ai_temperature'] < 0) $geoSettings['geowrite_ai_temperature'] = 0;
        if ($geoSettings['geowrite_ai_temperature'] > 2) $geoSettings['geowrite_ai_temperature'] = 2;
        if ($geoSettings['geowrite_ai_max_tokens'] < 100) $geoSettings['geowrite_ai_max_tokens'] = 100;
        if ($geoSettings['geowrite_ai_max_tokens'] > 8000) $geoSettings['geowrite_ai_max_tokens'] = 8000;

        // Validaciones BOT
        if ($botSettings['bot_ai_temperature'] < 0) $botSettings['bot_ai_temperature'] = 0;
        if ($botSettings['bot_ai_temperature'] > 2) $botSettings['bot_ai_temperature'] = 2;
        if ($botSettings['bot_ai_max_tokens'] < 100) $botSettings['bot_ai_max_tokens'] = 100;
        if ($botSettings['bot_ai_max_tokens'] > 4000) $botSettings['bot_ai_max_tokens'] = 4000;
        if ($botSettings['bot_ai_max_history'] < 1) $botSettings['bot_ai_max_history'] = 1;
        if ($botSettings['bot_ai_max_history'] > 50) $botSettings['bot_ai_max_history'] = 50;

        // Guardar todos los settings de IA
        $allAISettings = array_merge($geoSettings, $botSettings, $botKBSettings);
        foreach ($allAISettings as $key => $value) {
            $type = is_numeric($value) ? (is_float($value) ? 'float' : 'integer') : 'string';
            $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value, setting_type)
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?");
            $stmt->execute([$key, $value, $type, $value, $type]);
        }
        
        // Actualizar config.php con WooCommerce settings
        $configPath = __DIR__ . '/../../../config.php';
        
        if (is_writable($configPath)) {
            $configContent = file_get_contents($configPath);
            
            // Reemplazar WooCommerce API URL
            $configContent = preg_replace(
                "/define\('WC_API_URL',\s*'[^']*'\);/",
                "define('WC_API_URL', '" . addslashes($_POST['wc_api_url']) . "');",
                $configContent
            );
            
            // Reemplazar Consumer Key
            $configContent = preg_replace(
                "/define\('WC_CONSUMER_KEY',\s*'[^']*'\);/",
                "define('WC_CONSUMER_KEY', '" . addslashes($_POST['wc_consumer_key']) . "');",
                $configContent
            );
            
            // Reemplazar Consumer Secret
            $configContent = preg_replace(
                "/define\('WC_CONSUMER_SECRET',\s*'[^']*'\);/",
                "define('WC_CONSUMER_SECRET', '" . addslashes($_POST['wc_consumer_secret']) . "');",
                $configContent
            );
            
            file_put_contents($configPath, $configContent);
        }

        // No hacer redirect, mostrar mensaje de éxito directamente
        $success = '✅ Configuración guardada correctamente.';
    }

    // Leer todos los settings de BD
    $stmt = $db->prepare("SELECT setting_key, setting_value, setting_type FROM " . DB_PREFIX . "settings WHERE setting_key IN (
        'openai_api_key',
        'geowrite_ai_model', 'geowrite_ai_temperature', 'geowrite_ai_max_tokens', 'geowrite_ai_tone',
        'bot_ai_model', 'bot_ai_temperature', 'bot_ai_max_tokens', 'bot_ai_tone', 'bot_ai_max_history',
        'bot_kb_ai_model'
    )");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convertir a array asociativo
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Valores por defecto
    $openaiKey = $settings['openai_api_key'] ?? '';

    // GeoWrite defaults (asegurar que sean strings para comparación en HTML)
    $geowrite_ai_model = isset($settings['geowrite_ai_model']) ? (string)$settings['geowrite_ai_model'] : 'gpt-4o';
    $geowrite_ai_temperature = isset($settings['geowrite_ai_temperature']) ? $settings['geowrite_ai_temperature'] : '0.7';
    $geowrite_ai_max_tokens = isset($settings['geowrite_ai_max_tokens']) ? $settings['geowrite_ai_max_tokens'] : '2000';
    $geowrite_ai_tone = isset($settings['geowrite_ai_tone']) ? $settings['geowrite_ai_tone'] : 'profesional';

    // BOT defaults (asegurar que sean strings para comparación en HTML)
    $bot_ai_model = isset($settings['bot_ai_model']) ? (string)$settings['bot_ai_model'] : 'gpt-4o';
    $bot_ai_temperature = isset($settings['bot_ai_temperature']) ? $settings['bot_ai_temperature'] : '0.7';
    $bot_ai_max_tokens = isset($settings['bot_ai_max_tokens']) ? $settings['bot_ai_max_tokens'] : '1000';
    $bot_ai_tone = isset($settings['bot_ai_tone']) ? $settings['bot_ai_tone'] : 'profesional';
    $bot_ai_max_history = isset($settings['bot_ai_max_history']) ? $settings['bot_ai_max_history'] : '10';

    // BOT KB defaults
    $bot_kb_ai_model = isset($settings['bot_kb_ai_model']) ? (string)$settings['bot_kb_ai_model'] : 'gpt-4o';

    // Obtener lista de modelos disponibles
    $stmt = $db->prepare("SELECT DISTINCT model_name FROM " . DB_PREFIX . "model_prices WHERE is_active = 1 ORDER BY model_name");
    $stmt->execute();
    $availableModels = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Si no hay modelos en BD, usar lista por defecto y avisar
    if (empty($availableModels)) {
        $availableModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'];
        if (!$error) {
            $error = '⚠️ No hay modelos en la base de datos. <a href="../setup-default-models.php" target="_blank">Ejecuta el script de setup</a> o ve a <a href="?module=models">Modelos OpenAI</a> para agregar modelos.';
        }
    }

} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $openaiKey = '';
    $geowrite_ai_model = 'gpt-4o';
    $geowrite_ai_temperature = '0.7';
    $geowrite_ai_max_tokens = '2000';
    $geowrite_ai_tone = 'profesional';
    $bot_ai_model = 'gpt-4o';
    $bot_ai_temperature = '0.7';
    $bot_ai_max_tokens = '1000';
    $bot_ai_tone = 'profesional';
    $bot_ai_max_history = '10';
    $bot_kb_ai_model = 'gpt-4o';
    $availableModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-5', 'gpt-5-mini'];
}
?>

<style>
.settings-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.settings-card h3 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
}
.info-box {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 20px;
}
.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 20px;
}
.success-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}
.form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}
.form-group small {
    color: #6c757d;
    font-size: 12px;
    display: block;
    margin-top: 5px;
}
.btn {
    padding: 10px 20px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.btn-primary {
    background: #007bff;
    color: white;
}
.btn-primary:hover {
    background: #0056b3;
}
.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.key-preview {
    font-family: monospace;
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    word-break: break-all;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <!-- OpenAI Configuration -->
    <div class="settings-card">
        <h3>🤖 OpenAI API</h3>
        
        <div class="info-box">
            <strong>ℹ️ Información:</strong> La API Key se guarda de forma segura en la base de datos.<br>
            Obtén tu API Key en: <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
        </div>
        
        <?php if ($openaiKey): ?>
        <div class="success-box">
            <strong>✅ API Key Configurada</strong>
            <div class="key-preview" style="margin-top: 10px;">
                <?= htmlspecialchars(substr($openaiKey, 0, 10)) ?>...<?= htmlspecialchars(substr($openaiKey, -10)) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label>OpenAI API Key *</label>
            <input type="text"
                   name="openai_api_key"
                   value="<?= htmlspecialchars($openaiKey) ?>"
                   placeholder="sk-..."
                   required>
            <small>Formato: sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</small>
        </div>
    </div>

    <!-- WooCommerce Configuration -->
    <div class="settings-card">
        <h3>🛒 WooCommerce API</h3>
        
        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Obtén las credenciales en WooCommerce → Settings → Advanced → REST API
        </div>
        
        <div class="form-group">
            <label>WooCommerce API URL *</label>
            <input type="url" 
                   name="wc_api_url" 
                   value="<?= htmlspecialchars(WC_API_URL) ?>" 
                   required>
            <small>Ejemplo: https://tu-tienda.com/wp-json/wc/v3/</small>
        </div>
        
        <div class="form-group">
            <label>Consumer Key *</label>
            <input type="text" 
                   name="wc_consumer_key" 
                   value="<?= htmlspecialchars(WC_CONSUMER_KEY) ?>" 
                   required>
            <small>Empieza con: ck_</small>
        </div>
        
        <div class="form-group">
            <label>Consumer Secret *</label>
            <input type="text"
                   name="wc_consumer_secret"
                   value="<?= htmlspecialchars(WC_CONSUMER_SECRET) ?>"
                   required>
            <small>Empieza con: cs_</small>
        </div>
    </div>

    <!-- AI Configuration for GeoWrite -->
    <div class="settings-card">
        <h3>📝 Configuración de IA - GeoWriter</h3>

        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Esta configuración afecta a <strong>todos los sitios con licencia GEO</strong>. Los cambios son inmediatos.
        </div>

        <div class="form-group">
            <label>Modelo de IA *</label>
            <select name="geowrite_ai_model" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                <?php foreach ($availableModels as $model): ?>
                    <option value="<?= htmlspecialchars($model) ?>" <?= $model === $geowrite_ai_model ? 'selected' : '' ?>>
                        <?= htmlspecialchars($model) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>El modelo determina la calidad y costo de los artículos generados.</small>
        </div>

        <div class="form-group">
            <label>Temperatura (Creatividad)</label>
            <input type="number" name="geowrite_ai_temperature" value="<?= htmlspecialchars($geowrite_ai_temperature) ?>" step="0.1" min="0" max="2" required>
            <small>Entre 0 (más preciso) y 2 (más creativo). Recomendado: 0.7</small>
        </div>

        <div class="form-group">
            <label>Máximo de Tokens</label>
            <input type="number" name="geowrite_ai_max_tokens" value="<?= htmlspecialchars($geowrite_ai_max_tokens) ?>" step="100" min="100" max="8000" required>
            <small>Límite de tokens por artículo. Recomendado: 2000</small>
        </div>

        <div class="form-group">
            <label>Tono de Escritura</label>
            <input type="text" name="geowrite_ai_tone" value="<?= htmlspecialchars($geowrite_ai_tone) ?>" placeholder="profesional">
            <small>Ej: profesional, informativo, persuasivo, técnico</small>
        </div>
    </div>

    <!-- AI Configuration for Chatbot (BOT) -->
    <div class="settings-card">
        <h3>🤖 Configuración de IA - Chatbot (BOT)</h3>

        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Esta configuración afecta a <strong>todos los sitios con licencia BOT</strong>. Los cambios son inmediatos.
        </div>

        <div class="form-group">
            <label>Modelo de IA *</label>
            <select name="bot_ai_model" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                <?php foreach ($availableModels as $model): ?>
                    <option value="<?= htmlspecialchars($model) ?>" <?= $model === $bot_ai_model ? 'selected' : '' ?>>
                        <?= htmlspecialchars($model) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>El modelo determina la calidad y costo de las conversaciones del chatbot.</small>
        </div>

        <div class="form-group">
            <label>Temperatura (Creatividad)</label>
            <input type="number" name="bot_ai_temperature" value="<?= htmlspecialchars($bot_ai_temperature) ?>" step="0.1" min="0" max="2" required>
            <small>Entre 0 (más preciso) y 2 (más creativo). Recomendado: 0.7</small>
        </div>

        <div class="form-group">
            <label>Máximo de Tokens por Respuesta</label>
            <input type="number" name="bot_ai_max_tokens" value="<?= htmlspecialchars($bot_ai_max_tokens) ?>" step="50" min="100" max="4000" required>
            <small>Límite de tokens por respuesta del chatbot. Recomendado: 1000</small>
        </div>

        <div class="form-group">
            <label>Tono de Respuestas</label>
            <input type="text" name="bot_ai_tone" value="<?= htmlspecialchars($bot_ai_tone) ?>" placeholder="profesional">
            <small>Ej: profesional, amigable, técnico, casual</small>
        </div>

        <div class="form-group">
            <label>Mensajes de Historial</label>
            <input type="number" name="bot_ai_max_history" value="<?= htmlspecialchars($bot_ai_max_history) ?>" min="1" max="50" required>
            <small>Número de mensajes previos a incluir en el contexto. Recomendado: 10</small>
        </div>
    </div>

    <!-- AI Configuration for Knowledge Base (BOT KB) -->
    <div class="settings-card">
        <h3>📚 Configuración de IA - Knowledge Base (BOT KB)</h3>

        <div class="info-box">
            <strong>ℹ️ Información:</strong> Esta configuración es <strong>exclusiva para la generación de Knowledge Base</strong> del plugin BOT.<br>
            La KB generation suele requerir modelos más potentes debido a la complejidad y longitud del contenido generado.
        </div>

        <div class="warning-box">
            <strong>⚠️ Importante:</strong> Esta configuración afecta <strong>solo al endpoint generate-kb</strong>. El chatbot usa la configuración anterior.
        </div>

        <div class="form-group">
            <label>Modelo de IA para Knowledge Base *</label>
            <select name="bot_kb_ai_model" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                <?php foreach ($availableModels as $model): ?>
                    <option value="<?= htmlspecialchars($model) ?>" <?= $model === $bot_kb_ai_model ? 'selected' : '' ?>>
                        <?= htmlspecialchars($model) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Recomendado: gpt-4o o superior. La KB generation puede consumir 8000+ tokens por operación.</small>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">💾 Guardar Configuración</button>
</form>

<div class="info-box" style="margin-top: 20px;">
    <strong>ℹ️ Nota sobre modelos de IA:</strong><br>
    Los modelos listados provienen de la tabla <code>api_model_prices</code> con <code>is_active = 1</code>.<br>
    Los cambios en la configuración de IA afectan el costo y calidad de las operaciones. Modelos más avanzados (GPT-5, GPT-4.1) son más costosos pero ofrecen mejor calidad.
</div>
