# EduVN Manager — PHP Version

## Yêu cầu hệ thống
- PHP >= 8.0
- Apache/Nginx với mod_rewrite
- Không cần MySQL/Database

## Cấu trúc thư mục
```
eduvn-php/
├── core/        ← Logic PHP (không public)
├── data/        ← JSON database (không public)
├── templates/   ← Template PHP dùng chung
├── uploads/     ← File upload (không public)
└── public/      ← Document root (public_html)
    ├── index.php
    ├── dashboard.php
    └── ...
```

## Cài đặt trên localhost (XAMPP/Laragon)

### Cách 1: Dùng Laragon (khuyến nghị)
1. Giải nén vào thư mục `C:\laragon\www\eduvn\`
2. Cấu hình Virtual Host trỏ vào `eduvn/public/`
3. Truy cập: http://eduvn.test

### Cách 2: Dùng XAMPP
1. Giải nén vào `C:\xampp\htdocs\eduvn\`
2. Truy cập: http://localhost/eduvn/public/

### Cách 3: PHP Built-in server
```bash
cd eduvn-php/public
php -S localhost:8000
```
Truy cập: http://localhost:8000

## Tài khoản demo (mật khẩu: password)
- BGH:        bgiamdoc@thcsthpt-soctrang.edu.vn
- Tổ trưởng:  totruong.toan@thcsthpt-soctrang.edu.vn
- Giáo viên:  huong.nguyen@thcsthpt-soctrang.edu.vn

## Lưu ý quan trọng
- Cấu hình trường trong: core/config.php
- Dữ liệu lưu trong: data/*.json
- Thư mục `public/` là document root duy nhất public
- Thư mục `core/`, `data/`, `templates/` phải nằm NGOÀI public
