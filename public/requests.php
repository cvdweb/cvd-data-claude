<?php
/**
 * requests.php — Trang yêu cầu dữ liệu
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$requests         = $db->read('requests');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = count(array_filter($requests, fn($r) => ($r['status'] ?? '') !== 'complete'));

// Xử lý tạo yêu cầu mới (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser['role'] === 'admin') {
    $errors = validate($_POST, [
        'title'    => 'required|min:5|max:200',
        'deadline' => 'required|date',
    ]);
    if (empty($errors)) {
        $db->insert('requests', [
            'title'       => input('title'),
            'description' => input('description'),
            'created_by'  => $currentUser['id'],
            'target_dept' => input('target_dept') ?: 'all',
            'deadline'    => input('deadline'),
            'priority'    => input('priority') ?: 'medium',
            'status'      => 'pending',
            'completed'   => 0,
            'total'       => $db->count('teachers'),
        ]);
        flash('success', 'Đã tạo yêu cầu mới! Hệ thống sẽ gửi thông báo đến giáo viên.');
        redirect('/requests.php');
    }
}

// Filter
$filterStatus = input('status');
$filtered     = $filterStatus
    ? array_filter($requests, fn($r) => ($r['status'] ?? '') === $filterStatus)
    : $requests;
$filtered = array_values($filtered);

// Thống kê
$countAll        = count($requests);
$countPending    = count(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'pending'));
$countProcessing = count(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'processing'));
$countComplete   = count(array_filter($requests, fn($r) => ($r['status'] ?? '') === 'complete'));

$departments     = $db->read('departments');

// Màu sắc
$priColor = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#3b82f6'];
$priBg    = ['high' => 'var(--danger-light)', 'medium' => 'var(--warning-light)', 'low' => 'var(--info-light)'];
$priIcon  = ['high' => 'fa-fire', 'medium' => 'fa-tasks', 'low' => 'fa-info-circle'];
$priLabel = ['high' => 'Ưu tiên cao', 'medium' => 'Trung bình', 'low' => 'Thấp'];

$statusColor = ['complete' => '#22c55e', 'pending' => '#ef4444', 'processing' => '#f59e0b'];
$statusBadge = ['complete' => 'badge-success', 'pending' => 'badge-danger', 'processing' => 'badge-warning'];
$statusLabel = ['complete' => 'Hoàn thành', 'pending' => 'Chưa hoàn thành', 'processing' => 'Đang xử lý'];

$pageTitle    = 'Yêu cầu dữ liệu';
$pageSubtitle = 'Quản lý các yêu cầu cập nhật từ Ban giám hiệu';
$activePage   = 'requests.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
  <?php foreach ([
    ['Tổng yêu cầu',    $countAll,        '#1a6ef5', 'fa-tasks',        'var(--primary-light)'],
    ['Đang mở',         $countPending,    '#ef4444', 'fa-fire',         'var(--danger-light)'],
    ['Đang xử lý',      $countProcessing, '#f59e0b', 'fa-spinner',      'var(--warning-light)'],
    ['Hoàn thành',      $countComplete,   '#22c55e', 'fa-check-circle', 'var(--success-light)'],
  ] as [$label, $val, $color, $icon, $bg]): ?>
    <div class="stat-card" style="--card-accent:<?= $color ?>;--card-accent-light:<?= $bg ?>">
      <div class="stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
        <i class="fas <?= $icon ?>"></i>
      </div>
      <div class="stat-info">
        <div class="stat-label"><?= $label ?></div>
        <div class="stat-value"><?= $val ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">

  <!-- Filter tabs -->
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ([
      [''           , "Tất cả ({$countAll})"],
      ['pending'    , "Đang mở ({$countPending})"],
      ['processing' , "Đang xử lý ({$countProcessing})"],
      ['complete'   , "Hoàn thành ({$countComplete})"],
    ] as [$val, $label]): ?>
      <a href="/requests.php<?= $val ? '?status=' . $val : '' ?>"
         style="padding:7px 16px;border-radius:99px;font-size:13px;font-weight:600;
                border:1.5px solid <?= $filterStatus === $val ? 'var(--primary)' : 'var(--border)' ?>;
                background:<?= $filterStatus === $val ? 'var(--primary)' : 'var(--bg-card)' ?>;
                color:<?= $filterStatus === $val ? 'white' : 'var(--text-secondary)' ?>;
                text-decoration:none;transition:all .2s">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($currentUser['role'] === 'admin'): ?>
    <button class="btn btn-primary" data-open-modal="newReqModal">
      <i class="fas fa-plus"></i> Tạo yêu cầu mới
    </button>
  <?php endif; ?>
</div>

<!-- Danh sách yêu cầu -->
<?php if (empty($filtered)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <div class="empty-title">Không có yêu cầu nào</div>
    <div class="empty-desc">Chưa có yêu cầu nào trong danh mục này.</div>
  </div>
<?php else: ?>
  <?php foreach ($filtered as $req):
    $pri    = $req['priority'] ?? 'medium';
    $status = $req['status']   ?? 'pending';
    $pct    = $req['total'] > 0 ? round($req['completed'] / $req['total'] * 100) : 0;
    $dl     = strtotime($req['deadline']) - time();
    $dlDays = (int)ceil($dl / 86400);
    $dlColor = $dlDays < 0 ? '#ef4444' : ($dlDays <= 3 ? '#f59e0b' : '#22c55e');
    $dlLabel = $dlDays < 0 ? 'Quá hạn ' . abs($dlDays) . ' ngày' : ($dlDays === 0 ? 'Hết hạn hôm nay' : 'Còn ' . $dlDays . ' ngày');
  ?>
    <div class="card" style="margin-bottom:16px">
      <!-- Header -->
      <div style="padding:18px 22px 14px;border-bottom:1px solid var(--border-light);display:flex;align-items:flex-start;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:<?= $priBg[$pri] ?>;color:<?= $priColor[$pri] ?>;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
          <i class="fas <?= $priIcon[$pri] ?>"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:15px;font-weight:700;margin-bottom:5px"><?= e($req['title']) ?></div>
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--text-muted)">
            <span><i class="fas fa-users"></i>
              <?= $req['target_dept'] === 'all' ? 'Tất cả tổ' : e($req['target_dept']) ?>
            </span>
            <span><i class="fas fa-calendar"></i> Hạn: <?= formatDate($req['deadline']) ?></span>
            <span><i class="fas fa-user-check"></i> <?= $req['completed'] ?>/<?= $req['total'] ?> người nộp</span>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0">
          <span class="badge <?= $statusBadge[$status] ?>">
            <i class="fas fa-circle" style="font-size:7px"></i>
            <?= $statusLabel[$status] ?>
          </span>
          <span class="badge" style="background:<?= $priBg[$pri] ?>;color:<?= $priColor[$pri] ?>">
            <?= $priLabel[$pri] ?>
          </span>
        </div>
      </div>

      <!-- Body -->
      <div style="padding:16px 22px">
        <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px">
          <?= e($req['description'] ?? '') ?>
        </p>

        <!-- Progress bar -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
          <span style="font-size:12px;color:var(--text-muted);white-space:nowrap">Tiến độ nộp:</span>
          <div class="progress-bar" style="flex:1;height:8px">
            <div class="progress-fill"
                 style="width:<?= $pct ?>%;background:<?= $pct === 100 ? '#22c55e' : ($pct >= 80 ? '#1a6ef5' : ($pct >= 50 ? '#f59e0b' : '#ef4444')) ?>">
            </div>
          </div>
          <span style="font-size:13px;font-weight:800;color:<?= $pct === 100 ? '#22c55e' : ($pct >= 80 ? '#1a6ef5' : '#f59e0b') ?>;min-width:38px">
            <?= $pct ?>%
          </span>
        </div>

        <!-- Tổ breakdown (nếu target all) -->
        <?php if ($req['target_dept'] === 'all'): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php foreach ($departments as $dept): ?>
              <div style="display:flex;align-items:center;gap:6px;background:var(--bg);border:1px solid var(--border);border-radius:99px;padding:4px 10px;font-size:11.5px">
                <div style="width:8px;height:8px;border-radius:50%;background:<?= e($dept['color'] ?? '#1a6ef5') ?>"></div>
                <?= e(str_replace('Tổ ', '', $dept['name'])) ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <div style="padding:12px 22px;background:var(--bg);border-top:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:99px;font-size:11.5px;font-weight:600;background:<?= $dlColor ?>22;color:<?= $dlColor ?>;border:1px solid <?= $dlColor ?>44">
            <i class="fas fa-clock"></i> <?= $dlLabel ?>
          </span>
          <span style="font-size:12px;color:var(--text-muted)">
            <?= $req['completed'] ?> / <?= $req['total'] ?> giáo viên đã hoàn thành
          </span>
        </div>
        <div style="display:flex;gap:8px">
          <?php if ($status !== 'complete' && $currentUser['role'] === 'admin'): ?>
            <button class="btn btn-outline btn-sm"
                    onclick="showToast('info','Nhắc nhở','Đang gửi thông báo đến giáo viên chưa nộp...')">
              <i class="fas fa-bell"></i> Nhắc nhở
            </button>
          <?php endif; ?>
          <button class="btn btn-primary btn-sm"
                  onclick="showToast('info','Chi tiết','Mở chi tiết yêu cầu...')">
            <i class="fas fa-eye"></i> Chi tiết
          </button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Modal tạo yêu cầu mới -->
<?php if ($currentUser['role'] === 'admin'): ?>
<div class="modal-overlay" id="newReqModal" style="display:none">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-plus"></i> Tạo yêu cầu dữ liệu mới</span>
      <button class="btn-icon" data-close-modal="newReqModal"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="/requests.php">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tiêu đề <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="Nhập tiêu đề yêu cầu..." required>
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả chi tiết</label>
          <textarea name="description" class="form-control" rows="3"
                    placeholder="Mô tả yêu cầu, mục đích sử dụng dữ liệu..."></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Đối tượng áp dụng</label>
            <select name="target_dept" class="form-control">
              <option value="all">Tất cả tổ</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= e($dept['id']) ?>"><?= e($dept['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Hạn nộp <span style="color:var(--danger)">*</span></label>
            <input type="date" name="deadline" class="form-control"
                   value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Mức độ ưu tiên</label>
            <select name="priority" class="form-control">
              <option value="high">🔴 Cao</option>
              <option value="medium" selected>🟡 Trung bình</option>
              <option value="low">🔵 Thấp</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="newReqModal">Hủy</button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-paper-plane"></i> Gửi yêu cầu
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include TEMPLATES_DIR . '/_footer.php'; ?>
