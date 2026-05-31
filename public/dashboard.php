<?php
/**
 * dashboard.php — Trang tổng quan
 */
require_once __DIR__ . '/_path.php';

// Bắt buộc đăng nhập
$currentUser = $auth->requireLogin();

// ---- Lấy dữ liệu thật từ JSON ----
$teachers    = $db->read('teachers');
$departments = $db->read('departments');
$requests    = $db->read('requests');
$events      = $db->read('events');
$notifs      = $db->where('notifications', 'user_id', $currentUser['id']);

// ---- Tính thống kê ----
$totalTeachers    = count($teachers);
$totalDepts       = count($departments);
$pendingTeachers  = count(array_filter($teachers, fn($t) => ($t['status'] ?? '') === 'pending'));
$openRequests     = count(array_filter($requests, fn($r) => ($r['status'] ?? '') !== 'complete'));
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $openRequests; // dùng trong sidebar

// Tỉ lệ hoàn thành TB
$totalCompletion = array_sum(array_column($teachers, 'completion_pct'));
$avgCompletion   = $totalTeachers > 0 ? round($totalCompletion / $totalTeachers) : 0;

// Thống kê theo trạng thái
$statusCount = [
    'complete'   => count(array_filter($teachers, fn($t) => ($t['status'] ?? '') === 'complete')),
    'pending'    => count(array_filter($teachers, fn($t) => ($t['status'] ?? '') === 'pending')),
    'processing' => count(array_filter($teachers, fn($t) => ($t['status'] ?? '') === 'processing')),
];

// Thống kê theo tổ (cho chart)
$deptStats = [];
foreach ($departments as $dept) {
    $deptTeachers = array_filter($teachers, fn($t) => ($t['work']['dept_id'] ?? '') === $dept['id']);
    $deptComplete = array_filter($deptTeachers, fn($t) => ($t['status'] ?? '') === 'complete');
    $count = count($deptTeachers);
    $deptStats[] = [
        'name'     => str_replace('Tổ ', '', $dept['name']),
        'count'    => $count,
        'complete' => count($deptComplete),
        'pct'      => $count > 0 ? round(count($deptComplete) / $count * 100) : 0,
        'color'    => $dept['color'] ?? '#1a6ef5',
    ];
}

// Sự kiện sắp diễn ra (3 cái gần nhất)
$today         = date('Y-m-d');
$upcomingEvents = array_slice(
    array_filter($events, fn($e) => ($e['date'] ?? '') >= $today),
    0, 3
);

// Hoạt động gần đây (lấy từ audit_log hoặc notifications)
$recentActivities = array_slice(array_reverse($notifs), 0, 6);

// ---- Metadata trang ----
$pageTitle    = 'Tổng quan';
$pageSubtitle = 'Xin chào, hôm nay có gì mới?';
$activePage   = 'dashboard.php';

// Script CDN cần thêm
$extraScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
];

// Dữ liệu truyền sang JS (PHP → JSON → JS)
$chartDeptLabels = json_encode(array_column($deptStats, 'name'));
$chartDeptCounts = json_encode(array_column($deptStats, 'count'));
$chartDeptColors = json_encode(array_column($deptStats, 'color'));
$chartDeptPcts   = json_encode(array_column($deptStats, 'pct'));
$chartStatus     = json_encode([
    $statusCount['complete'],
    $statusCount['pending'],
    $statusCount['processing'],
]);

// ---- Render ----
include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- ===================================
     NỘI DUNG TRANG DASHBOARD
     =================================== -->

<!-- Welcome banner -->
<div class="welcome-banner">
  <div class="welcome-text">
    <h2>Chào, <?= e($currentUser['name']) ?> 👋</h2>
    <p>
      <?= date('l, d/m/Y') ?>
      <?php if ($openRequests > 0): ?>
        · Có <strong><?= $openRequests ?> yêu cầu</strong> đang chờ xử lý
      <?php else: ?>
        · Mọi yêu cầu đã hoàn thành ✓
      <?php endif; ?>
    </p>
  </div>
  <div class="welcome-actions">
    <a href="/requests.php" class="btn">
      <i class="fas fa-tasks"></i> Yêu cầu
    </a>
    <?php if ($currentUser['role'] === 'admin'): ?>
      <a href="/add-teacher.php" class="btn btn-wsolid">
        <i class="fas fa-plus"></i> Thêm GV
      </a>
    <?php endif; ?>
  </div>
  <div class="welcome-emoji">🏫</div>
</div>

