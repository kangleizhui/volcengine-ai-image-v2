<?php
/**
 * 火山AI生图 - 后台管理
 */
session_start();

define('ADMIN_PASSWORD', 'admin888');
define('CONFIG_FILE', __DIR__ . '/../data/settings.json');

function getSettings() {
    if (!file_exists(CONFIG_FILE)) {
        return ['volc_api_token' => '', 'volc_model' => 'doubao-vision-pro-32k'];
    }
    return json_decode(file_get_contents(CONFIG_FILE), true) ?: [];
}

function saveSettings($data) {
    file_put_contents(CONFIG_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin/'); exit; }

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $login_error = '密码错误';
        }
    } elseif ($_POST['action'] === 'save_settings' && isset($_SESSION['admin_logged_in'])) {
        $settings = getSettings();
        $settings['volc_api_token'] = $_POST['volc_api_token'] ?? '';
        $settings['volc_model'] = $_POST['volc_model'] ?? 'doubao-vision-pro-32k';
        saveSettings($settings);
        $saved = true;
    } elseif ($_POST['action'] === 'save_payment' && isset($_SESSION['admin_logged_in'])) {
        $settings = getSettings();
        $settings['alipay_app_id'] = $_POST['alipay_app_id'] ?? '';
        $settings['alipay_private_key'] = $_POST['alipay_private_key'] ?? '';
        $settings['alipay_public_key'] = $_POST['alipay_public_key'] ?? '';
        $settings['payment_notify_url'] = $_POST['payment_notify_url'] ?? '';
        saveSettings($settings);
        $saved = true;
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']);
$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>火山AI生图 - 后台管理</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0f0f0f; color:#e0e0e0; }
.header { background:linear-gradient(135deg,#1a1a2e,#16213e); padding:24px 20px; display:flex; justify-content:space-between; align-items:center; }
.header h1 { font-size:1.4em; background:linear-gradient(135deg,#f59e0b,#ef4444); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.header a { color:#f59e0b; text-decoration:none; font-size:0.9em; }
.container { max-width:900px; margin:0 auto; padding:30px 20px; }
.login-box { max-width:360px; margin:80px auto; background:#1a1a2e; border:1px solid #2a2a4e; border-radius:12px; padding:32px; text-align:center; }
.login-box h2 { margin-bottom:20px; color:#f59e0b; }
.login-box input { width:100%; padding:12px; border-radius:8px; border:1px solid #2a2a4e; background:#0a0a1a; color:#e0e0e0; font-size:1em; margin-bottom:12px; outline:none; }
.login-box input:focus { border-color:#f59e0b; }
.login-box button { width:100%; padding:12px; background:#f59e0b; border:none; border-radius:8px; color:#000; font-size:1em; font-weight:600; cursor:pointer; }
.login-box button:hover { background:#fbbf24; }
.login-box .error { color:#f87171; font-size:0.9em; margin-top:8px; }
.card { background:#1a1a2e; border:1px solid #2a2a4e; border-radius:12px; padding:24px; margin-bottom:20px; }
.card h2 { color:#f59e0b; font-size:1.2em; margin-bottom:16px; padding-bottom:8px; border-bottom:1px solid #2a2a4e; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; color:#aaa; font-size:0.85em; margin-bottom:4px; }
.form-group input, .form-group select { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #2a2a4e; background:#0a0a1a; color:#e0e0e0; font-size:0.95em; outline:none; }
.form-group input:focus, .form-group select:focus { border-color:#f59e0b; }
.form-group .hint { color:#666; font-size:0.8em; margin-top:4px; }
.btn-save { padding:12px 40px; background:linear-gradient(135deg,#f59e0b,#ef4444); border:none; border-radius:8px; color:#fff; font-size:1em; font-weight:600; cursor:pointer; }
.btn-save:hover { opacity:0.9; }
.success-msg { background:#065f46; color:#34d399; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-size:0.9em; }
table { width:100%; border-collapse:collapse; font-size:0.85em; }
th, td { padding:10px 12px; text-align:left; border-bottom:1px solid #2a2a4e; }
th { color:#f59e0b; font-weight:600; font-size:0.85em; }
td { color:#aaa; }
td .badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.8em; }
.badge-paid { background:#065f46; color:#34d399; }
.badge-pending { background:#78350f; color:#fbbf24; }
.badge-closed { background:#7f1d1d; color:#f87171; }
.tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.tab { padding:10px 20px; border-radius:8px; border:1px solid #2a2a4e; background:#1a1a2e; color:#888; cursor:pointer; font-size:0.9em; }
.tab.active { border-color:#f59e0b; color:#f59e0b; background:#252540; }
.tab:hover { border-color:#f59e0b; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }
.dot-active { background:#34d399; }
.dot-inactive { background:#f87171; }
.footer { text-align:center; color:#666; margin-top:40px; font-size:0.85em; }
</style>
</head>
<body>
<div class="header">
    <h1>⚙️ 火山AI生图 - 后台管理</h1>
    <div>
        <?php if ($is_logged_in): ?>
            <a href="/">🏠 首页</a> &nbsp; <a href="?logout=1">🚪 退出</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
<?php if (!$is_logged_in): ?>
    <div class="login-box">
        <h2>🔐 管理员登录</h2>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <input type="password" name="password" placeholder="输入管理密码" required>
            <button type="submit">登 录</button>
        </form>
        <?php if ($login_error): ?>
            <div class="error">❌ <?= $login_error ?></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="tabs">
        <div class="tab active" onclick="switchTab(this, 'settings')">⚙️ 配置</div>
        <div class="tab" onclick="switchTab(this, 'orders')">📦 订单</div>
        <div class="tab" onclick="switchTab(this, 'keys')">🔑 密钥</div>
        <div class="tab" onclick="switchTab(this, 'payment')">💳 收款</div>
        <div class="tab" onclick="switchTab(this, 'test')">🧪 测试</div>
    </div>

    <!-- 配置 -->
    <div id="tab-settings" class="tab-content active">
        <div class="card">
            <h2>⚙️ 火山引擎配置</h2>
            <?php if (isset($saved)): ?>
                <div class="success-msg">✅ 配置已保存！现在去首页或测试tab试试生图</div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="save_settings">
                <div class="form-group">
                    <label>🔥 API Token（火山引擎方舟平台）</label>
                    <input type="text" name="volc_api_token" value="<?= htmlspecialchars($settings['volc_api_token'] ?? '') ?>" placeholder="粘贴你的火山引擎API Token" style="font-family:monospace;">
                    <div class="hint">在火山引擎方舟平台 → API Key管理 → 创建API Token，复制粘贴到这里即可</div>
                </div>
                <div class="form-group">
                    <label>模型</label>
                    <div style="display:flex;gap:8px;">
                        <select name="volc_model" id="modelSelect" style="flex:1;">
                            <option value="doubao-vision-pro-32k" <?= ($settings['volc_model'] ?? '') === 'doubao-vision-pro-32k' ? 'selected' : '' ?>>豆包视觉 doubao-vision-pro-32k</option>
                            <option value="doubao-pro-32k-character" <?= ($settings['volc_model'] ?? '') === 'doubao-pro-32k-character' ? 'selected' : '' ?>>豆包角色 doubao-pro-32k-character</option>
                        </select>
                        <button type="button" class="btn-save" style="padding:10px 16px;font-size:0.85em;white-space:nowrap;" onclick="queryModels()">🔍 查询模型</button>
                    </div>
                    <div class="hint">如果上面列表没有你需要的，点"查询模型"从火山引擎拉取最新列表</div>
                </div>
                <button type="submit" class="btn-save">💾 保存</button>
            </form>
            <div id="modelResult" style="margin-top:12px;"></div>
        </div>
        <div class="card">
            <h2>📖 获取Token教程</h2>
            <div style="color:#888;font-size:0.9em;line-height:1.8;">
                <p>1. 打开 <a href="https://console.volcengine.com/ark/" target="_blank" style="color:#f59e0b;">火山引擎方舟平台</a></p>
                <p>2. 左侧菜单 → <strong>API Key管理</strong></p>
                <p>3. 点击「创建API Key」→ 复制生成的 Token</p>
                <p>4. 粘贴到上面表单，点保存即可</p>
                <p style="margin-top:8px;color:#666;">💡 只需要填一个Token，不需要AccessKey和SecretKey</p>
                <p style="color:#666;">💡 Token只保存在服务器本地，不会外传</p>
            </div>
        </div>
    </div>

    <!-- 订单 -->
    <div id="tab-orders" class="tab-content">
        <div class="card">
            <h2>📦 订单列表</h2>
            <?php
            $db = new SQLite3('/var/www/hsst/data/app.db');
            $orders = $db->query("SELECT * FROM orders ORDER BY id DESC LIMIT 50");
            ?>
            <table>
                <tr><th>订单号</th><th>套餐</th><th>金额</th><th>状态</th><th>时间</th></tr>
                <?php while ($o = $orders->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td style="font-family:monospace;font-size:0.85em;"><?= htmlspecialchars($o['order_id']) ?></td>
                    <td><?= htmlspecialchars($o['plan_name']) ?></td>
                    <td>¥<?= number_format($o['amount'], 2) ?></td>
                    <td><span class="badge badge-<?= $o['status'] ?>"><?= $o['status'] === 'paid' ? '✅ 已支付' : ($o['status'] === 'pending' ? '⏳ 待支付' : '❌ 已关闭') ?></span></td>
                    <td><?= $o['created_at'] ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- 密钥 -->
    <div id="tab-keys" class="tab-content">
        <div class="card">
            <h2>🔑 API密钥列表</h2>
            <?php
            $keys = $db->query("SELECT * FROM api_keys ORDER BY id DESC LIMIT 50");
            ?>
            <table>
                <tr><th>密钥</th><th>类型</th><th>状态</th><th>已用/总数</th><th>到期</th><th>创建时间</th></tr>
                <?php while ($k = $keys->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td style="font-family:monospace;font-size:0.8em;color:#67e8f9;"><?= htmlspecialchars($k['key_string']) ?></td>
                    <td><?= $k['is_test'] ? '🧪 测试' : ($k['type'] === 'count' ? '按次' : ($k['type'] === 'monthly' ? '包月' : ($k['type'] === 'daily' ? '包天' : '永久'))) ?></td>
                    <td><span class="status-dot <?= $k['is_active'] ? 'dot-active' : 'dot-inactive' ?>"></span><?= $k['is_active'] ? '正常' : '禁用' ?></td>
                    <td><?= $k['used_count'] ?>/<?= $k['total_count'] ?: '∞' ?></td>
                    <td><?= $k['expires_at'] ?: '永久' ?></td>
                    <td><?= $k['created_at'] ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <!-- 收款 -->
    <div id="tab-payment" class="tab-content">
        <div class="card">
            <h2>💳 支付宝收款配置</h2>
            <?php if (isset($saved) && $_POST["action"] === "save_payment"): ?>
                <div class="success-msg">✅ 收款配置已保存！</div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="save_payment">
                <div class="form-group">
                    <label>支付宝 App ID</label>
                    <input type="text" name="alipay_app_id" value="<?= htmlspecialchars($settings['alipay_app_id'] ?? '') ?>" placeholder="例如：2021002125647131" style="font-family:monospace;">
                    <div class="hint">在 支付宝开放平台 → 我的应用 → 应用信息 中查看</div>
                </div>
                <div class="form-group">
                    <label>应用私钥（PEM格式）</label>
                    <textarea name="alipay_private_key" rows="6" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #2a2a4e;background:#0a0a1a;color:#e0e0e0;font-family:monospace;font-size:0.85em;outline:none;resize:vertical;"><?= htmlspecialchars($settings['alipay_private_key'] ?? '') ?></textarea>
                    <div class="hint">应用公钥对应的私钥，从支付宝开放平台获取的 RSA2 私钥，以 -----BEGIN PRIVATE KEY----- 开头</div>
                </div>
                <div class="form-group">
                    <label>支付宝公钥</label>
                    <textarea name="alipay_public_key" rows="4" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #2a2a4e;background:#0a0a1a;color:#e0e0e0;font-family:monospace;font-size:0.85em;outline:none;resize:vertical;"><?= htmlspecialchars($settings['alipay_public_key'] ?? '') ?></textarea>
                    <div class="hint">在支付宝开放平台 → 查看公钥 → 复制支付宝公钥</div>
                </div>
                <div class="form-group">
                    <label>异步通知地址（notify_url）</label>
                    <input type="text" name="payment_notify_url" value="<?= htmlspecialchars($settings['payment_notify_url'] ?? '') ?>" placeholder="留空则自动检测当前域名" style="font-family:monospace;">
                    <div class="hint">在支付宝开放平台配置该地址。留空则自动检测当前域名，无需手动填写</div>
                </div>
                <button type="submit" class="btn-save">💾 保存收款配置</button>
            </form>
        </div>
        <div class="card">
            <h2>📖 支付宝配置步骤</h2>
            <div style="color:#888;font-size:0.9em;line-height:1.8;">
                <p>1. 登录 <a href="https://open.alipay.com/" target="_blank" style="color:#f59e0b;">支付宝开放平台</a></p>
                <p>2. 创建「网页/移动应用」→ 开通「当面付」功能</p>
                <p>3. 获取 <strong>App ID</strong> → 设置 <strong>应用公钥</strong>（用支付宝工具生成）</p>
                <p>4. 把生成的 <strong>应用私钥</strong> 和支付宝给你的 <strong>支付宝公钥</strong> 填到上面</p>
                <p>5. 在支付宝开放平台设置「接口加签方式」为 RSA2</p>
                <p>6. 在支付宝开放平台设置「异步通知地址」为你的 notify_url</p>
                <p style="margin-top:8px;color:#f59e0b;">💡 测试阶段可以在支付宝开放平台用沙箱环境</p>
                <p style="color:#666;">💡 密钥保存后只存储在服务器本地，不会外传</p>
            </div>
        </div>
    </div>
    <!-- 测试 -->
        <div class="card">
            <h2>🧪 测试生图</h2>
            <div style="margin-bottom:12px;">
                <input type="text" id="testPrompt" placeholder="输入描述文字，如：一只星空下的猫" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #2a2a4e;background:#0a0a1a;color:#e0e0e0;font-size:0.95em;outline:none;margin-bottom:8px;">
                <button onclick="testGenerate()" class="btn-save" style="padding:10px 24px;font-size:0.9em;">🎨 生成测试</button>
            </div>
            <div id="testResult" style="color:#888;font-size:0.9em;"></div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="footer">
    <p>火山AI生图 v1.0.0 · <a href="/">前台首页</a></p>
</div>

<script>
function switchTab(el, tabId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}

async function testGenerate() {
    const prompt = document.getElementById('testPrompt').value.trim();
    const result = document.getElementById('testResult');
    if (!prompt) { result.innerHTML = '请输入描述文字'; return; }
    result.innerHTML = '⏳ 生成中...';
    try {
        const res = await fetch('/api/generate.php?key=TEST1-ABCD-EFGH-IJKL-MNOP&prompt=' + encodeURIComponent(prompt));
        const data = await res.json();
        if (data.code === 200) {
            let html = '✅ 生成成功！<br>';
            data.data.images.forEach((img, i) => {
                html += `<img src="${img.url}" style="max-width:100%;border-radius:8px;margin-top:8px;">`;
            });
            result.innerHTML = html;
        } else {
            result.innerHTML = '❌ ' + (data.msg || '未知错误');
        }
    } catch (e) {
        result.innerHTML = '❌ 请求失败: ' + e.message;
    }
}

function renderModels(grouped, domainLabels) {
    const fallbackNames = {
        'ImageGeneration': { label: '🖼️ 生图模型', desc: '可生成图片（推荐选择）', color: '#34d399' },
        'VideoGeneration': { label: '🎬 视频生成', desc: '', color: '#f59e0b' },
        '3DGeneration':    { label: '🧊 3D生成', desc: '', color: '#67e8f9' },
        'VLM':             { label: '👁️ 多模态理解', desc: '不能生图', color: '#60a5fa' },
        'LLM':             { label: '💬 文本对话', desc: '不能生图', color: '#a78bfa' },
        'Embedding':       { label: '🔗 向量嵌入', desc: '不能生图', color: '#f472b6' },
        'Router':          { label: '🔀 路由模型', desc: '', color: '#fb923c' },
    };
    let html = '<div style="max-height:420px;overflow-y:auto;">';
    Object.keys(grouped).forEach(function(domain) {
        const items = grouped[domain] || [];
        if (items.length === 0) return;
        const info = domainLabels && domainLabels[domain] ? domainLabels[domain] : (fallbackNames[domain] || { label: domain, desc: '', color: '#888' });
        html += '<div style="margin-bottom:10px;">';
        html += '<div style="color:' + info.color + ';font-weight:600;font-size:0.9em;margin-bottom:2px;">';
        html += info.label + ' <span style="color:#666;font-weight:400;font-size:0.8em;">(' + items.length + '个' + (info.desc ? ') ' + info.desc : ')') + '</span>';
        html += '</div>';
        items.forEach(function(m) {
            const is_selected = m.id === document.getElementById('modelSelect').value;
            html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:3px 8px;font-family:monospace;font-size:0.82em;border-bottom:1px solid #1a1a2e;' + (is_selected ? 'background:#252540;border-radius:4px;' : '') + '">';
            html += '<span style="color:' + info.color + ';">' + m.id + (is_selected ? ' ✅' : '');
            if (m.status === 'Shutdown' || m.status === 'Retiring') {
                html += ' <span style="color:#f87171;font-size:0.75em;">(已下线)</span>';
            }
            html += '</span>';
            html += '<button onclick="testModel(\'' + m.id.replace(/'/g, "\\'") + '\')" style="padding:2px 8px;border-radius:4px;border:1px solid #555;background:transparent;color:#aaa;font-size:0.8em;cursor:pointer;">🧪测试</button>';
            html += '</div>';
        });
        html += '</div>';
    });
    html += '</div>';
    return html;
}

async function queryModels() {
    const result = document.getElementById('modelResult');
    const select = document.getElementById('modelSelect');
    result.innerHTML = '⏳ 查询中...';
    try {
        const res = await fetch('/api/volc_models.php');
        const data = await res.json();
        if (data.code === 200 && data.data.models) {
            const grouped = data.data.grouped || {};
            const domainLabels = data.data.domain_labels || {};
            // 清空并填充下拉框（按 domain 分组显示）
            select.innerHTML = '';
            // 先加一个占位提示
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- 请选择一个模型 --';
            placeholder.disabled = true;
            placeholder.selected = true;
            select.appendChild(placeholder);
            // 按 domain 分组填充下拉框
            Object.keys(grouped).forEach(function(domain) {
                const items = grouped[domain] || [];
                if (items.length === 0) return;
                const group = document.createElement('optgroup');
                const label = (domainLabels[domain] && domainLabels[domain].label) || domain;
                group.label = label;
                items.forEach(function(m) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.id + (m.status === 'Shutdown' || m.status === 'Retiring' ? ' (已下线)' : '');
                    group.appendChild(opt);
                });
                select.appendChild(group);
            });
            // 渲染结果面板
            result.innerHTML = renderModels(grouped, domainLabels);
            // 添加提示
            const hint = document.createElement('div');
            hint.style.cssText = 'color:#f59e0b;font-size:0.85em;margin-top:12px;padding:10px;background:#252540;border-radius:8px;';
            hint.innerHTML = '💡 <b>选模型方法：</b><br>' +
                '1. 点 🖼️ 生图模型 分类展开 → 选一个模型<br>' +
                '2. 也可以点 <b>🧪测试</b> 按钮验证是否真的能生图<br>' +
                '3. 选好后点「保存」即可';
            result.appendChild(hint);
        } else {
            result.innerHTML = '<div style="color:#f87171;font-size:0.9em;padding:12px;">❌ ' + (data.msg || '查询失败') + '</div>';
        }
    } catch (e) {
        result.innerHTML = '<div style="color:#f87171;font-size:0.9em;padding:12px;">❌ 请求失败: ' + e.message + '</div>';
    }
}

async function testModel(modelId) {
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '⏳';
    btn.disabled = true;
    // 新建或复用一个测试结果区
    let testDiv = document.getElementById('testModelResult');
    if (!testDiv) {
        testDiv = document.createElement('div');
        testDiv.id = 'testModelResult';
        testDiv.style.cssText = 'margin-top:8px;padding:10px;background:#0a0a1a;border-radius:8px;font-size:0.85em;';
        document.getElementById('modelResult').appendChild(testDiv);
    }
    testDiv.innerHTML = '🧪 正在测试 <b>' + modelId + '</b> 的生图能力...';
    try {
        // 临时用测试密钥 + 指定 model 参数测试
        const res = await fetch('/api/generate.php?key=TEST1-ABCD-EFGH-IJKL-MNOP&prompt=一只可爱的小猫&model=' + encodeURIComponent(modelId));
        const data = await res.json();
        if (data.code === 200) {
            testDiv.innerHTML = '✅ <span style="color:#34d399;">' + modelId + '</span> — 生图成功！🎉<br>';
            if (data.data && data.data.images) {
                testDiv.innerHTML += '<img src="' + data.data.images[0].url + '" style="max-width:200px;border-radius:8px;margin-top:8px;">';
            }
            testDiv.innerHTML += '<div style="color:#888;margin-top:6px;">💡 选这个模型，点「保存」即可</div>';
            // 自动选中这个模型
            document.getElementById('modelSelect').value = modelId;
        } else {
            const msg = data.msg || '未知错误';
            testDiv.innerHTML = '❌ <b>' + modelId + '</b> 生图失败: <span style="color:#f87171;">' + msg + '</span><br>' +
                '<div style="color:#888;margin-top:4px;">这个模型不支持图片生成，换一个试试</div>';
        }
    } catch (e) {
        testDiv.innerHTML = '❌ <b>' + modelId + '</b> 请求失败: ' + e.message;
    }
    btn.textContent = originalText;
    btn.disabled = false;
}
</script>
</body>
</html>
