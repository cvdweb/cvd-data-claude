<?php
/**
 * contacts.php — Danh bạ nội bộ
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$teachers         = $db->read('teachers');
$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

$q      = input('q');
$deptId = input('dept');

$filtered = $teachers;
if ($q !== '') {
    $kw = mb_strtolower($q);
    $filtered = array_filter($filtered, fn($t) =>
        str_contains(mb_strtolower($t['personal']['full_name'] ?? ''), $kw) ||
        str_contains(mb_strtolower($t['work']['subject'] ?? ''), $kw)
    );
}
if ($deptId !== '') {
    $filtered = array_filter($filtered, fn($t) => ($t['work']['dept_id'] ?? '') === $deptId);
}
$filtered = $db->orderBy(array_values($filtered), 'personal.full_name');

$deptMap = [];
foreach ($departments as $d) $deptMap[$d['id']] = $d;

// Nhóm theo tổ
$grouped = [];
foreach ($filtered as $t) {
    $did = $t['work']['dept_id'] ?? 'other';
    $grouped[$did][] = $t;
}

$roleCfg = [
    'Tổ trưởng' => ['#ef4444', 'fa-crown'],
    'Tổ phó'    => ['#f59e0b', 'fa-star'],
    'Giáo viên' => ['#94a3b8', 'fa-circle'],
];

$pageTitle    = 'Danh bạ';
$pageSubtitle = count($filtered) . ' giáo viên';
$activePage   = 'contacts.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display:ital@0;1&display=swap');

/* === LAYOUT === */
.cb-root { display: grid; grid-template-columns: 260px 1fr; gap: 0; min-height: calc(100vh - 120px); }

/* === LEFT PANEL === */
.cb-left {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  overflow: hidden;
  position: sticky;
  top: calc(var(--header-height) + 20px);
  max-height: calc(100vh - 140px);
  display: flex; flex-direction: column;
}

/* Search */
.cb-searchbox {
  padding: 16px 16px 12px;
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.cb-searchbox label {
  display: flex; align-items: center; gap: 9px;
  background: var(--bg); border: 1.5px solid var(--border);
  border-radius: 10px; padding: 9px 13px;
  transition: border-color .2s, box-shadow .2s;
}
.cb-searchbox label:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-glow);
}
.cb-searchbox i { color: var(--text-muted); font-size: 13px; flex-shrink: 0; }
.cb-searchbox input {
  border: none; background: transparent; outline: none;
  font-size: 13px; color: var(--text-primary);
  font-family: 'DM Sans', sans-serif; flex: 1; width: 100%;
}
.cb-searchbox input::placeholder { color: var(--text-muted); }

/* Dept nav */
.cb-dept-nav { flex: 1; overflow-y: auto; padding: 10px 10px; }
.cb-dept-nav::-webkit-scrollbar { width: 4px; }
.cb-dept-nav::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

.cb-dept-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 10px; cursor: pointer;
  text-decoration: none; transition: all .15s;
  margin-bottom: 2px; color: var(--text-secondary);
  font-size: 13px; font-weight: 500;
  font-family: 'DM Sans', sans-serif;
}
.cb-dept-item:hover { background: var(--bg); color: var(--text-primary); }
.cb-dept-item.active { background: var(--primary-light); color: var(--primary); font-weight: 700; }

.cb-dept-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0;
}
.cb-dept-count {
  margin-left: auto; font-size: 11.5px; font-weight: 700;
  color: var(--text-muted); background: var(--bg);
  border: 1px solid var(--border); border-radius: 99px;
  padding: 1px 8px; flex-shrink: 0;
}
.cb-dept-item.active .cb-dept-count { background: var(--primary); color: white; border-color: var(--primary); }

/* Divider */
.cb-nav-divider {
  padding: 8px 12px 4px;
  font-size: 10px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: var(--text-muted);
}

/* Stats bottom */
.cb-left-foot {
  padding: 12px 16px;
  border-top: 1px solid var(--border-light);
  font-size: 11.5px; color: var(--text-muted);
  flex-shrink: 0;
}

/* === RIGHT PANEL === */
.cb-right { padding-left: 20px; }

/* Toolbar top-right */
.cb-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
}
.cb-count { font-size: 13px; color: var(--text-muted); }
.cb-count strong { color: var(--text-primary); font-size: 15px; }

/* === TABLE === */
.cb-table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; }