<!-- Stat cards -->
<div class="stats-grid" style="margin-bottom:20px">

  <div class="stat-card" style="--card-accent:#1a6ef5;--card-accent-light:var(--primary-light)">
    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
    <div class="stat-info">
      <div class="stat-label">Tổng giáo viên</div>
      <div class="stat-value"><?= $totalTeachers ?></div>
      <div class="stat-change up">
        <i class="fas fa-users"></i> <?= $totalDepts ?> tổ chuyên môn
      </div>
    </div>
  </div>

  <div class="stat-card" style="--card-accent:#22c55e;--card-accent-light:var(--success-light)">
    <div class="stat-icon" style="background:var(--success-light);color:#22c55e">
      <i class="fas fa-check-circle"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Hồ sơ hoàn thành</div>
      <div class="stat-value"><?= $statusCount['complete'] ?></div>
      <div class="stat-change up">
        <i class="fas fa-arrow-up"></i>
        <?= $totalTeachers > 0 ? round($statusCount['complete'] / $totalTeachers * 100) : 0 ?>% tổng số GV
      </div>
    </div>
  </div>

  <div class="stat-card" style="--card-accent:#f59e0b;--card-accent-light:var(--warning-light)">
    <div class="stat-icon" style="background:var(--warning-light);color:#f59e0b">
      <i class="fas fa-exclamation-circle"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Hồ sơ chưa cập nhật</div>
      <div class="stat-value"><?= $pendingTeachers ?></div>
      <div class="stat-change <?= $pendingTeachers > 5 ? 'down' : 'up' ?>">
        <i class="fas fa-<?= $pendingTeachers > 5 ? 'exclamation' : 'check' ?>"></i>
        <?= $pendingTeachers > 5 ? 'Cần xử lý sớm' : 'Trong ngưỡng kiểm soát' ?>
      </div>
    </div>
  </div>

  <div class="stat-card" style="--card-accent:#ef4444;--card-accent-light:var(--danger-light)">
    <div class="stat-icon" style="background:var(--danger-light);color:#ef4444">
      <i class="fas fa-clock"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Yêu cầu chờ xử lý</div>
      <div class="stat-value"><?= $openRequests ?></div>
      <div class="stat-change <?= $openRequests > 0 ? 'down' : 'up' ?>">
        <i class="fas fa-<?= $openRequests > 0 ? 'fire' : 'check' ?>"></i>
        <?= $openRequests > 0 ? 'Cần xử lý sớm' : 'Đã hoàn thành tất cả' ?>
      </div>
    </div>
  </div>

