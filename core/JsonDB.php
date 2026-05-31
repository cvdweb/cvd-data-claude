<?php
/**
 * ============================================
 * EduVN Manager — JsonDB.php
 * Class đọc/ghi dữ liệu từ file JSON
 *
 * Hoạt động như một database đơn giản:
 * - Mỗi file JSON = 1 bảng
 * - Mỗi object trong array = 1 dòng
 * ============================================
 */

class JsonDB
{
    private string $dataDir;
    private array  $cache = []; // Cache trong 1 request, tránh đọc file nhiều lần

    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/');

        // Tạo thư mục nếu chưa có
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    // ============================================
    // ĐỌC DỮ LIỆU
    // ============================================

    /**
     * Đọc toàn bộ 1 bảng
     * Ví dụ: $db->read('teachers') → đọc teachers.json
     */
    public function read(string $table): array
    {
        // Dùng cache nếu đã đọc rồi
        if (isset($this->cache[$table])) {
            return $this->cache[$table];
        }

        $file = "{$this->dataDir}/{$table}.json";

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if (!$content) return [];

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("File {$table}.json bị lỗi JSON: " . json_last_error_msg());
        }

        // Hỗ trợ cả 2 format: [{...}] hoặc {"data":[{...}]}
        $data = isset($decoded['data']) ? $decoded['data'] : $decoded;

