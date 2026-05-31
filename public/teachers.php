<?php
/**
 * teachers.php — Danh sách hồ sơ giáo viên
 */
require_once __DIR__ . '/_path.php';

// Phân quyền: BGH và Tổ trưởng mới xem được
$currentUser = $auth->requireRole(['admin', 'dept_head']);

// ---- Lấy dữ liệu ----
$allTeachers  = $db->read('teachers');
$departments  = $db->read('departments');
$notifs       = $db->where('notifications', 'user_id', $currentUser['id']);
$openRequestCount = $db->count('requests', ['status' => 'pending']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));

// ---- Tổ trưởng chỉ thấy GV của tổ mình ----
if ($currentUser['role'] === 'dept_head') {
    $allTeachers = array_filter(
        $allTeachers,
        fn($t) => ($t['work']['dept_id'] ?? '') === $currentUser['dept_id']
    );
    $allTeachers = array_values($allTeachers);
}

// ---- Tìm kiếm & Lọc ----
$q      = input('q');
$status = input('status');
$deptId = input('dept');
$degree = input('degree');
$view   = input('view', 'card'); // card | table
$page   = max(1, (int)input('page', 1));

$filtered = $allTeachers;

// Tìm kiếm theo tên / môn
if ($q !== '') {
    $kw       = mb_strtolower($q);
    $filtered = array_filter($filtered, function ($t) use ($kw) {
        return str_contains(mb_strtolower($t['personal']['full_name'] ?? ''), $kw)
            || str_contains(mb_strtolower($t['work']['subject'] ?? ''), $kw)
            || str_contains(mb_strtolower($t['personal']['email'] ?? ''), $kw);
    });
}

// Lọc theo trạng thái
if ($status !== '') {
    $filtered = array_filter($filtered, fn($t) => ($t['status'] ?? '') === $status);
}

// Lọc theo tổ
if ($deptId !== '') {
    $filtered = array_filter($filtered, fn($t) => ($t['work']['dept_id'] ?? '') === $deptId);
}

// Lọc theo trình độ
if ($degree !== '') {
    $filtered = array_filter($filtered, fn($t) => ($t['education']['degree'] ?? '') === $degree);
}

$filtered = $db->orderBy(array_values($filtered), 'personal.full_name');

// Thống kê sau lọc
$statTotal      = count($filtered);
$statComplete   = count(array_filter($filtered, fn($t) => ($t['status'] ?? '') === 'complete'));
$statPending    = count(array_filter($filtered, fn($t) => ($t['status'] ?? '') === 'pending'));
$statProcessing = count(array_filter($filtered, fn($t) => ($t['status'] ?? '') === 'processing'));

// Phân trang
$paginated = $db->paginate($filtered, $page, PER_PAGE);
$teachers  = $paginated['data'];

// Xây dựng base URL cho phân trang (giữ các filter hiện tại)
$baseUrl = '/teachers.php?' . http_build_query(array_filter([
    'q'      => $q,
    'status' => $status,
    'dept'   => $deptId,
    'degree' => $degree,
    'view'   => $view,
]));

// ---- Metadata trang ----
$pageTitle    = 'Hồ sơ giáo viên';
$pageSubtitle = 'Danh sách và quản lý hồ sơ ' . count($allTeachers) . ' giáo viên';
$activePage   = 'teachers.php';

// Màu trạng thái
$statusCss = [
    'complete'   => ['badge-success', 'Hoàn thành',    '#22c55e'],
    'pending'    => ['badge-warning', 'Chưa cập nhật', '#f59e0b'],
    'processing' => ['badge-info',    'Đang xử lý',    '#3b82f6'],
];

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- ===================================
     TOOLBAR
     =================================== -->
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px">

  <!-- Tìm kiếm -->
  <form method="GET" action="/teachers.php"
        style="display:contents" id="filterForm">
    <input type="hidden" name="view" value="<?= e($view) ?>">

    <div class="toolbar-search" style="flex:1;min-width:220px">
      <i class="fas fa-search"></i>
      <input type="text" name="q" value="<?= e($q) ?>"
             placeholder="Tìm theo tên, môn dạy..."
             onchange="this.form.submit()">
    </div>

    <select name="dept" class="form-control" style="width:auto;min-width:160px"
            onchange="this.form.submit()">
      <option value="">Tất cả tổ</option>
      <?php foreach ($departments as $dept): ?>
        <option value="<?= e($dept['id']) ?>"
                <?= $deptId === $dept['id'] ? 'selected' : '' ?>>
          <?= e($dept['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="status" class="form-control" style="width:auto;min-width:160px"
            onchange="this.form.submit()">
      <option value="">Tất cả trạng thái</option>
      <option value="complete"   <?= $status === 'complete'   ? 'selected' : '' ?>>Hoàn thành</option>
      <option value="pending"    <?= $status === 'pending'    ? 'selected' : '' ?>>Chưa cập nhật</option>
      <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Đang xử lý</option>
    </select>

    <select name="degree" class="form-control" style="width:auto;min-width:130px"
            onchange="this.form.submit()">
      <option value="">Trình độ</option>
      <option value="Thạc sĩ" <?= $degree === 'Thạc sĩ' ? 'selected' : '' ?>>Thạc sĩ</option>
      <option value="Đại học" <?= $degree === 'Đại học' ? 'selected' : '' ?>>Đại học</option>
      <option value="Cao đẳng" <?= $degree === 'Cao đẳng' ? 'selected' : '' ?>>Cao đẳng</option>
    </select>
  </form>

  <!-- View toggle: card / table -->
  <div class="view-toggle">
    <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'card'])) ?>"
       class="view-btn <?= $view === 'card' ? 'active' : '' ?>" title="Dạng thẻ">
      <i class="fas fa-th-large"></i>
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'table'])) ?>"
       class="view-btn <?= $view === 'table' ? 'active' : '' ?>" title="Dạng bảng">
      <i class="fas fa-list"></i>
    </a>
  </div>

  <?php if ($currentUser['role'] === 'admin'): ?>
    <a href="/add-teacher.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Thêm giáo viên
    </a>
  <?php endif; ?>

