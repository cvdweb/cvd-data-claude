<?php
/**
 * _footer.php
 * Đóng tất cả div, load JS cuối trang
 * Biến tuỳ chọn:
 *   $extraScripts  — array các CDN/script cần load thêm
 *   $inlineScript  — JS inline của từng trang
 */
?>

</div><!-- end page-content -->
</div><!-- end main-content -->
</div><!-- end app-layout -->

<!-- Toast container -->
<div id="toastContainer" class="toast-container"></div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->

<!-- App utilities (không trùng với layout-php.js) -->
<script src="/assets/app-php.js"></script>

<!-- Layout JS (sidebar, header, dropdown, theme, toast, modal) -->
<script src="/assets/layout-php.js"></script>

<!-- Extra scripts từng trang (CDN như Chart.js) -->
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Config trường — truyền từ PHP sang JS -->
<script>
window.SCHOOL = {
    name:        <?= json_encode(SCHOOL_NAME) ?>,
    short_name:  <?= json_encode(SCHOOL_SHORT) ?>,
    school_year: <?= json_encode(SCHOOL_YEAR) ?>,
    primary:     <?= json_encode(PRIMARY_COLOR) ?>,
};

window.CURRENT_USER = {
    id:         <?= json_encode($currentUser['id']) ?>,
    name:       <?= json_encode($currentUser['name']) ?>,
    email:      <?= json_encode($currentUser['email']) ?>,
    role:       <?= json_encode($currentUser['role']) ?>,
    role_label: <?= json_encode($currentUser['role_label']) ?>,
    avatar:     <?= json_encode($currentUser['avatar']) ?>,
    dept_id:    <?= json_encode($currentUser['dept_id']) ?>,
};

// Token cho API calls từ JS
window.API_TOKEN = <?= json_encode($_SESSION['token'] ?? '') ?>;
</script>

<!-- Inline script của từng trang -->
<?php if (!empty($inlineScript)): ?>
  <script>
    <?= $inlineScript ?>
  </script>
<?php endif; ?>

<!-- Dev tools chỉ hiện trong development -->
<?php if (APP_DEBUG): ?>
<script>
console.group('%c EduVN Debug ', 'background:#1a6ef5;color:white;border-radius:4px;padding:2px 6px');
console.log('School:', window.SCHOOL.name);
console.log('User:', window.CURRENT_USER.name, '|', window.CURRENT_USER.role);
console.log('Page:', document.title);
console.groupEnd();
</script>
<?php endif; ?>

</body>
</html>
