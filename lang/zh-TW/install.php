<?php

return [
    'checks' => [
        'comment' => '備註',
        'item' => '項目',
        'php_required' => '需要 :version 或更高版本',
        'status' => '狀態',
        'title' => '安裝前檢查',
    ],
    'database' => [
        'credentials' => '資料庫憑證',
        'host' => '主機',
        'host_placeholder' => '使用 localhost 作為 Unix-Socket',
        'name' => '資料庫名稱',
        'password' => '密碼',
        'port' => '連接埠',
        'port_placeholder' => '使用 Unix-Socket 時留空',
        'socket' => 'Unix-Socket',
        'socket_placeholder' => '僅用於自訂 socket 路徑',
        'test' => '檢查憑證',
        'title' => '設定資料庫',
        'username' => '使用者',
    ],
    'finish' => [
        'config_exists' => 'config.php 檔案已存在',
        'config_not_required' => '這個檔案非必需，這是預設內容。',
        'config_not_written' => '無法寫入 config.php',
        'config_written' => 'config.php 檔案已寫入',
        'copied' => '已複製到剪貼簿',
        'dashboard' => '資訊看板',
        'env_manual' => '手動更新 :file 檔案，內容如下',
        'env_not_written' => '無法寫入 .env 檔案',
        'env_written' => '.env 檔案已寫入',
        'failed' => '無法儲存 .env',
        'finish' => '完成安裝',
        'manual_copy' => '按 Ctrl-C 複製',
        'retry' => '重試',
        'settings' => '附加設定',
        'success' => '安裝完成',
        'thanks' => '感謝您安裝 LibreNMS。',
        'title' => '完成安裝',
        'validate_button' => '驗證安裝',
    ],
    'install' => '安裝',
    'migrate' => [
        'building_interrupt' => '請勿關閉此頁面或中斷匯入！',
        'error' => '發生錯誤，請由輸出訊息檢視細節。',
        'migrate' => '建立資料庫',
        'retry' => '重試',
        'timeout' => 'HTTP 請求逾時，您的資料庫結構可能不一致。',
        'wait' => '請稍候...',
    ],
    'steps' => [
        'checks' => '安裝前檢查',
        'database' => '資料庫',
        'finish' => '完成安裝',
        'migrate' => '建立資料庫',
        'user' => '建立使用者',
    ],
    'title' => 'LibreNMS 安裝',
    'user' => [
        'button' => '新增使用者',
        'created' => '使用者已建立',
        'email' => '電子郵件',
        'failure' => '建立使用者失敗',
        'password' => '密碼',
        'success' => '建立使用者成功',
        'title' => '建立管理員使用者',
        'username' => '使用者名稱',
    ],
];