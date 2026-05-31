<?php
/**
 * reports.php — Báo cáo thống kê
 */
require_once __DIR__ . '/_path.php';

// Chỉ BGH và Tổ trưởng
$currentUser = $auth->requireRole(['admin', 'dept_head']);

$teachers         = $db->read('teachers');
$departments      = $db->read('departments');
$notifs           = $db->where('notifications', 'user_id', $currentUser['id']);
$unreadNotifCount = count(array_filter($notifs, fn($n) => !($n['is_read'] ?? false)));
$openRequestCount = $db->count('requests', ['status' => 'pending']);

// Tổ trưởng chỉ thấy tổ mình
if ($currentUser['role'] === 'dept_head') {
    $teachers = array_filter($teachers,
        fn($t) => ($t['work']['dept_id'] ?? '') === $currentUser['dept_id']
    );
    $teachers     = array_values($teachers);
    $departments  = array_filter($departments, fn($d) => $d['id'] === $currentUser['dept_id']);
    $departments  = array_values($departments);
}

// ---- Tính thống kê ----
$total       = count($teachers);
$masters     = count(array_filter($teachers, fn($t) => ($t['education']['degree'] ?? '') === 'Thạc sĩ'));
$bachelor    = count(array_filter($teachers, fn($t) => ($t['education']['degree'] ?? '') === 'Đại học'));
$phd         = count(array_filter($teachers, fn($t) => ($t['education']['degree'] ?? '') === 'Tiến sĩ'));
$partyMember = count(array_filter($teachers, fn($t) => ($t['work']['party_member'] ?? false)));
$female      = count(array_filter($teachers, fn($t) => ($t['personal']['gender'] ?? '') === 'Nữ'));
$male        = $total - $female;

