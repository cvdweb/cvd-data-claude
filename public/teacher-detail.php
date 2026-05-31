<?php
/**
 * teacher-detail.php — Hồ sơ chi tiết giáo viên
 */
require_once __DIR__ . '/_path.php';

$currentUser = $auth->requireLogin();

// Lấy ID từ URL
$teacherId = input('id');
if (!$teacherId) redirect('/teachers.php');

$teacher = $db->find('teachers', $teacherId);
if (!$teacher) {
    flash('danger', 'Không tìm thấy giáo viên này.');
    redirect('/teachers.php');
}

// Tổ trưởng chỉ xem GV trong tổ mình
if ($currentUser['role'] === 'dept_head') {
    if (($teacher['work']['dept_id'] ?? '') !== $currentUser['dept_id']) {
        flash('danger', 'Bạn không có quyền xem hồ sơ này.');
        redirect('/teachers.php');
    }
}

// GV chỉ xem hồ sơ của chính mình
if ($currentUser['role'] === 'teacher') {
    if (($currentUser['teacher_id'] ?? '') !== $teacherId) {
        redirect('/my-profile.php');
    }
}

// Lấy thêm dữ liệu
$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Tìm tên tổ
$deptName = '';
foreach ($departments as $d) {
    if ($d['id'] === ($teacher['work']['dept_id'] ?? '')) {
        $deptName = $d['name'];
        break;
    }
}

// Tính năm công tác
$yearsService = yearsOfService($teacher['work']['join_date'] ?? null);

