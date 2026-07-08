<?php
/**
 * 火山引擎方舟(Ark) 图片生成 API
 * 
 * 用法：
 *   GET /api/generate.php?key=密钥&prompt=一只猫&size=1024x1024&n=1
 * 
 * 支持火山引擎方舟平台的图片生成模型：
 * - doubao-vision-pro-32k
 * - doubao-pro-32k-character
 * - 等 OpenAI 兼容接口
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../admin/config.php';

// ========== 参数 ==========
$key = $_REQUEST['key'] ?? '';
$prompt = $_REQUEST['prompt'] ?? '';
$size = $_REQUEST['size'] ?? '1024x1024';
$n = intval($_REQUEST['n'] ?? 1);
$quality = $_REQUEST["quality"] ?? "standard";
$override_model = $_REQUEST["model"] ?? "";  // 可选，覆盖默认模型（用于测试）

if (empty($key)) jsonExit(400, '缺少参数: key');
if (empty($prompt)) jsonExit(400, '缺少参数: prompt');

if ($n < 1) $n = 1;
if ($n > 4) $n = 4;

$allowed_sizes = ['512x512', '768x768', '1024x1024', '1024x768', '768x1024', '1536x1536', '1920x1920', '1920x1080', '1080x1920'];
if (!in_array($size, $allowed_sizes)) $size = '1024x1024';

// ========== 验证密钥 ==========
$db = getDB();
$stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$key_data = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$key_data || !$key_data['is_active']) {
    jsonExit(403, '密钥无效');
}

// 检查过期
if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) {
    jsonExit(403, '密钥已过期');
}

// 检查用量
if ($key_data['type'] === 'count' && $key_data['total_count'] > 0 && $key_data['used_count'] >= $key_data['total_count']) {
    // 自动禁用
    $db->exec("UPDATE api_keys SET is_active = 0 WHERE id = {$key_data['id']}");
    jsonExit(403, '密钥次数已用完，已被禁用');
}

// 检查每日限制（仅测试密钥）
if ($key_data['is_test']) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $stmt->bindValue(3, $today, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($r && $r['used_count'] >= 10) {
        jsonExit(429, '测试密钥每日每IP限10次');
    }
}

// ========== 调用火山引擎API ==========
if (empty(VOLC_API_TOKEN)) {
    jsonExit(500, '服务端未配置火山引擎密钥，请先到 /admin/ 后台配置');
}

try {
    $result = callVolcEngine($prompt, $size, $n, $override_model);
} catch (Exception $e) {
    jsonExit(500, '生成失败: ' . $e->getMessage());
}

// ========== 记录用量 ==========
$stmt = $db->prepare("UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?");
$stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
$stmt->execute();

if ($key_data['is_test']) {
    $stmt = $db->prepare("INSERT INTO test_ip_usage (key_id, ip_address, date, used_count) VALUES (?, ?, ?, 1) ON CONFLICT(key_id, ip_address, date) DO UPDATE SET used_count = used_count + 1");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $stmt->bindValue(3, $today, SQLITE3_TEXT);
    $stmt->execute();
}

$db->close();

// ========== 响应 ==========
echo json_encode([
    'code' => 200,
    'msg' => '生成成功',
    'data' => [
        'images' => $result['images'],
        'prompt' => $prompt,
        'size' => $size,
        'n' => $n,
        'model' => $override_model ?: VOLC_MODEL,
        'key_info' => [
            'is_test' => (bool)$key_data['is_test'],
            'type' => $key_data['type'],
            'remaining' => $key_data['type'] === 'count' && $key_data['total_count'] > 0 
                ? $key_data['total_count'] - $key_data['used_count'] - 1 
                : null,
            'daily_remaining' => $key_data['is_test'] 
                ? 10 - ($r ? $r['used_count'] + 1 : 1) 
                : null,
            'expires_at' => $key_data['expires_at'],
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);


// ========== 火山引擎方舟API调用 ==========
function callVolcEngine($prompt, $size, $n, $override_model = "") {
    // 火山引擎方舟平台兼容 OpenAI 格式
    // 文档: https://www.volcengine.com/docs/82379/1186201
    
    $payload = [
        'model' => $override_model ?: VOLC_MODEL,
        'prompt' => $prompt,
        'n' => $n,
        'size' => $size,
        'response_format' => 'url',
    ];
    
    // 使用 Bearer Token 认证（火山引擎方舟平台支持）
    $timestamp = gmdate('Ymd\THis\Z');
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . VOLC_API_TOKEN,
        'X-Date: ' . $timestamp,
    ];
    
    $body = json_encode($payload);
    
    $ch = curl_init(VOLC_API_BASE . '/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("请求火山引擎失败: {$error}");
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        throw new Exception("火山引擎返回格式异常: " . substr($response, 0, 200));
    }
    
    if ($http_code !== 200) {
        $msg = $result['error']['message'] ?? json_encode($result);
        throw new Exception("火山引擎错误 ({$http_code}): {$msg}");
    }
    
    // 提取图片URL
    $images = [];
    foreach ($result['data'] ?? [] as $item) {
        $images[] = [
            'url' => $item['url'] ?? '',
            'revised_prompt' => $item['revised_prompt'] ?? '',
        ];
    }
    
    return ['images' => $images];
}

function jsonExit($code, $msg) {
    http_response_code($code);
    echo json_encode(['code' => $code, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