// Hạng GV
$rank1 = count(array_filter($teachers, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng I'));
$rank2 = count(array_filter($teachers, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng II'));
$rank3 = count(array_filter($teachers, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng III'));

// Độ tuổi
$ageGroups = ['<30'=>0, '30-35'=>0, '36-40'=>0, '41-45'=>0, '>45'=>0];
foreach ($teachers as $t) {
    if (!($t['personal']['dob'] ?? '')) continue;
    $age = (int)date('Y') - (int)date('Y', strtotime($t['personal']['dob']));
    if ($age < 30)       $ageGroups['<30']++;
    elseif ($age <= 35)  $ageGroups['30-35']++;
    elseif ($age <= 40)  $ageGroups['36-40']++;
    elseif ($age <= 45)  $ageGroups['41-45']++;
    else                 $ageGroups['>45']++;
}

// Hoàn thành theo tổ
$deptStats = [];
foreach ($departments as $dept) {
    $dt = array_filter($teachers, fn($t) => ($t['work']['dept_id'] ?? '') === $dept['id']);
    $dt = array_values($dt);
    $cnt = count($dt);
    $cmp = count(array_filter($dt, fn($t) => ($t['status'] ?? '') === 'complete'));
    $ms  = count(array_filter($dt, fn($t) => ($t['education']['degree'] ?? '') === 'Thạc sĩ'));
    $r1  = count(array_filter($dt, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng I'));
    $r2  = count(array_filter($dt, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng II'));
    $r3  = count(array_filter($dt, fn($t) => ($t['work']['rank'] ?? '') === 'GV hạng III'));
    $pv  = count(array_filter($dt, fn($t) => $t['work']['party_member'] ?? false));
    $ach = array_sum(array_map(fn($t) => count($t['achievements'] ?? []), $dt));
    $deptStats[] = [
        'name'     => $dept['name'],
        'color'    => $dept['color'] ?? '#1a6ef5',
        'total'    => $cnt,
        'complete' => $cmp,
        'pct'      => $cnt > 0 ? round($cmp/$cnt*100) : 0,
        'masters'  => $ms,
        'bachelor' => $cnt - $ms,
        'rank1'    => $r1, 'rank2' => $r2, 'rank3' => $r3,
        'party'    => $pv,
        'gdg'      => $ach > 0 ? min($ach, $cnt) : 0,
    ];
}

// Giáo viên tiêu biểu (nhiều thành tích nhất)
usort($teachers, fn($a,$b) =>
    count($b['achievements'] ?? []) - count($a['achievements'] ?? [])
);
$topTeachers = array_slice($teachers, 0, 5);

// JSON cho chart
$chartDeptNames  = json_encode(array_map(fn($d) => str_replace('Tổ ','',$d['name']), $deptStats));
$chartDeptCounts = json_encode(array_column($deptStats, 'total'));
$chartDeptColors = json_encode(array_column($deptStats, 'color'));
$chartDeptPcts   = json_encode(array_column($deptStats, 'pct'));
$chartDegree     = json_encode([$masters, $bachelor, $phd]);
$chartAge        = json_encode(array_values($ageGroups));
$chartRank       = json_encode([$rank1, $rank2, $rank3]);
$chartGender     = json_encode([$male, $female]);
$chartParty      = json_encode([$partyMember, $total - $partyMember]);
$chartStatus     = json_encode([
    count(array_filter($teachers, fn($t) => ($t['status']??'') === 'complete')),
    count(array_filter($teachers, fn($t) => ($t['status']??'') === 'pending')),
    count(array_filter($teachers, fn($t) => ($t['status']??'') === 'processing')),
]);

$pageTitle    = 'Báo cáo thống kê';
$pageSubtitle = 'Phân tích dữ liệu giáo viên toàn trường';
$activePage   = 'reports.php';
$extraScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'];

include TEMPLATES_DIR . '/_head.php';
include TEMPLATES_DIR . '/_sidebar.php';
include TEMPLATES_DIR . '/_header.php';
?>

<!-- Export bar -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding:14px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg)">
  <div style="font-size:13px;color:var(--text-secondary)">
    📊 Báo cáo năm học <strong><?= SCHOOL_YEAR ?></strong>
    &nbsp;·&nbsp; Cập nhật: <strong><?= date('d/m/Y H:i') ?></strong>
    &nbsp;·&nbsp; <strong><?= $total ?> giáo viên</strong>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-outline btn-sm" onclick="showToast('success','Xuất Excel','Đang tạo file Excel...')">
      <i class="fas fa-file-excel" style="color:#10b981"></i> Excel
    </button>
    <button class="btn btn-outline btn-sm" onclick="showToast('success','Xuất PDF','Đang tạo file PDF...')">
      <i class="fas fa-file-pdf" style="color:#ef4444"></i> PDF
    </button>
    <button class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="fas fa-print"></i> In
    </button>
  </div>
</div>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
  <?php foreach ([
    ['Tổng giáo viên', $total,       '#1a6ef5', 'fa-chalkboard-teacher', 'var(--primary-light)'],
    ['Có bằng Thạc sĩ',$masters,     '#8b5cf6', 'fa-graduation-cap',     '#f3e8ff'],
    ['Đảng viên',      $partyMember, '#10b981', 'fa-medal',              'var(--accent-light)'],
    ['GV hạng I',      $rank1,       '#f59e0b', 'fa-star',               'var(--warning-light)'],
  ] as [$label, $val, $color, $icon, $bg]): ?>
    <div class="stat-card" style="--card-accent:<?= $color ?>;--card-accent-light:<?= $bg ?>">
      <div class="stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>">
        <i class="fas <?= $icon ?>"></i>
      </div>
      <div class="stat-info">
        <div class="stat-label"><?= $label ?></div>
        <div class="stat-value"><?= $val ?></div>
        <div class="stat-change up"><?= $total > 0 ? round($val/$total*100) : 0 ?>% tổng số GV</div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Row 1: Dept bar + Degree doughnut -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px" class="charts-row">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Giáo viên theo tổ chuyên môn</div><div class="card-subtitle">Phân bố số lượng</div></div>
    </div>
    <div class="card-body">
      <div style="position:relative;width:100%;height:240px"><canvas id="cDept"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Trình độ đào tạo</div><div class="card-subtitle">Tỉ lệ bằng cấp</div></div>
    </div>
    <div class="card-body">
      <div style="position:relative;width:100%;height:180px"><canvas id="cDegree"></canvas></div>
      <div style="display:flex;flex-direction:column;gap:9px;margin-top:14px">
        <?php foreach ([['Thạc sĩ',$masters,'#1a6ef5'],['Đại học',$bachelor,'#10b981'],['Tiến sĩ',$phd,'#8b5cf6']] as [$l,$v,$c]): ?>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;align-items:center;gap:8px;font-size:12.5px">
              <div style="width:10px;height:10px;border-radius:50%;background:<?= $c ?>"></div>
              <span><?= $l ?></span>
            </div>
            <strong style="font-size:13px"><?= $v ?> GV (<?= $total > 0 ? round($v/$total*100) : 0 ?>%)</strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Row 2: Age + Rank + Gender/Party -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px" class="charts-row">
  <div class="card">
    <div class="card-header"><div><div class="card-title">Phân bố độ tuổi</div></div></div>
    <div class="card-body">
      <div style="position:relative;width:100%;height:200px"><canvas id="cAge"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><div class="card-title">Hạng giáo viên</div></div></div>
    <div class="card-body">
      <div style="position:relative;width:100%;height:200px"><canvas id="cRank"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><div class="card-title">Giới tính &amp; Đảng viên</div></div></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="position:relative;width:100%;height:120px"><canvas id="cGender"></canvas></div>
          <div style="text-align:center;font-size:12px;font-weight:700;margin-top:6px">Giới tính</div>
        </div>
        <div>
          <div style="position:relative;width:100%;height:120px"><canvas id="cParty"></canvas></div>
          <div style="text-align:center;font-size:12px;font-weight:700;margin-top:6px">Đảng viên</div>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:10px">
        <?php foreach ([['#1a6ef5','Nam ('.$male.')'],['#ec4899','Nữ ('.$female.')'],['#22c55e','Đảng viên ('.$partyMember.')'],['var(--border)','Quần chúng ('.($total-$partyMember).')']] as [$c,$l]): ?>
          <div style="display:flex;align-items:center;gap:5px;font-size:11.5px">
            <div style="width:9px;height:9px;border-radius:50%;background:<?= $c ?>"></div>
            <span><?= $l ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Row 3: Completion + Top teachers -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px" class="charts-row">
  <div class="card">
    <div class="card-header"><div><div class="card-title">Tỉ lệ hoàn thành hồ sơ theo tổ</div></div></div>
    <div class="card-body">
      <div style="position:relative;width:100%;height:240px"><canvas id="cCompletion"></canvas></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><div class="card-title">Giáo viên tiêu biểu</div><div class="card-subtitle">Thành tích nổi bật năm học <?= SCHOOL_YEAR ?></div></div></div>
    <div class="card-body">
      <?php foreach ($topTeachers as $i => $t):
        $medals = ['g','s','b','',''];
        $mColors= ['#fef3c7','#f1f5f9','#ffedd5','var(--bg)','var(--bg)'];
        $mText  = ['#92400e','#475569','#78350f','var(--text-muted)','var(--text-muted)'];
        $name   = $t['personal']['full_name'] ?? '—';
        $avatar = $t['avatar_url'] ?? 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=1a6ef5&color=fff&size=80';
        $ach    = $t['achievements'][0]['title'] ?? 'Chưa có thành tích';
        $deptName = '';
        foreach ($departments as $d) {
            if ($d['id'] === ($t['work']['dept_id'] ?? '')) { $deptName = str_replace('Tổ ','',$d['name']); break; }
        }
      ?>
        <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border-light)">
          <div style="width:24px;height:24px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;background:<?= $mColors[$i] ?>;color:<?= $mText[$i] ?>;flex-shrink:0">
            <?= $i+1 ?>
          </div>
          <img src="<?= e($avatar) ?>"
               style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--border)"
               onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($name) ?>&background=1a6ef5&color=fff&size=80'">
          <div style="flex:1;min-width:0">
            <div style="font-size:13.5px;font-weight:700"><?= e($name) ?></div>
            <div style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($ach) ?></div>
          </div>
          <span class="badge badge-primary" style="font-size:10.5px;white-space:nowrap"><?= e($deptName) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Detail table -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div><div class="card-title">Bảng tổng hợp theo tổ chuyên môn</div></div>
    <button class="btn btn-outline btn-sm" onclick="showToast('success','Xuất','Đang tạo file...')">
      <i class="fas fa-download"></i> Xuất
    </button>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tổ chuyên môn</th><th>Số GV</th><th>Thạc sĩ</th><th>Đại học</th>
          <th>Hạng I</th><th>Hạng II</th><th>Hạng III</th>
          <th>Đảng viên</th><th>Thành tích</th><th>Hồ sơ HT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deptStats as $row): ?>
          <tr>
            <td><strong><?= e($row['name']) ?></strong></td>
            <td><span class="badge badge-primary"><?= $row['total'] ?></span></td>
            <td><?= $row['masters'] ?></td>
            <td><?= $row['bachelor'] ?></td>
            <td><?= $row['rank1'] ?></td>
            <td><?= $row['rank2'] ?></td>
            <td><?= $row['rank3'] ?></td>
            <td><?= $row['party'] ?></td>
            <td><?= $row['gdg'] ?></td>
            <td>
              <span class="badge <?= $row['pct']===100?'badge-success':($row['pct']>=85?'badge-primary':'badge-warning') ?>">
                <?= $row['pct'] ?>%
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--primary-light);font-weight:800">
          <td style="padding:12px 16px">Tổng cộng</td>
          <td style="padding:12px 16px"><?= $total ?></td>
          <td style="padding:12px 16px"><?= $masters ?></td>
          <td style="padding:12px 16px"><?= $bachelor ?></td>
          <td style="padding:12px 16px"><?= $rank1 ?></td>
          <td style="padding:12px 16px"><?= $rank2 ?></td>
          <td style="padding:12px 16px"><?= $rank3 ?></td>
          <td style="padding:12px 16px"><?= $partyMember ?></td>
          <td style="padding:12px 16px"><?= array_sum(array_column($deptStats,'gdg')) ?></td>
          <td style="padding:12px 16px">
            <span class="badge badge-success">
              <?= $total > 0 ? round(array_sum(array_column($deptStats,'complete'))/$total*100) : 0 ?>%
            </span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php
$inlineScript = <<<JS
(function(){
  const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
  const gc = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
  const tc = () => isDark() ? '#94a3b8' : '#64748b';
  const CF = { family: 'Be Vietnam Pro', size: 12 };

  const deptNames  = $chartDeptNames;
  const deptCounts = $chartDeptCounts;
  const deptColors = $chartDeptColors;
  const deptPcts   = $chartDeptPcts;
  const degreeData = $chartDegree;
  const ageData    = $chartAge;
  const rankData   = $chartRank;
  const genderData = $chartGender;
  const partyData  = $chartParty;

  const charts = {};

  function mk(id, config) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(document.getElementById(id), config);
  }

  function build() {
    // Dept bar
    mk('cDept', { type:'bar', data:{ labels:deptNames, datasets:[{ data:deptCounts, backgroundColor:deptColors, borderRadius:8, borderSkipped:false }] },
      options:{ responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}},
        scales:{ x:{grid:{display:false},ticks:{color:tc(),font:CF}}, y:{grid:{color:gc()},ticks:{color:tc(),font:CF},border:{display:false},min:0} } } });

    // Degree doughnut
    mk('cDegree', { type:'doughnut', data:{ datasets:[{ data:degreeData, backgroundColor:['#1a6ef5','#10b981','#8b5cf6'], borderWidth:0, hoverOffset:5 }] },
      options:{ cutout:'68%', responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}} } });

    // Age bar
    mk('cAge', { type:'bar', data:{ labels:['<30','30-35','36-40','41-45','>45'], datasets:[{ data:ageData, backgroundColor:'#1a6ef5', borderRadius:6, borderSkipped:false }] },
      options:{ responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}},
        scales:{ x:{grid:{display:false},ticks:{color:tc(),font:CF}}, y:{grid:{color:gc()},ticks:{color:tc(),font:CF},border:{display:false}} } } });

    // Rank horizontal bar
    mk('cRank', { type:'bar', data:{ labels:['Hạng I','Hạng II','Hạng III'], datasets:[{ data:rankData, backgroundColor:['#f59e0b','#1a6ef5','#10b981'], borderRadius:6, borderSkipped:false }] },
      options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}},
        scales:{ x:{grid:{color:gc()},ticks:{color:tc(),font:CF},border:{display:false}}, y:{grid:{display:false},ticks:{color:tc(),font:CF}} } } });

    // Gender doughnut
    mk('cGender', { type:'doughnut', data:{ datasets:[{ data:genderData, backgroundColor:['#1a6ef5','#ec4899'], borderWidth:0 }] },
      options:{ cutout:'62%', responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}} } });

    // Party doughnut
    mk('cParty', { type:'doughnut', data:{ datasets:[{ data:partyData, backgroundColor:['#22c55e', isDark()?'rgba(255,255,255,0.1)':'#e2e8f0'], borderWidth:0 }] },
      options:{ cutout:'62%', responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}} } });

    // Completion horizontal
    mk('cCompletion', { type:'bar', data:{ labels:deptNames, datasets:[{ data:deptPcts, backgroundColor:deptColors, borderRadius:6, borderSkipped:false }] },
      options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, animation:{duration:500}, plugins:{legend:{display:false}},
        scales:{ x:{grid:{color:gc()},ticks:{color:tc(),font:CF,callback:v=>v+'%'},border:{display:false},max:100},
                 y:{grid:{display:false},ticks:{color:tc(),font:CF}} } } });
  }

  requestAnimationFrame(() => setTimeout(build, 80));
  document.addEventListener('click', e => { if(e.target.closest('.btn-theme')) setTimeout(build,380); });
})();
JS;

include TEMPLATES_DIR . '/_footer.php';
?>
