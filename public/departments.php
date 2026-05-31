<?php
/**
 * departments.php — Trang tổ chuyên môn
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$departments      = $db->read('departments');
$teachers         = $db->read('teachers');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Tổ trưởng chỉ xem tổ mình
if ($currentUser['role'] === 'dept_head') {
    $departments = array_filter($departments, fn($d) => $d['id'] === $currentUser['dept_id']);
    $departments = array_values($departments);
}

// Tính thống kê cho từng tổ
$deptData = [];
foreach ($departments as $dept) {
    $deptTeachers = array_filter($teachers, fn($t) => ($t['work']['dept_id'] ?? '') === $dept['id']);
    $deptTeachers = array_values($deptTeachers);
    $complete     = count(array_filter($deptTeachers, fn($t) => ($t['status'] ?? '') === 'complete'));
    $count        = count($deptTeachers);
    $pct          = $count > 0 ? round($complete / $count * 100) : 0;

    // Tìm tổ trưởng, tổ phó
    $head  = $db->find('teachers', $dept['head_id']  ?? '');
    $deputy= $db->find('teachers', $dept['deputy_id'] ?? '');

    $deptData[] = [
        'dept'     => $dept,
        'teachers' => $deptTeachers,
        'count'    => $count,
        'complete' => $complete,
        'pct'      => $pct,
        'head'     => $head,
        'deputy'   => $deputy,
    ];
}

// Tổng quan
$totalTeachers = count($teachers);
$totalDepts    = count($departments);
$avgCompletion = $totalTeachers > 0
    ? round(array_sum(array_column($teachers, 'completion_pct')) / $totalTeachers)
    : 0;
$totalDocs = array_sum(array_column($departments, 'docs'));

$pageTitle    = 'Tổ chuyên môn';
$pageSubtitle = 'Quản lý các tổ và thành viên';
$activePage   = 'departments.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Tổng quan -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
  <?php foreach ([
    ['Tổ chuyên môn',     $totalDepts,    '#1a6ef5', 'fa-sitemap',     'var(--primary-light)'],
    ['Tổng giáo viên',    $totalTeachers, '#10b981', 'fa-chalkboard-teacher', 'var(--accent-light)'],
    ['Tài liệu trong kho',$totalDocs,     '#8b5cf6', 'fa-file-alt',    '#f3e8ff'],
    ['Hoàn thành TB',     $avgCompletion.'%', '#f59e0b', 'fa-percentage','var(--warning-light)'],
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

<!-- Grid tổ chuyên môn -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px">
  <?php foreach ($deptData as $item):
    $dept    = $item['dept'];
    $color   = $dept['color'] ?? '#1a6ef5';
    $color2  = $color . 'cc';
    $pct     = $item['pct'];
    $pctColor= $pct === 100 ? '#22c55e' : ($pct >= 85 ? '#1a6ef5' : '#f59e0b');
  ?>
    <div class="dept-card-v2" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-sm);transition:all var(--transition)"
         onmouseenter="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow)'"
         onmouseleave="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">

      <!-- Header màu -->
      <div style="background:linear-gradient(135deg,<?= $color ?>,<?= $color2 ?>);padding:20px 22px 16px;position:relative;overflow:hidden">
        <div style="position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:52px;opacity:.18;line-height:1;pointer-events:none">
          <?= $dept['icon'] ?? '📚' ?>
        </div>
        <div style="font-size:30px;margin-bottom:10px"><?= $dept['icon'] ?? '📚' ?></div>
        <h3 style="color:white;font-size:18px;font-weight:800;margin-bottom:4px"><?= e($dept['name']) ?></h3>
        <p style="color:rgba(255,255,255,.75);font-size:12.5px">
          <?= $item['count'] ?> giáo viên · <?= $dept['docs'] ?? 0 ?> tài liệu
        </p>

        <!-- Mini stats -->
        <div style="display:flex;gap:0;border-top:1px solid rgba(255,255,255,.12);margin-top:14px">
          <?php foreach ([
            [$item['count'], 'Thành viên'],
            [$pct . '%',     'Hồ sơ HT'],
            [$dept['docs'] ?? 0, 'Tài liệu'],
          ] as $i => [$v, $l]): ?>
            <div style="flex:1;text-align:center;padding:10px 6px;<?= $i > 0 ? 'border-left:1px solid rgba(255,255,255,.12)' : '' ?>">
              <div style="color:white;font-size:18px;font-weight:900;line-height:1"><?= $v ?></div>
              <div style="color:rgba(255,255,255,.6);font-size:10.5px;margin-top:3px"><?= $l ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Body -->
      <div style="padding:18px 22px">

        <!-- Ban lãnh đạo -->
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:10px">Ban lãnh đạo tổ</div>
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
          <?php foreach ([[$item['head'],'Tổ trưởng'],[$item['deputy'],'Tổ phó']] as [$person, $role]): ?>
            <?php if ($person): ?>
              <div style="display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:99px;padding:5px 12px 5px 5px;flex:1;min-width:130px">
                <img src="<?= e($person['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($person['personal']['full_name'] ?? '') . '&background=1a6ef5&color=fff&size=80') ?>"
                     style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0"
                     onerror="this.src='https://ui-avatars.com/api/?name=GV&background=1a6ef5&color=fff&size=80'">
                <div style="min-width:0">
                  <div style="font-size:11.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= e($person['personal']['full_name'] ?? '—') ?>
                  </div>
                  <div style="font-size:10.5px;color:var(--text-muted)"><?= $role ?></div>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- Progress -->
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
            <span style="color:var(--text-muted)">Tiến độ hồ sơ</span>
            <strong style="color:<?= $pctColor ?>"><?= $pct ?>%</strong>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
          </div>
        </div>

        <!-- Thành viên (3 người đầu) -->
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:8px">Thành viên</div>
        <?php foreach (array_slice($item['teachers'], 0, 3) as $t): ?>
          <?php
          $tName   = $t['personal']['full_name'] ?? '—';
          $tAvatar = $t['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($tName) . '&background=1a6ef5&color=fff&size=80';
          $tStatus = $t['status'] ?? 'pending';
          $tDotClr = ['complete'=>'#22c55e','pending'=>'#f59e0b','processing'=>'#3b82f6'][$tStatus] ?? '#94a3b8';
          ?>
          <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light)">
            <img src="<?= e($tAvatar) ?>"
                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($tName) ?>&background=1a6ef5&color=fff&size=80'">
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($tName) ?></div>
              <div style="font-size:11.5px;color:var(--text-muted)"><?= e($t['work']['subject'] ?? '—') ?></div>
            </div>
            <div style="width:8px;height:8px;border-radius:50%;background:<?= $tDotClr ?>;flex-shrink:0" title="<?= ['complete'=>'Hoàn thành','pending'=>'Chưa cập nhật','processing'=>'Đang xử lý'][$tStatus] ?? '' ?>"></div>
          </div>
        <?php endforeach; ?>

        <?php if (count($item['teachers']) > 3): ?>
          <div style="font-size:12px;color:var(--text-muted);padding:8px 0;text-align:center">
            +<?= count($item['teachers']) - 3 ?> giáo viên khác
          </div>
        <?php endif; ?>
      </div>

      <!-- Footer actions -->
      <div style="display:flex;gap:8px;padding:14px 22px;background:var(--bg);border-top:1px solid var(--border-light)">
        <button class="btn btn-primary btn-sm" style="flex:1;justify-content:center"
                onclick="showToast('info','Chi tiết','Đang mở chi tiết tổ...')">
          <i class="fas fa-eye"></i> Chi tiết
        </button>
        <a href="/teachers.php?dept=<?= e($dept['id']) ?>" class="btn btn-outline btn-sm">
          <i class="fas fa-users"></i> Danh sách GV
        </a>
        <a href="/documents.php?dept=<?= e($dept['id']) ?>" class="btn btn-outline btn-sm"
           title="Kho tài liệu">
          <i class="fas fa-folder-open"></i>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include TEMPLATES_DIR . '/_footer.php'; ?>
