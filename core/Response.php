<?php
/**
 * ============================================
 * EduVN Manager — Response.php
 * Chuẩn hóa JSON response cho API
 * ============================================
 */

class Response
{
    /**
     * Trả về JSON thành công
     *
     * Ví dụ: Response::success($teachers, 'Lấy dữ liệu thành công')
     */
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Trả về JSON lỗi
     *
     * Ví dụ: Response::error('Không tìm thấy giáo viên', 404)
     */
    public static function error(string $message, int $code = 400, mixed $errors = null): never
    {
        $body = [
            'success' => false,
            'error'   => $message,
            'code'    => $code,
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        self::json($body, $code);
    }

    /**
     * Trả về danh sách có phân trang
     */
    public static function paginated(array $paginateResult, string $message = 'OK'): never
    {
        self::json([
            'success'      => true,
            'message'      => $message,
            'data'         => $paginateResult['data'],
            'pagination'   => [
                'total'        => $paginateResult['total'],
                'per_page'     => $paginateResult['per_page'],
                'current_page' => $paginateResult['current_page'],
                'last_page'    => $paginateResult['last_page'],
                'from'         => $paginateResult['from'],
                'to'           => $paginateResult['to'],
            ],
        ]);
    }

    /**
     * Gửi JSON response thực sự
     */
    private static function json(array $body, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        // Cho phép CORS trong development
        if (APP_DEBUG) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