        $this->cache[$table] = $data;
        return $data;
    }

    /**
     * Tìm 1 record theo ID
     * Ví dụ: $db->find('teachers', 'tchr_001')
     */
    public function find(string $table, string $id): ?array
    {
        $all = $this->read($table);
        foreach ($all as $row) {
            if (($row['id'] ?? '') === $id) return $row;
        }
        return null;
    }

    /**
     * Tìm nhiều record theo 1 điều kiện
     * Ví dụ: $db->where('teachers', 'status', 'pending')
     *
     * Hỗ trợ nested key bằng dấu chấm:
     * $db->where('teachers', 'work.dept_id', 'dept_001')
     */
    public function where(string $table, string $field, mixed $value): array
    {
        return array_values(array_filter(
            $this->read($table),
            fn($row) => $this->getNestedValue($row, $field) === $value
        ));
    }

    /**
     * Tìm theo nhiều điều kiện (AND)
     * Ví dụ: $db->filter('teachers', ['status'=>'complete', 'work.dept_id'=>'dept_001'])
     */
    public function filter(string $table, array $conditions): array
    {
        return array_values(array_filter(
            $this->read($table),
            function ($row) use ($conditions) {
                foreach ($conditions as $field => $value) {
                    if ($this->getNestedValue($row, $field) !== $value) {
                        return false;
                    }
                }
                return true;
            }
        ));
    }

    /**
     * Tìm kiếm text (không phân biệt hoa thường)
     * Ví dụ: $db->search('teachers', ['personal.full_name','work.subject'], 'Hương')
     */
    public function search(string $table, array $fields, string $keyword): array
    {
        if (empty(trim($keyword))) return $this->read($table);

        $keyword = mb_strtolower(trim($keyword));

        return array_values(array_filter(
            $this->read($table),
            function ($row) use ($fields, $keyword) {
                foreach ($fields as $field) {
                    $val = mb_strtolower((string)($this->getNestedValue($row, $field) ?? ''));
                    if (str_contains($val, $keyword)) return true;
                }
                return false;
            }
        ));
    }

    /**
     * Đếm số record
     * Ví dụ: $db->count('teachers') hoặc $db->count('teachers', ['status'=>'complete'])
     */
    public function count(string $table, array $conditions = []): int
    {
        if (empty($conditions)) {
            return count($this->read($table));
        }
        return count($this->filter($table, $conditions));
    }

    // ============================================
    // SẮP XẾP & PHÂN TRANG
    // ============================================

    /**
     * Sắp xếp array
     * Ví dụ: $db->orderBy($data, 'personal.full_name', 'asc')
     */
    public function orderBy(array $data, string $field, string $direction = 'asc'): array
    {
        usort($data, function ($a, $b) use ($field, $direction) {
            $va = $this->getNestedValue($a, $field) ?? '';
            $vb = $this->getNestedValue($b, $field) ?? '';
            $cmp = is_numeric($va) && is_numeric($vb)
                ? $va <=> $vb
                : strcmp((string)$va, (string)$vb);
            return $direction === 'desc' ? -$cmp : $cmp;
        });
        return $data;
    }

    /**
     * Phân trang
     * Trả về data + thông tin trang
     */
    public function paginate(array $data, int $page = 1, int $perPage = PER_PAGE): array
    {
        $page    = max(1, $page);
        $total   = count($data);
        $pages   = (int)ceil($total / $perPage);
        $offset  = ($page - 1) * $perPage;

        return [
            'data'         => array_slice($data, $offset, $perPage),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, $pages),
            'from'         => $total > 0 ? $offset + 1 : 0,
            'to'           => min($offset + $perPage, $total),
        ];
    }

    // ============================================
    // GHI DỮ LIỆU
    // ============================================

    /**
     * Thêm record mới
     * ID tự động tạo nếu không truyền vào
     */
    public function insert(string $table, array $record): array
    {
        $data = $this->read($table);

        // Auto-generate ID dạng: tchr_6507f1f4c9 (prefix + uniqid)
        if (empty($record['id'])) {
            $prefix = substr($table, 0, 4);
            $record['id'] = $prefix . '_' . uniqid();
        }

        $record['created_at'] = date('c'); // ISO 8601
        $record['updated_at'] = date('c');

        $data[] = $record;
        $this->write($table, $data);

        return $record;
    }

    /**
     * Cập nhật record theo ID
     * Chỉ update các field được truyền vào, giữ nguyên phần còn lại
     */
    public function update(string $table, string $id, array $changes): ?array
    {
        $data  = $this->read($table);
        $found = false;

        foreach ($data as &$row) {
            if ($row['id'] === $id) {
                // Deep merge: giữ nguyên nested object không được truyền
                $row = $this->deepMerge($row, $changes);
                $row['updated_at'] = date('c');
                $found = true;
                break;
            }
        }
        unset($row);

        if (!$found) return null;

        $this->write($table, $data);
        return $this->find($table, $id);
    }

    /**
     * Xóa record theo ID
     */
    public function delete(string $table, string $id): bool
    {
        $data     = $this->read($table);
        $filtered = array_filter($data, fn($row) => $row['id'] !== $id);

        if (count($filtered) === count($data)) return false; // Không tìm thấy

        $this->write($table, array_values($filtered));
        return true;
    }

    /**
     * Ghi toàn bộ data vào file (có file lock tránh conflict)
     */
    public function write(string $table, array $data): bool
    {
        $file = "{$this->dataDir}/{$table}.json";

        $payload = json_encode(
            [
                'meta' => [
                    'table'      => $table,
                    'total'      => count($data),
                    'updated_at' => date('c'),
                    'version'    => '1.0',
                ],
                'data' => array_values($data),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // File lock: tránh 2 request ghi cùng lúc làm hỏng file
        $fp = fopen($file, 'c');
        if (!$fp) return false;

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        // Xóa cache để lần đọc sau lấy data mới
        unset($this->cache[$table]);

        return true;
    }

    // ============================================
    // TIỆN ÍCH
    // ============================================

    /**
     * Backup toàn bộ data
     * Gọi từ cron job hàng ngày
     */
    public function backup(string $backupDir): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $dir       = "{$backupDir}/{$timestamp}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (glob("{$this->dataDir}/*.json") as $file) {
            copy($file, $dir . '/' . basename($file));
        }

        return $dir; // Trả về đường dẫn backup
    }

    /**
     * Lấy giá trị nested bằng dot notation
     * Ví dụ: getNestedValue($teacher, 'personal.full_name')
     *        → $teacher['personal']['full_name']
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $val  = $data;

        foreach ($keys as $k) {
            if (!is_array($val) || !array_key_exists($k, $val)) {
                return null;
            }
            $val = $val[$k];
        }

        return $val;
    }

    /**
     * Deep merge: merge đệ quy 2 array
     * Khác array_merge: giữ nguyên nested key không bị ghi đè
     */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Xóa cache (dùng khi test)
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
