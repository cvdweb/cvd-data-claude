<?php
/**
 * notifications.php — Trung tâm thông báo
 */
require_once __DIR__ . '/_path.php';

$currentUser = $auth->requireLogin();

// Đánh dấu đã đọc tất cả
if (input('action') === 'read_all') {
    $allNotifs = $db->read('notifications');
    foreach ($allNotifs as $n) {
        if (($n['user_id'] ?? '') === $currentUser['id'] && !($n['is_read'] ?? false)) {
            $db->update('notifications', $n['id'], ['is_read' => true]);
        }
    }
    flash('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    redirect('/notifications.php');
}

// Đánh dấu 1 thông báo đã đọc
if (input('action') === 'read' && input('id')) {
    $db->update('notifications', input('id'), ['is_read' => true]);
    redirect(input('link') ?: '/notifications.php');
}

// Xóa thông báo
if (input('action') === 'delete' && input('id')) {
    $db->delete('notifications', input('id'));
    flash('success', 'Đã xóa thông báo.');
    redirect('/notifications.php');
}

// Lấy tất cả thông báo của user hiện tại
$allNotifs = $db->where('notifications', 'user_id', $currentUser['id']);
// Sắp xếp mới nhất lên đầu
usort($allNotifs, fn($a,$b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// Filter
$filterType = input('type', 'all');
$filtered   = $allNotifs;
if ($filterType === 'unread') {
    $filtered = array_filter($allNotifs, fn($n) => !($n['is_read'] ?? false));
} elseif ($filterType !== 'all') {
    $filtered = array_filter($allNotifs, fn($n) => ($n['type'] ?? '') === $filterType);
}
$filtered = array_values($filtered);

// Thống kê
$totalCount    = count($allNotifs);
$unreadCount   = count(array_filter($allNotifs, fn($n) => !($n['is_read'] ?? false)));
$byType        = [];
foreach ($allNotifs as $n) {
    $t = $n['type'] ?? 'system';
    $byType[$t] = ($byType[$t] ?? 0) + 1;
}

$unreadNotifCount = $unreadCount;
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Type config
$typeConfig = [
    'request'  => ['#1a6ef5', 'var(--primary-light)',  'fa-tasks',       'Yêu cầu'],
    'deadline' => ['#ef4444', 'var(--danger-light)',   'fa-clock',       'Hạn nộp'],
    'doc'      => ['#10b981', 'var(--accent-light)',   'fa-file-upload', 'Tài liệu'],
    'profile'  => ['#8b5cf6', '#f3e8ff',               'fa-user-edit',   'Hồ sơ'],
    'system'   => ['#64748b', 'var(--bg)',              'fa-cog',         'Hệ thống'],
];

$pageTitle    = 'Trung tâm Thông báo';
$pageSubtitle = 'Quản lý tất cả thông báo hệ thống';
$activePage   = 'notifications.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start" class="notif-layout">

  <!-- Main list -->
  <div>
    <!-- Top bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">

      <!-- Filter tabs -->
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php
        $tabs = [
          ['all',      "Tất cả ({$totalCount})"],
          ['unread',   "Chưa đọc ({$unreadCount})"],
          ['request',  '<i class="fas fa-tasks"></i> Yêu cầu'],
          ['deadline', '<i class="fas fa-clock"></i> Hạn nộp'],
          ['doc',      '<i class="fas fa-file"></i> Tài liệu'],
          ['system',   '<i class="fas fa-cog"></i> Hệ thống'],
        ];
        foreach ($tabs as [$val, $label]):
          $isActive = $filterType === $val;
        ?>
          <a href="/notifications.php?type=<?= $val ?>"
             style="padding:7px 14px;border-radius:99px;font-size:13px;font-weight:600;
                    border:1.5px solid <?= $isActive ? 'var(--primary)' : 'var(--border)' ?>;
                    background:<?= $isActive ? 'var(--primary)' : 'var(--bg-card)' ?>;
                    color:<?= $isActive ? 'white' : 'var(--text-secondary)' ?>;
                    text-decoration:none;display:flex;align-items:center;gap:6px;transition:all .2s">
            <?= $label ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:8px">
        <?php if ($unreadCount > 0): ?>
          <a href="/notifications.php?action=read_all"
             class="btn btn-outline btn-sm">
            <i class="fas fa-check-double"></i> Đánh dấu đã đọc
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notification list -->
    <?php if (empty($filtered)): ?>
      <div class="empty-state">
        <div class="empty-icon">🔔</div>
        <div class="empty-title">Không có thông báo nào</div>
        <div class="empty-desc">
          <?= $filterType !== 'all' ? 'Không có thông báo trong danh mục này.' : 'Bạn chưa có thông báo nào.' ?>
        </div>
      </div>
    <?php else: ?>
      <?php
      // Group theo ngày
      $groups = [];
      foreach ($filtered as $n) {
          $date = date('Y-m-d', strtotime($n['created_at'] ?? 'now'));
          $groups[$date][] = $n;
      }
      $today    = '2025-01-21'; // demo date
      $yesterday= date('Y-m-d', strtotime($today . ' -1 day'));

      $wdays = ['Chủ nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
      foreach ($groups as $date => $notifs):
          $d = new DateTime($date);
          if ($date === $today)     $groupLabel = 'Hôm nay';
          elseif ($date === $yesterday) $groupLabel = 'Hôm qua';
          else $groupLabel = $wdays[$d->format('w')] . ', ' . $d->format('d/m/Y');
          $isToday = $date === $today;
      ?>
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:<?= $isToday ? 'var(--primary)' : 'var(--text-muted)' ?>;padding:10px 0 8px;display:flex;align-items:center;gap:6px">
          <?php if ($isToday): ?>
            <i class="fas fa-circle" style="font-size:7px"></i>
          <?php endif; ?>
          <?= $groupLabel ?>
        </div>

        <?php foreach ($notifs as $n):
          $tp     = $typeConfig[$n['type'] ?? 'system'] ?? $typeConfig['system'];
          $isRead = $n['is_read'] ?? false;
        ?>
          <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px;display:flex;gap:12px;margin-bottom:10px;cursor:pointer;transition:all .2s;<?= !$isRead ? 'border-left:3px solid var(--primary);' : '' ?>"
               onmouseenter="this.style.boxShadow='var(--shadow)';this.style.borderColor='<?= $tp[0] ?>'"
               onmouseleave="this.style.boxShadow='';this.style.borderColor='<?= !$isRead ? 'var(--primary)' : 'var(--border)' ?>'"
               onclick="location.href='/notifications.php?action=read&id=<?= e($n['id']) ?>&link=<?= urlencode($n['link'] ?? '/dashboard.php') ?>'">

            <!-- Icon -->
            <div style="width:42px;height:42px;border-radius:11px;background:<?= $tp[1] ?>;color:<?= $tp[0] ?>;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0">
              <i class="fas <?= $tp[2] ?>"></i>
            </div>

            <!-- Content -->
            <div style="flex:1;min-width:0">
              <div style="font-size:13.5px;font-weight:<?= !$isRead ? '700' : '600' ?>;margin-bottom:5px;color:<?= !$isRead ? 'var(--text-primary)' : 'var(--text-secondary)' ?>">
                <?= $n['body'] ?? '' ?>
              </div>
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-size:11.5px;color:var(--text-muted);display:flex;align-items:center;gap:4px">
                  <i class="fas fa-clock"></i> <?= timeAgo($n['created_at'] ?? '') ?>
                </span>
                <span class="badge" style="background:<?= $tp[1] ?>;color:<?= $tp[0] ?>;font-size:10.5px">
                  <?= $tp[3] ?>
                </span>
                <?php if (!$isRead): ?>
                  <span style="width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block" title="Chưa đọc"></span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0" onclick="event.stopPropagation()">
              <?php if (!$isRead): ?>
                <a href="/notifications.php?action=read&id=<?= e($n['id']) ?>"
                   class="btn-icon" style="width:32px;height:32px;border-radius:8px" title="Đánh dấu đã đọc">
                  <i class="fas fa-check" style="font-size:12px"></i>
                </a>
              <?php endif; ?>
              <a href="/notifications.php?action=delete&id=<?= e($n['id']) ?>"
                 class="btn-icon" style="width:32px;height:32px;border-radius:8px;color:var(--danger)"
                 title="Xóa" onclick="return confirm('Xóa thông báo này?')">
                <i class="fas fa-trash" style="font-size:12px"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Right sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Summary -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px">
      <div style="font-size:13.5px;font-weight:800;margin-bottom:14px">📊 Tổng quan</div>
      <?php foreach ([
        ['fa-circle text-danger', 'Chưa đọc',   $unreadCount,          'var(--danger)'],
        ['fa-tasks',              'Yêu cầu',     $byType['request']??0, 'var(--primary)'],
        ['fa-clock',              'Hạn nộp',     $byType['deadline']??0,'#ef4444'],
        ['fa-file-upload',        'Tài liệu',    $byType['doc']??0,     '#10b981'],
        ['fa-cog',                'Hệ thống',    $byType['system']??0,  '#64748b'],
      ] as [$icon, $label, $val, $color]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border-light);font-size:13px">
          <div style="display:flex;align-items:center;gap:8px;color:var(--text-secondary)">
            <i class="fas <?= $icon ?>" style="width:16px;text-align:center;color:<?= $color ?>"></i>
            <?= $label ?>
          </div>
          <span style="font-weight:700;color:<?= $color ?>"><?= $val ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick actions -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px">
      <div style="font-size:13.5px;font-weight:800;margin-bottom:12px">⚡ Thao tác nhanh</div>
      <?php foreach ([
        ['/requests.php',       'fa-tasks',       'Xem tất cả yêu cầu'],
        ['/teachers.php',       'fa-user-clock',  'GV chưa cập nhật hồ sơ'],
        ['/documents.php',      'fa-folder-open', 'Vào kho tài liệu'],
        ['/reports.php',        'fa-chart-pie',   'Xem báo cáo thống kê'],
        ['/calendar.php',       'fa-calendar',    'Xem lịch sự kiện'],
        ['/settings.php',       'fa-bell-slash',  'Cài đặt thông báo'],
      ] as [$href, $icon, $label]): ?>
        <a href="<?= $href ?>"
           style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg-card);font-size:13px;font-weight:500;color:var(--text-primary);text-decoration:none;margin-bottom:8px;transition:all .2s"
           onmouseenter="this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';this.style.color='var(--primary)'"
           onmouseleave="this.style.borderColor='var(--border)';this.style.background='var(--bg-card)';this.style.color='var(--text-primary)'">
          <i class="fas <?= $icon ?>" style="width:16px;text-align:center;color:var(--text-muted)"></i>
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Upcoming deadlines -->
    <?php
    $requests = $db->read('requests');
    $todayStr = '2025-01-21';
    $nearDeadlines = array_filter($requests, function($r) use ($todayStr) {
        if (($r['status'] ?? '') === 'complete') return false;
        $dl   = $r['deadline'] ?? '';
        $days = (strtotime($dl) - strtotime($todayStr)) / 86400;
        return $days >= 0 && $days <= 30;
    });
    usort($nearDeadlines, fn($a,$b) => strcmp($a['deadline']??'', $b['deadline']??''));
    $nearDeadlines = array_slice($nearDeadlines, 0, 4);
    ?>
    <?php if (!empty($nearDeadlines)): ?>
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px">
        <div style="font-size:13.5px;font-weight:800;margin-bottom:12px">⏰ Hạn nộp sắp đến</div>
        <?php foreach ($nearDeadlines as $r):
          $days   = (int)ceil((strtotime($r['deadline']) - strtotime($todayStr)) / 86400);
          $color  = $days <= 3 ? '#ef4444' : ($days <= 7 ? '#f59e0b' : '#10b981');
        ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border-light)">
            <div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></div>
              <span style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['title']) ?></span>
            </div>
            <span style="font-size:12px;font-weight:700;color:<?= $color ?>;margin-left:8px;white-space:nowrap">
              <?= $days === 0 ? 'Hôm nay' : "Còn {$days}d" ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include TEMPLATES_DIR . '/_footer.php'; ?>
