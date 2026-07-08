<?php
/**
 * 火山AI生图 - 数据库配置
 */
define('DB_PATH', __DIR__ . '/../data/app.db');
define('SETTINGS_FILE', __DIR__ . '/../data/settings.json');

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
    }
    return $db;
}

// 从 settings.json 读取火山引擎配置（支持后台网页配置）
function getVolcSettings() {
    if (!file_exists(SETTINGS_FILE)) {
        return ['volc_api_token' => '', 'volc_model' => 'doubao-vision-pro-32k'];
    }
    return json_decode(file_get_contents(SETTINGS_FILE), true) ?: [];
}

$volcSettings = getVolcSettings();
define('VOLC_API_TOKEN', $volcSettings['volc_api_token'] ?? '');
define('VOLC_MODEL', $volcSettings['volc_model'] ?? 'doubao-vision-pro-32k');
define('VOLC_API_BASE', 'https://ark.cn-beijing.volces.com/api/v3');