</div>

<!-- Stats bar -->
<div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;padding:14px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg)">
  <div style="display:flex;align-items:center;gap:8px">
    <div style="width:10px;height:10px;border-radius:50%;background:#1a6ef5"></div>
    <span style="font-size:12.5px;color:var(--text-secondary)">Tổng:</span>
    <strong><?= $statTotal ?></strong>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <div style="width:10px;height:10px;border-radius:50%;background:#22c55e"></div>
    <span style="font-size:12.5px;color:var(--text-secondary)">Hoàn thành:</span>
    <strong><?= $statComplete ?></strong>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <div style="width:10px;height:10px;border-radius:50%;background:#f59e0b"></div>
    <span style="font-size:12.5px;color:var(--text-secondary)">Chưa cập nhật:</span>
    <strong><?= $statPending ?></strong>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <div style="width:10px;height:10px;border-radius:50%;background:#3b82f6"></div>
    <span style="font-size:12.5px;color:var(--text-secondary)">Đang xử lý:</span>
    <strong><?= $statProcessing ?></strong>
  </div>
  <?php if ($q || $status || $deptId || $degree): ?>
    <a href="/teachers.php" style="margin-left:auto;font-size:12.5px;color:var(--danger);display:flex;align-items:center;gap:5px">
      <i class="fas fa-times"></i> Xóa bộ lọc
    </a>
  <?php else: ?>
    <div style="margin-left:auto">
      <a href="/teachers.php?export=excel" class="btn btn-outline btn-sm"
         onclick="showToast('success','Xuất Excel','Đang tạo file...');return false">
        <i class="fas fa-file-excel" style="color:#10b981"></i> Xuất Excel
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- ===================================
     CARD VIEW
     =================================== -->
<?php if ($view === 'card'): ?>

  <?php if (empty($teachers)): ?>
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <div class="empty-title">Không tìm thấy kết quả</div>
      <div class="empty-desc">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</div>
      <a href="/teachers.php" class="btn btn-outline" style="margin-top:12px">
        <i class="fas fa-times"></i> Xóa bộ lọc
      </a>
    </div>
  <?php else: ?>
    <div class="teacher-grid">
      <?php foreach ($teachers as $t):
        $name     = $t['personal']['full_name'] ?? '—';
        $subject  = $t['work']['subject']       ?? '—';
        $deptName = '';
        foreach ($departments as $d) {
            if ($d['id'] === ($t['work']['dept_id'] ?? '')) {
                $deptName = $d['name'];
                break;
            }
        }
        $degree   = $t['education']['degree'] ?? 'Đại học';
        $rank     = $t['work']['rank']        ?? 'GV hạng III';
        $avatar   = $t['avatar_url']          ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=150';
        $status   = $t['status']              ?? 'pending';
        $compl    = $t['completion_pct']      ?? 0;
        [$badgeCls, $badgeLabel, $dotColor] = $statusCss[$status];
      ?>
        <div class="teacher-card-inner" onclick="location.href='/teacher-detail.php?id=<?= e($t['id']) ?>'">
          <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?= $dotColor ?>"></div>
          <div style="position:relative">
            <img src="<?= e($avatar) ?>" alt="<?= e($name) ?>"
                 style="width:72px;height:72px;border-radius:50%;border:3px solid var(--border);object-fit:cover"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=150'">
            <div style="position:absolute;bottom:2px;right:2px;width:14px;height:14px;border-radius:50%;background:<?= $dotColor ?>;border:2px solid var(--bg-card)"></div>
          </div>
          <div class="tc-name"><?= e($name) ?></div>
          <div class="tc-subject"><?= e($subject) ?></div>
          <div class="tc-dept"><?= e($deptName) ?></div>
          <div class="tc-degree"><?= e($degree) ?></div>
          <div style="margin:4px 0"><span class="badge <?= $badgeCls ?>"><?= $badgeLabel ?></span></div>

          <!-- Progress bar nhỏ -->
          <div style="width:100%;margin-top:4px">
            <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--text-muted);margin-bottom:3px">
              <span>Hồ sơ</span><span><?= $compl ?>%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width:<?= $compl ?>%;background:<?= $dotColor ?>"></div>
            </div>
          </div>

          <div class="tc-actions">
            <button class="btn btn-outline btn-sm"
                    onclick="event.stopPropagation();openQuickView('<?= e($t['id']) ?>')">
              <i class="fas fa-eye"></i> Xem
            </button>
            <a href="/teacher-detail.php?id=<?= e($t['id']) ?>"
               class="btn btn-primary btn-sm"
               onclick="event.stopPropagation()">
              <i class="fas fa-edit"></i> Chi tiết
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<!-- ===================================
     TABLE VIEW
     =================================== -->
