<?php
/**
 * ============================================
 * EduVN Manager — Helpers.php
 * Các hàm tiện ích dùng khắp nơi
 * ============================================
 */

// ============================================
// FORMAT DỮ LIỆU
// ============================================

/**
 * Format ngày tháng sang dạng Việt Nam
 * Ví dụ: formatDate('1986-05-12') → '12/05/1986'
 */
function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (!$date) return '—';
    try {
        return (new DateTime($date))->format($format);
    } catch (\Exception $e) {
        return '—';
    }
}

/**
 * Format ngày giờ
 * Ví dụ: formatDateTime('2025-01-21T07:30:00+07:00') → '07:30 21/01/2025'
 */
function formatDateTime(?string $datetime): string
{
    if (!$datetime) return '—';
    try {
        return (new DateTime($datetime))->format('H:i d/m/Y');
    } catch (\Exception $e) {
        return '—';
    }
}

/**
 * Thời gian tương đối
 * Ví dụ: timeAgo('2025-01-21T06:00:00') → '1 giờ trước'
 */
function timeAgo(string $datetime): string
{
    try {
        $diff = time() - (new DateTime($datetime))->getTimestamp();
    } catch (\Exception $e) {
        return '—';
    }

    if ($diff < 60)         return 'Vừa xong';
    if ($diff < 3600)       return (int)($diff / 60) . ' phút trước';
    if ($diff < 86400)      return (int)($diff / 3600) . ' giờ trước';
    if ($diff < 604800)     return (int)($diff / 86400) . ' ngày trước';
    if ($diff < 2592000)    return (int)($diff / 604800) . ' tuần trước';
    if ($diff < 31536000)   return (int)($diff / 2592000) . ' tháng trước';
    return (int)($diff / 31536000) . ' năm trước';
}

/**
 * Tính số năm công tác
 * Ví dụ: yearsOfService('2010-08-01') → 15
 */
function yearsOfService(?string $joinDate): int
{
    if (!$joinDate) return 0;
    try {
        return (new DateTime($joinDate))->diff(new DateTime())->y;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * Format số byte thành KB/MB
 * Ví dụ: formatFileSize(1048576) → '1.0 MB'
 */
function formatFileSize(int $bytes): string
{
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1048576)     return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ============================================
// BẢO MẬT
// ============================================

/**
 * Escape HTML — luôn dùng khi output dữ liệu từ DB ra HTML
 * Ví dụ: e($teacher['personal']['full_name'])
 */
function e(?string $str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Lấy input từ GET/POST đã được sanitize
 * Ví dụ: input('q') → $_GET['q'] hoặc $_POST['q']
 */
function input(string $key, mixed $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return trim((string)$val);
}

/**
 * Lấy JSON body từ request (dùng trong API)
 */
function jsonBody(): array
{
    static $body = null;
    if ($body === null) {
        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];
    }
    return $body;
}

/**
 * Lấy 1 field từ JSON body
 */
function bodyInput(string $key, mixed $default = null): mixed
{
    return jsonBody()[$key] ?? $default;
}

// ============================================
// VALIDATE
// ============================================

/**
 * Validate dữ liệu theo rules
 *
 * Ví dụ:
 * $errors = validate($_POST, [
 *   'email'    => 'required|email',
 *   'name'     => 'required|min:2|max:100',
 *   'phone'    => 'regex:/^0[0-9]{9}$/',
 * ]);
 */
function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $ruleStr) {
        $ruleList = explode('|', $ruleStr);
        $value    = trim((string)($data[$field] ?? ''));

        foreach ($ruleList as $rule) {
            // required
            if ($rule === 'required' && $value === '') {
                $errors[$field] = "Trường này là bắt buộc.";
                break;
            }

            if ($value === '') continue; // Bỏ qua các rule khác nếu rỗng và không required

            // email
            if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Email không hợp lệ.";
                break;
            }

            // min:N
            if (str_starts_with($rule, 'min:')) {
                $min = (int)substr($rule, 4);
                if (mb_strlen($value) < $min) {
                    $errors[$field] = "Tối thiểu {$min} ký tự.";
                    break;
                }
            }

            // max:N
            if (str_starts_with($rule, 'max:')) {
                $max = (int)substr($rule, 4);
                if (mb_strlen($value) > $max) {
                    $errors[$field] = "Tối đa {$max} ký tự.";
                    break;
                }
            }

            // regex:/pattern/
            if (str_starts_with($rule, 'regex:')) {
                $pattern = substr($rule, 6);
                if (!preg_match($pattern, $value)) {
                    $errors[$field] = "Giá trị không đúng định dạng.";
                    break;
                }
            }

            // numeric
            if ($rule === 'numeric' && !is_numeric($value)) {
                $errors[$field] = "Phải là số.";
                break;
            }

            // date
            if ($rule === 'date') {
                $d = DateTime::createFromFormat('Y-m-d', $value);
                if (!$d || $d->format('Y-m-d') !== $value) {
                    $errors[$field] = "Ngày không hợp lệ (YYYY-MM-DD).";
                    break;
                }
            }
        }
    }

    return $errors;
}