/* Section header */
.cb-section {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 20px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  font-size: 11px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .09em;
  color: var(--text-muted);
  font-family: 'DM Sans', sans-serif;
  position: sticky; top: 0; z-index: 2;
}
.cb-section-pip { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.cb-section-cnt {
  margin-left: auto; background: var(--bg-card);
  border: 1px solid var(--border); border-radius: 99px;
  padding: 1px 8px; font-size: 10.5px; color: var(--text-muted);
}

/* Row */
.cb-row {
  display: grid;
  grid-template-columns: 42px minmax(160px,1.2fr) 120px minmax(180px,1.5fr) 96px;
  align-items: center; gap: 0;
  padding: 0 20px;
  min-height: 58px;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background .12s;
  position: relative;
}
.cb-row:last-child { border-bottom: none; }
.cb-row:hover { background: var(--bg); }
.cb-row:hover .cb-row-actions { opacity: 1; }

/* Avatar */
.cb-row-av {
  width: 36px; height: 36px; border-radius: 50%;
  object-fit: cover; border: 2px solid var(--border);
  flex-shrink: 0; transition: border-color .15s;
  display: block;
}
.cb-row:hover .cb-row-av { border-color: var(--primary); }

/* Name col */
.cb-row-name {
  padding: 0 14px 0 12px; min-width: 0;
}
.cb-row-fullname {
  font-size: 13.5px; font-weight: 600;
  color: var(--text-primary);
  font-family: 'DM Sans', sans-serif;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cb-row-sub {
  font-size: 11.5px; color: var(--text-muted);
  margin-top: 1px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Role col */
.cb-row-role { padding: 0 14px; }
.cb-role-tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 6px;
  font-size: 11.5px; font-weight: 700;
  font-family: 'DM Sans', sans-serif;
}

/* Contact col */
.cb-row-contact { padding: 0 14px; min-width: 0; }
.cb-row-contact a {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--text-secondary);
  text-decoration: none;
  word-break: break-all;
  transition: color .15s; line-height: 1.8;
}
.cb-row-contact a:hover { color: var(--primary); }
.cb-row-contact a i { color: var(--text-muted); width: 12px; flex-shrink: 0; font-size: 11px; }
.cb-no-contact { font-size: 11.5px; color: var(--border); display: flex; align-items: center; gap: 5px; }

/* Actions col */
.cb-row-actions {
  padding: 0 0 0 8px;
  display: flex; gap: 5px;
  opacity: 0; transition: opacity .15s;
  justify-content: flex-end;
}
.cb-btn {
  width: 28px; height: 28px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--bg-card);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: var(--text-muted);
  text-decoration: none; cursor: pointer;
  transition: all .12s; flex-shrink: 0;
}
.cb-btn:hover        { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.cb-btn.ph:hover     { border-color: #22c55e; color: #22c55e; background: rgba(34,197,94,.08); }
.cb-btn.zl:hover     { border-color: #0068ff; color: #0068ff; background: rgba(0,104,255,.07); }

/* === MODAL === */
.cm-wrap { font-family: 'DM Sans', sans-serif; }
.cm-hero {
  padding: 28px 24px 22px; text-align: center;
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  position: relative; overflow: hidden;
}
.cm-hero::before {
  content: ''; position: absolute; inset: 0;
  background: inherit; filter: blur(0); z-index: 0;
}
.cm-hero-content { position: relative; z-index: 1; }
.cm-close {
  position: absolute; top: 14px; right: 14px; z-index: 2;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
  border-radius: 8px; padding: 5px 12px; color: white;
  cursor: pointer; font-size: 13px; line-height: 1;
}
.cm-close:hover { background: rgba(255,255,255,.3); }
.cm-av {
  width: 88px; height: 88px; border-radius: 50%;
  border: 3px solid rgba(255,255,255,.35);
  object-fit: cover; margin: 0 auto 14px; display: block;
  box-shadow: 0 8px 24px rgba(0,0,0,.2);
}
.cm-name { color: white; font-size: 18px; font-weight: 700; margin-bottom: 4px; line-height: 1.2; }
.cm-sub  { color: rgba(255,255,255,.75); font-size: 13px; }
.cm-tags { display: flex; gap: 6px; justify-content: center; margin-top: 12px; flex-wrap: wrap; }
.cm-tag  {
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.2);
  border-radius: 6px; padding: 3px 10px;
  font-size: 11.5px; color: white; font-weight: 600;
}
.cm-body { padding: 18px 22px 20px; }
.cm-contact-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; border-radius: 10px;
  background: var(--bg); border: 1px solid var(--border);
  margin-bottom: 8px; transition: border-color .15s;
}
.cm-contact-item:hover { border-color: var(--primary); }
.cm-ci-icon {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0;
}
.cm-ci-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); margin-bottom: 2px; }
.cm-ci-value { font-size: 13px; font-weight: 600; }
.cm-ci-value a { text-decoration: none; transition: color .15s; word-break: break-all; }
.cm-actions { display: flex; gap: 8px; margin-top: 16px; }

