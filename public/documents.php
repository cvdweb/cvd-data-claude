<?php
/**
 * documents.php — Kho tài liệu
 */
require_once __DIR__ . '/_path.php';

$currentUser      = $auth->requireLogin();
$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Đọc danh sách file từ JSON (hoặc scan thư mục thật)
$documents = $db->read('documents');

// Lọc theo tổ nếu là tổ trưởng
if ($currentUser['role'] === 'dept_head') {
    $documents = array_filter($documents,
        fn($d) => ($d['dept_id'] ?? '') === $currentUser['dept_id']
                  || ($d['dept_id'] ?? '') === 'all'
    );
}

// Filter params
$filterDept   = input('dept');
$filterType   = input('type');
$filterFolder = input('folder');
$q            = input('q');

$filtered = array_values($documents);

if ($filterDept !== '') {
    $filtered = array_filter($filtered, fn($d) => ($d['dept_id'] ?? '') === $filterDept);
}
if ($filterFolder !== '') {
    $filtered = array_filter($filtered, fn($d) => ($d['folder'] ?? '') === $filterFolder);
}
if ($filterType !== '') {
    $filtered = array_filter($filtered, fn($d) => ($d['ext'] ?? '') === strtolower($filterType));
}
if ($q !== '') {
    $kw = mb_strtolower($q);
    $filtered = array_filter($filtered,
        fn($d) => str_contains(mb_strtolower($d['name'] ?? ''), $kw)
    );
}
$filtered = array_values($filtered);

// Tổng dung lượng
$totalSize  = array_sum(array_column($documents, 'size_bytes'));
$totalFiles = count($documents);