<?php else: ?>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Giáo viên</th>
            <th>Môn dạy</th>
            <th>Tổ chuyên môn</th>
            <th>Trình độ</th>
            <th>Hạng GV</th>
            <th>Hồ sơ</th>
            <th>Trạng thái</th>
            <th style="text-align:center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($teachers)): ?>
            <tr>
              <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                <i class="fas fa-search" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.3"></i>
                Không tìm thấy kết quả
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($teachers as $t):
              $name    = $t['personal']['full_name'] ?? '—';
              $email   = $t['personal']['email']     ?? ($t['user_id'] ?? '');
              $subject = $t['work']['subject']        ?? '—';
              $degree  = $t['education']['degree']    ?? '—';
              $rank    = $t['work']['rank']           ?? '—';
              $compl   = $t['completion_pct']         ?? 0;
              $status  = $t['status']                 ?? 'pending';
              $avatar  = $t['avatar_url']             ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=80';
              $deptName = '';
              foreach ($departments as $d) {
                  if ($d['id'] === ($t['work']['dept_id'] ?? '')) { $deptName = $d['name']; break; }
              }
              [$badgeCls, $badgeLabel, $dotColor] = $statusCss[$status];
            ?>
              <tr style="cursor:pointer" onclick="location.href='/teacher-detail.php?id=<?= e($t['id']) ?>'">
                <td>
                  <div style="display:flex;align-items:center;gap:10px">
                    <img src="<?= e($avatar) ?>" alt="<?= e($name) ?>"
                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--border)"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=80'">
                    <div>
                      <div style="font-size:13.5px;font-weight:600"><?= e($name) ?></div>
                      <div style="font-size:11.5px;color:var(--text-muted)"><?= e($email) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge badge-primary"><?= e($subject) ?></span></td>
                <td><?= e($deptName) ?></td>
                <td><?= e($degree) ?></td>
                <td><?= e($rank) ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px">
                    <div class="progress-bar" style="width:60px">
                      <div class="progress-fill" style="width:<?= $compl ?>%;background:<?= $dotColor ?>"></div>
                    </div>
                    <span style="font-size:12px;font-weight:700;color:<?= $dotColor ?>"><?= $compl ?>%</span>
                  </div>
                </td>
                <td>
                  <span class="badge <?= $badgeCls ?>">
                    <i class="fas fa-circle" style="font-size:7px"></i>
                    <?= $badgeLabel ?>
                  </span>
                </td>
                <td style="text-align:center">
                  <div style="display:flex;gap:6px;justify-content:center">
                    <button class="btn btn-outline btn-sm"
                            onclick="event.stopPropagation();openQuickView('<?= e($t['id']) ?>')"
                            title="Xem nhanh">
                      <i class="fas fa-eye"></i>
                    </button>
                    <a href="/teacher-detail.php?id=<?= e($t['id']) ?>"
                       class="btn btn-primary btn-sm"
                       onclick="event.stopPropagation()"
                       title="Chi tiết">
                      <i class="fas fa-arrow-right"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<!-- Phân trang -->
<?= paginationHtml($paginated, $baseUrl) ?>

<!-- ===================================
     MODAL XEM NHANH
     =================================== -->
