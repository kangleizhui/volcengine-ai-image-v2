<?php
/**
 * 火山AI生图 - 支付配置
 */
define('PAY_DB_PATH', __DIR__ . '/../data/app.db');

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(PAY_DB_PATH);
        $db->exec('PRAGMA journal_mode=WAL');
    }
    return $db;
}