// Các folder
$folders = [
    ['name' => 'Phân phối chương trình', 'icon' => '📋', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    ['name' => 'Kế hoạch dạy học',       'icon' => '📚', 'color' => '#1a6ef5', 'bg' => 'rgba(26,110,245,0.1)'],
    ['name' => 'Đề kiểm tra',            'icon' => '📝', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)'],
    ['name' => 'Minh chứng thi đua',     'icon' => '🏆', 'color' => '#8b5cf6', 'bg' => '#f3e8ff'],
    ['name' => 'Văn bản BGD',            'icon' => '📜', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)'],
];

// Đếm file theo folder
$folderCounts = [];
foreach ($folders as $f) {
    $folderCounts[$f['name']] = count(array_filter($documents,
        fn($d) => ($d['folder'] ?? '') === $f['name']
    ));
}

$pageTitle    = 'Kho tài liệu';
$pageSubtitle = 'Lưu trữ và chia sẻ tài liệu tổ chuyên môn';
$activePage   = 'documents.php';

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Layout: sidebar trái + content -->
<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start" class="docs-layout">

  <!-- Sidebar tài liệu -->
  <aside style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;position:sticky;top:calc(var(--header-height) + 24px)">

    <div style="padding:14px 16px 10px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--text-muted);border-bottom:1px solid var(--border-light)">
      Kho tài liệu
    </div>

    <!-- Nav items -->
    <?php
    $navItems = [
      ['',        'fa-home',      'Tất cả',       $totalFiles],
      ['recent',  'fa-clock',     'Gần đây',       min(8, $totalFiles)],
      ['starred', 'fa-star',      'Đã đánh dấu',   3],
    ];
    foreach ($navItems as [$val, $icon, $label, $cnt]):
      $active = $filterFolder === $val && $filterDept === '';
    ?>
      <a href="/documents.php<?= $val ? '?folder=' . $val : '' ?>"
         style="display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:13.5px;font-weight:500;color:<?= $active ? 'var(--primary)' : 'var(--text-secondary)' ?>;background:<?= $active ? 'var(--primary-light)' : 'transparent' ?>;border-left:3px solid <?= $active ? 'var(--primary)' : 'transparent' ?>;text-decoration:none;transition:all .2s">
        <i class="fas <?= $icon ?>" style="width:16px;text-align:center"></i>
        <span style="flex:1"><?= $label ?></span>
        <span style="font-size:10.5px;font-weight:700;background:var(--bg);padding:2px 7px;border-radius:99px;border:1px solid var(--border);color:var(--text-muted)"><?= $cnt ?></span>
      </a>
    <?php endforeach; ?>

    <!-- Theo tổ -->
    <div style="padding:10px 16px 4px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--text-muted);margin-top:8px">
      Theo tổ
    </div>
    <?php foreach ($departments as $dept):
      $dActive = $filterDept === $dept['id'];
      $dCount  = count(array_filter($documents, fn($d) => ($d['dept_id'] ?? '') === $dept['id']));
    ?>
      <a href="/documents.php?dept=<?= e($dept['id']) ?>"
         style="display:flex;align-items:center;gap:10px;padding:9px 14px;font-size:13px;font-weight:500;color:<?= $dActive ? 'var(--primary)' : 'var(--text-secondary)' ?>;background:<?= $dActive ? 'var(--primary-light)' : 'transparent' ?>;border-left:3px solid <?= $dActive ? 'var(--primary)' : 'transparent' ?>;text-decoration:none;transition:all .2s">
        <span style="font-size:14px"><?= $dept['icon'] ?? '📁' ?></span>
        <span style="flex:1"><?= e(str_replace('Tổ ', '', $dept['name'])) ?></span>
        <span style="font-size:10.5px;font-weight:700;background:var(--bg);padding:2px 7px;border-radius:99px;border:1px solid var(--border);color:var(--text-muted)"><?= $dCount ?></span>
      </a>
    <?php endforeach; ?>

    <!-- Dung lượng -->
    <div style="padding:14px 16px;border-top:1px solid var(--border-light);margin-top:8px">
      <div style="display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:5px">
        <span style="font-weight:600"><?= round($totalSize / 1048576, 1) ?> MB đã dùng</span>
        <span style="color:var(--text-muted)">/ 10 GB</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= round($totalSize / 1048576 / 100 * 100, 1) ?>%;background:#1a6ef5;min-width:3px"></div>
      </div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:5px"><?= $totalFiles ?> tài liệu</div>
    </div>
  </aside>

  <!-- Main content -->
  <div>
    <!-- Toolbar -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <form method="GET" action="/documents.php" style="display:contents">
        <?php if ($filterDept): ?>
          <input type="hidden" name="dept" value="<?= e($filterDept) ?>">
        <?php endif; ?>
        <div style="flex:1;min-width:200px;display:flex;align-items:center;gap:8px;background:var(--bg-card);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:9px 14px;transition:all .2s">
          <i class="fas fa-search" style="color:var(--text-muted)"></i>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="Tìm tài liệu..."
                 style="border:none;background:transparent;outline:none;font-size:13.5px;color:var(--text-primary);flex:1;font-family:inherit"
                 onchange="this.form.submit()">
        </div>
        <select name="type" class="form-control" style="width:auto;min-width:110px" onchange="this.form.submit()">
          <option value="">Tất cả loại</option>
          <option value="pdf"  <?= $filterType === 'pdf'  ? 'selected' : '' ?>>PDF</option>
          <option value="docx" <?= $filterType === 'docx' ? 'selected' : '' ?>>Word</option>
          <option value="xlsx" <?= $filterType === 'xlsx' ? 'selected' : '' ?>>Excel</option>
          <option value="jpg"  <?= $filterType === 'jpg'  ? 'selected' : '' ?>>Hình ảnh</option>
        </select>
      </form>

      <div style="display:flex;gap:2px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:3px">
        <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>"
           class="view-btn <?= (input('view') ?: 'grid') === 'grid' ? 'active' : '' ?>" title="Lưới">
          <i class="fas fa-th-large"></i>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'list'])) ?>"
           class="view-btn <?= input('view') === 'list' ? 'active' : '' ?>" title="Danh sách">
          <i class="fas fa-list"></i>
        </a>
      </div>

      <button class="btn btn-primary" data-open-modal="uploadModal">
        <i class="fas fa-cloud-upload-alt"></i> Tải lên
      </button>
      <button class="btn btn-outline" onclick="showToast('info','Thư mục mới','Nhập tên thư mục...')">
        <i class="fas fa-folder-plus"></i>
      </button>
    </div>

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:16px;flex-wrap:wrap">
      <a href="/documents.php" style="color:var(--primary);text-decoration:none">
        <i class="fas fa-home"></i> Kho tài liệu
      </a>
      <?php if ($filterDept): ?>
        <?php $activeDept = array_filter($departments, fn($d) => $d['id'] === $filterDept); ?>
        <?php if ($activeDept): ?>
          <i class="fas fa-chevron-right" style="font-size:10px;color:var(--border)"></i>
          <span style="font-weight:600"><?= e(array_values($activeDept)[0]['name'] ?? '') ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($filterFolder && $filterFolder !== 'recent' && $filterFolder !== 'starred'): ?>
        <i class="fas fa-chevron-right" style="font-size:10px;color:var(--border)"></i>
        <span style="font-weight:600"><?= e($filterFolder) ?></span>
      <?php endif; ?>
    </div>

    <!-- Folders grid (chỉ hiện khi chưa lọc folder cụ thể) -->
    <?php if (!$filterFolder || $filterFolder === ''): ?>
      <div style="margin-bottom:22px">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
          <span>📁 Thư mục (<?= count($folders) ?>)</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px">
          <?php foreach ($folders as $folder): ?>
            <a href="/documents.php?folder=<?= urlencode($folder['name']) ?><?= $filterDept ? '&dept='.$filterDept : '' ?>"
               style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 14px;display:flex;flex-direction:column;align-items:center;gap:10px;text-decoration:none;text-align:center;transition:all .2s;cursor:pointer"
               onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow)';this.style.borderColor='var(--primary)'"
               onmouseleave="this.style.transform='';this.style.boxShadow='';this.style.borderColor='var(--border)'">
              <div style="font-size:38px;filter:drop-shadow(0 2px 4px <?= $folder['color'] ?>44)">
                <?= $folder['icon'] ?>
              </div>
              <div style="font-size:12.5px;font-weight:700;color:var(--text-primary);line-height:1.3">
                <?= e($folder['name']) ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted)">
                <?= $folderCounts[$folder['name']] ?? 0 ?> tài liệu
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Files section -->
    <div>
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
        <span>📄 <?= $filterFolder ? e($filterFolder) : 'Tài liệu gần đây' ?> (<?= count($filtered) ?>)</span>
        <a href="#" onclick="showToast('info','Sắp xếp','Chọn tiêu chí sắp xếp...');return false"
           style="font-size:12px;color:var(--primary);text-decoration:none;display:flex;align-items:center;gap:5px">
          <i class="fas fa-sort"></i> Sắp xếp
        </a>
      </div>

      <?php if (empty($filtered)): ?>
        <div class="empty-state">
          <div class="empty-icon">📂</div>
          <div class="empty-title">
            <?= $q ? 'Không tìm thấy kết quả' : 'Thư mục trống' ?>
          </div>
          <div class="empty-desc">
            <?= $q ? 'Thử từ khóa khác.' : 'Chưa có tài liệu nào trong thư mục này.' ?>
          </div>
          <button class="btn btn-primary" data-open-modal="uploadModal" style="margin-top:12px">
            <i class="fas fa-cloud-upload-alt"></i> Tải lên tài liệu đầu tiên
          </button>
        </div>
      <?php else: ?>
        <div class="card">
          <!-- Table header -->
          <div style="display:grid;grid-template-columns:2fr 1fr 1fr 80px 90px;gap:12px;padding:10px 16px;background:var(--bg);border-bottom:1px solid var(--border-light)">
            <?php foreach (['Tên tài liệu','Tổ','Ngày sửa','Kích thước',''] as $h): ?>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)"><?= $h ?></div>
            <?php endforeach; ?>
          </div>
          <!-- Rows -->
          <?php
          $extColors = [
            'pdf'  => ['#ef4444','rgba(239,68,68,.1)','PDF'],
            'docx' => ['#1a6ef5','rgba(26,110,245,.1)','DOC'],
            'doc'  => ['#1a6ef5','rgba(26,110,245,.1)','DOC'],
            'xlsx' => ['#10b981','rgba(16,185,129,.1)','XLS'],
            'xls'  => ['#10b981','rgba(16,185,129,.1)','XLS'],
            'jpg'  => ['#f59e0b','rgba(245,158,11,.1)','IMG'],
            'jpeg' => ['#f59e0b','rgba(245,158,11,.1)','IMG'],
            'png'  => ['#f59e0b','rgba(245,158,11,.1)','IMG'],
          ];
          foreach ($filtered as $file):
            $ext    = strtolower($file['ext'] ?? 'file');
            [$fc, $fb, $fl] = $extColors[$ext] ?? ['#64748b','var(--bg)','FILE'];
            $deptName = '';
            foreach ($departments as $d) {
                if ($d['id'] === ($file['dept_id'] ?? '')) { $deptName = str_replace('Tổ ','', $d['name']); break; }
            }
          ?>
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 80px 90px;gap:12px;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border-light);cursor:pointer;transition:background .15s"
                 onmouseenter="this.style.background='var(--bg)'"
                 onmouseleave="this.style.background=''"
                 onclick="previewFile('<?= e($file['name']) ?>')">
              <div style="display:flex;align-items:center;gap:10px;min-width:0">
                <div style="width:36px;height:36px;border-radius:8px;background:<?= $fb ?>;color:<?= $fc ?>;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">
                  <?= $fl ?>
                </div>
                <div style="min-width:0">
                  <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= e($file['name']) ?>
                  </div>
                  <div style="font-size:11px;color:var(--text-muted)"><?= e($file['folder'] ?? '') ?></div>
                </div>
              </div>
              <div style="font-size:12.5px;color:var(--text-secondary)"><?= $deptName ?: 'Chung' ?></div>
              <div style="font-size:12.5px;color:var(--text-secondary)"><?= formatDate($file['updated_at'] ?? null, 'd/m/Y') ?></div>
              <div style="font-size:12.5px;color:var(--text-secondary)"><?= formatFileSize($file['size_bytes'] ?? 0) ?></div>
              <div style="display:flex;gap:4px;opacity:0;transition:opacity .15s" class="file-actions">
                <button class="btn-icon" title="Xem"
                        onclick="event.stopPropagation();previewFile('<?= e($file['name']) ?>')">
                  <i class="fas fa-eye" style="font-size:12px"></i>
                </button>
                <button class="btn-icon" title="Tải xuống"
                        onclick="event.stopPropagation();showToast('success','Tải xuống','Đang tải file về máy...')">
                  <i class="fas fa-download" style="font-size:12px"></i>
                </button>
              </div>
            </div>
            <style>.file-actions { opacity:0 } tr:hover .file-actions, div:hover > .file-actions { opacity:1 }</style>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal" style="display:none">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-cloud-upload-alt"></i> Tải tài liệu lên</span>
      <button class="btn-icon" data-close-modal="uploadModal"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="/api/documents/upload.php"
          enctype="multipart/form-data" id="uploadForm">
      <div class="modal-body">
        <!-- Drop zone -->
        <label style="display:block;border:2px dashed var(--border);border-radius:var(--radius-lg);padding:36px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px"
               id="dropZone"
               ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)'"
               ondragleave="this.style.borderColor='';this.style.background=''"
               ondrop="handleDrop(event)">
          <input type="file" name="files[]" id="fileInput" multiple
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                 style="display:none" onchange="showSelectedFiles(this)">
          <i class="fas fa-cloud-upload-alt" style="font-size:36px;color:var(--primary);margin-bottom:12px;display:block"></i>
          <div style="font-size:14px;font-weight:700;margin-bottom:5px">Kéo thả file vào đây</div>
          <div style="font-size:12px;color:var(--text-muted)">PDF, Word, Excel, JPG, PNG · Tối đa 10MB/file</div>
          <div class="btn btn-outline btn-sm" style="margin-top:14px;pointer-events:none">
            <i class="fas fa-folder-open"></i> Chọn file
          </div>
        </label>

        <div id="selectedFiles"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Thư mục đích</label>
            <select name="folder" class="form-control">
              <?php foreach ($folders as $f): ?>
                <option value="<?= e($f['name']) ?>"
                        <?= $filterFolder === $f['name'] ? 'selected' : '' ?>>
                  <?= $f['icon'] ?> <?= e($f['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tổ chuyên môn</label>
            <select name="dept_id" class="form-control">
              <option value="all">Tất cả tổ</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= e($dept['id']) ?>"
                        <?= $filterDept === $dept['id'] ? 'selected' : '' ?>>
                  <?= e($dept['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="uploadModal">Hủy</button>
        <button type="button" class="btn btn-primary" onclick="fakeUpload()">
          <i class="fas fa-upload"></i> Tải lên
        </button>
      </div>
    </form>
  </div>
</div>

<?php
$inlineScript = <<<JS
function previewFile(name) {
    showToast('info', 'Xem trước', 'Đang mở: ' + name);
}

function handleDrop(e) {
    e.preventDefault();
    const zone = document.getElementById('dropZone');
    zone.style.borderColor = '';
    zone.style.background  = '';
    const files = e.dataTransfer.files;
    if (files.length) showToast('info', files.length + ' file nhận được', 'Nhấn Tải lên để tiếp tục.');
}

function showSelectedFiles(input) {
    const container = document.getElementById('selectedFiles');
    if (!input.files.length) { container.innerHTML = ''; return; }
    container.innerHTML = '<div style="margin-bottom:12px">' +
        Array.from(input.files).map(f =>
            '<div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border-light);font-size:13px">' +
            '<i class="fas fa-file" style="color:var(--primary)"></i>' +
            '<span style="flex:1">' + f.name + '</span>' +
            '<span style="color:var(--text-muted);font-size:11.5px">' + Math.round(f.size/1024) + ' KB</span>' +
            '</div>'
        ).join('') + '</div>';
}

function fakeUpload() {
    closeModal('uploadModal');
    showToast('success', 'Tải lên thành công!', 'File đã được lưu vào kho tài liệu.');
    setTimeout(() => location.reload(), 1500);
}

// Hover show file actions
document.querySelectorAll('.card > div[style*="cursor:pointer"]').forEach(row => {
    const actions = row.querySelector('.file-actions');
    if (actions) {
        row.addEventListener('mouseenter', () => actions.style.opacity = '1');
        row.addEventListener('mouseleave', () => actions.style.opacity = '0');
    }
});
JS;

include TEMPLATES_DIR . '/_footer.php';
?>