<?php
/**
 * index.php — Trang đăng nhập
 * Không cần đăng nhập để vào trang này
 */

require_once __DIR__ . '/_path.php';

// Nếu đã đăng nhập rồi → chuyển vào dashboard
if ($auth->check()) {
    redirect('/dashboard.php');
}

// ---- Xử lý form đăng nhập ----
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = input('email');
    $password = input('password');

    // Validate cơ bản
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $result = $auth->login($email, $password);

        if ($result) {
            // Đăng nhập thành công → redirect
            $redirectTo = input('redirect') ?: '/dashboard.php';
            redirect($redirectTo);
        } else {
            $error = 'Email hoặc mật khẩu không đúng. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập — <?= e(SCHOOL_SHORT) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { min-height:100vh; display:flex; align-items:stretch; overflow-y:auto; overflow-x:hidden; }

    /* Left panel */
    .login-left {
      flex:1; min-height:100vh;
      background: linear-gradient(150deg,#071227 0%,#0d1f4f 45%,#0a3a8a 100%);
      display:flex; flex-direction:column; justify-content:center;
      padding:56px 52px; position:relative; overflow:hidden;
    }
    .login-left::before {
      content:''; position:absolute; inset:0;
      background-image:radial-gradient(rgba(255,255,255,0.045) 1px,transparent 1px);
      background-size:26px 26px; pointer-events:none;
    }
    .blob { position:absolute; border-radius:50%; filter:blur(90px); pointer-events:none; }
    .blob-1 { width:400px;height:400px;background:#1a6ef5;opacity:.12;top:-130px;right:-70px; }
    .blob-2 { width:300px;height:300px;background:#00c6ae;opacity:.09;bottom:-50px;left:-50px; }

    .brand-row { display:flex;align-items:center;gap:14px;margin-bottom:48px;position:relative;z-index:1; }
    .brand-box {
      width:46px;height:46px;border-radius:13px;
      background:linear-gradient(135deg,<?= PRIMARY_COLOR ?>,#00c6ae);
      display:flex;align-items:center;justify-content:center;
      font-size:19px;font-weight:900;color:white;
      box-shadow:0 6px 20px rgba(26,110,245,.4);flex-shrink:0;
    }
    .brand-info strong { display:block;color:white;font-size:16px;font-weight:800; }
    .brand-info span   { color:rgba(255,255,255,.45);font-size:11.5px; }

    .hero { position:relative;z-index:1;margin-bottom:44px; }
    .hero h2 {
      font-size:clamp(26px,3vw,38px);font-weight:900;color:white;
      line-height:1.18;letter-spacing:-.03em;margin-bottom:14px;
    }
    .hero h2 em { font-style:normal;color:#60a5fa; }
    .hero p { font-size:14px;color:rgba(255,255,255,.58);line-height:1.75;max-width:370px; }

    .feats { position:relative;z-index:1;display:flex;flex-direction:column;gap:13px;margin-bottom:44px; }
    .feat-row { display:flex;align-items:flex-start;gap:13px; }
    .feat-ico {
      width:36px;height:36px;border-radius:9px;flex-shrink:0;
      background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);
      display:flex;align-items:center;justify-content:center;color:#93c5fd;font-size:14px;
    }
    .feat-body strong { display:block;color:white;font-size:12.5px;font-weight:700;margin-bottom:1px; }
    .feat-body span   { color:rgba(255,255,255,.52);font-size:12px;line-height:1.5; }

    .stats-strip {
      display:flex;border:1px solid rgba(255,255,255,.1);
      border-radius:13px;overflow:hidden;position:relative;z-index:1;
    }
    .stat-pill { flex:1;padding:13px 8px;text-align:center;border-right:1px solid rgba(255,255,255,.08); }
    .stat-pill:last-child { border-right:none; }
    .stat-pill strong { display:block;color:white;font-size:19px;font-weight:900;line-height:1; }
    .stat-pill span   { color:rgba(255,255,255,.45);font-size:10px;margin-top:4px;display:block; }
    .left-foot { position:relative;z-index:1;margin-top:28px;color:rgba(255,255,255,.28);font-size:11px; }

    /* Right panel */
    .login-right {
      width:460px;flex-shrink:0;background:var(--bg-card);
      overflow-y:auto;display:flex;flex-direction:column;
      justify-content:center;padding:52px 46px;
    }
    .form-head { margin-bottom:28px; }
    .form-head h1 { font-size:23px;font-weight:900;letter-spacing:-.02em;margin-bottom:5px; }
    .form-head p  { font-size:13px;color:var(--text-muted); }
    .school-tag {
      display:inline-flex;align-items:center;gap:6px;
      background:var(--primary-light);color:var(--primary);
      border:1px solid rgba(26,110,245,.18);border-radius:99px;
      padding:5px 12px;font-size:11.5px;font-weight:700;margin-top:9px;
    }

    /* Error box */
    .error-box {
      background:var(--danger-light);border:1px solid rgba(239,68,68,.25);
      border-radius:var(--radius-sm);padding:12px 14px;
      display:flex;align-items:center;gap:10px;margin-bottom:18px;
      font-size:13px;color:var(--danger);
    }

    .btn-google {
      width:100%;display:flex;align-items:center;justify-content:center;gap:10px;
      padding:12px 16px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
      background:var(--bg-card);font-size:13.5px;font-weight:600;
      color:var(--text-primary);cursor:pointer;transition:all var(--transition);margin-bottom:18px;
    }
    .btn-google:hover { border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-glow); }

    .or-line { display:flex;align-items:center;gap:10px;margin-bottom:18px; }
    .or-line::before,.or-line::after { content:'';flex:1;height:1px;background:var(--border); }
    .or-line span { font-size:11.5px;color:var(--text-muted);font-weight:500;white-space:nowrap; }

    .fw { position:relative; }
    .fw .fi { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none; }
    .fw .form-control { padding-left:38px; }
    .fw .eye-btn {
      position:absolute;right:12px;top:50%;transform:translateY(-50%);
      background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:13px;
      transition:color var(--transition);
    }
    .fw .eye-btn:hover { color:var(--primary); }

    .meta-row { display:flex;align-items:center;justify-content:space-between;margin-bottom:18px; }
    .chk-lbl { display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--text-secondary);cursor:pointer; }
    .chk-lbl input { accent-color:var(--primary); }
    .forgot { font-size:12.5px;color:var(--primary);font-weight:600; }
    .forgot:hover { text-decoration:underline; }

    .btn-submit {
      width:100%;padding:13px;background:var(--primary);color:white;border:none;
      border-radius:var(--radius-sm);font-size:14.5px;font-weight:700;cursor:pointer;
      display:flex;align-items:center;justify-content:center;gap:8px;
      transition:all var(--transition);margin-bottom:22px;letter-spacing:.01em;
    }
    .btn-submit:hover:not(:disabled) {
      background:var(--primary-dark);
      box-shadow:0 6px 20px rgba(26,110,245,.35);
      transform:translateY(-1px);
    }
    .btn-submit:disabled { opacity:.72;cursor:not-allowed;transform:none!important; }

    .demo-box { background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:14px; }
    .demo-lbl {
      font-size:10.5px;font-weight:800;text-transform:uppercase;
      letter-spacing:.09em;color:var(--text-muted);margin-bottom:9px;
      display:flex;align-items:center;gap:6px;
    }
    .demo-accounts { display:flex;flex-direction:column;gap:6px; }
    .demo-btn {
      display:flex;align-items:center;gap:10px;padding:9px 11px;
      background:var(--bg-card);border:1px solid var(--border);
      border-radius:var(--radius-sm);cursor:pointer;
      transition:all var(--transition);text-align:left;width:100%;
    }
    .demo-btn:hover { border-color:var(--primary);background:var(--primary-light); }
    .demo-btn img { width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0; }
    .demo-btn-info { flex:1;min-width:0; }
    .demo-btn-info strong { display:block;font-size:12.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .demo-btn-info span  { font-size:11px;color:var(--text-muted); }

    .foot-note { text-align:center;font-size:11.5px;color:var(--text-muted);margin-top:18px;line-height:1.6; }
    .foot-note a { color:var(--primary); }

    /* Loading overlay */
    .lo { position:fixed;inset:0;background:var(--primary);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px; }
    .lo.hide { animation:loFade .4s ease forwards; }
    @keyframes loFade { to { opacity:0;pointer-events:none; } }
    .lo-logo { color:white;font-size:24px;font-weight:900;letter-spacing:-.02em; }
    .lo-ring {
      width:32px;height:32px;border:3px solid rgba(255,255,255,.3);
      border-top-color:white;border-radius:50%;
      animation:spin .8s linear infinite;
    }
    .lo-msg { color:rgba(255,255,255,.65);font-size:13px; }
    @keyframes spin { to { transform:rotate(360deg); } }

    @media(max-width:900px) {
      body { flex-direction:column; }
      .login-left { min-height:auto;padding:36px 28px 32px; }
      .feats,.left-foot { display:none; }
      .hero { margin-bottom:24px; }
      .hero h2 { font-size:24px; }
      .brand-row { margin-bottom:24px; }
      .login-right { width:100%;justify-content:flex-start;padding:36px 24px 48px; }
    }
  </style>
</head>
<body>

<!-- Loading overlay -->
<div class="lo" id="lo">
  <div class="lo-logo">🏫 <?= e(SCHOOL_SHORT) ?></div>
  <div class="lo-ring"></div>
  <div class="lo-msg">Đang khởi tạo hệ thống...</div>
</div>

<!-- ===== LEFT PANEL ===== -->
<div class="login-left">
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>

  <div class="brand-row">
    <div class="brand-box"><?= mb_substr(SCHOOL_SHORT, 0, 1) ?></div>
    <div class="brand-info">
      <strong><?= e(SCHOOL_SHORT) ?></strong>
      <span>Hệ thống quản lý giáo dục</span>
    </div>
  </div>

  <div class="hero">
    <h2>Quản lý hồ sơ<br>giáo viên <em>thông minh</em><br>&amp; hiện đại</h2>
    <p>Tập trung hóa toàn bộ dữ liệu nhà trường — giáo viên chỉ nhập một lần, Ban giám hiệu không cần tạo Google Form lặp lại.</p>
  </div>

  <div class="feats">
    <div class="feat-row">
      <div class="feat-ico"><i class="fas fa-shield-halved"></i></div>
      <div class="feat-body">
        <strong>Phân quyền 3 cấp</strong>
        <span>BGH · Tổ trưởng · Giáo viên — mỗi vai trò một góc nhìn riêng</span>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-ico"><i class="fas fa-chart-pie"></i></div>
      <div class="feat-body">
        <strong>Báo cáo &amp; thống kê tức thì</strong>
        <span>Biểu đồ trực quan, xuất Excel &amp; PDF ngay lập tức</span>
      </div>
    </div>
    <div class="feat-row">
      <div class="feat-ico"><i class="fas fa-folder-open"></i></div>
      <div class="feat-body">
        <strong>Kho tài liệu tập trung</strong>
        <span>Hồ sơ và tài liệu tổ chuyên môn có tổ chức</span>
      </div>
    </div>
  </div>

  <div class="stats-strip">
    <?php
    // Lấy số liệu thật từ JSON
    $totalTeachers = $db->count('teachers');
    $totalDepts    = $db->count('departments');
    $completedPct  = 89; // Tính từ teachers sau
    ?>
    <div class="stat-pill"><strong><?= $totalTeachers ?: 40 ?></strong><span>Giáo viên</span></div>
    <div class="stat-pill"><strong><?= $totalDepts ?: 6 ?></strong><span>Tổ chuyên môn</span></div>
    <div class="stat-pill"><strong><?= $completedPct ?>%</strong><span>Hồ sơ HT</span></div>
    <div class="stat-pill"><strong><?= SCHOOL_YEAR ?></strong><span>Năm học</span></div>
  </div>

  <div class="left-foot">© <?= date('Y') ?> <?= e(SCHOOL_SHORT) ?> · EduVN Manager v1.0</div>
</div>

<!-- ===== RIGHT PANEL ===== -->
<div class="login-right">

  <div class="form-head">
    <h1>Đăng nhập</h1>
    <p>Chào mừng trở lại hệ thống EduVN Manager</p>
    <div class="school-tag">
      <i class="fas fa-school" style="font-size:11px"></i>
      <?= e(SCHOOL_NAME) ?>
    </div>
  </div>

  <!-- Hiển thị lỗi nếu có -->
  <?php if ($error): ?>
    <div class="error-box">
      <i class="fas fa-exclamation-circle"></i>
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <!-- Google OAuth (nếu bật) -->
  <?php if (FEATURE_GOOGLE_LOGIN): ?>
    <a href="/api/auth/google.php" class="btn-google">
      <svg width="18" height="18" viewBox="0 0 24 24">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Tiếp tục với Google (<?= e(GOOGLE_DOMAIN) ?>)
    </a>
    <div class="or-line"><span>hoặc đăng nhập bằng tài khoản nội bộ</span></div>
  <?php endif; ?>

  <!-- Form đăng nhập -->
  <form method="POST" action="/index.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
    <?php if (!empty($_GET['redirect'])): ?>
      <input type="hidden" name="redirect" value="<?= e($_GET['redirect']) ?>">
    <?php endif; ?>

    <div class="form-group">
      <label class="form-label">Email / Tên đăng nhập</label>
      <div class="fw">
        <i class="fas fa-envelope fi"></i>
        <input type="text" name="email" class="form-control"
               value="<?= e(input('email')) ?>"
               placeholder="email@<?= e(GOOGLE_DOMAIN) ?>"
               autocomplete="email" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Mật khẩu</label>
      <div class="fw">
        <i class="fas fa-lock fi"></i>
        <input type="password" name="password" id="pwd" class="form-control"
               placeholder="Nhập mật khẩu"
               autocomplete="current-password" required>
        <button type="button" class="eye-btn" onclick="togglePwd()">
          <i class="fas fa-eye" id="eyeIco"></i>
        </button>
      </div>
    </div>

    <div class="meta-row">
      <label class="chk-lbl">
        <input type="checkbox" name="remember" checked> Ghi nhớ đăng nhập
      </label>
      <a href="#" class="forgot">Quên mật khẩu?</a>
    </div>

    <button type="submit" class="btn-submit" id="loginBtn">
      <i class="fas fa-right-to-bracket"></i> Đăng nhập
    </button>
  </form>

  <!-- Tài khoản demo -->
  <div class="demo-box">
    <div class="demo-lbl"><i class="fas fa-key"></i> Tài khoản demo</div>
    <div class="demo-accounts">
      <button class="demo-btn" type="button"
              onclick="quickLogin('bgiamdoc@thcsthpt-soctrang.edu.vn')">
        <img src="https://i.pravatar.cc/40?img=60" alt="">
        <div class="demo-btn-info">
          <strong>Nguyễn Văn An – Hiệu trưởng</strong>
          <span>bgiamdoc@thcsthpt-soctrang.edu.vn</span>
        </div>
        <span class="badge badge-danger">BGH</span>
      </button>
      <button class="demo-btn" type="button"
              onclick="quickLogin('totruong.toan@thcsthpt-soctrang.edu.vn')">
        <img src="https://i.pravatar.cc/40?img=12" alt="">
        <div class="demo-btn-info">
          <strong>Trần Văn Bình – Tổ trưởng Toán</strong>
          <span>totruong.toan@thcsthpt-soctrang.edu.vn</span>
        </div>
        <span class="badge badge-primary">Tổ trưởng</span>
      </button>
      <button class="demo-btn" type="button"
              onclick="quickLogin('huong.nguyen@thcsthpt-soctrang.edu.vn')">
        <img src="https://i.pravatar.cc/40?img=47" alt="">
        <div class="demo-btn-info">
          <strong>Nguyễn Thị Hương – Giáo viên</strong>
          <span>huong.nguyen@thcsthpt-soctrang.edu.vn</span>
        </div>
        <span class="badge badge-neutral">GV</span>
      </button>
    </div>
  </div>

  <div class="foot-note">
    Bằng cách đăng nhập, bạn đồng ý với
    <a href="#">Điều khoản sử dụng</a> &amp;
    <a href="#">Chính sách bảo mật</a>
  </div>

</div><!-- end login-right -->

<script src="/assets/layout-php.js"></script>
<script>
// Loading overlay
window.addEventListener('load', () => {
    setTimeout(() => {
        const el = document.getElementById('lo');
        el.classList.add('hide');
        setTimeout(() => el.remove(), 400);
    }, 800);
});

// Toggle password
function togglePwd() {
    const i = document.getElementById('pwd');
    const ico = document.getElementById('eyeIco');
    i.type = i.type === 'password' ? 'text' : 'password';
    ico.className = i.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// Quick login — điền email vào form
function quickLogin(email) {
    document.querySelector('input[name="email"]').value = email;
    document.querySelector('input[name="password"]').value = 'password';
    document.getElementById('loginBtn').click();
}

// Loading state khi submit
document.querySelector('form').addEventListener('submit', () => {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<div class="lo-ring" style="width:16px;height:16px;border-width:2px"></div> Đang xác thực...';
    btn.disabled = true;
});
</script>
</body>
</html>
