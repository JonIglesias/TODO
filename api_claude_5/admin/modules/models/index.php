<?php
/**
 * Models Module - Gestión de Modelos OpenAI y Precios
 */

$success = '';
$error = '';

// Función para sincronizar precios desde OpenAI
function syncOpenAIPrices($db) {
    try {
        // Obtener API key
        $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = 'openai_api_key' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $apiKey = $result['setting_value'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API Key de OpenAI no configurada'];
        }

        // Consultar modelos desde OpenAI
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Error al consultar OpenAI API (HTTP ' . $httpCode . ')'];
        }

        $data = json_decode($response, true);
        if (!isset($data['data'])) {
            return ['success' => false, 'error' => 'Respuesta inválida de OpenAI'];
        }

        // Precios conocidos de OpenAI (Diciembre 2024) - USD por 1.000 tokens
        $knownPrices = [
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-2024-11-20' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-2024-08-06' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-2024-05-13' => ['input' => 0.005, 'output' => 0.015],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4o-mini-2024-07-18' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4-turbo-2024-04-09' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-0613' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'gpt-3.5-turbo-0125' => ['input' => 0.0005, 'output' => 0.0015],
            'chatgpt-4o-latest' => ['input' => 0.005, 'output' => 0.015],
            'o1' => ['input' => 0.015, 'output' => 0.06],
            'o1-preview' => ['input' => 0.015, 'output' => 0.06],
            'o1-mini' => ['input' => 0.003, 'output' => 0.012]
        ];

        $updated = 0;
        $added = 0;

        foreach ($data['data'] as $model) {
            $modelId = $model['id'];

            // Solo procesar modelos GPT y O1
            if (!preg_match('/^(gpt-|o1|chatgpt)/i', $modelId)) {
                continue;
            }

            // FILTRAR MODELOS FICTICIOS QUE NO EXISTEN EN OPENAI
            // gpt-4.1, gpt-4.1-mini, gpt-4.1-nano NO SON MODELOS REALES
            $invalidModels = ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano'];
            $isInvalid = false;
            foreach ($invalidModels as $invalidPrefix) {
                if (strpos($modelId, $invalidPrefix) === 0) {
                    $isInvalid = true;
                    break;
                }
            }
            if ($isInvalid) {
                continue; // Saltar modelos ficticios
            }

            // Buscar precio conocido
            $prices = null;
            if (isset($knownPrices[$modelId])) {
                $prices = $knownPrices[$modelId];
            } else {
                // Intentar detectar familia
                foreach ($knownPrices as $knownModel => $knownPrice) {
                    if (strpos($modelId, $knownModel) === 0) {
                        $prices = $knownPrice;
                        break;
                    }
                }
            }

            if (!$prices) {
                continue; // Saltar modelos sin precio conocido
            }

            // Verificar si el modelo ya existe
            $stmt = $db->prepare("SELECT id FROM " . DB_PREFIX . "model_prices WHERE model_name = ? LIMIT 1");
            $stmt->execute([$modelId]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                // Actualizar precio existente
                $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices
                    SET price_input_per_1k = ?, price_output_per_1k = ?, source = 'openai_api_sync', updated_at = NOW()
                    WHERE model_name = ? AND is_active = 1");
                $stmt->execute([$prices['input'], $prices['output'], $modelId]);
                $updated++;
            } else {
                // Insertar nuevo modelo
                $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "model_prices
                    (model_name, price_input_per_1k, price_output_per_1k, source, notes, is_active, updated_at)
                    VALUES (?, ?, ?, 'openai_api_sync', 'Auto-importado desde OpenAI API', 1, NOW())");
                $stmt->execute([$modelId, $prices['input'], $prices['output']]);
                $added++;
            }
        }

        return [
            'success' => true,
            'updated' => $updated,
            'added' => $added
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Obtener conexión a BD
try {
    $db = Database::getInstance();

    // Procesar formularios
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    // Agregar nuevo modelo
                    $model_name = trim($_POST['model_name']);
                    $price_input = floatval($_POST['price_input_per_1k']);
                    $price_output = floatval($_POST['price_output_per_1k']);
                    $source = trim($_POST['source']) ?: 'manual';
                    $notes = trim($_POST['notes']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    if (empty($model_name)) {
                        $error = '⚠️ El nombre del modelo es requerido';
                    } else {
                        $stmt = $db->prepare("INSERT INTO " . DB_PREFIX . "model_prices
                            (model_name, price_input_per_1k, price_output_per_1k, source, notes, is_active, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$model_name, $price_input, $price_output, $source, $notes, $is_active]);
                        $success = '✅ Modelo agregado correctamente';
                    }
                    break;

                case 'edit':
                    // Editar modelo existente
                    $id = intval($_POST['id']);
                    $model_name = trim($_POST['model_name']);
                    $price_input = floatval($_POST['price_input_per_1k']);
                    $price_output = floatval($_POST['price_output_per_1k']);
                    $source = trim($_POST['source']) ?: 'manual';
                    $notes = trim($_POST['notes']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;

                    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices
                        SET model_name = ?, price_input_per_1k = ?, price_output_per_1k = ?,
                            source = ?, notes = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$model_name, $price_input, $price_output, $source, $notes, $is_active, $id]);
                    $success = '✅ Modelo actualizado correctamente';
                    break;

                case 'delete':
                    // Eliminar modelo (soft delete - marcar como inactivo)
                    $id = intval($_POST['id']);
                    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = '✅ Modelo desactivado correctamente';
                    break;

                case 'toggle':
                    // Activar/desactivar modelo
                    $id = intval($_POST['id']);
                    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "model_prices
                        SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW()
                        WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = '✅ Estado del modelo actualizado';
                    break;

                case 'sync_openai':
                    // Sincronizar precios desde OpenAI
                    $result = syncOpenAIPrices($db);
                    if ($result['success']) {
                        $success = "✅ Sincronización completada: {$result['updated']} modelos actualizados, {$result['added']} nuevos modelos agregados";
                    } else {
                        $error = "⚠️ Error al sincronizar: {$result['error']}";
                    }
                    // No hacer redirect, mostrar mensaje directamente
                    break;
            }
        }
    }

    // Obtener todos los modelos
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "model_prices ORDER BY is_active DESC, model_name ASC");
    $stmt->execute();
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $models = [];
}
?>

