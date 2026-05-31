<?php
/**
 * calendar.php — Lịch & Sự kiện
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$events           = $db->read('events');
$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Xử lý thêm sự kiện mới (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'add_event') {
    $errors = validate($_POST, [
        'title'    => 'required|min:3',
        'date'     => 'required|date',
        'type'     => 'required',
    ]);
    if (empty($errors)) {
        $db->insert('events', [
            'title'       => input('title'),
            'type'        => input('type'),
            'date'        => input('date'),
            'end_date'    => input('end_date') ?: null,
            'time'        => input('time') ?: null,
            'location'    => input('location') ?: '—',
            'description' => input('description') ?: '',
            'created_by'  => $currentUser['id'],
            'dept'        => input('dept') ?: 'Toàn trường',
        ]);
        flash('success', 'Đã thêm sự kiện "' . input('title') . '" vào lịch.');
        redirect('/calendar.php');
    }
}

// Xóa sự kiện
if (input('action') === 'delete' && input('id') && $currentUser['role'] === 'admin') {
    $db->delete('events', input('id'));
    flash('success', 'Đã xóa sự kiện.');
    redirect('/calendar.php');
}

// Tháng/năm đang xem
$today = new DateTime('2025-01-21'); // Demo date
$month = (int)input('month', $today->format('n'));
$year  = (int)input('year',  $today->format('Y'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$viewDate  = new DateTime("{$year}-{$month}-01");
$prevMonth = (clone $viewDate)->modify('-1 month');
$nextMonth = (clone $viewDate)->modify('+1 month');

// Group events theo ngày
$eventsByDate = [];
foreach ($events as $ev) {
    $date = $ev['date'] ?? '';
    if (!$date) continue;
    // Sự kiện nhiều ngày
    if (!empty($ev['end_date']) && $ev['end_date'] !== $date) {
        $cur = new DateTime($date);
        $end = new DateTime($ev['end_date']);
        while ($cur <= $end) {
            $key = $cur->format('Y-m-d');
            $eventsByDate[$key][] = $ev;
            $cur->modify('+1 day');
        }
    } else {
        $eventsByDate[$date][] = $ev;
    }
}

// Upcoming events (từ hôm nay trở đi)
$todayStr = $today->format('Y-m-d');
$upcoming = array_slice(
    array_filter($events, fn($e) => ($e['date'] ?? '') >= $todayStr),
    0, 6
);

// Tháng dạng tiếng Việt
$monthsVI = ['','Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6',
             'Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];

// Type config
$typeConfig = [
    'meeting'  => ['#1a6ef5', 'rgba(26,110,245,.15)',  'Họp / Hội nghị', 'fa-users'],
    'deadline' => ['#ef4444', 'rgba(239,68,68,.15)',   'Hạn nộp',        'fa-clock'],
    'exam'     => ['#8b5cf6', 'rgba(139,92,246,.15)',  'Kiểm tra / Thi', 'fa-pen'],
    'training' => ['#10b981', 'rgba(16,185,129,.15)',  'Bồi dưỡng',      'fa-book-open'],
    'holiday'  => ['#f59e0b', 'rgba(245,158,11,.15)',  'Nghỉ lễ',        'fa-star'],
    'other'    => ['#64748b', 'rgba(100,116,139,.15)', 'Khác',           'fa-circle'],
];

$pageTitle    = 'Lịch & Sự kiện';
$pageSubtitle = 'Lịch họp, hạn nộp và sự kiện nhà trường';
$activePage   = 'calendar.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<style>
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); }
.cal-dow  { display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid var(--border-light); }
.cal-dow-cell { padding:10px 6px; text-align:center; font-size:11.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); }
.cal-dow-cell:first-child { color:var(--danger); }
.cal-dow-cell:last-child  { color:var(--primary); }
.cal-cell {
  min-height:100px; padding:5px 5px 4px;
  border-right:1px solid var(--border-light);
  border-bottom:1px solid var(--border-light);
  cursor:pointer; transition:background .15s; position:relative; overflow:hidden;
}
.cal-cell:nth-child(7n) { border-right:none; }
.cal-cell:hover { background:var(--bg); }
.cal-cell.other-month { background:var(--bg); }
.cal-cell.other-month .cal-day { color:var(--text-muted); opacity:.4; }
.cal-cell.is-today { background:var(--primary-light); }
.cal-day {
  font-size:13px; font-weight:700; color:var(--text-primary);
  width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center;
  border-radius:50%; margin-bottom:3px;
}
.cal-cell.is-today .cal-day { background:var(--primary); color:white; }
.cal-cell:nth-child(7n+1) .cal-day { color:var(--danger); }
.cal-cell:nth-child(7n)   .cal-day { color:var(--primary); }
.cal-cell.is-today .cal-day { color:white !important; }
.cal-ev {
  display:block; padding:2px 5px; border-radius:4px;
  font-size:10.5px; font-weight:600; margin-bottom:2px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  cursor:pointer; transition:opacity .15s;
}
.cal-ev:hover { opacity:.8; }
.cal-more { font-size:10px; color:var(--text-muted); font-weight:600; padding:0 3px; cursor:pointer; }
.cal-more:hover { color:var(--primary); }
@media(max-width:600px) {
  .cal-cell { min-height:60px; padding:3px; }
  .cal-ev { display:none; }
  .cal-more { display:block !important; font-size:9px; }
}
</style>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start" class="cal-layout">

  <!-- Main calendar -->
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-sm)">

    <!-- Toolbar -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 14px;border-bottom:1px solid var(--border-light);flex-wrap:wrap;gap:10px">
      <div style="display:flex;align-items:center;gap:8px">
        <a href="/calendar.php?month=<?= $prevMonth->format('n') ?>&year=<?= $prevMonth->format('Y') ?>"
           style="width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--bg-card);display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:13px;text-decoration:none;transition:all .2s"
           onmouseenter="this.style.background='var(--primary)';this.style.color='white'"
           onmouseleave="this.style.background='';this.style.color=''">
          <i class="fas fa-chevron-left"></i>
        </a>
        <a href="/calendar.php"
           style="padding:7px 14px;border-radius:9px;border:1px solid var(--border);background:var(--bg-card);font-size:12.5px;font-weight:700;color:var(--text-secondary);text-decoration:none">
          Hôm nay
        </a>
        <a href="/calendar.php?month=<?= $nextMonth->format('n') ?>&year=<?= $nextMonth->format('Y') ?>"
           style="width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--bg-card);display:flex;align-items:center;justify-content:center;color:var(--text-secondary);font-size:13px;text-decoration:none;transition:all .2s"
           onmouseenter="this.style.background='var(--primary)';this.style.color='white'"
           onmouseleave="this.style.background='';this.style.color=''">
          <i class="fas fa-chevron-right"></i>
        </a>
      </div>

      <div style="font-size:18px;font-weight:800">
        <?= $monthsVI[$month] ?> năm <?= $year ?>
      </div>

      <div style="display:flex;gap:8px">
        <?php if (in_array($currentUser['role'], ['admin', 'dept_head'])): ?>
          <button class="btn btn-primary btn-sm" data-open-modal="addEventModal">
            <i class="fas fa-plus"></i> Thêm sự kiện
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Day of week header -->
    <div class="cal-dow">
      <?php foreach (['CN','T2','T3','T4','T5','T6','T7'] as $d): ?>
        <div class="cal-dow-cell"><?= $d ?></div>
      <?php endforeach; ?>
    </div>

    <!-- Calendar grid -->
    <?php
    $firstDay      = (int)(new DateTime("{$year}-{$month}-01"))->format('w'); // 0=Sun
    $daysInMonth   = (int)(new DateTime("{$year}-{$month}-01"))->format('t');
    $daysInPrev    = (int)(new DateTime($prevMonth->format('Y-m-01')))->format('t');
    $todayDateStr  = $today->format('Y-m-d');

    $cells = [];
    for ($i = $firstDay - 1; $i >= 0; $i--) {
        $d = new DateTime($prevMonth->format('Y-m-') . str_pad($daysInPrev - $i, 2, '0', STR_PAD_LEFT));
        $cells[] = ['date' => $d, 'current' => false];
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $cells[] = ['date' => new DateTime("{$year}-{$month}-" . str_pad($d, 2, '0', STR_PAD_LEFT)), 'current' => true];
    }
    $remaining = 42 - count($cells);
    for ($d = 1; $d <= $remaining; $d++) {
        $cells[] = ['date' => new DateTime($nextMonth->format('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT)), 'current' => false];
    }
    ?>
    <div class="cal-grid">
      <?php foreach ($cells as $cell):
        $dateStr  = $cell['date']->format('Y-m-d');
        $dayEvs   = $eventsByDate[$dateStr] ?? [];
        $isToday  = $dateStr === $todayDateStr;
        $isCur    = $cell['current'];
        $classes  = 'cal-cell' . (!$isCur ? ' other-month' : '') . ($isToday ? ' is-today' : '');
        $showEvs  = array_slice($dayEvs, 0, 2);
        $moreCount= count($dayEvs) - 2;
      ?>
        <div class="<?= $classes ?>" onclick="openDayModal('<?= $dateStr ?>')" data-date="<?= $dateStr ?>">
          <div class="cal-day"><?= $cell['date']->format('j') ?></div>
          <?php foreach ($showEvs as $ev):
            $tp = $typeConfig[$ev['type'] ?? 'other'] ?? $typeConfig['other'];
          ?>
            <span class="cal-ev"
                  style="background:<?= $tp[1] ?>;color:<?= $tp[0] ?>"
                  onclick="event.stopPropagation();openEventModal(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)"
                  title="<?= e($ev['title']) ?>">
              <?= e($ev['title']) ?>
            </span>
          <?php endforeach; ?>
          <?php if ($moreCount > 0): ?>
            <div class="cal-more">+<?= $moreCount ?> sự kiện</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Legend -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px">
      <div style="font-size:13.5px;font-weight:800;margin-bottom:12px">📌 Loại sự kiện</div>
      <?php foreach ($typeConfig as $key => [$color, $bg, $label, $icon]):
        $cnt = count(array_filter($events, fn($e) => ($e['type'] ?? '') === $key));
      ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light);font-size:13px">
          <div style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;flex-shrink:0"></div>
          <span style="flex:1"><?= $label ?></span>
          <span style="font-size:12px;font-weight:700;color:var(--text-muted)"><?= $cnt ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Upcoming events -->
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
      <div style="padding:14px 16px 10px;font-size:13.5px;font-weight:800;border-bottom:1px solid var(--border-light)">
        ⏰ Sắp diễn ra
      </div>
      <?php if (empty($upcoming)): ?>
        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
          Không có sự kiện nào sắp tới
        </div>
      <?php else: ?>
        <?php foreach ($upcoming as $ev):
          $d  = new DateTime($ev['date']);
          $tp = $typeConfig[$ev['type'] ?? 'other'] ?? $typeConfig['other'];
        ?>
          <div style="display:flex;gap:12px;padding:11px 16px;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .15s"
               onclick="openEventModal(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)"
               onmouseenter="this.style.background='var(--bg)'"
               onmouseleave="this.style.background=''">
            <div style="text-align:center;flex-shrink:0;width:38px">
              <div style="font-size:18px;font-weight:900;color:<?= $tp[0] ?>;line-height:1"><?= $d->format('j') ?></div>
              <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted)">Th<?= $d->format('n') ?></div>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:12.5px;font-weight:700;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($ev['title']) ?></div>
              <div style="font-size:11px;color:<?= $tp[0] ?>"><?= $tp[2] ?><?= $ev['time'] ? ' · '.$ev['time'] : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Event Detail Modal -->
<div class="modal-overlay" id="eventDetailModal" style="display:none">
  <div class="modal" style="max-width:480px" id="eventDetailContent">
    <div style="padding:60px;text-align:center"><div class="lo-ring" style="margin:0 auto"></div></div>
  </div>
</div>

<!-- Day Events Modal -->
<div class="modal-overlay" id="dayModal" style="display:none">
  <div class="modal" style="max-width:480px" id="dayModalContent">
    <div style="padding:60px;text-align:center"><div class="lo-ring" style="margin:0 auto"></div></div>
  </div>
</div>

<!-- Add Event Modal -->
<?php if (in_array($currentUser['role'], ['admin', 'dept_head'])): ?>
<div class="modal-overlay" id="addEventModal" style="display:none">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-calendar-plus"></i> Thêm sự kiện mới</span>
      <button class="btn-icon" data-close-modal="addEventModal"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="/calendar.php">
      <input type="hidden" name="action" value="add_event">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tên sự kiện <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="VD: Họp tổ chuyên môn tháng 2" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Ngày bắt đầu <span style="color:var(--danger)">*</span></label>
            <input type="date" name="date" class="form-control" value="<?= $today->format('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Ngày kết thúc</label>
            <input type="date" name="end_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Giờ bắt đầu</label>
            <input type="time" name="time" class="form-control" value="07:30">
          </div>
          <div class="form-group">
            <label class="form-label">Loại sự kiện <span style="color:var(--danger)">*</span></label>
            <select name="type" class="form-control" required>
              <?php foreach ($typeConfig as $key => [,,$label]): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Địa điểm</label>
          <input type="text" name="location" class="form-control" placeholder="VD: Phòng họp A, Hội trường...">
        </div>
        <div class="form-group">
          <label class="form-label">Đối tượng</label>
          <select name="dept" class="form-control">
            <option value="Toàn trường">Toàn trường</option>
            <option value="Ban Giám hiệu">Ban Giám hiệu</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= e($d['name']) ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Nội dung, chú ý..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="addEventModal">Hủy</button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Lưu sự kiện
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$typeJson    = json_encode($typeConfig);
$eventsJson  = json_encode($events);
$byDateJson  = json_encode($eventsByDate);
$canDelete   = json_encode($currentUser['role'] === 'admin');
$wdays = ['Chủ nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
$wdaysJson = json_encode($wdays);

$inlineScript = <<<JS
const TYPE_CONFIG = $typeJson;
const ALL_EVENTS  = $eventsJson;
const BY_DATE     = $byDateJson;
const CAN_DELETE  = $canDelete;
const WDAYS       = $wdaysJson;

function openEventModal(ev) {
    if (typeof ev === 'string') ev = JSON.parse(ev);
    const tp  = TYPE_CONFIG[ev.type] || TYPE_CONFIG.other;
    const d   = new Date(ev.date + 'T00:00:00');
    const dow = WDAYS[d.getDay()];
    const dateStr = dow + ', ' + d.getDate() + '/' + (d.getMonth()+1) + '/' + d.getFullYear();
    const endStr  = ev.end_date && ev.end_date !== ev.date
        ? ' → ' + new Date(ev.end_date + 'T00:00:00').toLocaleDateString('vi-VN') : '';

    document.getElementById('eventDetailContent').innerHTML = `
        <div style="background:linear-gradient(135deg,\${tp[0]},\${tp[0]}cc);padding:20px 22px;border-radius:var(--radius-xl) var(--radius-xl) 0 0;color:white">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <div style="width:38px;height:38px;border-radius:9px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px">
                    <i class="fas \${tp[3]}"></i>
                </div>
                <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:99px;padding:3px 12px;font-size:12px;font-weight:700">\${tp[2]}</span>
                <button style="margin-left:auto;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:5px 10px;color:white;cursor:pointer;font-size:13px"
                        onclick="closeModal('eventDetailModal')">✕</button>
            </div>
            <h2 style="font-size:17px;font-weight:800;margin-bottom:4px">\${ev.title}</h2>
            <p style="opacity:.8;font-size:13px">\${dateStr}\${endStr}</p>
        </div>
        <div style="padding:20px 22px">
            \${[
                ['fa-calendar','\${dateStr}\${endStr}'],
                ev.time ? ['fa-clock',ev.time] : null,
                ['fa-map-marker-alt', ev.location || '—'],
                ['fa-users', ev.dept || 'Toàn trường'],
                ev.description ? ['fa-info-circle', ev.description] : null,
            ].filter(Boolean).map(([ico,val]) =>
                '<div style="display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid var(--border-light);font-size:13.5px">' +
                '<i class="fas ' + ico + '" style="width:16px;color:var(--text-muted);margin-top:2px;flex-shrink:0"></i>' +
                '<span>' + val + '</span></div>'
            ).join('')}
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                \${CAN_DELETE ? '<a href="/calendar.php?action=delete&id='+ev.id+'" class="btn btn-outline btn-sm" style="color:var(--danger)" onclick="return confirm(\'Xóa sự kiện này?\')"><i class="fas fa-trash"></i> Xóa</a>' : ''}
                <button class="btn btn-outline" onclick="closeModal('eventDetailModal')">Đóng</button>
            </div>
        </div>`;
    openModal('eventDetailModal');
}

function openDayModal(dateStr) {
    const evs = BY_DATE[dateStr] || [];
    if (!evs.length) {
        // Mở form thêm sự kiện với ngày được điền sẵn
        document.querySelector('[name=date]').value = dateStr;
        openModal('addEventModal');
        return;
    }
    const d   = new Date(dateStr + 'T00:00:00');
    const dow = WDAYS[d.getDay()];

    document.getElementById('dayModalContent').innerHTML = `
        <div class="modal-header">
            <span class="modal-title">\${dow}, \${d.getDate()}/\${d.getMonth()+1}/\${d.getFullYear()}</span>
            <button class="btn-icon" onclick="closeModal('dayModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            \${evs.map(ev => {
                const tp = TYPE_CONFIG[ev.type] || TYPE_CONFIG.other;
                return '<div style="display:flex;gap:10px;padding:11px 0;border-bottom:1px solid var(--border-light);cursor:pointer" onclick="closeModal(\'dayModal\');openEventModal('+JSON.stringify(JSON.stringify(ev))+')">' +
                '<div style="width:4px;border-radius:99px;background:'+tp[0]+';flex-shrink:0"></div>' +
                '<div style="width:38px;height:38px;border-radius:9px;background:'+tp[1]+';color:'+tp[0]+';display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="fas '+tp[3]+'"></i></div>' +
                '<div style="flex:1"><div style="font-size:13.5px;font-weight:700">'+ev.title+'</div>' +
                '<div style="font-size:12px;color:'+tp[0]+';margin-top:2px">'+tp[2]+(ev.time?' · '+ev.time:'')+'</div></div></div>';
            }).join('')}
            <div style="margin-top:12px;text-align:right">
                <button class="btn btn-outline btn-sm" onclick="closeModal('dayModal')">Đóng</button>
            </div>
        </div>`;
    openModal('dayModal');
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
