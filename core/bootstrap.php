<?php
/**
 * ============================================
 * EduVN Manager — bootstrap.php
 * File này được include ở đầu MỌI trang PHP
 *
 * Nó load tất cả những gì cần thiết:
 * config, class, khởi tạo DB và Auth
 * ============================================
 */

// ---- Load config (phải đầu tiên) ----
require_once __DIR__ . '/config.php';

// ---- Load các class core ----
require_once __DIR__ . '/JsonDB.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Helpers.php';

// ---- Khởi tạo database và auth ----
// $db và $auth có sẵn trong MỌI trang sau khi include bootstrap.php
$db   = new JsonDB(DATA_DIR);
$auth = new Auth($db);

// ---- Xử lý OPTIONS request (CORS preflight) ----
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
