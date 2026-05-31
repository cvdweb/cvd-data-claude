<?php
/**
 * _sidebar.php
 * Sidebar dùng chung cho tất cả trang
 * Biến cần có: $currentUser, $activePage, $config
 */

$activePage = $activePage ?? '';

// ---- Định nghĩa menu ----
$navMain = [
    [
        'href'  => '/dashboard.php',
        'icon'  => 'fa-tachometer-alt',
        'label' => 'Tổng quan',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/teachers.php',
        'icon'  => 'fa-chalkboard-teacher',
        'label' => 'Hồ sơ giáo viên',
        'badge' => null,
        'roles' => ['admin', 'dept_head'],
    ],
    [
        'href'  => '/departments.php',
        'icon'  => 'fa-users',
        'label' => 'Tổ chuyên môn',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/contacts.php',
        'icon'  => 'fa-address-book',
        'label' => 'Danh bạ nội bộ',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/requests.php',
        'icon'  => 'fa-tasks',
        'label' => 'Yêu cầu dữ liệu',
        'badge' => $openRequestCount ?? null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/documents.php',
        'icon'  => 'fa-folder-open',
        'label' => 'Kho tài liệu',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/reports.php',
        'icon'  => 'fa-chart-pie',
        'label' => 'Báo cáo',
        'badge' => null,
        'roles' => ['admin', 'dept_head'],
    ],
    [
        'href'  => '/calendar.php',
        'icon'  => 'fa-calendar-alt',
        'label' => 'Lịch & Sự kiện',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
];

$navPersonal = [
    [
        'href'  => '/notifications.php',
        'icon'  => 'fa-bell',
        'label' => 'Thông báo',
        'badge' => $unreadNotifCount ?? null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/my-profile.php',
        'icon'  => 'fa-id-badge',
        'label' => 'Hồ sơ của tôi',
        'badge' => null,
        'roles' => ['admin', 'dept_head', 'teacher'],
    ],
    [
        'href'  => '/settings.php',
        'icon'  => 'fa-cog',
        'label' => 'Cài đặt',
        'badge' => null,
        'roles' => ['admin'],
    ],
];

// ---- Helper render 1 nav item ----
$renderNavItem = function (array $item) use ($activePage, $currentUser): string {
    // Kiểm tra quyền — chỉ hiện menu phù hợp với role
    if (!in_array($currentUser['role'], $item['roles'])) {
        return '';
    }

    $isActive = basename($activePage) === basename($item['href']) ? 'active' : '';

    $badge = '';
    if (!empty($item['badge'])) {
        $badge = '<span class="nav-badge">' . (int)$item['badge'] . '</span>';
    }

    return "
    <a href=\"{$item['href']}\" class=\"nav-item {$isActive}\">
        <i class=\"fas {$item['icon']}\"></i>
        <span>{$item['label']}</span>
        {$badge}
    </a>";
};
?>

<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon">
      <?php if (!empty($config['logo']) && file_exists(PUBLIC_DIR . $config['logo'])): ?>
        <img src="<?= e($config['logo']) ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:10px">
      <?php else: ?>
        <?= mb_substr($config['short_name'] ?? 'E', 0, 1) ?>
      <?php endif; ?>
    </div>
    <div class="logo-text">
      <strong><?= e($config['short_name'] ?? SCHOOL_SHORT) ?></strong>
      <span>Năm học <?= e($config['school_year'] ?? SCHOOL_YEAR) ?></span>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <div class="nav-section-label">Quản lý</div>
    <?php foreach ($navMain as $item): ?>
      <?= $renderNavItem($item) ?>
    <?php endforeach; ?>

    <div class="nav-section-label" style="margin-top:12px">Cá nhân</div>
    <?php foreach ($navPersonal as $item): ?>
      <?= $renderNavItem($item) ?>
    <?php endforeach; ?>

    <div class="nav-section-label" style="margin-top:12px">Hệ thống</div>

    <!-- Theme toggle -->
    <a href="#" class="nav-item" id="themeNavBtn">
      <i class="fas fa-moon theme-icon-nav"></i>
      <span id="themeLabel">Chế độ tối</span>
    </a>

    <!-- Đăng xuất -->
    <a href="/logout.php" class="nav-item"
       onclick="return confirm('Bạn có chắc muốn đăng xuất không?')">
      <i class="fas fa-sign-out-alt"></i>
      <span>Đăng xuất</span>
    </a>

  </nav>

  <!-- User info -->
  <div class="sidebar-footer">
    <a href="/my-profile.php" class="sidebar-user" style="text-decoration:none">
      <img src="<?= e($currentUser['avatar']) ?>"
           alt="<?= e($currentUser['name']) ?>"
           onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($currentUser['name']) ?>&background=1a6ef5&color=fff&size=80'">
      <div class="sidebar-user-info">
        <strong><?= e($currentUser['name']) ?></strong>
        <span><?= e($currentUser['role_label']) ?></span>
      </div>
      <i class="fas fa-ellipsis-v"
         style="color:rgba(255,255,255,0.3);font-size:12px"></i>
    </a>
  </div>

</aside>

<!-- Overlay khi sidebar mở trên mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
