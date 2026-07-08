<?php
/**
 * 查询火山引擎可用模型列表
 * GET /api/volc_models.php
 * 
 * 返回模型列表并按 domain 分组，精准区分生图/视频/文本/多模态模型
 * 模型端点分页参数：page_size=200 一次性获取全部
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';

if (empty(VOLC_API_TOKEN)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '请先在后台配置API Token'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 调火山引擎方舟平台模型列表API（注意：必须用 Bearer 前缀，不能用 ***）
$url = 'https://ark.cn-beijing.volces.com/api/v3/models?page_size=200';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPGET => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . VOLC_API_TOKEN,  // 关键：用 Bearer 不是 ***
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => '查询失败: ' . $error], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);

if ($http_code !== 200 || !$result) {
    $err_msg = $result['error']['message'] ?? '未知错误';
    http_response_code(500);
    echo json_encode([
        'code' => 500, 
        'msg' => '查询失败: ' . $err_msg,
        'http_code' => $http_code,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 模型 domain → 中文分类名
$domainLabels = [
    'ImageGeneration' => ['label' => '🖼️ 生图模型', 'desc' => '可生成图片（推荐选择）', 'color' => '#34d399'],
    'VideoGeneration' => ['label' => '🎬 视频生成', 'desc' => '可生成视频', 'color' => '#f59e0b'],
    '3DGeneration'    => ['label' => '🧊 3D生成', 'desc' => '', 'color' => '#67e8f9'],
    'VLM'             => ['label' => '👁️ 多模态理解', 'desc' => '可看图描述图，但不能生图', 'color' => '#60a5fa'],
    'LLM'             => ['label' => '💬 文本对话', 'desc' => '纯文本模型，不能生图', 'color' => '#a78bfa'],
    'Embedding'       => ['label' => '🔗 向量嵌入', 'desc' => '文本向量化，不能生图', 'color' => '#f472b6'],
    'Router'          => ['label' => '🔀 路由模型', 'desc' => '自动分发请求，不能生图', 'color' => '#fb923c'],
];

// 提取模型列表，按 domain 分组
$grouped = [];
$models = [];

foreach ($result['data'] ?? [] as $m) {
    $id = $m['id'] ?? '';
    $domain = $m['domain'] ?? '';
    $object = $m['object'] ?? '';
    $owned = $m['owned_by'] ?? '';
    $status = $m['status'] ?? '';
    $name = $m['name'] ?? '';
    
    // domain 为空时尝试自动识别
    if (empty($domain)) {
        $id_lower = strtolower($id);
        if (preg_match('/\bvision\b/', $id_lower)) {
            $domain = 'VLM';
        } elseif (preg_match('/\bembedding\b/', $id_lower)) {
            $domain = 'Embedding';
        } elseif (preg_match('/\b(seed|seedream)\b/', $id_lower)) {
            $domain = 'ImageGeneration';  // 含 seed/seedream 的多半是生图
        } elseif (preg_match('/\b(chat|character|pro|lite)\b/', $id_lower)) {
            $domain = 'LLM';
        }
    }
    
    if (empty($domain)) {
        $domain = '其他';
    }
    
    $item = [
        'id' => $id,
        'name' => $name,
        'domain' => $domain,
        'object' => $object,
        'status' => $status,
        'owned_by' => $owned,
    ];
    
    $models[] = $item;
    
    if (!isset($grouped[$domain])) {
        $grouped[$domain] = [];
    }
    $grouped[$domain][] = $item;
}

// 按 domain 优先级排序（生图优先）
$domainOrder = ['ImageGeneration', 'VideoGeneration', '3DGeneration', 'VLM', 'LLM', 'Embedding', 'Router'];
uksort($grouped, function($a, $b) use ($domainOrder) {
    $pa = array_search($a, $domainOrder);
    $pb = array_search($b, $domainOrder);
    if ($pa === false && $pb === false) return strcmp($a, $b);
    if ($pa === false) return 1;
    if ($pb === false) return -1;
    return $pa - $pb;
});

echo json_encode([
    'code' => 200,
    'msg' => 'ok',
    'data' => [
        'total' => count($models),
        'models' => $models,
        'grouped' => $grouped,
        'domain_labels' => $domainLabels,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
