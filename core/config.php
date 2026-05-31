<?php
/**
 * ============================================
 * EduVN Manager — config.php
 * Cấu hình hệ thống
 *
 * KHI TRIỂN KHAI CHO TRƯỜNG MỚI:
 * Chỉ cần sửa file này, không đụng file nào khác
 * ============================================
 */

// ---- Thông tin trường ----
define('SCHOOL_NAME',        'Trường THCS & THPT TP. Sóc Trăng');
define('SCHOOL_SHORT',       'THCS&THPT Sóc Trăng');
define('SCHOOL_CODE',        'STG001');
define('SCHOOL_ADDRESS',     '123 Lê Lợi, Phường 1, TP. Sóc Trăng, Sóc Trăng');
define('SCHOOL_PHONE',       '0299 123 456');
define('SCHOOL_EMAIL',       'contact@thcsthpt-soctrang.edu.vn');
define('SCHOOL_YEAR',        '2024 – 2025');
define('SCHOOL_LOGO',        '/assets/logo.png');

// ---- Google OAuth ----
// Giáo viên đăng nhập bằng email @thcsthpt-soctrang.edu.vn
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_DOMAIN',        'thcsthpt-soctrang.edu.vn');

// ---- Giao diện ----
define('PRIMARY_COLOR',   '#1a6ef5');
define('ACCENT_COLOR',    '#00c6ae');

// ---- Bảo mật ----
// Đổi chuỗi này thành chuỗi ngẫu nhiên dài khi deploy thật
define('JWT_SECRET',      'eduvn-secret-key-change-this-in-production-2025');
define('JWT_EXPIRE',      60 * 60 * 24 * 7); // 7 ngày (giây)
define('BCRYPT_COST',     12);

// ---- Đường dẫn thư mục ----
define('ROOT_DIR',        dirname(__DIR__));          // /eduvn
define('CORE_DIR',        ROOT_DIR . '/core');
define('TEMPLATES_DIR',   ROOT_DIR . '/templates');
define('DATA_DIR',        ROOT_DIR . '/data');
define('UPLOAD_DIR',      ROOT_DIR . '/uploads');
define('PUBLIC_DIR',      ROOT_DIR . '/public');

// ---- Upload files ----
define('MAX_FILE_SIZE',   10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES',   ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png']);

// ---- Phân trang ----
define('PER_PAGE',        12);

// ---- Tính năng bật/tắt ----
define('FEATURE_GOOGLE_LOGIN',   true);
define('FEATURE_FILE_UPLOAD',    true);
define('FEATURE_EMAIL_NOTIFY',   false); // Bật khi có SMTP
define('FEATURE_EXPORT_EXCEL',   true);
define('FEATURE_EXPORT_PDF',     true);

// ---- Email (nếu FEATURE_EMAIL_NOTIFY = true) ----
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'noreply@thcsthpt-soctrang.edu.vn');
define('SMTP_PASS',       'YOUR_APP_PASSWORD');
define('SMTP_FROM_NAME',  SCHOOL_SHORT);

// ---- Môi trường ----
// 'development' hoặc 'production'
define('APP_ENV',         'development');
define('APP_DEBUG',       APP_ENV === 'development');

// ---- Cấu hình dạng array (dùng trong template PHP) ----
$config = [
    'name'          => SCHOOL_NAME,
    'short_name'    => SCHOOL_SHORT,
    'code'          => SCHOOL_CODE,
    'address'       => SCHOOL_ADDRESS,
    'phone'         => SCHOOL_PHONE,
    'email'         => SCHOOL_EMAIL,
    'school_year'   => SCHOOL_YEAR,
    'logo'          => SCHOOL_LOGO,
    'primary_color' => PRIMARY_COLOR,
    'accent_color'  => ACCENT_COLOR,
    'google_domain' => GOOGLE_DOMAIN,
];

// ---- Xử lý lỗi theo môi trường ----
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ---- Timezone ----
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ---- Session config ----
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
