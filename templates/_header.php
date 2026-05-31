<?php
/**
 * _header.php
 * Header dùng chung cho tất cả trang
 * Biến cần có: $currentUser, $pageTitle, $pageSubtitle
 * Biến tuỳ chọn: $headerActions (HTML nút bên phải breadcrumb)
 */

$pageTitle    = $pageTitle    ?? 'EduVN';
$pageSubtitle = $pageSubtitle ?? '';

// ---- Lấy thông báo chưa đọc ----
// (đã tính ở bootstrap, dùng biến $unreadNotifCount)
$unreadCount = $unreadNotifCount ?? 0;

// ---- Lấy flash message nếu có ----
$flash = flash();
?>

<!-- Mở main-content -->
<div class="main-content">

<header class="header">

  <!-- Nút mở sidebar (mobile) -->
  <button class="sidebar-toggle" id="sidebarToggleBtn" title="Menu">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Tiêu đề trang -->
  <div class="header-title">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($pageSubtitle): ?>
      <p><?= e($pageSubtitle) ?></p>
    <?php endif; ?>
  </div>

  <!-- Actions bên phải -->
  <div class="header-actions">

    <!-- Tìm kiếm -->
    <div class="header-search">
      <i class="fas fa-search"></i>
      <input type="text"
             placeholder="Tìm kiếm..."
             id="globalSearch"
             autocomplete="off">
    </div>

    <!-- Thông báo -->
    <div style="position:relative">
      <button class="btn-icon" data-dropdown="notifPanel" title="Thông báo">
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="notif-dot"></span>
        <?php endif; ?>
      </button>

      <!-- Dropdown thông báo -->
      <div class="notif-panel" id="notifPanel" style="display:none">
        <div class="notif-header">
          <span class="notif-title">
            Thông báo
            <?php if ($unreadCount > 0): ?>
              <span class="badge badge-danger" style="margin-left:6px;font-size:10px">
                <?= $unreadCount ?>
              </span>
            <?php endif; ?>
          </span>
          <a href="/notifications.php?action=read_all"
             style="font-size:12px;color:var(--primary)">Đánh dấu đã đọc</a>
        </div>

        <!-- Hiển thị 4 thông báo gần nhất -->
        <?php
        $recentNotifs = array_slice(
            array_filter(
                $db->where('notifications', 'user_id', $currentUser['id']),
                fn($n) => true
            ),
            0, 4
        );

        if (empty($recentNotifs)):
        ?>
          <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
            <i class="fas fa-bell-slash" style="font-size:24px;margin-bottom:8px;display:block"></i>
            Không có thông báo mới
          </div>
        <?php else: ?>
          <?php foreach ($recentNotifs as $notif): ?>
            <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
              <div class="notif-dot-small"
                   style="<?= $notif['is_read'] ? 'background:transparent' : '' ?>">
              </div>
              <div>
                <div class="notif-text">
                  <?= e($notif['body'] ?? '') ?>
                </div>
                <div class="notif-time">
                  <?= timeAgo($notif['created_at'] ?? '') ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div style="padding:10px 18px;border-top:1px solid var(--border-light)">
          <a href="/notifications.php"
             class="btn btn-outline btn-sm"
             style="width:100%;justify-content:center">
            Xem tất cả thông báo
          </a>
        </div>
      </div>
    </div>

    <!-- Theme toggle -->
    <button class="btn-icon btn-theme" title="Chuyển giao diện sáng/tối">
      <span class="theme-icon"><i class="fas fa-moon"></i></span>
    </button>

    <!-- User menu -->
    <div style="position:relative">
      <img src="<?= e($currentUser['avatar']) ?>"
           class="header-avatar"
           data-dropdown="userMenu"
           alt="<?= e($currentUser['name']) ?>"
           title="<?= e($currentUser['name']) ?>"
           onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($currentUser['name']) ?>&background=1a6ef5&color=fff&size=80'">

      <div class="dropdown-menu" id="userMenu" style="display:none">
        <!-- User info -->
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-light)">
          <div style="font-size:13px;font-weight:700">
            <?= e($currentUser['name']) ?>
          </div>
          <div style="font-size:11.5px;color:var(--text-muted)">
            <?= e($currentUser['email']) ?>
          </div>
          <span class="badge badge-<?= $currentUser['role'] === 'admin' ? 'danger' : ($currentUser['role'] === 'dept_head' ? 'primary' : 'neutral') ?>"
                style="margin-top:6px">
            <?= e($currentUser['role_label']) ?>
          </span>
        </div>

        <a class="dropdown-item" href="/my-profile.php">
          <i class="fas fa-user" style="color:var(--text-muted);width:16px"></i>
          Hồ sơ cá nhân
        </a>
        <a class="dropdown-item" href="/notifications.php">
          <i class="fas fa-bell" style="color:var(--text-muted);width:16px"></i>
          Thông báo
          <?php if ($unreadCount > 0): ?>
            <span class="nav-badge" style="margin-left:auto"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
        <?php if ($currentUser['role'] === 'admin'): ?>
          <a class="dropdown-item" href="/settings.php">
            <i class="fas fa-cog" style="color:var(--text-muted);width:16px"></i>
            Cài đặt hệ thống
          </a>
        <?php endif; ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item"
           href="/logout.php"
           onclick="return confirm('Bạn có chắc muốn đăng xuất không?')">
          <i class="fas fa-sign-out-alt" style="color:var(--danger);width:16px"></i>
          Đăng xuất
        </a>
      </div>
    </div>

  </div><!-- end header-actions -->
</header>

<!-- Flash message (sau redirect) -->
<?php if ($flash): ?>
<div style="padding:0 24px;margin-top:12px">
  <div class="toast <?= e($flash['type']) ?>"
       style="max-width:100%;animation:none;position:relative;top:0;right:0">
    <div class="toast-icon">
      <i class="fas <?= $flash['type'] === 'success' ? 'fa-check' : ($flash['type'] === 'danger' ? 'fa-times' : 'fa-info') ?>"></i>
    </div>
    <div class="toast-content">
      <div class="toast-msg"><?= e($flash['message']) ?></div>
    </div>
    <button class="toast-close" onclick="this.closest('.toast').remove()">×</button>
  </div>
</div>
<?php endif; ?>

<!-- Mở page-content -->
<div class="page-content">