<div class="modal-overlay" id="quickViewModal" style="display:none">
  <div class="modal" style="max-width:600px" id="quickViewContent">
    <div style="padding:60px;text-align:center;color:var(--text-muted)">
      <div class="lo-ring" style="margin:0 auto 16px"></div>
      Đang tải...
    </div>
  </div>
</div>

<?php
// Truyền dữ liệu teachers sang JS để quick view không cần fetch API
$teachersJson = json_encode(array_map(function($t) use ($departments) {
    $deptName = '';
    foreach ($departments as $d) {
        if ($d['id'] === ($t['work']['dept_id'] ?? '')) { $deptName = $d['name']; break; }
    }
    return [
        'id'     => $t['id'],
        'name'   => $t['personal']['full_name']  ?? '—',
        'subject'=> $t['work']['subject']         ?? '—',
        'dept'   => $deptName,
        'degree' => $t['education']['degree']     ?? '—',
        'rank'   => $t['work']['rank']            ?? '—',
        'phone'  => $t['personal']['phone']       ?? '—',
        'email'  => $t['personal']['email']       ?? '—',
        'dob'    => $t['personal']['dob']         ?? null,
        'gender' => $t['personal']['gender']      ?? '—',
        'party'  => $t['work']['party_member']    ?? false,
        'join'   => $t['work']['join_date']       ?? null,
        'avatar' => $t['avatar_url']              ?? '',
        'status' => $t['status']                  ?? 'pending',
        'compl'  => $t['completion_pct']          ?? 0,
    ];
}, $allTeachers), JSON_UNESCAPED_UNICODE);

$inlineScript = <<<JS
const ALL_TEACHERS_JS = $teachersJson;

const STATUS_MAP = {
    complete:   { label: 'Hoàn thành',    cls: 'badge-success', color: '#22c55e' },
    pending:    { label: 'Chưa cập nhật', cls: 'badge-warning', color: '#f59e0b' },
    processing: { label: 'Đang xử lý',   cls: 'badge-info',    color: '#3b82f6' },
};

function openQuickView(id) {
    const t = ALL_TEACHERS_JS.find(x => x.id === id);
    if (!t) return;

    const s = STATUS_MAP[t.status] || STATUS_MAP.pending;
    const avatar = t.avatar || `https://ui-avatars.com/api/?name=\${encodeURIComponent(t.name)}&background=1a6ef5&color=fff&size=150`;

    document.getElementById('quickViewContent').innerHTML = `
        <div style="background:linear-gradient(135deg,#1a6ef5,#0047c0);padding:22px 24px;border-radius:var(--radius-xl) var(--radius-xl) 0 0;display:flex;align-items:center;gap:16px;color:white">
            <img src="\${avatar}" style="width:72px;height:72px;border-radius:50%;border:3px solid rgba(255,255,255,.3);object-fit:cover"
                 onerror="this.src='https://ui-avatars.com/api/?name=\${encodeURIComponent(t.name)}&background=1a6ef5&color=fff&size=150'">
            <div style="flex:1">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:4px">\${t.name}</h2>
                <p style="opacity:.8;font-size:13px">\${t.subject} · \${t.dept}</p>
                <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                    <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11.5px">\${t.degree}</span>
                    <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11.5px">\${t.rank}</span>
                    <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11.5px">
                        <i class="fas fa-circle" style="font-size:7px;color:\${s.color}"></i> \${s.label}
                    </span>
                </div>
            </div>
            <button style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:7px 12px;color:white;cursor:pointer"
                    onclick="closeModal('quickViewModal')">✕</button>
        </div>
        <div style="padding:22px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
                \${[
                    ['Ngày sinh', t.dob ? new Date(t.dob).toLocaleDateString('vi-VN') : '—'],
                    ['Giới tính', t.gender],
                    ['Điện thoại', t.phone],
                    ['Email', t.email],
                    ['Ngày vào ngành', t.join ? new Date(t.join).toLocaleDateString('vi-VN') : '—'],
                    ['Đảng viên', t.party ? '✅ Đảng viên' : '❌ Chưa vào Đảng'],
                ].map(([k,v]) => \`
                    <div>
                        <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:3px">\${k}</div>
                        <div style="font-size:13.5px;font-weight:600">\${v}</div>
                    </div>\`).join('')}
            </div>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
                    <span style="color:var(--text-muted)">Tiến độ hồ sơ</span>
                    <strong style="color:\${s.color}">\${t.compl}%</strong>
                </div>
                <div class="progress-bar" style="height:8px">
                    <div class="progress-fill" style="width:\${t.compl}%;background:\${s.color}"></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button class="btn btn-outline" onclick="closeModal('quickViewModal')">Đóng</button>
                <a href="/teacher-detail.php?id=\${t.id}" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Xem hồ sơ đầy đủ
                </a>
            </div>
        </div>`;

    openModal('quickViewModal');
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
