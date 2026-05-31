<?php
/**
 * my-profile.php — GV tự cập nhật hồ sơ cá nhân
 * 4 tabs: Cá nhân & Gia đình | Công tác | Đào tạo & Chứng chỉ | Thành tích & Minh chứng
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);
$departments      = $db->read('departments');

// Lấy hồ sơ GV
$teacher = null;
if (!empty($currentUser['teacher_id'])) {
    $teacher = $db->find('teachers', $currentUser['teacher_id']);
}
if (!$teacher) {
    $teacher = $db->find('teachers', 'tchr_001') ?? [];
}

// Tên tổ
$deptName = '';
foreach ($departments as $d) {
    if ($d['id'] === ($teacher['work']['dept_id'] ?? '')) { $deptName = $d['name']; break; }
}

// ---- Xử lý POST ----
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');

    // Tab 1: Cá nhân & Gia đình
    if ($action === 'save_personal_family') {
        $errors = validate($_POST, [
            'phone' => 'required|regex:/^0[0-9]{9}$/',
        ]);
        if (empty($errors)) {
            // Lưu phần cá nhân
            $db->update('teachers', $teacher['id'], [
                'personal' => array_merge($teacher['personal'] ?? [], [
                    'full_name' => input('full_name') ?: ($teacher['personal']['full_name'] ?? ''),
                    'dob'       => input('dob')       ?: null,
                    'gender'    => input('gender')    ?: 'Nam',
                    'ethnicity' => input('ethnicity') ?: 'Kinh',
                    'religion'  => input('religion')  ?: 'Không',
                    'cccd'      => input('cccd')      ?: null,
                    'cccd_date' => input('cccd_date') ?: null,
                    'hometown'  => input('hometown')  ?: null,
                    'address'   => input('address')   ?: null,
                    'phone'     => input('phone'),
                    'email'     => input('personal_email') ?: ($teacher['personal']['email'] ?? ''),
                ]),
            ]);

            // Lưu phần gia đình
            $children = [];
            foreach ($_POST['child_name'] ?? [] as $i => $n) {
                if (!empty(trim($n))) {
                    $children[] = [
                        'name'   => trim($n),
                        'year'   => (int)($_POST['child_year'][$i]   ?? 0),
                        'gender' => $_POST['child_gender'][$i] ?? 'Nam',
                    ];
                }
            }
            $db->update('teachers', $teacher['id'], [
                'family' => [
                    'marital_status'    => input('marital_status') ?: 'Chưa cập nhật',
                    'spouse_name'       => input('spouse_name')    ?: null,
                    'spouse_job'        => input('spouse_job')     ?: null,
                    'spouse_phone'      => input('spouse_phone')   ?: null,
                    'children'          => $children,
                    'emergency_contact' => input('emergency_contact') ?: null,
                ],
            ]);

            $teacher = $db->find('teachers', $teacher['id']);
            $db->update('teachers', $teacher['id'], ['completion_pct' => calcCompletion($teacher)]);
            flash('success', 'Đã lưu thông tin cá nhân và gia đình.');
            redirect('/my-profile.php?tab=personal');
        }
    }

    // Tab 3: Đào tạo & Chứng chỉ
    if ($action === 'save_edu_cert') {
        $db->update('teachers', $teacher['id'], [
            'education' => [
                'degree'       => input('degree')       ?: 'Đại học',
                'major'        => input('major')        ?: null,
                'institution'  => input('institution')  ?: null,
                'grad_year'    => (int)input('grad_year') ?: null,
                'foreign_lang' => input('foreign_lang') ?: 'Chưa có',
                'it_level'     => input('it_level')     ?: 'Chưa có',
            ],
        ]);
        $teacher = $db->find('teachers', $teacher['id']);
        $db->update('teachers', $teacher['id'], ['completion_pct' => calcCompletion($teacher)]);
        flash('success', 'Đã lưu trình độ đào tạo.');
        redirect('/my-profile.php?tab=edu');
    }
}

// Active tab (4 tabs)
$activeTab = input('tab', 'personal');
$tabs = [
    ['personal', 'fa-user-circle',    'Cá nhân & Gia đình'],
    ['work',     'fa-briefcase',      'Công tác'],
    ['edu',      'fa-graduation-cap', 'Đào tạo & Chứng chỉ'],
    ['achieve',  'fa-trophy',         'Thành tích & Minh chứng'],
];

$name      = $teacher['personal']['full_name'] ?? $currentUser['name'];
$avatar    = $teacher['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=200';
$compl     = $teacher['completion_pct'] ?? 0;
$status    = $teacher['status'] ?? 'pending';
$complColor= $compl >= 80 ? '#22c55e' : ($compl >= 50 ? '#f59e0b' : '#ef4444');
$yearsService = yearsOfService($teacher['work']['join_date'] ?? null);

$pageTitle    = 'Hồ sơ của tôi';
$pageSubtitle = 'Cập nhật thông tin cá nhân';
$activePage   = 'my-profile.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Hero banner -->
<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0a3a8a 100%);
            border-radius:var(--radius-xl);padding:24px 28px;
            display:flex;align-items:center;gap:20px;margin-bottom:22px;
            position:relative;overflow:hidden;flex-wrap:wrap">
  <div style="position:absolute;right:-40px;top:-40px;width:200px;height:200px;
              background:rgba(255,255,255,0.04);border-radius:50%;pointer-events:none"></div>

  <!-- Avatar -->
  <div style="position:relative;flex-shrink:0">
    <img src="<?= e($avatar) ?>"
         style="width:84px;height:84px;border-radius:50%;border:4px solid rgba(255,255,255,.25);object-fit:cover"
         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=200'">
    <label style="position:absolute;bottom:0;right:0;width:26px;height:26px;border-radius:50%;
                  background:var(--primary);border:2px solid white;
                  display:flex;align-items:center;justify-content:center;
                  cursor:pointer;font-size:10px;color:white" title="Đổi ảnh">
      <input type="file" accept="image/*" style="display:none"
             onchange="showToast('info','Đổi ảnh','Tính năng sẽ có sớm.')">
      <i class="fas fa-camera"></i>
    </label>
  </div>

  <!-- Info -->
  <div style="flex:1;min-width:160px">
    <h1 style="color:white;font-size:19px;font-weight:900;margin-bottom:4px"><?= e($name) ?></h1>
    <p style="color:rgba(255,255,255,.7);font-size:13px;margin-bottom:10px">
      <?= e($teacher['work']['subject'] ?? '—') ?> · <?= e($deptName) ?>
    </p>
    <div style="display:flex;gap:7px;flex-wrap:wrap">
      <?php foreach ([
        $teacher['education']['degree'] ?? 'Đại học',
        $teacher['work']['rank']        ?? 'GV hạng III',
        ($teacher['work']['party_member'] ?? false) ? '🏅 Đảng viên' : 'Quần chúng',
      ] as $tag): ?>
        <span style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
                     border-radius:99px;padding:3px 11px;color:white;font-size:11.5px">
          <?= e($tag) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Completion -->
  <div style="display:flex;align-items:center;gap:12px;
              background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
              border-radius:var(--radius-lg);padding:12px 16px;z-index:1">
    <svg width="56" height="56" viewBox="0 0 56 56">
      <circle cx="28" cy="28" r="23" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="5"/>
      <circle cx="28" cy="28" r="23" fill="none"
              stroke="<?= $complColor ?>" stroke-width="5" stroke-linecap="round"
              stroke-dasharray="144"
              stroke-dashoffset="<?= 144 - round(144 * $compl / 100) ?>"
              style="transform:rotate(-90deg);transform-origin:50% 50%"/>
      <text x="28" y="33" text-anchor="middle" font-size="12" font-weight="900"
            fill="white" font-family="Be Vietnam Pro"><?= $compl ?>%</text>
    </svg>
    <div style="color:white">
      <div style="font-size:18px;font-weight:900"><?= $compl ?>%</div>
      <div style="font-size:11px;opacity:.6">Hồ sơ HT</div>
      <div style="margin-top:5px;background:<?= $compl>=80?'rgba(34,197,94,.25)':'rgba(245,158,11,.25)' ?>;
                  border:1px solid <?= $compl>=80?'rgba(34,197,94,.4)':'rgba(245,158,11,.4)' ?>;
                  border-radius:99px;padding:2px 9px;font-size:10.5px;font-weight:700;
                  color:<?= $compl>=80?'#4ade80':'#fbbf24' ?>">
        <?= $compl>=100?'Hoàn thành':($compl>=80?'Gần xong':'Cần bổ sung') ?>
      </div>
    </div>
  </div>
</div>

<!-- Layout -->
<div style="display:grid;grid-template-columns:1fr 250px;gap:20px;align-items:start" class="detail-layout">
<div>

  <!-- 4 Tab bar -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4px;
              background:var(--bg-card);border:1px solid var(--border);
              border-radius:var(--radius-sm);padding:4px;margin-bottom:20px">
    <?php foreach ($tabs as [$id, $icon, $label]): ?>
      <a href="/my-profile.php?tab=<?= $id ?>"
         style="padding:9px 8px;border-radius:6px;font-size:12.5px;
                font-weight:<?= $activeTab===$id?'700':'500' ?>;
                color:<?= $activeTab===$id?'var(--primary)':'var(--text-secondary)' ?>;
                background:<?= $activeTab===$id?'var(--bg-card)':'transparent' ?>;
                box-shadow:<?= $activeTab===$id?'var(--shadow-sm)':'none' ?>;
                text-decoration:none;display:flex;align-items:center;
                justify-content:center;gap:6px;transition:all .2s;text-align:center">
        <i class="fas <?= $icon ?>"></i>
        <span style="display:none;display:inline"><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ==============================
       TAB 1: CÁ NHÂN & GIA ĐÌNH
       ============================== -->
  <?php if ($activeTab === 'personal'): ?>
  <form method="POST" action="/my-profile.php">
    <input type="hidden" name="action" value="save_personal_family">

    <!-- Thông tin cá nhân -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:9px;background:var(--primary-light);
                      color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:13px">
            <i class="fas fa-user"></i>
          </div>
          <div><div class="card-title">Thông tin cá nhân</div><div class="card-subtitle">Họ tên, CCCD, địa chỉ</div></div>
        </div>
      </div>
      <div class="card-body">
        <?php $p = $teacher['personal'] ?? []; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

          <div class="form-group" style="margin:0;grid-column:1/-1">
            <label class="form-label">Họ và tên <span style="color:var(--danger)">*</span></label>
            <input type="text" name="full_name" class="form-control"
                   value="<?= e($p['full_name'] ?? '') ?>" placeholder="Nguyễn Văn A">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Ngày sinh</label>
            <input type="date" name="dob" class="form-control" value="<?= e($p['dob'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Giới tính</label>
            <select name="gender" class="form-control">
              <option value="Nam" <?= ($p['gender']??'')==='Nam'?'selected':'' ?>>Nam</option>
              <option value="Nữ"  <?= ($p['gender']??'')==='Nữ' ?'selected':'' ?>>Nữ</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Dân tộc</label>
            <select name="ethnicity" class="form-control">
              <?php foreach (['Kinh','Khmer','Hoa','Khác'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($p['ethnicity']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Tôn giáo</label>
            <select name="religion" class="form-control">
              <?php foreach (['Không','Phật giáo','Thiên Chúa giáo','Khác'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($p['religion']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Số CCCD</label>
            <input type="text" name="cccd" class="form-control"
                   value="<?= e($p['cccd'] ?? '') ?>" placeholder="12 chữ số" maxlength="12">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Ngày cấp CCCD</label>
            <input type="date" name="cccd_date" class="form-control" value="<?= e($p['cccd_date'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Điện thoại <span style="color:var(--danger)">*</span></label>
            <input type="tel" name="phone" class="form-control"
                   value="<?= e($p['phone'] ?? '') ?>" placeholder="09xxxxxxxx">
            <?php if (isset($errors['phone'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['phone']) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Email cá nhân</label>
            <input type="email" name="personal_email" class="form-control"
                   value="<?= e($p['email'] ?? '') ?>" placeholder="email@gmail.com">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Quê quán</label>
            <input type="text" name="hometown" class="form-control"
                   value="<?= e($p['hometown'] ?? '') ?>" placeholder="Tỉnh/TP">
          </div>
          <div class="form-group" style="margin:0;grid-column:1/-1">
            <label class="form-label">Địa chỉ thường trú</label>
            <input type="text" name="address" class="form-control"
                   value="<?= e($p['address'] ?? '') ?>"
                   placeholder="Số nhà, đường, phường/xã, TP">
          </div>
        </div>
      </div>
    </div>

    <!-- Thông tin gia đình (gộp vào cùng form) -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:9px;background:#f3e8ff;
                      color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:13px">
            <i class="fas fa-heart"></i>
          </div>
          <div><div class="card-title">Thông tin gia đình</div><div class="card-subtitle">Hôn nhân, vợ/chồng, con cái</div></div>
        </div>
      </div>
      <div class="card-body">
        <?php $fam = $teacher['family'] ?? []; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Tình trạng hôn nhân</label>
            <select name="marital_status" class="form-control">
              <?php foreach (['Đã kết hôn','Độc thân','Ly hôn','Góa'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($fam['marital_status']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Họ tên vợ/chồng</label>
            <input type="text" name="spouse_name" class="form-control" value="<?= e($fam['spouse_name'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Nghề nghiệp vợ/chồng</label>
            <input type="text" name="spouse_job" class="form-control" value="<?= e($fam['spouse_job'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">SĐT vợ/chồng</label>
            <input type="tel" name="spouse_phone" class="form-control" value="<?= e($fam['spouse_phone'] ?? '') ?>">
          </div>
          <div class="form-group" style="margin:0;grid-column:1/-1">
            <label class="form-label">Người liên hệ khẩn cấp</label>
            <input type="text" name="emergency_contact" class="form-control"
                   value="<?= e($fam['emergency_contact'] ?? '') ?>" placeholder="Tên - SĐT">
          </div>
        </div>

        <!-- Con cái -->
        <div style="margin-top:16px">
          <div style="font-size:11px;font-weight:800;text-transform:uppercase;
                      letter-spacing:.07em;color:var(--text-muted);margin-bottom:8px">
            Con cái
          </div>
          <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:6px;
                      margin-bottom:6px;padding:0 2px">
            <?php foreach (['Họ tên','Năm sinh','Giới tính'] as $h): ?>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;
                          letter-spacing:.06em;color:var(--text-muted)"><?= $h ?></div>
            <?php endforeach; ?>
          </div>
          <div id="childrenList">
            <?php $children = $fam['children'] ?? [[]]; if (empty($children)) $children = [[]]; ?>
            <?php foreach ($children as $child): ?>
              <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:6px;margin-bottom:7px">
                <input type="text" name="child_name[]" class="form-control"
                       value="<?= e($child['name'] ?? '') ?>" placeholder="Họ và tên">
                <input type="number" name="child_year[]" class="form-control"
                       value="<?= e($child['year'] ?? '') ?>" placeholder="2020" min="1990" max="2025">
                <select name="child_gender[]" class="form-control">
                  <option value="Nam" <?= ($child['gender']??'Nam')==='Nam'?'selected':'' ?>>Nam</option>
                  <option value="Nữ"  <?= ($child['gender']??'')==='Nữ' ?'selected':'' ?>>Nữ</option>
                </select>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addChild()">
            <i class="fas fa-plus"></i> Thêm con
          </button>
        </div>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="reset" class="btn btn-outline">Hủy</button>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Lưu Cá nhân &amp; Gia đình
      </button>
    </div>
  </form>

  <!-- ==============================
       TAB 2: CÔNG TÁC (chỉ xem)
       ============================== -->
  <?php elseif ($activeTab === 'work'): ?>
  <div class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:9px;background:var(--accent-light);
                    color:#10b981;display:flex;align-items:center;justify-content:center;font-size:13px">
          <i class="fas fa-briefcase"></i>
        </div>
        <div>
          <div class="card-title">Thông tin công tác</div>
          <div class="card-subtitle">Do BGH quản lý — chỉ xem</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:5px;background:var(--warning-light);
                  border:1px solid rgba(245,158,11,.3);border-radius:99px;
                  padding:4px 12px;font-size:12px;font-weight:700;color:var(--warning)">
        <i class="fas fa-lock"></i> Khóa bởi BGH
      </div>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <?php foreach ([
          ['Tổ chuyên môn',  $deptName],
          ['Môn dạy chính',  $teacher['work']['subject']       ?? '—'],
          ['Chức vụ',        $teacher['work']['role']           ?? '—'],
          ['Hạng GV',        $teacher['work']['rank']           ?? '—'],
          ['Ngày vào ngành', formatDate($teacher['work']['join_date'] ?? null)],
          ['Số năm CT',      $yearsService . ' năm'],
          ['Số tiết/tuần',   ($teacher['work']['weekly_lessons'] ?? '—') . ' tiết'],
          ['Hệ số lương',    $teacher['work']['salary_coeff']   ?? '—'],
          ['Đảng viên',      ($teacher['work']['party_member'] ?? false)
            ? '✅ Đảng viên (' . ($teacher['work']['party_year'] ?? '') . ')'
            : '❌ Quần chúng'],
        ] as [$k, $v]): ?>
          <div>
            <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;
                        letter-spacing:.07em;color:var(--text-muted);margin-bottom:4px"><?= $k ?></div>
            <div style="font-size:13.5px;font-weight:600;padding:10px 14px;
                        background:var(--bg);border-radius:var(--radius-sm);
                        border:1px solid var(--border);color:var(--text-secondary)">
              <?= e($v) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:16px;padding:12px 14px;background:var(--warning-light);
                  border:1px solid rgba(245,158,11,.25);border-radius:var(--radius);
                  display:flex;gap:10px">
        <i class="fas fa-lock" style="color:var(--warning);margin-top:2px;flex-shrink:0"></i>
        <p style="font-size:12.5px;color:#92400e;margin:0;line-height:1.5">
          Thông tin công tác được Ban Giám hiệu quản lý và khóa chỉnh sửa.
          Nếu có sai sót, vui lòng liên hệ văn phòng nhà trường.
        </p>
      </div>
    </div>
  </div>

  <!-- ==============================
       TAB 3: ĐÀO TẠO & CHỨNG CHỈ
       ============================== -->
  <?php elseif ($activeTab === 'edu'): ?>
  <form method="POST" action="/my-profile.php">
    <input type="hidden" name="action" value="save_edu_cert">
    <?php $edu = $teacher['education'] ?? []; ?>

    <!-- Trình độ đào tạo -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:9px;background:#f3e8ff;
                      color:#8b5cf6;display:flex;align-items:center;justify-content:center;font-size:13px">
            <i class="fas fa-graduation-cap"></i>
          </div>
          <div><div class="card-title">Trình độ đào tạo</div><div class="card-subtitle">Bằng cấp, chuyên ngành, ngoại ngữ</div></div>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin:0">
            <label class="form-label">Trình độ cao nhất</label>
            <select name="degree" class="form-control">
              <?php foreach (['Cao đẳng','Đại học','Thạc sĩ','Tiến sĩ'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($edu['degree']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Chuyên ngành</label>
            <input type="text" name="major" class="form-control" value="<?= e($edu['major']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Cơ sở đào tạo</label>
            <input type="text" name="institution" class="form-control" value="<?= e($edu['institution']??'') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Năm tốt nghiệp</label>
            <input type="number" name="grad_year" class="form-control"
                   value="<?= e($edu['grad_year']??'') ?>" min="1990" max="<?= date('Y') ?>">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Trình độ Ngoại ngữ</label>
            <select name="foreign_lang" class="form-control">
              <?php foreach (['Chưa có','A1','A2','B1','B2','C1','IELTS 5.0+','IELTS 6.0+','IELTS 7.0+'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($edu['foreign_lang']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Trình độ Tin học</label>
            <select name="it_level" class="form-control">
              <?php foreach (['Chưa có','Đạt chuẩn kỹ năng số','IC3','MOS'] as $opt): ?>
                <option value="<?= $opt ?>" <?= ($edu['it_level']??'')===$opt?'selected':'' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Chứng chỉ bồi dưỡng (gộp vào cùng tab) -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:9px;background:var(--warning-light);
                      color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:13px">
            <i class="fas fa-certificate"></i>
          </div>
          <div><div class="card-title">Chứng chỉ bồi dưỡng</div></div>
        </div>
      </div>
      <div class="card-body">
        <?php if (!empty($teacher['certificates'])): ?>
          <?php foreach ($teacher['certificates'] as $cert): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:11px 14px;
                        border:1px solid var(--border);border-radius:var(--radius);margin-bottom:9px">
              <div style="width:38px;height:38px;border-radius:9px;background:var(--warning-light);
                          color:var(--warning);display:flex;align-items:center;justify-content:center">
                <i class="fas fa-certificate"></i>
              </div>
              <div style="flex:1">
                <div style="font-weight:700;font-size:13.5px"><?= e($cert['name']??'—') ?></div>
                <div style="font-size:12px;color:var(--text-muted)"><?= e($cert['issued_by']??'—') ?></div>
              </div>
              <span class="badge badge-primary"><?= e($cert['year']??'—') ?></span>
              <span class="badge badge-success">Xác nhận</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state" style="padding:24px">
            <div class="empty-icon">📜</div>
            <div class="empty-title">Chưa có chứng chỉ</div>
          </div>
        <?php endif; ?>
        <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px"
                onclick="showToast('info','Thêm chứng chỉ','Tính năng sẽ có sớm.')">
          <i class="fas fa-plus"></i> Thêm chứng chỉ
        </button>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Lưu Đào tạo
      </button>
    </div>
  </form>

  <!-- ==============================
       TAB 4: THÀNH TÍCH & MINH CHỨNG
       ============================== -->
  <?php else: ?>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:9px;background:var(--warning-light);
                    color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:13px">
          <i class="fas fa-trophy"></i>
        </div>
        <div><div class="card-title">Danh hiệu &amp; Khen thưởng</div></div>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($teacher['achievements'])): ?>
        <?php foreach ($teacher['achievements'] as $ach): ?>
          <div style="display:flex;gap:10px;padding:12px;border:1px solid var(--border);
                      border-radius:var(--radius);margin-bottom:9px">
            <div style="font-size:26px">🏆</div>
            <div style="flex:1">
              <div style="font-weight:700;font-size:13.5px"><?= e($ach['title']??'—') ?></div>
              <div style="font-size:12px;color:var(--text-muted)"><?= e($ach['awarded_by']??'—') ?></div>
            </div>
            <span class="badge badge-primary"><?= e($ach['year']??'—') ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="padding:24px">
          <div class="empty-icon">🏆</div>
          <div class="empty-title">Chưa có thành tích</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- File minh chứng -->
  <div class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:9px;background:var(--primary-light);
                    color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:13px">
          <i class="fas fa-paperclip"></i>
        </div>
        <div><div class="card-title">File minh chứng</div><div class="card-subtitle">Bằng cấp, chứng chỉ, CCCD...</div></div>
      </div>
    </div>
    <div class="card-body">
      <!-- Files mẫu -->
      <?php foreach ([
        ['Bằng tốt nghiệp.pdf', '1.2 MB', '#ef4444','var(--danger-light)', 'badge-success'],
        ['CCCD mặt trước.jpg',  '234 KB', '#3b82f6','var(--info-light)',   'badge-success'],
      ] as [$fname, $size, $color, $bg, $badge]): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                    border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:8px">
          <div style="width:36px;height:36px;border-radius:8px;background:<?= $bg ?>;
                      color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <?= strpos($fname,'.pdf')!==false ? '📄' : '🖼️' ?>
          </div>
          <div style="flex:1"><div style="font-size:13px;font-weight:600"><?= e($fname) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $size ?></div>
          </div>
          <span class="badge <?= $badge ?>">Đã duyệt</span>
        </div>
      <?php endforeach; ?>

      <!-- Upload zone -->
      <div style="border:2px dashed var(--border);border-radius:var(--radius-lg);
                  padding:28px;text-align:center;cursor:pointer;margin-top:12px;
                  transition:all .2s"
           onclick="showToast('info','Upload','Tính năng upload sẽ có sớm.')"
           onmouseenter="this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)'"
           onmouseleave="this.style.borderColor='var(--border)';this.style.background=''">
        <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--primary);margin-bottom:8px;display:block"></i>
        <div style="font-size:14px;font-weight:700;margin-bottom:4px">Kéo thả hoặc nhấn để tải lên</div>
        <div style="font-size:12px;color:var(--text-muted)">PDF, Word, JPG, PNG · Tối đa 10MB</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- end left -->

<!-- RIGHT SIDEBAR -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- Checklist -->
  <div class="card">
    <div class="card-header"><div class="card-title">📋 Tiến độ hồ sơ</div></div>
    <div class="card-body">
      <?php
      $checks = [
        ['Cá nhân (CCCD, SĐT)', !empty($teacher['personal']['cccd']) && !empty($teacher['personal']['phone']), 'personal'],
        ['Thông tin gia đình',   !empty($teacher['family']['marital_status']) && ($teacher['family']['marital_status'] ?? '') !== 'Chưa cập nhật', 'personal'],
        ['Trình độ đào tạo',     !empty($teacher['education']['degree']),   'edu'],
        ['Chứng chỉ bồi dưỡng', !empty($teacher['certificates']),           'edu'],
        ['Thành tích',           !empty($teacher['achievements']),           'achieve'],
      ];
      foreach ($checks as [$label, $done, $tab]):
      ?>
        <a href="/my-profile.php?tab=<?= $tab ?>"
           style="display:flex;align-items:center;gap:8px;padding:8px 0;
                  border-bottom:1px solid var(--border-light);font-size:12.5px;
                  text-decoration:none;color:var(--text-primary)">
          <div style="width:18px;height:18px;border-radius:50%;
                      display:flex;align-items:center;justify-content:center;
                      font-size:9px;flex-shrink:0;
                      background:<?= $done?'var(--success-light)':'var(--warning-light)' ?>;
                      color:<?= $done?'var(--success)':'var(--warning)' ?>">
            <i class="fas fa-<?= $done?'check':'clock' ?>"></i>
          </div>
          <span style="<?= $done?'':'color:var(--warning)' ?>;flex:1"><?= $label ?></span>
          <i class="fas fa-chevron-right" style="font-size:10px;color:var(--text-muted)"></i>
        </a>
      <?php endforeach; ?>
      <div style="margin-top:12px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
          <span style="color:var(--text-muted)">Tổng tiến độ</span>
          <strong style="color:<?= $complColor ?>"><?= $compl ?>%</strong>
        </div>
        <div class="progress-bar" style="height:8px">
          <div class="progress-fill" style="width:<?= $compl ?>%;background:<?= $complColor ?>"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick info -->
  <div class="card">
    <div class="card-header"><div class="card-title">Thông tin nhanh</div></div>
    <div class="card-body">
      <?php foreach ([
        ['fa-school',       'Tổ',         $deptName],
        ['fa-book',         'Môn dạy',    $teacher['work']['subject'] ?? '—'],
        ['fa-calendar',     'Vào ngành',  $teacher['work']['join_date'] ? date('Y', strtotime($teacher['work']['join_date'])) : '—'],
        ['fa-clock',        'Công tác',   $yearsService . ' năm'],
        ['fa-envelope',     'Email',      $currentUser['email']],
      ] as [$icon, $lbl, $val]): ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:12.5px;padding:6px 0">
          <i class="fas <?= $icon ?>" style="width:15px;color:var(--primary);flex-shrink:0"></i>
          <span style="color:var(--text-muted);flex:1"><?= $lbl ?></span>
          <strong style="text-align:right;max-width:130px;white-space:nowrap;
                          overflow:hidden;text-overflow:ellipsis" title="<?= e($val) ?>">
            <?= e($val) ?>
          </strong>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div><!-- end right -->
</div><!-- end grid -->

<?php
$inlineScript = <<<JS
function addChild() {
    const list = document.getElementById('childrenList');
    if (!list) return;
    const cnt = list.querySelectorAll('div[style*="grid-template-columns"]').length + 1;
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr;gap:6px;margin-bottom:7px';
    row.innerHTML =
        '<input type="text" name="child_name[]" class="form-control" placeholder="Họ và tên con ' + cnt + '">' +
        '<input type="number" name="child_year[]" class="form-control" placeholder="Năm sinh" min="1990" max="2025">' +
        '<select name="child_gender[]" class="form-control"><option value="Nam">Nam</option><option value="Nữ">Nữ</option></select>';
    list.appendChild(row);
    row.querySelector('input').focus();
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>