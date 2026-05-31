<?php
/**
 * add-teacher.php — Thêm giáo viên mới (BGH dùng)
 * Chỉ nhập thông tin cơ bản + tạo tài khoản
 * GV sẽ tự nhập chi tiết qua my-profile.php
 */
require_once __DIR__ . '/_path.php';

$currentUser = $auth->requireRole(['admin']);

$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate
    $errors = validate($_POST, [
        'full_name' => 'required|min:2|max:100',
        'email'     => 'required|email',
        'phone'     => 'required|regex:/^0[0-9]{9}$/',
        'dept_id'   => 'required',
        'subject'   => 'required|min:2',
        'password'  => 'required|min:6',
    ]);

    // Kiểm tra email trùng
    if (empty($errors['email'])) {
        $exist = $db->where('users', 'email', strtolower(input('email')));
        if (!empty($exist)) {
            $errors['email'] = 'Email này đã có tài khoản trong hệ thống.';
        }
    }

    if (empty($errors)) {
        // 1. Tạo hồ sơ giáo viên (chỉ thông tin cơ bản)
        $teacher = $db->insert('teachers', [
            'personal' => [
                'full_name' => input('full_name'),
                'phone'     => input('phone'),
                'email'     => input('email'),
                'gender'    => input('gender') ?: 'Nam',
                'dob'       => input('dob') ?: null,
            ],
            'work' => [
                'dept_id'        => input('dept_id'),
                'subject'        => input('subject'),
                'role'           => input('role') ?: 'Giáo viên',
                'rank'           => input('rank') ?: 'GV hạng III',
                'join_date'      => input('join_date') ?: null,
                'weekly_lessons' => 18,
                'salary_coeff'   => 2.34,
                'party_member'   => false,
                // Khóa thông tin công tác — chỉ BGH mới sửa được
                'locked'         => true,
            ],
            'education'    => [],
            'family'       => [],
            'certificates' => [],
            'achievements' => [],
            'avatar_url'   => null,
            'status'       => 'pending',
            'completion_pct' => 10,
        ]);

        // 2. Tạo tài khoản đăng nhập
        $auth->createUser([
            'email'      => strtolower(input('email')),
            'password'   => input('password'),
            'role'       => input('user_role') ?: 'teacher',
            'teacher_id' => $teacher['id'],
            'dept_id'    => input('dept_id'),
        ]);

        // 3. Gửi thông báo cho GV (lưu vào notifications)
        $db->insert('notifications', [
            'user_id'  => $currentUser['id'],
            'title'    => 'Thêm GV thành công',
            'body'     => 'Đã tạo hồ sơ cho GV <strong>' . e(input('full_name')) . '</strong>. GV cần tự hoàn thiện hồ sơ.',
            'type'     => 'profile',
            'link'     => '/teacher-detail.php?id=' . $teacher['id'],
            'is_read'  => false,
        ]);

        flash('success', 'Đã tạo tài khoản cho ' . input('full_name') . '. Email đăng nhập: ' . input('email'));
        redirect('/teachers.php');
    }
}

$pageTitle    = 'Thêm giáo viên mới';
$pageSubtitle = 'Tạo tài khoản — GV tự hoàn thiện hồ sơ';
$activePage   = 'teachers.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Breadcrumb -->
<div style="margin-bottom:16px">
  <?= breadcrumb(['Hồ sơ giáo viên' => '/teachers.php', 'Thêm mới' => null]) ?>
</div>

