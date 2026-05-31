<?php
/**
 * _path.php — Tự động tìm đường dẫn đúng đến thư mục core/
 * Đặt trong public/ — được include đầu tiên bởi mọi trang
 *
 * Hỗ trợ cả 2 cấu trúc:
 *   XAMPP: htdocs/eduvn-php/public/dashboard.php → core/ ở htdocs/eduvn-php/core/
 *   VPS:   /var/www/eduvn/public/ → core/ ở /var/www/eduvn/core/
 */

// Thư mục chứa public/ (tức là thư mục gốc project)
define('PROJECT_ROOT', dirname(__DIR__));

// Kiểm tra core/ có tồn tại không
if (!is_dir(PROJECT_ROOT . '/core')) {
    die('❌ Không tìm thấy thư mục core/. Cấu trúc thư mục không đúng.<br>
        Đảm bảo cấu trúc như sau:<br>
        <pre>eduvn-php/
├── core/
├── data/
├── templates/
├── uploads/
└── public/  ← Document root (htdocs/eduvn-php/public/)</pre>');
}

require_once PROJECT_ROOT . '/core/bootstrap.php';
