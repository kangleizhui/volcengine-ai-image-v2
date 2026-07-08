<?php
/**
 * 密钥信息查询
 * GET /api/key_info.php?key=XXXX
 * 不消耗次数
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';

$key = $_GET['key'] ?? '';
if (empty($key)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '缺少参数: key']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$data = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$data) {
    echo json_encode(['code' => 404, 'msg' => '密钥不存在', 'data' => null]);
    exit;
}

echo json_encode([
    'code' => 200,
    'msg' => 'ok',
    'data' => [
        'is_test' => (bool)$data['is_test'],
        'type' => $data['type'],
        'is_active' => (bool)$data['is_active'],
        'total_count' => (int)$data['total_count'],
        'used_count' => (int)$data['used_count'],
        'remaining' => $data['type'] === 'count' && $data['total_count'] > 0 
            ? $data['total_count'] - $data['used_count'] 
            : null,
        'daily_limit' => (int)$data['daily_limit'],
        'expires_at' => $data['expires_at'],
        'created_at' => $data['created_at'],
    ]
], JSON_UNESCAPED_UNICODE);
