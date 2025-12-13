<?php
/**
 * Diagnóstico completo de Telegram para PHSBOT
 *
 * INSTRUCCIONES:
 * 1. Sube este archivo a /wp-content/plugins/phsbot/
 * 2. Accede a: https://tu-sitio.com/wp-content/plugins/phsbot/diagnostico-telegram.php
 */

// Cargar WordPress
$wp_load_candidates = [
    __DIR__ . '/../../../../wp-load.php',  // Desde /wp-content/plugins/phsbot/
    __DIR__ . '/../../../wp-load.php',     // Alternativa
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once($candidate);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('❌ No se puede cargar WordPress. Asegúrate de que este archivo está en /wp-content/plugins/phsbot/');
}

// Verificar permisos (solo admins)
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('❌ Acceso denegado. Debes estar logueado como administrador.');
}

// Constantes
if (!defined('PHSBOT_MAIN_SETTINGS_OPT')) define('PHSBOT_MAIN_SETTINGS_OPT', 'phsbot_settings');
if (!defined('PHSBOT_LEADS_SETTINGS_OPT')) define('PHSBOT_LEADS_SETTINGS_OPT', 'phsbot_leads_settings');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Telegram PHSBOT</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        h2 { color: #34495e; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h3 { color: #555; margin: 15px 0 10px 0; font-size: 16px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; }
        code { background: #f4f4f4; padding: 3px 6px; border-radius: 3px; font-family: monospace; font-size: 13px; }
        pre { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        ol, ul { margin-left: 20px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔍 Diagnóstico Completo de Telegram - PHSBOT</h1>
            <p><strong>Sitio:</strong> <?php echo esc_html(home_url()); ?></p>
            <p><strong>Fecha:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

<?php
// =====================================================
// 1. LEER CONFIGURACIÓN
// =====================================================
$main = get_option(PHSBOT_MAIN_SETTINGS_OPT, []);
$leads = get_option(PHSBOT_LEADS_SETTINGS_OPT, []);

$token = isset($main['telegram_bot_token']) ? trim($main['telegram_bot_token']) : '';
$chat_id = isset($main['telegram_chat_id']) ? trim($main['telegram_chat_id']) : '';
$threshold = isset($leads['telegram_threshold']) ? floatval($leads['telegram_threshold']) : 8.0;
?>

        <!-- CONFIGURACIÓN ACTUAL -->
        <div class="card">
            <h2>📋 1. Configuración Actual</h2>
            <table>
                <tr>
                    <th style="width: 30%;">Parámetro</th>
                    <th style="width: 40%;">Valor</th>
                    <th style="width: 30%;">Estado</th>
                </tr>
                <tr>
                    <td><strong>Token del Bot</strong></td>
                    <td><code><?php echo $token ? substr($token, 0, 25) . '...' : '(vacío)'; ?></code></td>
                    <td><?php echo $token ? '✅ Configurado' : '❌ Falta'; ?></td>
                </tr>
                <tr>
                    <td><strong>Chat ID</strong></td>
                    <td><code><?php echo $chat_id ?: '(vacío)'; ?></code></td>
                    <td><?php echo $chat_id ? '✅ Configurado' : '❌ Falta'; ?></td>
                </tr>
                <tr>
                    <td><strong>Umbral Telegram</strong></td>
                    <td><code><?php echo $threshold; ?></code></td>
                    <td><?php
                        if ($threshold <= 1) {
                            echo '✅ Muy bajo (enviará casi siempre)';
                        } elseif ($threshold <= 5) {
                            echo '⚠️ Medio (solo leads buenos)';
                        } else {
                            echo '❌ Alto (solo leads excelentes)';
                        }
                    ?></td>
                </tr>
            </table>

            <?php if (!$token || !$chat_id): ?>
                <div class="error">
                    <strong>❌ Configuración incompleta</strong><br>
                    Ve a <strong>WordPress Admin → PHSBOT → Configuración → Conexiones</strong> y configura:
                    <ul>
                        <?php if (!$token): ?><li>Token del Bot</li><?php endif; ?>
                        <?php if (!$chat_id): ?><li>Chat ID</li><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

<?php if ($token && $chat_id): ?>
        <!-- TEST DE CONEXIÓN -->
        <div class="card">
            <h2>🧪 2. Test de Conexión con Telegram</h2>

            <?php
            // Verificar info del bot
            $info_url = "https://api.telegram.org/bot{$token}/getMe";
            $info_response = wp_remote_get($info_url, ['timeout' => 10]);

            if (is_wp_error($info_response)) {
                echo '<div class="error"><strong>❌ Error de red:</strong> ' . esc_html($info_response->get_error_message()) . '</div>';
            } else {
                $info_code = wp_remote_retrieve_response_code($info_response);
                $info_body = wp_remote_retrieve_body($info_response);
                $info_data = json_decode($info_body, true);

                if ($info_code === 200 && isset($info_data['ok']) && $info_data['ok']) {
                    echo '<div class="success">';
                    echo '<strong>✅ Bot verificado:</strong> ';
                    echo '<strong>' . esc_html($info_data['result']['first_name']) . '</strong> ';
                    echo '(@' . esc_html($info_data['result']['username']) . ')';
                    echo '</div>';

                    $bot_username = $info_data['result']['username'];
                } else {
                    echo '<div class="error">';
                    echo '<strong>❌ Token inválido</strong><br>';
                    echo 'El token del bot no funciona. Verifica que lo hayas copiado correctamente desde @BotFather';
                    echo '</div>';
                }
            }

            // Intentar enviar mensaje de prueba
            if (isset($info_data['ok']) && $info_data['ok']) {
                echo '<h3>Enviando mensaje de prueba...</h3>';

                $test_message = "🧪 *Test de PHSBOT*\n\n";
                $test_message .= "✅ La configuración funciona correctamente\n\n";
                $test_message .= "• Hora: " . date('Y-m-d H:i:s') . "\n";
                $test_message .= "• Sitio: " . home_url() . "\n";
                $test_message .= "• Umbral: {$threshold}\n\n";
                $test_message .= "Si ves este mensaje, recibirás las notificaciones de leads.";

                $send_response = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'timeout' => 10,
                    'body' => [
                        'chat_id' => $chat_id,
                        'text' => $test_message,
                        'parse_mode' => 'Markdown'
                    ]
                ]);

                if (is_wp_error($send_response)) {
                    echo '<div class="error"><strong>❌ Error:</strong> ' . esc_html($send_response->get_error_message()) . '</div>';
                } else {
                    $send_code = wp_remote_retrieve_response_code($send_response);
                    $send_body = wp_remote_retrieve_body($send_response);
                    $send_data = json_decode($send_body, true);

                    if ($send_code === 200 && isset($send_data['ok']) && $send_data['ok']) {
                        echo '<div class="success">';
                        echo '<strong>✅ ¡MENSAJE ENVIADO CORRECTAMENTE!</strong><br>';
                        echo 'Revisa tu Telegram, deberías ver el mensaje de prueba.';
                        echo '</div>';
                    } else {
                        echo '<div class="error">';
                        echo '<strong>❌ No se pudo enviar el mensaje</strong><br>';

                        if (isset($send_data['description'])) {
                            echo '<p><strong>Error:</strong> ' . esc_html($send_data['description']) . '</p>';

                            if (strpos($send_data['description'], 'bot can\'t initiate conversation') !== false ||
                                strpos($send_data['description'], 'Forbidden') !== false) {
                                echo '<div class="warning" style="margin-top:15px;">';
                                echo '<strong>⚠️ SOLUCIÓN:</strong><br>';
                                echo '<ol>';
                                echo '<li>Abre Telegram</li>';
                                echo '<li>Busca: <strong>@' . (isset($bot_username) ? $bot_username : 'tu_bot') . '</strong></li>';
                                echo '<li>Haz clic en <strong>"INICIAR"</strong> o envía <code>/start</code></li>';
                                echo '<li>Refresca esta página y vuelve a probar</li>';
                                echo '</ol>';
                                echo '</div>';
                            }
                        }

                        echo '<details style="margin-top:10px;"><summary>Ver respuesta completa</summary>';
                        echo '<pre>' . esc_html(json_encode($send_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                        echo '</details>';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>

        <!-- CÓMO FUNCIONA LA NOTIFICACIÓN -->
        <div class="card">
            <h2>⚙️ 3. Cómo Funciona la Notificación de Leads</h2>

            <div class="info">
                <strong>Se enviará notificación a Telegram cuando se cumpla ALGUNA de estas condiciones:</strong>
            </div>

            <table>
                <tr>
                    <th>Condición</th>
                    <th>Descripción</th>
                    <th>Estado Actual</th>
                </tr>
                <tr>
                    <td>✓ Usuario da <strong>teléfono</strong></td>
                    <td>Si el usuario proporciona su teléfono en la conversación</td>
                    <td>✅ Siempre envía (ignora umbral)</td>
                </tr>
                <tr>
                    <td>✓ Usuario da <strong>email</strong> + score ≥ umbral</td>
                    <td>Si el usuario da su email Y el score es mayor o igual al umbral</td>
                    <td><?php
                        if ($threshold <= 1) {
                            echo '✅ Enviará casi siempre (umbral: ' . $threshold . ')';
                        } else {
                            echo '⚠️ Solo si score ≥ ' . $threshold;
                        }
                    ?></td>
                </tr>
                <tr>
                    <td>✓ Ya existe mensaje previo</td>
                    <td>Si ya se envió una notificación antes (para editarla)</td>
                    <td>✅ Activo</td>
                </tr>
            </table>

            <div class="warning" style="margin-top: 15px;">
                <strong>⚠️ IMPORTANTE:</strong> El sistema NO enviará notificación si:
                <ul>
                    <li>El usuario NO proporciona ni email ni teléfono</li>
                    <li>Proporciona email pero el score es menor que <?php echo $threshold; ?></li>
                </ul>
            </div>
        </div>

        <!-- INSTRUCCIONES DE PRUEBA -->
        <div class="card">
            <h2>🧪 4. Cómo Probar que Funciona</h2>

            <ol style="line-height: 2;">
                <li><strong>Abre el chatbot</strong> en tu sitio web</li>
                <li><strong>Chatea normalmente</strong> (5-10 mensajes)</li>
                <li><strong>Proporciona tu email</strong> cuando el bot te lo pida (o menciónalo en la conversación)</li>
                <li><strong>Espera 10-20 segundos</strong></li>
                <li><strong>Revisa Telegram</strong> - deberías recibir la notificación</li>
            </ol>

            <div class="info" style="margin-top: 15px;">
                <strong>💡 Tip:</strong> Si el umbral es <?php echo $threshold; ?>, también puedes:
                <ul>
                    <li>Mencionar tu <strong>teléfono</strong> en la conversación (enviará siempre, sin importar el score)</li>
                    <li>Hacer preguntas específicas sobre productos/servicios (aumenta el score)</li>
                    <li>Mostrar interés de compra (aumenta el score)</li>
                </ul>
            </div>
        </div>

        <!-- SOLUCIONES COMUNES -->
        <div class="card">
            <h2>🔧 5. Soluciones a Problemas Comunes</h2>

            <h3>❌ "No recibo mensajes en Telegram"</h3>
            <ul>
                <li>✓ Verifica que hiciste <code>/start</code> en el bot</li>
                <li>✓ Asegúrate de dar tu email en el chat</li>
                <li>✓ Verifica que el umbral sea bajo (≤ 1.0)</li>
                <li>✓ Espera al menos 15-20 segundos después de dar tu email</li>
            </ul>

            <h3>❌ "Error 403: Forbidden"</h3>
            <p>Significa que NO has iniciado conversación con el bot.</p>
            <p><strong>Solución:</strong> Busca <strong>@<?php echo isset($bot_username) ? $bot_username : 'tu_bot'; ?></strong> en Telegram y envíale <code>/start</code></p>

            <h3>❌ "El mensaje de prueba funciona pero no los leads reales"</h3>
            <ul>
                <li>✓ Verifica que el umbral sea <strong>1.0 o menor</strong> (actual: <?php echo $threshold; ?>)</li>
                <li>✓ Asegúrate de proporcionar <strong>email o teléfono</strong> en el chat</li>
                <li>✓ Revisa los logs en: <strong>PHSBOT → Leads → Listado</strong></li>
            </ul>
        </div>

        <!-- ACCIONES -->
        <div class="card">
            <h2>🎯 Próximos Pasos</h2>
            <a href="<?php echo admin_url('admin.php?page=phsbot'); ?>" class="btn">← Volver a PHSBOT</a>
            <a href="<?php echo admin_url('admin.php?page=phsbot-leads'); ?>" class="btn">Ver Leads</a>
            <a href="javascript:location.reload()" class="btn btn-success">🔄 Probar de nuevo</a>
        </div>

<?php else: ?>
        <div class="card">
            <div class="error">
                <h2>❌ Configuración Incompleta</h2>
                <p>Antes de poder probar, necesitas configurar:</p>
                <ol>
                    <?php if (!$token): ?>
                    <li><strong>Token del Bot:</strong> Ve a PHSBOT → Configuración → Conexiones</li>
                    <?php endif; ?>
                    <?php if (!$chat_id): ?>
                    <li><strong>Chat ID:</strong> Ve a PHSBOT → Configuración → Conexiones</li>
                    <?php endif; ?>
                </ol>
            </div>
        </div>
<?php endif; ?>

    </div>
</body>
</html>
