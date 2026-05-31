<?php
/**
 * ============================================
 * EduVN Manager — Auth.php
 * Xác thực người dùng, phân quyền
 * ============================================
 */

class Auth
{
    private JsonDB $db;

    // Nhãn hiển thị cho từng role
    const ROLE_LABELS = [
        'admin'     => 'Ban Giám hiệu',
        'dept_head' => 'Tổ trưởng',
        'teacher'   => 'Giáo viên',
    ];

    public function __construct(JsonDB $db)
    {
        $this->db = $db;
    }

    // ============================================
    // ĐĂNG NHẬP / ĐĂNG XUẤT
    // ============================================

    /**
     * Đăng nhập bằng email + password
     * Trả về token nếu thành công, null nếu sai
     */
    public function login(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));

        // Tìm user theo email
        $users = $this->db->where('users', 'email', $email);
        if (empty($users)) return null;

        $user = $users[0];

        // Kiểm tra tài khoản có bị khóa không
        if (!($user['is_active'] ?? true)) {
            return null;
        }

        // Kiểm tra mật khẩu
        if (!password_verify($password, $user['password'] ?? '')) {
            return null;
        }

        // Cập nhật last_login
        $this->db->update('users', $user['id'], [
            'last_login' => date('c'),
            'login_count' => ($user['login_count'] ?? 0) + 1,
        ]);

        // Tạo token và lưu vào session
        $token = $this->createToken($user);
        $this->setSession($user, $token);

        return [
            'token' => $token,
            'user'  => $this->formatUser($user),
        ];
    }

    /**
     * Đăng xuất
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        session_destroy();
    }

    // ============================================
    // KIỂM TRA ĐĂNG NHẬP (dùng trong mỗi trang)
    // ============================================

    /**
     * Lấy thông tin user đang đăng nhập
     * Trả về null nếu chưa đăng nhập
     */
    public function user(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        // Refresh data từ DB (tránh dữ liệu cũ)
        $user = $this->db->find('users', $_SESSION['user_id']);
        if (!$user || !($user['is_active'] ?? true)) {
            $this->logout();
            return null;
        }

        return $this->formatUser($user);
    }

    /**
     * Bắt buộc đăng nhập — redirect về login nếu chưa
     * Dùng ở đầu mỗi trang cần đăng nhập
     *
     * Ví dụ: $currentUser = $auth->requireLogin();
     */
    public function requireLogin(): array
    {
        $user = $this->user();
        if (!$user) {
            $this->redirectToLogin();
        }
        return $user;
    }

    /**
     * Bắt buộc role cụ thể — redirect 403 nếu không đủ quyền
     *
     * Ví dụ: $auth->requireRole(['admin', 'dept_head']);
     */
    public function requireRole(array $roles): array
    {
        $user = $this->requireLogin();
        if (!in_array($user['role'], $roles)) {
            $this->forbidden();
        }
        return $user;
    }

    /**
     * Kiểm tra user có role không (không redirect)
     *
     * Ví dụ: if ($auth->hasRole('admin')) { ... }
     */
    public function hasRole(string ...$roles): bool
    {
        $user = $this->user();
        if (!$user) return false;
        return in_array($user['role'], $roles);
    }

    /**
     * Kiểm tra đang đăng nhập không
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    // ============================================
    // QUẢN LÝ TÀI KHOẢN
    // ============================================

    /**
     * Tạo tài khoản mới
     */
    public function createUser(array $data): array
    {
        // Kiểm tra email đã tồn tại chưa
        $existing = $this->db->where('users', 'email', strtolower($data['email']));
        if (!empty($existing)) {
            throw new \InvalidArgumentException('Email đã tồn tại trong hệ thống.');
        }

        $user = [
            'email'       => strtolower(trim($data['email'])),
            'password'    => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'role'        => $data['role'] ?? 'teacher',
            'teacher_id'  => $data['teacher_id'] ?? null,
            'dept_id'     => $data['dept_id'] ?? null,
            'is_active'   => true,
            'login_count' => 0,
            'last_login'  => null,
        ];

        return $this->db->insert('users', $user);
    }

    /**
     * Đổi mật khẩu
     */
    public function changePassword(string $userId, string $oldPass, string $newPass): bool
    {
        $user = $this->db->find('users', $userId);
        if (!$user) return false;

        if (!password_verify($oldPass, $user['password'])) {
            return false; // Mật khẩu cũ sai
        }

        $this->db->update('users', $userId, [
            'password' => password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
        ]);

        return true;
    }

    /**
     * Reset mật khẩu (BGH dùng)
     */
    public function resetPassword(string $userId, string $newPass): bool
    {
        $user = $this->db->find('users', $userId);
        if (!$user) return false;

        $this->db->update('users', $userId, [
            'password'             => password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'must_change_password' => true,
        ]);

        return true;
    }

    /**
     * Khóa / mở tài khoản
     */
    public function toggleActive(string $userId, bool $active): bool
    {
        return (bool)$this->db->update('users', $userId, ['is_active' => $active]);
    }

    // ============================================
    // TOKEN (JWT đơn giản, không cần thư viện)
    // ============================================

    /**
     * Tạo JWT token
     */
    public function createToken(array $user): string
    {
        $header  = $this->base64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64url(json_encode([
            'sub'     => $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
            'dept_id' => $user['dept_id'] ?? null,
            'iat'     => time(),
            'exp'     => time() + JWT_EXPIRE,
        ]));
        $sig = $this->base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        return "$header.$payload.$sig";
    }

    /**
     * Xác thực JWT token
     * Trả về payload nếu hợp lệ, null nếu không
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;

        // Kiểm tra chữ ký
        $expected = $this->base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
        if (!hash_equals($expected, $sig)) return null;

        // Giải mã payload
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!$data) return null;

        // Kiểm tra hết hạn
        if (($data['exp'] ?? 0) < time()) return null;

        return $data;
    }

    /**
     * Middleware cho API: lấy user từ Bearer token
     * Dùng trong api/*.php
     *
     * Ví dụ: $user = $auth->apiGuard(['admin','dept_head']);
     */
    public function apiGuard(array $allowedRoles = []): array
    {
        // Lấy token từ header Authorization: Bearer xxx
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? apache_request_headers()['Authorization']
            ?? '';

        $token = str_replace('Bearer ', '', $authHeader);

        if (empty($token)) {
            $this->apiError('Chưa đăng nhập', 401);
        }

        $payload = $this->verifyToken($token);
        if (!$payload) {
            $this->apiError('Token không hợp lệ hoặc đã hết hạn', 401);
        }

        if (!empty($allowedRoles) && !in_array($payload['role'], $allowedRoles)) {
            $this->apiError('Không có quyền truy cập', 403);
        }

        return $payload;
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    private function setSession(array $user, string $token): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true); // Chống session fixation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['token']   = $token;
    }

    private function formatUser(array $user): array
    {
        // Lấy thêm thông tin giáo viên nếu có
        $teacher = null;
        if (!empty($user['teacher_id'])) {
            $teacher = $this->db->find('teachers', $user['teacher_id']);
        }

        $name   = $teacher['personal']['full_name'] ?? 'Người dùng';
        $avatar = (!empty($teacher['avatar_url']))
            ? $teacher['avatar_url']
            : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=150';

        return [
            'id'         => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'role_label' => self::ROLE_LABELS[$user['role']] ?? $user['role'],
            'dept_id'    => $user['dept_id'] ?? null,
            'name'       => $name,
            'avatar'     => $avatar,
            'teacher_id' => $user['teacher_id'] ?? null,
            'is_active'  => $user['is_active'] ?? true,
            'last_login' => $user['last_login'] ?? null,
            'must_change_password' => $user['must_change_password'] ?? false,
        ];
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function redirectToLogin(): never
    {
        $current = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: /index.php?redirect={$current}");
        exit;
    }

    private function forbidden(): never
    {
        http_response_code(403);
        include TEMPLATES_DIR . '/errors/403.php';
        exit;
    }

    private function apiError(string $message, int $code): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => $message,
            'code'    => $code,
        ]);
        exit;
    }
}