// ============================================
// TIỆN ÍCH KHÁC
// ============================================

/**
 * Redirect
 * Ví dụ: redirect('/dashboard.php')
 */
function redirect(string $url, int $code = 302): never
{
    header("Location: {$url}", true, $code);
    exit;
}

/**
 * Lấy URL hiện tại (không có query string)
 */
function currentUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
}

/**
 * Tính % hoàn thành hồ sơ giáo viên
 * Dựa trên các mục đã điền
 */
function calcCompletion(array $teacher): int
{
    $sections = [
        'personal' => ['full_name', 'dob', 'gender', 'cccd', 'phone'],
        'work'     => ['dept_id', 'subject', 'rank', 'join_date'],
        'education'=> ['degree', 'major', 'institution', 'grad_year'],
        'family'   => ['marital_status'],
    ];

    $total   = 0;
    $filled  = 0;

    foreach ($sections as $section => $fields) {
        foreach ($fields as $field) {
            $total++;
            $val = $teacher[$section][$field] ?? null;
            if (!empty($val)) $filled++;
        }
    }

    // Cộng thêm nếu có chứng chỉ và thành tích
    $total  += 2;
    if (!empty($teacher['certificates']))  $filled++;
    if (!empty($teacher['achievements']))  $filled++;

    return $total > 0 ? (int)round($filled / $total * 100) : 0;
}

/**
 * Tạo breadcrumb HTML
 *
 * Ví dụ:
 * breadcrumb([
 *   'Trang chủ' => '/dashboard.php',
 *   'Giáo viên' => '/teachers.php',
 *   'Nguyễn Thị Hương' => null,
 * ])
 */
function breadcrumb(array $items): string
{
    $parts = [];
    $last  = array_key_last($items);

    foreach ($items as $label => $url) {
        if ($label === $last || $url === null) {
            $parts[] = '<span class="bc-current">' . e($label) . '</span>';
        } else {
            $parts[] = '<a href="' . e($url) . '" class="bc-link">' . e($label) . '</a>';
        }
    }

    return '<nav class="breadcrumb">' . implode('<i class="fas fa-chevron-right bc-sep"></i>', $parts) . '</nav>';
}

/**
 * Flash message (lưu qua redirect)
 * set: flash('success', 'Lưu thành công!')
 * get: $msg = flash()
 */
function flash(?string $type = null, ?string $message = null): mixed
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    if ($type && $message) {
        // Set flash
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }

    // Get and clear flash
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Tạo pagination HTML
 */
function paginationHtml(array $pag, string $baseUrl): string
{
    if ($pag['last_page'] <= 1) return '';

    $html  = '<div class="pagination-wrap">';
    $html .= '<div class="pagination-info">Hiển thị ' . $pag['from'] . '–' . $pag['to'] . ' / ' . $pag['total'] . ' giáo viên</div>';
    $html .= '<div class="pagination">';

    // Prev
    $prevDisabled = $pag['current_page'] <= 1 ? 'disabled' : '';
    $html .= "<a href='{$baseUrl}&page=" . ($pag['current_page'] - 1) . "' class='page-btn {$prevDisabled}'><i class='fas fa-chevron-left' style='font-size:11px'></i></a>";

    // Pages
    for ($i = 1; $i <= $pag['last_page']; $i++) {
        if ($i === 1 || $i === $pag['last_page'] || abs($i - $pag['current_page']) <= 1) {
            $active = $i === $pag['current_page'] ? 'active' : '';
            $html  .= "<a href='{$baseUrl}&page={$i}' class='page-btn {$active}'>{$i}</a>";
        } elseif (abs($i - $pag['current_page']) === 2) {
            $html .= "<span class='page-btn' style='cursor:default'>…</span>";
        }
    }

    // Next
    $nextDisabled = $pag['current_page'] >= $pag['last_page'] ? 'disabled' : '';
    $html .= "<a href='{$baseUrl}&page=" . ($pag['current_page'] + 1) . "' class='page-btn {$nextDisabled}'><i class='fas fa-chevron-right' style='font-size:11px'></i></a>";

    $html .= '</div></div>';
    return $html;
}

/**
 * Status badge HTML
 * Ví dụ: statusBadge('complete') → <span class="badge badge-success">Hoàn thành</span>
 */
function statusBadge(string $status): string
{
    $map = [
        'complete'   => ['badge-success', 'Hoàn thành'],
        'pending'    => ['badge-warning', 'Chưa cập nhật'],
        'processing' => ['badge-info',    'Đang xử lý'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-neutral', $status];
    return "<span class='badge {$cls}'>{$label}</span>";
}