/* Empty */
.cb-empty { padding: 60px 20px; text-align: center; color: var(--text-muted); }
.cb-empty-icon { font-size: 40px; margin-bottom: 12px; display: block; opacity: .4; }

/* === RESPONSIVE === */
@media (max-width: 900px) {
  .cb-root { grid-template-columns: 1fr; }
  .cb-left { position: static; max-height: none; border-radius: var(--radius-lg); margin-bottom: 16px; }
  .cb-right { padding-left: 0; }
  .cb-dept-nav { max-height: 120px; }
  .cb-row { grid-template-columns: 36px 1fr 100px; }
  .cb-row-role, .cb-row-contact { display: none; }
  .cb-row-actions { opacity: 1; }
}
@media (max-width: 600px) {
  .cb-row { grid-template-columns: 36px 1fr 80px; padding: 0 14px; min-height: 52px; }
}
</style>

<div class="cb-root">

  <!-- LEFT: Nav -->
  <div class="cb-left">

    <!-- Search -->
    <div class="cb-searchbox">
      <label>
        <i class="fas fa-search"></i>
        <input type="text" id="cbSearch"
               placeholder="Tìm tên, môn dạy..."
               value="<?= e($q) ?>"
               autocomplete="off">
      </label>
    </div>

    <div class="cb-dept-nav">
      <!-- All -->
      <div class="cb-nav-divider">Lọc theo tổ</div>
      <a href="/contacts.php" class="cb-dept-item <?= !$deptId ? 'active' : '' ?>">
        <div class="cb-dept-icon" style="background:var(--bg)">👥</div>
        Tất cả giáo viên
        <span class="cb-dept-count"><?= count($teachers) ?></span>
      </a>

      <?php foreach ($departments as $dept):
        $cnt      = count(array_filter($teachers, fn($t) => ($t['work']['dept_id'] ?? '') === $dept['id']));
        $isActive = $deptId === $dept['id'];
        $color    = $dept['color'] ?? '#1a6ef5';
      ?>
        <a href="/contacts.php?dept=<?= e($dept['id']) ?>"
           class="cb-dept-item <?= $isActive ? 'active' : '' ?>">
          <div class="cb-dept-icon" style="background:<?= $color ?>18">
            <?= $dept['icon'] ?? '📁' ?>
          </div>
          <?= e(str_replace('Tổ ', '', $dept['name'])) ?>
          <span class="cb-dept-count"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Footer stats -->
    <div class="cb-left-foot">
      <?php
      $masters  = count(array_filter($teachers, fn($t) => ($t['education']['degree'] ?? '') === 'Thạc sĩ'));
      $heads    = count(array_filter($teachers, fn($t) => ($t['work']['role'] ?? '') === 'Tổ trưởng'));
      ?>
      <div style="display:flex;gap:14px;flex-wrap:wrap">
        <span><strong style="color:var(--text-primary)"><?= count($teachers) ?></strong> GV</span>
        <span><strong style="color:var(--text-primary)"><?= $masters ?></strong> Thạc sĩ</span>
        <span><strong style="color:var(--text-primary)"><?= $heads ?></strong> Tổ trưởng</span>
      </div>
    </div>
  </div>

  <!-- RIGHT: List -->
  <div class="cb-right">

    <div class="cb-toolbar">
      <div class="cb-count">
        Hiển thị <strong id="cbCountNum"><?= count($filtered) ?></strong> giáo viên
        <?php if ($q || $deptId): ?>
          <a href="/contacts.php" style="margin-left:8px;font-size:12px;color:var(--danger);text-decoration:none">
            <i class="fas fa-times-circle"></i> Xóa bộ lọc
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($filtered)): ?>
      <div class="cb-table-wrap">
        <div class="cb-empty">
          <span class="cb-empty-icon">🔍</span>
          <div style="font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:6px">Không có kết quả</div>
          <div style="font-size:13px">Thử từ khóa khác</div>
        </div>
      </div>
    <?php else: ?>

      <div class="cb-table-wrap" id="cbTable">
        <?php foreach ($grouped as $did => $group):
          $dept      = $deptMap[$did] ?? null;
          $deptColor = $dept ? ($dept['color'] ?? '#1a6ef5') : '#64748b';
          $deptName  = $dept ? $dept['name'] : 'Chưa phân tổ';
          $deptIcon  = $dept ? ($dept['icon'] ?? '📁') : '📁';
        ?>

          <!-- Section header -->
          <div class="cb-section">
            <div class="cb-section-pip" style="background:<?= $deptColor ?>"></div>
            <?= $deptIcon ?> <?= e($deptName) ?>
            <span class="cb-section-cnt"><?= count($group) ?> GV</span>
          </div>

          <?php foreach ($group as $t):
            $name    = $t['personal']['full_name'] ?? '—';
            $subject = $t['work']['subject']       ?? '—';
            $role    = $t['work']['role']          ?? 'Giáo viên';
            $phone   = $t['personal']['phone']     ?? null;
            $email   = $t['personal']['email']     ?? null;
            $avatar  = $t['avatar_url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a6ef5&color=fff&size=80';
            [$rclr, $ricon] = $roleCfg[$role] ?? ['#94a3b8','fa-circle'];
            $isHead  = in_array($role, ['Tổ trưởng','Tổ phó']);

            $mData = htmlspecialchars(json_encode([
              'name'    => $name,
              'subject' => $subject,
              'dept'    => $deptName,
              'role'    => $role,
              'phone'   => $phone,
              'email'   => $email,
              'avatar'  => $avatar,
              'color'   => $deptColor,
              'degree'  => $t['education']['degree'] ?? '—',
              'rank'    => $t['work']['rank']         ?? '—',
              'years'   => yearsOfService($t['work']['join_date'] ?? null),
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
          ?>

            <div class="cb-row" onclick="openContact(<?= $mData ?>)"
                 data-name="<?= e(mb_strtolower($name)) ?>"
                 data-subject="<?= e(mb_strtolower($subject)) ?>">

              <!-- Avatar -->
              <img src="<?= e($avatar) ?>" class="cb-row-av" alt="<?= e($name) ?>"
                   onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=80'">

              <!-- Name + sub -->
              <div class="cb-row-name">
                <div class="cb-row-fullname">
                  <?php if ($isHead): ?>
                    <i class="fas <?= $ricon ?>" style="color:<?= $rclr ?>;font-size:10px;margin-right:4px"></i>
                  <?php endif; ?>
                  <?= e($name) ?>
                </div>
                <div class="cb-row-sub"><?= e($subject) ?></div>
              </div>

              <!-- Role -->
              <div class="cb-row-role">
                <span class="cb-role-tag"
                      style="background:<?= $rclr ?>15;color:<?= $rclr ?>">
                  <?= e($role) ?>
                </span>
              </div>

              <!-- Contact -->
              <div class="cb-row-contact">
                <?php if ($email): ?>
                  <a href="mailto:<?= e($email) ?>" onclick="event.stopPropagation()">
                    <i class="fas fa-envelope"></i><?= e($email) ?>
                  </a>
                <?php endif; ?>
                <?php if ($phone): ?>
                  <a href="tel:<?= e($phone) ?>" onclick="event.stopPropagation()">
                    <i class="fas fa-phone"></i><?= e($phone) ?>
                  </a>
                <?php else: ?>
                  <div class="cb-no-contact"><i class="fas fa-lock" style="font-size:10px"></i> Chưa chia sẻ SĐT</div>
                <?php endif; ?>
              </div>

              <!-- Actions -->
              <div class="cb-row-actions" onclick="event.stopPropagation()">
                <?php if ($email): ?>
                  <a href="mailto:<?= e($email) ?>" class="cb-btn" title="Gửi email">
                    <i class="fas fa-envelope"></i>
                  </a>
                <?php endif; ?>
                <?php if ($phone): ?>
                  <a href="tel:<?= e($phone) ?>" class="cb-btn ph" title="Gọi điện">
                    <i class="fas fa-phone"></i>
                  </a>
                  <a href="https://zalo.me/<?= e($phone) ?>" target="_blank" class="cb-btn zl" title="Zalo">
                    <i class="fas fa-comment-dots"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>

          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="contactModal" style="display:none">
  <div class="modal cm-wrap" style="max-width:380px;padding:0;overflow:hidden" id="contactModalInner"></div>
</div>

<?php
$inlineScript = <<<JS
// === Mở modal chi tiết ===
function openContact(data) {
    if (typeof data === 'string') data = JSON.parse(data);
    const p = data.phone, e = data.email;

    document.getElementById('contactModalInner').innerHTML = \`
        <div class="cm-hero" style="background:linear-gradient(150deg, \${data.color}ee, \${data.color}88)">
            <button class="cm-close" onclick="closeModal('contactModal')">✕</button>
            <div class="cm-hero-content">
                <img src="\${data.avatar}" class="cm-av"
                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=\${encodeURIComponent(data.name)}&background=1a6ef5&color=fff&size=160'">
                <div class="cm-name">\${data.name}</div>
                <div class="cm-sub">\${data.subject} · \${data.dept}</div>
                <div class="cm-tags">
                    \${[data.degree, data.rank, data.role].map(t => \`<span class="cm-tag">\${t}</span>\`).join('')}
                    \${data.years ? \`<span class="cm-tag">\${data.years} năm CT</span>\` : ''}
                </div>
            </div>
        </div>
        <div class="cm-body">
            \${e ? \`
            <div class="cm-contact-item">
                <div class="cm-ci-icon" style="background:var(--primary-light);color:var(--primary)">
                    <i class="fas fa-envelope"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="cm-ci-label">Email</div>
                    <div class="cm-ci-value"><a href="mailto:\${e}" style="color:var(--primary)">\${e}</a></div>
                </div>
            </div>\` : ''}

            \${p ? \`
            <div class="cm-contact-item">
                <div class="cm-ci-icon" style="background:rgba(34,197,94,.1);color:#22c55e">
                    <i class="fas fa-phone"></i>
                </div>
                <div style="flex:1">
                    <div class="cm-ci-label">Điện thoại</div>
                    <div class="cm-ci-value"><a href="tel:\${p}" style="color:#22c55e">\${p}</a></div>
                </div>
            </div>\` : \`
            <div class="cm-contact-item" style="opacity:.6">
                <div class="cm-ci-icon" style="background:var(--bg);color:var(--text-muted)">
                    <i class="fas fa-lock"></i>
                </div>
                <div><div class="cm-ci-label">Điện thoại</div>
                <div class="cm-ci-value" style="color:var(--text-muted);font-size:12px">Chưa chia sẻ</div></div>
            </div>\`}

            <div class="cm-actions">
                \${e ? \`<a href="mailto:\${e}" class="btn btn-primary" style="flex:1;justify-content:center;font-size:13px">
                    <i class="fas fa-envelope"></i> Email</a>\` : ''}
                \${p ? \`
                <a href="tel:\${p}" class="btn btn-outline" style="flex:1;justify-content:center;font-size:13px;border-color:#22c55e;color:#22c55e">
                    <i class="fas fa-phone"></i> Gọi</a>
                <a href="https://zalo.me/\${p}" target="_blank" class="btn btn-outline"
                   style="flex:1;justify-content:center;font-size:13px;border-color:#0068ff;color:#0068ff">
                    <i class="fas fa-comment-dots"></i> Zalo</a>\` : ''}
            </div>
        </div>\`;
    openModal('contactModal');
}

// === Live search ===
const searchInput = document.getElementById('cbSearch');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const kw = this.value.toLowerCase().trim();
        let visible = 0;

        document.querySelectorAll('.cb-row').forEach(row => {
            const n = row.dataset.name    || '';
            const s = row.dataset.subject || '';
            const show = !kw || n.includes(kw) || s.includes(kw);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Ẩn section header nếu không có row nào
        document.querySelectorAll('.cb-section').forEach(sec => {
            let next = sec.nextElementSibling;
            let anyVis = false;
            while (next && !next.classList.contains('cb-section')) {
                if (next.classList.contains('cb-row') && next.style.display !== 'none') anyVis = true;
                next = next.nextElementSibling;
            }
            sec.style.display = anyVis ? '' : 'none';
        });

        const cnt = document.getElementById('cbCountNum');
        if (cnt) cnt.textContent = visible;
    });
    // Focus ngay khi load
    searchInput.focus();
}
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