<!-- Info banner -->
<div style="background:var(--primary-light);border:1px solid rgba(26,110,245,.2);border-radius:var(--radius-lg);padding:14px 18px;display:flex;gap:12px;margin-bottom:20px">
  <i class="fas fa-info-circle" style="color:var(--primary);font-size:16px;margin-top:2px;flex-shrink:0"></i>
  <div>
    <div style="font-size:13.5px;font-weight:700;color:var(--primary);margin-bottom:3px">Quy trình 2 bước</div>
    <div style="font-size:13px;color:var(--primary);line-height:1.6;opacity:.85">
      BGH chỉ nhập <strong>thông tin cơ bản + tạo tài khoản</strong>. Sau đó hệ thống sẽ gửi email cho giáo viên tự hoàn thiện hồ sơ cá nhân (gia đình, bằng cấp, chứng chỉ...). 
      Thông tin công tác được <strong>khóa</strong> — chỉ BGH mới chỉnh sửa được.
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,.25);border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;display:flex;gap:10px">
    <i class="fas fa-exclamation-circle" style="color:var(--danger);margin-top:2px"></i>
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--danger);margin-bottom:5px">Vui lòng kiểm tra lại:</div>
      <?php foreach ($errors as $msg): ?>
        <div style="font-size:13px;color:var(--danger)">• <?= e($msg) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<form method="POST" action="/add-teacher.php">
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

  <!-- LEFT: Form -->
  <div>

    <!-- BƯỚC 1: Thông tin cơ bản -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:99px;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">1</div>
          <div>
            <div class="card-title">Thông tin cơ bản</div>
            <div class="card-subtitle">BGH nhập — bắt buộc</div>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label class="form-label">Họ và tên đầy đủ <span style="color:var(--danger)">*</span></label>
            <input type="text" name="full_name" class="form-control"
                   value="<?= e(input('full_name')) ?>"
                   placeholder="VD: Nguyễn Văn A" required autofocus
                   oninput="updatePreview(this.value)">
            <?php if (isset($errors['full_name'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['full_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Điện thoại <span style="color:var(--danger)">*</span></label>
            <input type="tel" name="phone" class="form-control"
                   value="<?= e(input('phone')) ?>"
                   placeholder="09xxxxxxxx" required>
            <?php if (isset($errors['phone'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['phone']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Giới tính</label>
            <select name="gender" class="form-control">
              <option value="Nam"  <?= input('gender') !== 'Nữ' ? 'selected' : '' ?>>Nam</option>
              <option value="Nữ"   <?= input('gender') === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
            </select>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Ngày sinh</label>
            <input type="date" name="dob" class="form-control" value="<?= e(input('dob')) ?>">
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Ngày vào ngành</label>
            <input type="date" name="join_date" class="form-control" value="<?= e(input('join_date')) ?>">
          </div>

        </div>
      </div>
    </div>

    <!-- BƯỚC 1b: Thông tin công tác (BGH khóa) -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:99px;background:#10b981;color:white;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">2</div>
          <div>
            <div class="card-title">Thông tin công tác</div>
            <div class="card-subtitle">BGH nhập &amp; khóa — GV không thể tự sửa</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;background:var(--warning-light);border:1px solid rgba(245,158,11,.3);border-radius:99px;padding:5px 12px;font-size:12px;font-weight:700;color:var(--warning)">
          <i class="fas fa-lock"></i> Khóa sau khi lưu
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

          <div class="form-group" style="margin:0">
            <label class="form-label">Tổ chuyên môn <span style="color:var(--danger)">*</span></label>
            <select name="dept_id" class="form-control" required onchange="updateDept(this)">
              <option value="">— Chọn tổ —</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= e($dept['id']) ?>"
                        data-name="<?= e($dept['name']) ?>"
                        <?= input('dept_id') === $dept['id'] ? 'selected' : '' ?>>
                  <?= e($dept['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['dept_id'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['dept_id']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Môn dạy chính <span style="color:var(--danger)">*</span></label>
            <input type="text" name="subject" class="form-control"
                   value="<?= e(input('subject')) ?>"
                   placeholder="VD: Toán học, Ngữ văn..." required
                   oninput="updateSubject(this.value)">
            <?php if (isset($errors['subject'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['subject']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Chức vụ</label>
            <select name="role" class="form-control">
              <option value="Giáo viên"  <?= (input('role') ?: 'Giáo viên') === 'Giáo viên'  ? 'selected' : '' ?>>Giáo viên</option>
              <option value="Tổ trưởng"  <?= input('role') === 'Tổ trưởng' ? 'selected' : '' ?>>Tổ trưởng</option>
              <option value="Tổ phó"     <?= input('role') === 'Tổ phó'    ? 'selected' : '' ?>>Tổ phó</option>
            </select>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Hạng GV</label>
            <select name="rank" class="form-control" onchange="updateRank(this.value)">
              <option value="GV hạng III" <?= (input('rank') ?: 'GV hạng III') === 'GV hạng III' ? 'selected' : '' ?>>GV hạng III</option>
              <option value="GV hạng II"  <?= input('rank') === 'GV hạng II'  ? 'selected' : '' ?>>GV hạng II</option>
              <option value="GV hạng I"   <?= input('rank') === 'GV hạng I'   ? 'selected' : '' ?>>GV hạng I</option>
            </select>
          </div>

        </div>

        <!-- Ghi chú khóa -->
        <div style="margin-top:14px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);display:flex;gap:10px">
          <i class="fas fa-shield-alt" style="color:#10b981;margin-top:2px;flex-shrink:0"></i>
          <p style="font-size:12.5px;color:var(--text-secondary);margin:0;line-height:1.6">
            Thông tin công tác (Tổ, Môn dạy, Chức vụ, Hạng GV) sẽ được <strong>khóa</strong> sau khi tạo.
            Giáo viên có thể xem nhưng <strong>không thể tự sửa</strong>. Chỉ BGH mới có quyền thay đổi
            qua trang <em>Chi tiết hồ sơ</em>.
          </p>
        </div>
      </div>
    </div>

    <!-- BƯỚC 2: Tài khoản -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:99px;background:#8b5cf6;color:white;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800">3</div>
          <div>
            <div class="card-title">Tài khoản đăng nhập</div>
            <div class="card-subtitle">Email + mật khẩu tạm thời</div>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

          <div class="form-group" style="grid-column:1/-1;margin:0">
            <label class="form-label">Email đăng nhập <span style="color:var(--danger)">*</span></label>
            <input type="email" name="email" class="form-control" id="emailField"
                   value="<?= e(input('email')) ?>"
                   placeholder="gv@<?= e(GOOGLE_DOMAIN) ?>" required>
            <?php if (isset($errors['email'])): ?>
              <div style="font-size:12px;color:var(--danger);margin-top:4px"><?= e($errors['email']) ?></div>
            <?php endif; ?>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px">
              <i class="fas fa-lightbulb" style="color:var(--warning)"></i>
              Nên dùng email nhà trường: <strong>tên.họ@<?= e(GOOGLE_DOMAIN) ?></strong>
            </div>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Mật khẩu tạm thời <span style="color:var(--danger)">*</span></label>
            <div style="position:relative">
              <input type="text" name="password" id="passField" class="form-control"
                     value="<?= e(input('password') ?: 'EduVN@' . date('Y')) ?>"
                     style="padding-right:110px" required>
              <button type="button" onclick="genPass()"
                      style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:var(--primary);color:white;border:none;border-radius:6px;padding:4px 10px;font-size:11.5px;font-weight:700;cursor:pointer">
                Tạo ngẫu nhiên
              </button>
            </div>
          </div>

          <div class="form-group" style="margin:0">
            <label class="form-label">Vai trò hệ thống</label>
            <select name="user_role" class="form-control">
              <option value="teacher"   <?= (input('user_role') ?: 'teacher') === 'teacher'   ? 'selected' : '' ?>>Giáo viên</option>
              <option value="dept_head" <?= input('user_role') === 'dept_head' ? 'selected' : '' ?>>Tổ trưởng</option>
            </select>
          </div>

        </div>

        <div style="margin-top:14px;padding:12px 14px;background:var(--primary-light);border:1px solid rgba(26,110,245,.2);border-radius:var(--radius);display:flex;gap:10px">
          <i class="fas fa-paper-plane" style="color:var(--primary);margin-top:2px;flex-shrink:0"></i>
          <p style="font-size:12.5px;color:var(--primary);margin:0;line-height:1.6">
            Sau khi tạo, giáo viên đăng nhập bằng email &amp; mật khẩu tạm thời trên. 
            GV nên đổi mật khẩu ngay lần đầu. Hồ sơ chi tiết GV tự bổ sung qua trang <strong>"Hồ sơ của tôi"</strong>.
          </p>
        </div>
      </div>
    </div>

    <!-- Action bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:14px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg)">
      <a href="/teachers.php" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Hủy bỏ
      </a>
      <button type="submit" class="btn btn-primary" style="padding:11px 28px;font-size:14px">
        <i class="fas fa-user-plus"></i> Tạo tài khoản giáo viên
      </button>
    </div>

  </div>

  <!-- RIGHT: Preview card -->
  <div style="position:sticky;top:calc(var(--header-height) + 24px)">

    <!-- Preview -->
    <div style="background:linear-gradient(135deg,#1a6ef5,#0047c0);border-radius:var(--radius-lg);padding:20px;color:white;text-align:center;margin-bottom:16px">
      <div style="width:68px;height:68px;border-radius:50%;border:3px solid rgba(255,255,255,.3);margin:0 auto 12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900" id="previewAvatar">
        👤
      </div>
      <h3 style="font-size:16px;font-weight:800;margin-bottom:4px" id="previewName">Họ tên giáo viên</h3>
      <p style="opacity:.75;font-size:12.5px;margin-bottom:10px" id="previewSub">Môn dạy · Tổ chuyên môn</p>
      <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center" id="previewTags">
        <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11px">GV hạng III</span>
        <span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11px">Giáo viên</span>
      </div>
    </div>

    <!-- Checklist GV cần làm -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div class="card-title">📋 GV cần tự hoàn thiện</div>
      </div>
      <div class="card-body">
        <?php foreach ([
          ['fa-user',          'Thông tin cá nhân (CCCD, địa chỉ...)'],
          ['fa-heart',         'Thông tin gia đình'],
          ['fa-graduation-cap','Bằng cấp, trình độ đào tạo'],
          ['fa-certificate',   'Chứng chỉ bồi dưỡng'],
          ['fa-trophy',        'Danh hiệu, thành tích'],
          ['fa-paperclip',     'File minh chứng đính kèm'],
        ] as [$icon, $label]): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--border-light);font-size:12.5px;color:var(--text-secondary)">
            <i class="fas <?= $icon ?>" style="width:16px;text-align:center;color:var(--text-muted)"></i>
            <?= $label ?>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:10px;font-size:12px;color:var(--text-muted);font-style:italic">
          GV tự điền qua trang "Hồ sơ của tôi" sau khi đăng nhập lần đầu.
        </div>
      </div>
    </div>

    <!-- BGH giữ quyền -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">🔒 BGH giữ quyền kiểm soát</div>
      </div>
      <div class="card-body">
        <?php foreach ([
          ['#10b981', 'Tổ chuyên môn'],
          ['#10b981', 'Môn dạy chính'],
          ['#10b981', 'Chức vụ (TT/TP/GV)'],
          ['#10b981', 'Hạng giáo viên'],
          ['#10b981', 'Ngày vào ngành'],
          ['#10b981', 'Hệ số lương'],
        ] as [$c, $label]): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:12.5px">
            <i class="fas fa-lock" style="color:<?= $c ?>;width:14px;font-size:11px"></i>
            <span><?= $label ?></span>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:10px;font-size:12px;color:var(--text-muted);font-style:italic">
          GV xem được nhưng không thể tự thay đổi.
        </div>
      </div>
    </div>

  </div>
</div>
</form>

<?php
$deptJson = json_encode(array_map(fn($d) => [
    'id'   => $d['id'],
    'name' => $d['name'],
], $departments));

$inlineScript = <<<JS
const DEPTS = $deptJson;

function updatePreview(name) {
    const el = document.getElementById('previewName');
    if (el) el.textContent = name || 'Họ tên giáo viên';

    // Update avatar initials
    const av = document.getElementById('previewAvatar');
    if (av && name) {
        const parts = name.trim().split(' ');
        const initials = parts.length >= 2
            ? parts[parts.length-2][0] + parts[parts.length-1][0]
            : parts[0].substring(0,2);
        av.textContent = initials.toUpperCase();
    }

    // Auto-suggest email
    if (name) {
        const emailField = document.getElementById('emailField');
        if (emailField && !emailField.value) {
            const normalized = name.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g,'d').replace(/Đ/g,'D')
                .replace(/[^a-z0-9\\s]/g,'')
                .trim().split(/\\s+/);
            const last  = normalized[normalized.length-1] || '';
            const first = normalized.length > 1 ? normalized.slice(0,-1).join('') : '';
            if (last) emailField.placeholder = last + '.' + first + '@<?= GOOGLE_DOMAIN ?>';
        }
    }
}

function updateDept(sel) {
    const opt = sel.options[sel.selectedIndex];
    const sub = document.getElementById('previewSub');
    if (sub) {
        const subjectVal = document.querySelector('[name=subject]')?.value || 'Môn dạy';
        sub.textContent  = subjectVal + ' · ' + (opt.dataset.name || 'Tổ chuyên môn');
    }
}

function updateSubject(val) {
    const deptSel = document.querySelector('[name=dept_id]');
    const sub     = document.getElementById('previewSub');
    if (sub) {
        const deptName = deptSel?.options[deptSel.selectedIndex]?.dataset.name || 'Tổ chuyên môn';
        sub.textContent = (val || 'Môn dạy') + ' · ' + deptName;
    }
}

function updateRank(val) {
    const tags = document.getElementById('previewTags');
    if (tags) {
        const role = document.querySelector('[name=role]')?.value || 'Giáo viên';
        tags.innerHTML =
            '<span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11px">' + val + '</span>' +
            '<span style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:3px 10px;font-size:11px">' + role + '</span>';
    }
}

function genPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#!';
    let pass = '';
    for (let i = 0; i < 10; i++) pass += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('passField').value = pass;
    showToast('info', 'Mật khẩu mới', pass);
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
