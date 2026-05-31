<?php
/**
 * serve.php — Helper chạy PHP built-in server
 * Chạy từ thư mục gốc eduvn-php/:
 *   php serve.php
 * Sau đó truy cập: http://localhost:8000
 */

$host = 'localhost';
$port = 8000;
$root = __DIR__ . '/public';

echo "🚀 EduVN PHP Server\n";
echo "📁 Document root: {$root}\n";
echo "🌐 URL: http://{$host}:{$port}\n";
echo "⏹  Nhấn Ctrl+C để dừng\n\n";

passthru("php -S {$host}:{$port} -t {$root}");