<style>
.models-container {
    max-width: 1400px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
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

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 16px;
}

.card-body {
    padding: 20px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-warning {
    background: #ffc107;
    color: #000;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.model-name {
    font-family: monospace;
    font-weight: 600;
}

.price {
    color: #28a745;
    font-weight: 500;
}

.info-box {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
}

.actions {
    display: flex;
    gap: 5px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 8px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

.modal-close {
    float: right;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}
</style>

<div class="models-container">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <strong>ℹ️ Sobre los modelos de IA</strong><br>
        Aquí puedes gestionar los modelos de IA disponibles y sus precios. Los modelos activos aparecerán en los selectores de configuración de GeoWriter y Chatbot.
        Los precios se expresan en USD por 1.000 tokens (input y output separados).
    </div>

    <!-- Botones de acción -->
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="openAddModal()">
            ➕ Agregar Nuevo Modelo
        </button>
        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Sincronizar precios desde OpenAI? Esto actualizará los modelos existentes con los precios más recientes.')">
            <input type="hidden" name="action" value="sync_openai">
            <button type="submit" class="btn btn-success">
                🔄 Actualizar Precios desde OpenAI
            </button>
        </form>
    </div>

    <!-- Tabla de modelos -->
    <div class="card">
        <div class="card-header">
            🤖 Modelos de IA Configurados
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Modelo</th>
                        <th>Precio Input ($/1K tokens)</th>
                        <th>Precio Output ($/1K tokens)</th>
                        <th>Fuente</th>
                        <th>Última Actualización</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                No hay modelos configurados
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($models as $model): ?>
                            <tr>
                                <td>
                                    <?php if ($model['is_active']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="model-name"><?= htmlspecialchars($model['model_name']) ?></td>
                                <td class="price">$<?= number_format($model['price_input_per_1k'], 6) ?></td>
                                <td class="price">$<?= number_format($model['price_output_per_1k'], 6) ?></td>
                                <td><?= htmlspecialchars($model['source'] ?? 'manual') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($model['updated_at'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-sm btn-warning" onclick='editModel(<?= json_encode($model) ?>)'>
                                            ✏️ Editar
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Cambiar estado del modelo?')">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $model['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $model['is_active'] ? 'btn-secondary' : 'btn-success' ?>">
                                                <?= $model['is_active'] ? '⏸️ Desactivar' : '▶️ Activar' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="warning-box">
        <strong>⚠️ Importante:</strong> Los cambios en los precios de los modelos afectan los cálculos de costos en tiempo real.
        Desactivar un modelo lo ocultará de los selectores pero no afectará las configuraciones existentes que lo usen.
    </div>
</div>

<!-- Modal para agregar modelo -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-close" onclick="closeAddModal()">&times;</span>
            ➕ Agregar Nuevo Modelo
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Nombre del Modelo *</label>
                <input type="text" name="model_name" placeholder="gpt-4o, claude-3-5-sonnet, etc." required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Precio Input ($/1K tokens) *</label>
                    <input type="number" name="price_input_per_1k" step="0.000001" min="0" placeholder="0.005" required>
                </div>
                <div class="form-group">
                    <label>Precio Output ($/1K tokens) *</label>
                    <input type="number" name="price_output_per_1k" step="0.000001" min="0" placeholder="0.015" required>
                </div>
            </div>

            <div class="form-group">
                <label>Fuente</label>
                <input type="text" name="source" placeholder="openai_pricing_dic2024, anthropic_pricing, etc.">
            </div>

            <div class="form-group">
                <label>Notas</label>
                <textarea name="notes" placeholder="Información adicional sobre este modelo..."></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" checked>
                    <span>Activar modelo inmediatamente</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">💾 Guardar Modelo</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para editar modelo -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            ✏️ Editar Modelo
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label>Nombre del Modelo *</label>
                <input type="text" name="model_name" id="edit_model_name" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Precio Input ($/1K tokens) *</label>
                    <input type="number" name="price_input_per_1k" id="edit_price_input" step="0.000001" min="0" required>
                </div>
                <div class="form-group">
                    <label>Precio Output ($/1K tokens) *</label>
                    <input type="number" name="price_output_per_1k" id="edit_price_output" step="0.000001" min="0" required>
                </div>
            </div>

            <div class="form-group">
                <label>Fuente</label>
                <input type="text" name="source" id="edit_source">
            </div>

            <div class="form-group">
                <label>Notas</label>
                <textarea name="notes" id="edit_notes"></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" id="edit_is_active">
                    <span>Modelo activo</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-success">💾 Actualizar Modelo</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function editModel(model) {
    document.getElementById('edit_id').value = model.id;
    document.getElementById('edit_model_name').value = model.model_name;
    document.getElementById('edit_price_input').value = model.price_input_per_1k;
    document.getElementById('edit_price_output').value = model.price_output_per_1k;
    document.getElementById('edit_source').value = model.source || '';
    document.getElementById('edit_notes').value = model.notes || '';
    document.getElementById('edit_is_active').checked = model.is_active == 1;
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>