</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:20px"
     class="charts-row">

  <!-- Bar chart: GV theo tổ -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Giáo viên theo tổ chuyên môn</div>
        <div class="card-subtitle">Phân bố và tỉ lệ hoàn thành hồ sơ</div>
      </div>
    </div>
    <div class="card-body">
      <div class="chart-box" style="height:240px;position:relative;width:100%">
        <canvas id="deptChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Doughnut: Trạng thái hồ sơ -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Trạng thái hồ sơ</div>
        <div class="card-subtitle">Tổng quan toàn trường</div>
      </div>
    </div>
    <div class="card-body">
      <div class="chart-box-sm" style="height:180px;position:relative;width:100%">
        <canvas id="statusChart"></canvas>
      </div>
      <div style="margin-top:14px;display:flex;flex-direction:column;gap:9px">
        <?php
        $statusInfo = [
            ['Hoàn thành',    $statusCount['complete'],   '#22c55e', 'badge-success'],
            ['Chưa cập nhật', $statusCount['pending'],    '#f59e0b', 'badge-warning'],
            ['Đang xử lý',    $statusCount['processing'], '#3b82f6', 'badge-info'],
        ];
        foreach ($statusInfo as [$label, $count, $color, $badge]):
        ?>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;align-items:center;gap:8px;font-size:12.5px">
              <div style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></div>
              <span><?= e($label) ?></span>
            </div>
            <strong style="font-size:13px"><?= $count ?> GV</strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- Bottom row -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px" class="bottom-row">

  <!-- Hoạt động / Thông báo gần đây -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Hoạt động gần đây</div>
      <a href="/notifications.php" style="font-size:12.5px;color:var(--primary)">Xem tất cả</a>
    </div>
    <div class="card-body">
      <?php if (empty($recentActivities)): ?>
        <div class="empty-state" style="padding:30px 20px">
          <div class="empty-icon">📭</div>
          <div class="empty-title">Chưa có hoạt động nào</div>
        </div>
      <?php else: ?>
        <?php
        $actIcons = [
            'request'  => ['fa-tasks',       '#1a6ef5', 'var(--primary-light)'],
            'deadline' => ['fa-clock',        '#ef4444', 'var(--danger-light)'],
            'system'   => ['fa-cog',          '#8b5cf6', '#f3e8ff'],
            'doc'      => ['fa-file-upload',  '#10b981', 'var(--accent-light)'],
            'profile'  => ['fa-user-edit',    '#1a6ef5', 'var(--primary-light)'],
        ];
        foreach ($recentActivities as $act):
            [$icon, $color, $bg] = $actIcons[$act['type'] ?? 'system'] ?? ['fa-info', '#64748b', 'var(--bg)'];
        ?>
          <div class="activity-item">
            <div class="activity-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
              <i class="fas <?= e($icon) ?>"></i>
            </div>
            <div class="activity-content">
              <div class="activity-text"><?= e($act['body'] ?? '') ?></div>
              <div class="activity-time"><?= timeAgo($act['created_at'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cột phải -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Tổ chuyên môn: tiến độ hồ sơ -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Tiến độ theo tổ</div>
        <a href="/departments.php" style="font-size:12.5px;color:var(--primary)">Chi tiết</a>
      </div>
      <div class="card-body">
        <?php foreach ($deptStats as $dept): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light)">
            <div style="width:9px;height:9px;border-radius:50%;background:<?= e($dept['color']) ?>;flex-shrink:0"></div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600"><?= e($dept['name']) ?></div>
              <div style="font-size:11px;color:var(--text-muted)"><?= $dept['count'] ?> giáo viên</div>
            </div>
            <div style="width:80px">
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $dept['pct'] ?>%;background:<?= e($dept['color']) ?>"></div>
              </div>
            </div>
            <div style="font-size:12px;font-weight:700;color:<?= e($dept['color']) ?>;min-width:34px;text-align:right">
              <?= $dept['pct'] ?>%
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Yêu cầu đang mở -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Yêu cầu đang mở</div>
        <a href="/requests.php" style="font-size:12.5px;color:var(--primary)">Xem tất cả</a>
      </div>
      <div class="card-body">
        <?php
        $openReqList = array_slice(
            array_filter($requests, fn($r) => ($r['status'] ?? '') !== 'complete'),
            0, 3
        );
        $priColors = ['high'=>'#ef4444','medium'=>'#f59e0b','low'=>'#3b82f6'];
        $priBgs    = ['high'=>'var(--danger-light)','medium'=>'var(--warning-light)','low'=>'var(--info-light)'];
        $priIcons  = ['high'=>'fa-fire','medium'=>'fa-tasks','low'=>'fa-info-circle'];
        ?>
        <?php if (empty($openReqList)): ?>
          <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px">
            <i class="fas fa-check-circle" style="color:var(--success);font-size:24px;display:block;margin-bottom:8px"></i>
            Tất cả yêu cầu đã hoàn thành!
          </div>
        <?php else: ?>
          <?php foreach ($openReqList as $req):
            $pri   = $req['priority'] ?? 'medium';
            $pct   = $req['total'] > 0 ? round($req['completed'] / $req['total'] * 100) : 0;
          ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);border:1px solid var(--border-light);margin-bottom:8px;cursor:pointer"
                 onclick="location.href='/requests.php'">
              <div style="width:36px;height:36px;border-radius:9px;background:<?= $priBgs[$pri] ?>;color:<?= $priColors[$pri] ?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">
                <i class="fas <?= $priIcons[$pri] ?>"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?= e($req['title']) ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted)">
                  Hạn: <?= formatDate($req['deadline']) ?>
                </div>
              </div>
              <div style="font-size:12px;font-weight:800;color:<?= $priColors[$pri] ?>">
                <?= $pct ?>%
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php
// ---- Truyền dữ liệu PHP → JS cho chart ----
$inlineScript = <<<JS
(function() {
  const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
  const gc     = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
  const tc     = () => isDark() ? '#94a3b8' : '#64748b';
  const CF     = { family: 'Be Vietnam Pro', size: 12 };

  // Dữ liệu từ PHP
  const deptLabels = $chartDeptLabels;
  const deptCounts = $chartDeptCounts;
  const deptColors = $chartDeptColors;
  const statusData = $chartStatus;

  let deptChart   = null;
  let statusChart = null;

  function buildCharts() {
    if (deptChart)   { deptChart.destroy(); }
    if (statusChart) { statusChart.destroy(); }

    // Chart 1: Giáo viên theo tổ
    deptChart = new Chart(document.getElementById('deptChart'), {
      type: 'bar',
      data: {
        labels: deptLabels,
        datasets: [{
          data: deptCounts,
          backgroundColor: deptColors,
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: tc(), font: CF } },
          y: { grid: { color: gc() }, ticks: { color: tc(), font: CF, stepSize: 2 }, border: { display: false }, min: 0 }
        }
      }
    });

    // Chart 2: Trạng thái hồ sơ
    statusChart = new Chart(document.getElementById('statusChart'), {
      type: 'doughnut',
      data: {
        labels: ['Hoàn thành', 'Chưa cập nhật', 'Đang xử lý'],
        datasets: [{
          data: statusData,
          backgroundColor: ['#22c55e', '#f59e0b', '#3b82f6'],
          borderWidth: 0, hoverOffset: 5,
        }]
      },
      options: {
        cutout: '70%', responsive: true, maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: { legend: { display: false } }
      }
    });
  }

  // Khởi tạo sau khi DOM render xong
  requestAnimationFrame(() => setTimeout(buildCharts, 60));

  // Rebuild khi đổi theme
  document.addEventListener('click', e => {
    if (e.target.closest('.btn-theme')) setTimeout(buildCharts, 350);
  });
})();
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
