<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

if (isset($_GET['department_id'])) {
    $_SESSION['department_id'] = (int)$_GET['department_id'];
}
$department_id = $_SESSION['department_id'] ?? null;

$department = null;
if ($department_id) {
    $stmt = $pdo->prepare(
        "SELECT d.* FROM m_department d
         JOIN m_checksheet_section s ON s.department_id = d.id
         WHERE d.id = ? AND d.is_active = 1 AND s.route = 'bakeoven_list.php' AND s.is_active = 1"
    );
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'bakeoven_list.php';
require_once __DIR__ . '/includes/auth.php';
require_section_access($pdo, (int) $department['id'], 'bakeoven_list.php');

// F-PS-07: fixed check times per day, up to 16:30.
$timeSlots = [
    '0700' => '7:00', '0900' => '9:00', '1100' => '11:00', '1300' => '13:00',
    '1400' => '14:00', '1630' => '16:30',
];
const BAKEOVEN_TEMP_MIN = 160;
const BAKEOVEN_TEMP_MAX = 165;

// Checked By entries scoped strictly to this section.
$stmt = $pdo->prepare(
    "SELECT c.* FROM m_checker c
     WHERE c.department_id = ? AND c.is_active = 1
       AND c.section_id = (SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = 'bakeoven_list.php')
     ORDER BY c.name"
);
$stmt->execute([$department['id'], $department['id']]);
$allCheckers = $stmt->fetchAll();
$asstForemen = array_values(array_filter($allCheckers, fn($c) => $c['role'] === 'asst_foreman'));
$foremenList = array_values(array_filter($allCheckers, fn($c) => $c['role'] === 'foreman'));
$supervisorsList = array_values(array_filter($allCheckers, fn($c) => $c['role'] === 'supervisor'));

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

$stmt = $pdo->prepare('SELECT * FROM t_bakeoven_entry WHERE department_id = ? AND tanggal BETWEEN ? AND ?');
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt->execute([$department['id'], $monthStart, $monthEnd]);
$entries = [];
foreach ($stmt->fetchAll() as $e) {
    $entries[(int)date('j', strtotime($e['tanggal']))] = $e;
}

$stmt = $pdo->prepare('SELECT * FROM t_bakeoven_month WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department['id'], $month, $year]);
$monthRow = $stmt->fetch();

$base_url = '';
$active_nav = 'checksheet';
$page_title = 'Production Check Sheet - Bake Oven Temperature';
$page_subtitle = $department['name'] . ' · Standard 160&deg;C ~ 165&deg;C';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'bakeoven_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <form method="get" class="form-grid-top" style="margin-bottom:14px;">
        <input type="hidden" name="department_id" value="<?= $department['id'] ?>">
        <div class="field-block">
            <label>Month</label>
            <select name="month" onchange="this.form.submit()">
                <?php
                $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach ($monthNames as $i => $mName): ?>
                    <option value="<?= $i + 1 ?>" <?= ($i + 1) == $month ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Year</label>
            <select name="year" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Standard</label>
            <div style="padding:10px 0;font-weight:600;">160&deg;C ~ 165&deg;C</div>
        </div>
    </form>

    <div class="table-wrap">
        <table id="bakeoven-table" class="bakeoven-table">
            <thead>
                <tr>
                    <th>Waktu Pengecekan</th>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <th><?= $day ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $today = date('Y-m-d');
                foreach ($timeSlots as $key => $label): ?>
                <tr>
                    <td class="row-label"><?= $label ?></td>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $e = $entries[$day] ?? null;
                        $val = $e["t_$key"] ?? '';
                        $rowDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dis = $rowDate > $today ? 'disabled' : '';
                    ?>
                        <td>
                            <input type="number" step="0.1" class="temp-input" inputmode="decimal"
                                   data-day="<?= $day ?>" data-field="t_<?= $key ?>"
                                   value="<?= htmlspecialchars($val) ?>" <?= $dis ?>>
                        </td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="paraf-row">
                    <td class="row-label">Paraf</td>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $e = $entries[$day] ?? null;
                        $rowDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dis = $rowDate > $today ? 'disabled' : '';
                    ?>
                        <td>
                            <select class="w-select" data-day="<?= $day ?>" data-field="checker_id" <?= $dis ?>>
                                <option value="">-</option>
                                <?php foreach ($allCheckers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($e['checker_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    <?php endfor; ?>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="bakeoven-footer">
        <div class="field-block" style="flex:1;min-width:220px;">
            <label>Keterangan</label>
            <textarea id="f_keterangan" rows="2" style="width:100%;"><?= htmlspecialchars($monthRow['keterangan'] ?? '') ?></textarea>
        </div>
        <div class="field-block">
            <label>Asst. Foreman</label>
            <select id="f_asst_foreman">
                <option value="">-</option>
                <?php foreach ($asstForemen as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($monthRow['asst_foreman_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <select id="f_foreman">
                <option value="">-</option>
                <?php foreach ($foremenList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($monthRow['foreman_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <select id="f_supervisor">
                <option value="">-</option>
                <?php foreach ($supervisorsList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($monthRow['supervisor_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-submit" id="btn-submit">Save Entries</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const MONTH = <?= json_encode($month) ?>;
    const YEAR = <?= json_encode($year) ?>;
    const DAYS_IN_MONTH = <?= json_encode($daysInMonth) ?>;
    const TIME_KEYS = <?= json_encode(array_keys($timeSlots)) ?>;
    const TEMP_MIN = <?= json_encode(BAKEOVEN_TEMP_MIN) ?>;
    const TEMP_MAX = <?= json_encode(BAKEOVEN_TEMP_MAX) ?>;
</script>
<script src="assets/js/bakeoven.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