// Dữ liệu
$name    = $teacher['personal']['full_name'] ?? '—';
$avatar  = $teacher['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=200';
$status  = $teacher['status'] ?? 'pending';
$compl   = $teacher['completion_pct'] ?? 0;

$statusMap = [
    'complete'   => ['badge-success', 'Hoàn thành',    '#22c55e'],
    'pending'    => ['badge-warning', 'Chưa cập nhật', '#f59e0b'],
    'processing' => ['badge-info',    'Đang xử lý',    '#3b82f6'],
];
[$badgeCls, $badgeLabel, $dotColor] = $statusMap[$status];

$pageTitle    = e($name);
$pageSubtitle = ($teacher['work']['subject'] ?? '') . ' · ' . $deptName;
$activePage   = 'teachers.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Breadcrumb -->
<div style="margin-bottom:16px">
  <?= breadcrumb([
    'Hồ sơ giáo viên' => '/teachers.php',
    $name             => null,
  ]) ?>
</div>

<!-- Hero banner -->
<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0a3a8a 100%);border-radius:var(--radius-xl);padding:28px 32px;display:flex;align-items:center;gap:24px;margin-bottom:24px;position:relative;overflow:hidden;flex-wrap:wrap">
  <div style="position:absolute;right:-40px;top:-40px;width:260px;height:260px;background:rgba(255,255,255,0.04);border-radius:50%;pointer-events:none"></div>

  <!-- Avatar -->
  <div style="position:relative;flex-shrink:0">
    <img src="<?= e($avatar) ?>"
         style="width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,.25);object-fit:cover"
         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=200'"
         alt="<?= e($name) ?>">
    <div style="position:absolute;bottom:2px;right:2px;width:18px;height:18px;border-radius:50%;background:<?= $dotColor ?>;border:2px solid white"></div>
  </div>

  <!-- Info -->
  <div style="flex:1;min-width:200px">
    <h1 style="color:white;font-size:22px;font-weight:900;margin-bottom:5px"><?= e($name) ?></h1>
    <p style="color:rgba(255,255,255,.7);font-size:13.5px;margin-bottom:12px">
      <?= e($teacher['work']['subject'] ?? '—') ?> · <?= e($deptName) ?>
    </p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ([
        $teacher['education']['degree'] ?? 'Đại học',
        $teacher['work']['rank'] ?? 'GV hạng III',
        ($teacher['work']['party_member'] ?? false) ? '🏅 Đảng viên' : 'Quần chúng',
        $badgeLabel,
      ] as $tag): ?>
        <span style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:99px;padding:4px 12px;color:white;font-size:12px">
          <?= e($tag) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:flex;gap:24px;padding:0 20px;border-left:1px solid rgba(255,255,255,.12);z-index:1;flex-wrap:wrap">
    <?php foreach ([
      [$yearsService, 'Năm công tác'],
      [$teacher['personal']['dob'] ? date('Y') - (int)date('Y', strtotime($teacher['personal']['dob'])) : '—', 'Tuổi'],
      [count($teacher['certificates'] ?? []), 'Chứng chỉ'],
      [count($teacher['achievements'] ?? []), 'Thành tích'],
    ] as [$val, $lbl]): ?>
      <div style="text-align:center">
        <div style="font-size:22px;font-weight:900;color:white;line-height:1"><?= $val ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.55);margin-top:4px"><?= $lbl ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Actions -->
  <div style="display:flex;flex-direction:column;gap:8px;z-index:1">
    <?php if (in_array($currentUser['role'], ['admin', 'dept_head'])): ?>
      <a href="/teacher-detail.php?id=<?= e($teacherId) ?>&edit=1"
         style="background:white;color:var(--primary);border-radius:var(--radius-sm);padding:9px 16px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:7px;text-decoration:none">
        <i class="fas fa-edit"></i> Chỉnh sửa
      </a>
      <button onclick="showToast('info','Xuất PDF','Đang tạo file PDF hồ sơ...')"
              style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:var(--radius-sm);padding:9px 16px;font-size:13px;font-weight:600;color:white;cursor:pointer;display:flex;align-items:center;gap:7px">
        <i class="fas fa-file-pdf"></i> Xuất PDF
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Tabs + Content -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start" class="detail-layout">

  <div>
    <!-- Tab bar -->
    <div style="display:flex;gap:2px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:4px;margin-bottom:20px;overflow-x:auto" id="tabBar">
      <?php
      $tabs = [
        ['fa-id-card',        'Cá nhân',   'tab-personal'],
        ['fa-briefcase',      'Công tác',  'tab-work'],
        ['fa-graduation-cap', 'Đào tạo',   'tab-edu'],
        ['fa-certificate',    'Chứng chỉ', 'tab-cert'],
        ['fa-trophy',         'Thành tích','tab-achieve'],
        ['fa-paperclip',      'Minh chứng','tab-files'],
      ];
      foreach ($tabs as $i => [$icon, $label, $id]): ?>
        <button class="tab-btn <?= $i === 0 ? 'active' : '' ?>"
                onclick="switchTab('<?= $id ?>', this)"
                style="display:flex;align-items:center;gap:6px;white-space:nowrap">
          <i class="fas <?= $icon ?>"></i> <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- TAB: Cá nhân -->
    <div id="tab-personal">
      <?php
      $sections = [
        ['Thông tin cơ bản', 'fa-user', 'var(--primary-light)', 'var(--primary)', [
          ['Họ và tên',      $teacher['personal']['full_name'] ?? '—'],
          ['Ngày sinh',      formatDate($teacher['personal']['dob'] ?? null)],
          ['Giới tính',      $teacher['personal']['gender'] ?? '—'],
          ['Dân tộc',        $teacher['personal']['ethnicity'] ?? '—'],
          ['Tôn giáo',       $teacher['personal']['religion'] ?? 'Không'],
          ['Quê quán',       $teacher['personal']['hometown'] ?? '—'],
          ['Số CCCD',        $teacher['personal']['cccd'] ?? '—'],
          ['Ngày cấp CCCD',  formatDate($teacher['personal']['cccd_date'] ?? null)],
        ]],
        ['Liên hệ', 'fa-phone', 'var(--accent-light)', '#10b981', [
          ['Điện thoại',     $teacher['personal']['phone'] ?? '—'],
          ['Email',          $teacher['personal']['email'] ?? '—'],
          ['Địa chỉ',        $teacher['personal']['address'] ?? '—'],
        ]],
        ['Thông tin gia đình', 'fa-heart', '#f3e8ff', '#8b5cf6', [
          ['Hôn nhân',       $teacher['family']['marital_status'] ?? '—'],
          ['Tên vợ/chồng',   $teacher['family']['spouse_name'] ?? '—'],
          ['Nghề nghiệp',    $teacher['family']['spouse_job'] ?? '—'],
          ['SĐT vợ/chồng',   $teacher['family']['spouse_phone'] ?? '—'],
          ['Số con',         count($teacher['family']['children'] ?? []) . ' người'],
          ['Liên hệ khẩn',   $teacher['family']['emergency_contact'] ?? '—'],
        ]],
      ];
      ?>
      <?php foreach ($sections as [$title, $icon, $bg, $color, $rows]): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:8px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:13px">
                <i class="fas <?= $icon ?>"></i>
              </div>
              <div class="card-title"><?= $title ?></div>
            </div>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <?php foreach ($rows as [$k, $v]): ?>
                <div>
                  <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:3px"><?= $k ?></div>
                  <div style="font-size:13.5px;font-weight:600"><?= e($v ?: '—') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- TAB: Công tác -->
    <div id="tab-work" style="display:none">
      <div class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">Thông tin công tác hiện tại</div></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <?php foreach ([
              ['Tổ chuyên môn',  $deptName],
              ['Môn dạy chính',  $teacher['work']['subject']       ?? '—'],
              ['Chức vụ',        $teacher['work']['role']           ?? '—'],
              ['Hạng GV',        $teacher['work']['rank']           ?? '—'],
              ['Ngày vào ngành', formatDate($teacher['work']['join_date'] ?? null)],
              ['Số năm CT',      $yearsService . ' năm'],
              ['Số tiết/tuần',   ($teacher['work']['weekly_lessons'] ?? '—') . ' tiết'],
              ['Hệ số lương',    $teacher['work']['salary_coeff']   ?? '—'],
              ['Đảng viên',      ($teacher['work']['party_member'] ?? false) ? '✅ Đảng viên (' . ($teacher['work']['party_year'] ?? '') . ')' : '❌ Chưa vào Đảng'],
            ] as [$k, $v]): ?>
              <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:3px"><?= $k ?></div>
                <div style="font-size:13.5px;font-weight:600"><?= e($v) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Timeline -->
      <div class="card">
        <div class="card-header"><div class="card-title">Lịch sử công tác</div></div>
        <div class="card-body">
          <div class="timeline">
            <?php
            $joinYear = $teacher['work']['join_date'] ? date('Y', strtotime($teacher['work']['join_date'])) : '—';
            $timeline = [
              [date('Y'),   'Được xếp loại viên chức "Hoàn thành xuất sắc nhiệm vụ"'],
              [date('Y')-2, 'Nâng bậc lương theo định kỳ'],
              [date('Y')-4, 'Hoàn thành bồi dưỡng chuẩn chức danh nghề nghiệp'],
              [$joinYear,   'Bắt đầu công tác tại ' . SCHOOL_NAME],
            ];
            foreach ($timeline as [$year, $text]): ?>
              <div class="timeline-item">
                <div class="timeline-date"><?= e($year) ?></div>
                <div class="timeline-content"><?= e($text) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Đào tạo -->
    <div id="tab-edu" style="display:none">
      <div class="card">
        <div class="card-header"><div class="card-title">Trình độ đào tạo</div></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <?php foreach ([
              ['Trình độ cao nhất',  $teacher['education']['degree']       ?? '—'],
              ['Chuyên ngành',       $teacher['education']['major']         ?? '—'],
              ['Cơ sở đào tạo',      $teacher['education']['institution']   ?? '—'],
              ['Năm tốt nghiệp',     $teacher['education']['grad_year']     ?? '—'],
              ['Trình độ Ngoại ngữ', $teacher['education']['foreign_lang']  ?? '—'],
              ['Trình độ Tin học',   $teacher['education']['it_level']      ?? '—'],
            ] as [$k, $v]): ?>
              <div>
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:3px"><?= $k ?></div>
                <div style="font-size:13.5px;font-weight:600"><?= e($v ?: '—') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Chứng chỉ -->
    <div id="tab-cert" style="display:none">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Danh sách chứng chỉ</div>
          <?php if (in_array($currentUser['role'], ['admin'])): ?>
            <button class="btn btn-outline btn-sm"
                    onclick="showToast('info','Thêm','Chức năng đang phát triển.')">
              <i class="fas fa-plus"></i> Thêm
            </button>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (empty($teacher['certificates'])): ?>
            <div class="empty-state" style="padding:30px">
              <div class="empty-icon">📜</div>
              <div class="empty-title">Chưa có chứng chỉ nào</div>
            </div>
          <?php else: ?>
            <?php foreach ($teacher['certificates'] as $cert): ?>
              <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px">
                <div style="width:42px;height:42px;border-radius:10px;background:var(--warning-light);color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
                  <i class="fas fa-certificate"></i>
                </div>
                <div style="flex:1">
                  <div style="font-size:13.5px;font-weight:700;margin-bottom:3px"><?= e($cert['name'] ?? '—') ?></div>
                  <div style="font-size:12px;color:var(--text-muted)"><?= e($cert['issued_by'] ?? '—') ?></div>
                </div>
                <span class="badge badge-primary"><?= e($cert['year'] ?? '—') ?></span>
                <span class="badge badge-success">Đã xác nhận</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TAB: Thành tích -->
    <div id="tab-achieve" style="display:none">
      <div class="card">
        <div class="card-header"><div class="card-title">Danh hiệu &amp; Khen thưởng</div></div>
        <div class="card-body">
          <?php if (empty($teacher['achievements'])): ?>
            <div class="empty-state" style="padding:30px">
              <div class="empty-icon">🏆</div>
              <div class="empty-title">Chưa có thành tích nào</div>
            </div>
          <?php else: ?>
            <?php
            $levelBadge = ['school'=>'badge-neutral','district'=>'badge-info','province'=>'badge-primary','national'=>'badge-danger'];
            $levelLabel = ['school'=>'Cấp trường','district'=>'Cấp huyện','province'=>'Cấp tỉnh','national'=>'Cấp quốc gia'];
            foreach ($teacher['achievements'] as $ach):
            ?>
              <div style="display:flex;gap:12px;padding:14px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px">
                <div style="font-size:28px;line-height:1">🏆</div>
                <div style="flex:1">
                  <div style="font-size:13.5px;font-weight:700;margin-bottom:3px"><?= e($ach['title'] ?? '—') ?></div>
                  <div style="font-size:12px;color:var(--text-muted)"><?= e($ach['awarded_by'] ?? '—') ?></div>
                </div>
                <span class="badge badge-primary"><?= e($ach['year'] ?? '—') ?></span>
                <span class="badge <?= $levelBadge[$ach['level'] ?? 'school'] ?? 'badge-neutral' ?>">
                  <?= $levelLabel[$ach['level'] ?? 'school'] ?? $ach['level'] ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TAB: Minh chứng -->
    <div id="tab-files" style="display:none">
      <div class="card">
        <div class="card-header"><div class="card-title">File minh chứng đã nộp</div></div>
        <div class="card-body">
          <!-- Files mẫu -->
          <?php
          $sampleFiles = [
            ['Bang_tot_nghiep_' . strtolower($teacher['education']['degree'] ?? 'dh') . '.pdf', '1.2 MB', '#ef4444', 'var(--danger-light)', 'badge-success', 'Đã duyệt'],
            ['CCCD_mat_truoc.jpg', '234 KB', '#3b82f6', 'var(--info-light)', 'badge-success', 'Đã duyệt'],
          ];
          foreach ($sampleFiles as [$fname, $size, $color, $bg, $badge, $blabel]): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:8px">
              <div style="width:36px;height:36px;border-radius:8px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">
                <?= strpos($fname, '.pdf') !== false ? '📄' : '🖼️' ?>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($fname) ?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?= $size ?></div>
              </div>
              <span class="badge <?= $badge ?>"><?= $blabel ?></span>
              <button class="btn btn-outline btn-sm"
                      onclick="showToast('info','Tải xuống','Đang tải file...')">
                <i class="fas fa-download"></i>
              </button>
            </div>
          <?php endforeach; ?>

          <!-- Upload zone -->
          <?php if (in_array($currentUser['role'], ['admin', 'dept_head'])): ?>
            <div style="border:2px dashed var(--border);border-radius:var(--radius-lg);padding:28px;text-align:center;cursor:pointer;margin-top:12px;transition:all .2s"
                 onclick="showToast('info','Upload','Chọn file để tải lên.')">
              <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--primary);margin-bottom:8px;display:block"></i>
              <div style="font-size:14px;font-weight:700;margin-bottom:4px">Kéo thả hoặc nhấn để tải lên</div>
              <div style="font-size:12px;color:var(--text-muted)">PDF, Word, JPG, PNG · Tối đa 10MB</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- end left column -->

  <!-- Right sidebar -->
  <div>
    <!-- Completion -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><div class="card-title">Tiến độ hồ sơ</div></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <svg width="72" height="72" viewBox="0 0 72 72" style="flex-shrink:0">
            <circle cx="36" cy="36" r="30" fill="none" stroke="var(--border)" stroke-width="7"/>
            <circle cx="36" cy="36" r="30" fill="none"
                    stroke="<?= $dotColor ?>" stroke-width="7"
                    stroke-linecap="round"
                    stroke-dasharray="188"
                    stroke-dashoffset="<?= 188 - round(188 * $compl / 100) ?>"
                    style="transform:rotate(-90deg);transform-origin:50% 50%"/>
            <text x="36" y="42" text-anchor="middle" font-size="14" font-weight="800"
                  fill="<?= $dotColor ?>" font-family="Be Vietnam Pro"><?= $compl ?>%</text>
          </svg>
          <div>
            <div style="font-size:22px;font-weight:800"><?= $compl ?>%</div>
            <div style="font-size:12px;color:var(--text-muted)">Hồ sơ hoàn thành</div>
            <span class="badge <?= $badgeCls ?>" style="margin-top:6px"><?= $badgeLabel ?></span>
          </div>
        </div>

        <!-- Checklist -->
        <?php
        $checks = [
          ['Thông tin cá nhân', !empty($teacher['personal']['full_name'])],
          ['Số CCCD',           !empty($teacher['personal']['cccd'])],
          ['Thông tin gia đình',!empty($teacher['family']['marital_status'])],
          ['Bằng cấp',          !empty($teacher['education']['degree'])],
          ['Chứng chỉ bồi dưỡng', !empty($teacher['certificates'])],
          ['Thành tích',        !empty($teacher['achievements'])],
        ];
        foreach ($checks as [$label, $done]): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:12.5px">
            <div style="width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0;background:<?= $done ? 'var(--success-light)' : 'var(--warning-light)' ?>;color:<?= $done ? 'var(--success)' : 'var(--warning)' ?>">
              <i class="fas fa-<?= $done ? 'check' : 'clock' ?>"></i>
            </div>
            <span style="<?= $done ? '' : 'color:var(--warning)' ?>"><?= $label ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Quick info -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><div class="card-title">Thông tin nhanh</div></div>
      <div class="card-body">
        <?php foreach ([
          ['fa-school',      'Tổ chuyên môn', $deptName],
          ['fa-book',        'Môn dạy',        $teacher['work']['subject'] ?? '—'],
          ['fa-star',        'Hạng GV',        $teacher['work']['rank'] ?? '—'],
          ['fa-calendar',    'Vào ngành',       $teacher['work']['join_date'] ? date('Y', strtotime($teacher['work']['join_date'])) : '—'],
          ['fa-birthday-cake','Tuổi',           ($teacher['personal']['dob'] ? (date('Y') - (int)date('Y', strtotime($teacher['personal']['dob']))) . ' tuổi' : '—')],
        ] as [$icon, $lbl, $val]): ?>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;padding:6px 0">
            <i class="fas <?= $icon ?>" style="width:16px;color:var(--primary)"></i>
            <span style="color:var(--text-muted);flex:1"><?= $lbl ?></span>
            <strong><?= e($val) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Actions -->
    <div class="card">
      <div class="card-header"><div class="card-title">Thao tác</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <?php if (in_array($currentUser['role'], ['admin', 'dept_head'])): ?>
          <button class="btn btn-primary" style="justify-content:flex-start"
                  onclick="showToast('success','Đã lưu','Hồ sơ được lưu thành công.')">
            <i class="fas fa-save"></i> Lưu thay đổi
          </button>
          <button class="btn btn-outline" style="justify-content:flex-start"
                  onclick="showToast('info','In hồ sơ','Đang chuẩn bị...')">
            <i class="fas fa-print"></i> In hồ sơ
          </button>
          <button class="btn btn-outline" style="justify-content:flex-start"
                  onclick="showToast('info','Xuất PDF','Đang tạo file PDF...')">
            <i class="fas fa-file-pdf" style="color:#ef4444"></i> Xuất PDF
          </button>
          <button class="btn btn-outline" style="justify-content:flex-start;color:var(--danger)"
                  onclick="showToast('warning','Nhắc nhở','Đã gửi thông báo yêu cầu cập nhật.')">
            <i class="fas fa-paper-plane"></i> Gửi yêu cầu cập nhật
          </button>
        <?php endif; ?>
        <a href="/teachers.php" class="btn btn-ghost" style="justify-content:flex-start">
          <i class="fas fa-arrow-left"></i> Quay lại danh sách
        </a>
      </div>
    </div>
  </div>

</div>

<?php
$inlineScript = <<<JS
function switchTab(id, btn) {
    // Ẩn tất cả tab panels
    ['tab-personal','tab-work','tab-edu','tab-cert','tab-achieve','tab-files']
        .forEach(t => {
            const el = document.getElementById(t);
            if (el) el.style.display = 'none';
        });

    // Deactivate tất cả tab buttons
    document.querySelectorAll('#tabBar .tab-btn')
            .forEach(b => b.classList.remove('active'));

    // Hiện tab được chọn
    const panel = document.getElementById(id);
    if (panel) panel.style.display = 'block';
    btn.classList.add('active');
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
