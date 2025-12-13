<?php
/**
 * Diagnóstico Completo de Notificaciones Telegram - PHSBOT
 *
 * Ejecutar desde: https://tu-sitio.com/wp-content/plugins/phsbot/diagnostico-notificaciones.php
 */

// Cargar WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('No se puede cargar WordPress. Asegúrate de que el archivo está en /wp-content/plugins/phsbot/');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('No tienes permisos para ejecutar este script.');
}

header('Content-Type: text/html; charset=utf-8');

// Constantes necesarias
if (!defined('PHSBOT_MAIN_SETTINGS_OPT')) {
    define('PHSBOT_MAIN_SETTINGS_OPT', 'phsbot_settings');
}
if (!defined('PHSBOT_LEADS_SETTINGS_OPT')) {
    define('PHSBOT_LEADS_SETTINGS_OPT', 'phsbot_leads_settings');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Notificaciones - PHSBOT</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; background: #f0f0f0; font-size: 14px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-top: 0; }
        h2 { color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-top: 30px; }
        h3 { color: #555; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        .code { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ddd; }
        th { background: #e9ecef; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-yes { background: #28a745; color: white; }
        .badge-no { background: #dc3545; color: white; }
        .badge-partial { background: #ffc107; color: #000; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico Completo de Notificaciones Telegram</h1>

<?php

// ============================================
// 1. CONFIGURACIÓN GENERAL
// ============================================
echo '<h2>1️⃣ Configuración General</h2>';

$main_settings = get_option(PHSBOT_MAIN_SETTINGS_OPT, array());
$leads_settings = get_option(PHSBOT_LEADS_SETTINGS_OPT, array());

$token = isset($main_settings['telegram_bot_token']) ? trim($main_settings['telegram_bot_token']) : '';
$chat_id = isset($main_settings['telegram_chat_id']) ? trim($main_settings['telegram_chat_id']) : '';

// Threshold con defaults
$threshold_raw = isset($leads_settings['telegram_threshold']) ? $leads_settings['telegram_threshold'] : null;
$threshold = $threshold_raw !== null ? floatval($threshold_raw) : 8.0;

echo '<table>';
echo '<tr><th>Parámetro</th><th>Valor</th><th>Estado</th></tr>';
echo '<tr>';
echo '<td><strong>Token del Bot</strong></td>';
echo '<td><code>' . ($token ? substr($token, 0, 25) . '...' : '(vacío)') . '</code></td>';
echo '<td>' . ($token ? '<span class="badge badge-yes">✓ OK</span>' : '<span class="badge badge-no">✗ FALTA</span>') . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td><strong>Chat ID</strong></td>';
echo '<td><code>' . ($chat_id ?: '(vacío)') . '</code></td>';
echo '<td>' . ($chat_id ? '<span class="badge badge-yes">✓ OK</span>' : '<span class="badge badge-no">✗ FALTA</span>') . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td><strong>Umbral de Telegram</strong></td>';
echo '<td><code>' . $threshold . '</code></td>';
echo '<td>';
if ($threshold_raw === null) {
    echo '<span class="badge badge-partial">DEFAULT (8.0)</span>';
} elseif ($threshold <= 1) {
    echo '<span class="badge badge-yes">✓ BAJO (' . $threshold . ')</span>';
} else {
    echo '<span class="badge badge-partial">ALTO (' . $threshold . ')</span>';
}
echo '</td>';
echo '</tr>';
echo '</table>';

if ($threshold_raw === null || $threshold > 1) {
    echo '<div class="warning">';
    echo '<strong>⚠️ Umbral no configurado o muy alto</strong><br>';
    echo 'El umbral actual es <strong>' . $threshold . '</strong>. Solo se enviarán notificaciones para leads con score ≥ ' . $threshold . '.<br>';
    echo '<strong>Solución:</strong> Ve a <strong>PHSBOT → Leads → Settings</strong> y cambia el umbral a <strong>1.0</strong> o menos.';
    echo '</div>';
}

echo '<div class="info">';
echo '<strong>Configuración almacenada en WordPress:</strong>';
echo '</div>';
echo '<div class="code">';
echo "PHSBOT_MAIN_SETTINGS_OPT: " . PHSBOT_MAIN_SETTINGS_OPT . "\n";
echo "PHSBOT_LEADS_SETTINGS_OPT: " . PHSBOT_LEADS_SETTINGS_OPT . "\n\n";
echo "Leads Settings guardados:\n";
echo htmlspecialchars(json_encode($leads_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo '</div>';


// ============================================
// 2. LÓGICA DE NOTIFICACIÓN
// ============================================
echo '<h2>2️⃣ Lógica de Notificación</h2>';

echo '<div class="section">';
echo '<h3>Condiciones para enviar notificación a Telegram:</h3>';
echo '<table>';
echo '<tr><th>Condición</th><th>Descripción</th></tr>';
echo '<tr><td><strong>1. Ya existe telegram_msg_id</strong></td><td>Si ya se envió, se EDITA el mensaje (no duplica)</td></tr>';
echo '<tr><td><strong>2. Lead tiene teléfono</strong></td><td>Si hay <code>phone</code> → <strong>SIEMPRE notifica</strong> (sin importar score)</td></tr>';
echo '<tr><td><strong>3. Lead tiene email + score alto</strong></td><td>Si hay <code>email</code> Y <code>score >= ' . $threshold . '</code> → notifica</td></tr>';
echo '</table>';

echo '<div class="warning">';
echo '<strong>📌 IMPORTANTE:</strong><br>';
echo 'Si tu lead solo tiene email pero NO tiene teléfono, necesitas que su <strong>score sea ≥ ' . $threshold . '</strong><br>';
echo 'Si tu lead tiene teléfono, se notifica <strong>SIEMPRE</strong> (sin importar el score).';
echo '</div>';
echo '</div>';


// ============================================
// 3. ANÁLISIS DE LEADS RECIENTES
// ============================================
echo '<h2>3️⃣ Análisis de Leads Recientes</h2>';

global $wpdb;
$table = $wpdb->prefix . 'phsbot_leads';

// Verificar si existe la tabla
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;

if (!$table_exists) {
    echo '<div class="error">';
    echo '<strong>❌ Tabla de leads no existe</strong><br>';
    echo 'La tabla <code>' . $table . '</code> no existe en la base de datos.<br>';
    echo 'Asegúrate de que el plugin PHSBOT está correctamente instalado y activado.';
    echo '</div>';
} else {
    $leads = $wpdb->get_results("SELECT * FROM {$table} ORDER BY last_seen DESC LIMIT 10", ARRAY_A);

    if (empty($leads)) {
        echo '<div class="warning">';
        echo '<strong>⚠️ No hay leads en la base de datos</strong><br>';
        echo 'No se han registrado conversaciones aún. Inicia una conversación con el chatbot para generar un lead.';
        echo '</div>';
    } else {
        echo '<p>Mostrando los <strong>' . count($leads) . '</strong> leads más recientes:</p>';

        echo '<table>';
        echo '<tr>';
        echo '<th>CID</th>';
        echo '<th>Email</th>';
        echo '<th>Teléfono</th>';
        echo '<th>Score</th>';
        echo '<th>Msgs</th>';
        echo '<th>Telegram ID</th>';
        echo '<th>¿Debería notificar?</th>';
        echo '</tr>';

        foreach ($leads as $lead) {
            // Deserializar datos
            if (!empty($lead['messages']) && is_string($lead['messages'])) {
                $lead['messages'] = maybe_unserialize($lead['messages']);
            }
            if (!empty($lead['notified']) && is_string($lead['notified'])) {
                $lead['notified'] = maybe_unserialize($lead['notified']);
            }

            $cid = $lead['cid'];
            $email = !empty($lead['email']) ? $lead['email'] : '';
            $phone = !empty($lead['phone']) ? $lead['phone'] : '';
            $score = isset($lead['score']) && $lead['score'] !== null ? floatval($lead['score']) : null;
            $msg_count = is_array($lead['messages']) ? count($lead['messages']) : 0;
            $telegram_msg_id = !empty($lead['telegram_msg_id']) ? $lead['telegram_msg_id'] : '';

            // Evaluar si debería notificar según la lógica actual
            $should_notify = false;
            $reason = '';

            if (!empty($telegram_msg_id)) {
                $should_notify = true;
                $reason = 'Ya existe telegram_msg_id (editaría mensaje)';
            } elseif (!empty($phone)) {
                $should_notify = true;
                $reason = 'Tiene teléfono (siempre notifica)';
            } elseif (!empty($email) && $score !== null && $score >= $threshold) {
                $should_notify = true;
                $reason = "Tiene email + score ({$score}) >= threshold ({$threshold})";
            } else {
                $reason = 'NO cumple condiciones';
                if (empty($email) && empty($phone)) {
                    $reason .= ' (sin email ni teléfono)';
                } elseif (!empty($email) && ($score === null || $score < $threshold)) {
                    $reason .= " (score {$score} < {$threshold})";
                }
            }

            echo '<tr>';
            echo '<td><code>' . esc_html(substr($cid, 0, 8)) . '...</code></td>';
            echo '<td>' . ($email ? '<code>' . esc_html($email) . '</code>' : '<em>—</em>') . '</td>';
            echo '<td>' . ($phone ? '<code>' . esc_html($phone) . '</code>' : '<em>—</em>') . '</td>';
            echo '<td>' . ($score !== null ? '<strong>' . $score . '</strong>' : '<em>—</em>') . '</td>';
            echo '<td>' . $msg_count . '</td>';
            echo '<td>' . ($telegram_msg_id ? '<code>' . esc_html($telegram_msg_id) . '</code>' : '<em>—</em>') . '</td>';
            echo '<td>';
            if ($should_notify) {
                echo '<span class="badge badge-yes">SÍ</span><br><small>' . esc_html($reason) . '</small>';
            } else {
                echo '<span class="badge badge-no">NO</span><br><small>' . esc_html($reason) . '</small>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}


// ============================================
// 4. TEST DE CONEXIÓN TELEGRAM
// ============================================
if ($token && $chat_id) {
    echo '<h2>4️⃣ Test de Conexión Telegram</h2>';

    $test_message = "🧪 *Test automático de PHSBOT*\n\n";
    $test_message .= "Este mensaje confirma que:\n";
    $test_message .= "• El bot puede enviar mensajes ✅\n";
    $test_message .= "• El Chat ID es correcto ✅\n";
    $test_message .= "• La configuración funciona ✅\n\n";
    $test_message .= "Hora: " . date('Y-m-d H:i:s');

    $api_url = "https://api.telegram.org/bot{$token}/sendMessage";

    $response = wp_remote_post($api_url, array(
        'timeout' => 12,
        'body' => array(
            'chat_id' => $chat_id,
            'text' => $test_message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        )
    ));

    if (is_wp_error($response)) {
        echo '<div class="error">';
        echo '<strong>❌ Error de conexión:</strong><br>';
        echo esc_html($response->get_error_message());
        echo '</div>';
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($http_code === 200 && isset($data['ok']) && $data['ok']) {
            echo '<div class="success">';
            echo '<strong>✅ Mensaje de prueba enviado correctamente</strong><br>';
            echo 'Revisa tu Telegram. Deberías ver el mensaje de prueba.';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>❌ Error al enviar:</strong> ' . ($data['description'] ?? 'Unknown error');
            echo '</div>';
            echo '<div class="code">' . htmlspecialchars($body) . '</div>';
        }
    }
}


// ============================================
// 5. RECOMENDACIONES
// ============================================
echo '<h2>5️⃣ Recomendaciones</h2>';

echo '<div class="section">';
echo '<h3>Para que funcionen las notificaciones:</h3>';
echo '<ol>';
echo '<li><strong>Verifica el umbral:</strong> Si es > 1, solo notificará leads con score alto. Cámbialo a <code>1.0</code> en <strong>PHSBOT → Leads → Settings</strong></li>';
echo '<li><strong>Asegúrate de que los leads tengan email o teléfono:</strong> Sin estos datos, no se puede notificar.</li>';
echo '<li><strong>Verifica que se calcule el score:</strong> Los leads deben tener un score asignado (visible en la tabla de arriba).</li>';
echo '<li><strong>Inicia conversaciones de prueba:</strong> Proporciona email y genera un lead con score alto para probar.</li>';
echo '</ol>';
echo '</div>';

?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef; text-align: center;">
            <a href="<?php echo admin_url('admin.php?page=phsbot-leads'); ?>" class="btn">📊 Ver Leads</a>
            <a href="<?php echo admin_url('admin.php?page=phsbot-leads&tab=settings'); ?>" class="btn">⚙️ Configuración</a>
            <a href="javascript:location.reload()" class="btn">🔄 Recargar</a>
        </div>
    </div>
</body>
</html>
