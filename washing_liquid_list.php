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
         WHERE d.id = ? AND d.is_active = 1 AND s.route = 'washing_liquid_list.php' AND s.is_active = 1"
    );
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'washing_liquid_list.php';
require_once __DIR__ . '/includes/auth.php';
require_section_access($pdo, (int) $department['id'], 'washing_liquid_list.php');

// Checked By entries scoped to this section (or still unassigned to any
// section) only — keeps Washing's Foreman/Supervisor lists from mixing
// with Torque/Sub Assembly/FO Pump Assy's, which share the same
// department_id.
$stmt = $pdo->prepare(
    "SELECT c.* FROM m_checker c
     WHERE c.department_id = ? AND c.is_active = 1 AND c.role = 'foreman'
       AND (c.section_id IS NULL OR c.section_id = (SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = 'washing_liquid_list.php'))
     ORDER BY c.name"
);
$stmt->execute([$department['id'], $department['id']]);
$foremen = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT c.* FROM m_checker c
     WHERE c.department_id = ? AND c.is_active = 1 AND c.role = 'supervisor'
       AND (c.section_id IS NULL OR c.section_id = (SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = 'washing_liquid_list.php'))
     ORDER BY c.name"
);
$stmt->execute([$department['id'], $department['id']]);
$supervisors = $stmt->fetchAll();

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

$stmt = $pdo->prepare(
    'SELECT * FROM t_washing_entry WHERE department_id = ? AND tanggal BETWEEN ? AND ?'
);
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt->execute([$department['id'], $monthStart, $monthEnd]);
$entries = [];
foreach ($stmt->fetchAll() as $e) {
    $entries[(int)date('j', strtotime($e['tanggal']))] = $e;
}

$base_url = '';
$active_nav = 'checksheet';
$page_title = 'Production Check Sheet - Washing Machine Liquid Monitoring';
$page_subtitle = $department['name'] . ' · Monthly log';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'washing_liquid_list.php');
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
    </form>

    <div class="table-wrap">
        <table id="washing-table" class="washing-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ganti Air (Kuras)<br><small>YM08 = min 30 ltr</small></th>
                    <th>Temperatur Air (&deg;C)<br><small>std. 50 - 60 &deg;C</small></th>
                    <th>Penambahan Gildaon YM08<br><small>0.5 poin = 4 ltr</small></th>
                    <th>Total Acid<br><small>(3 - 4 point)</small></th>
                    <th>Checker<br><small>Foreman</small></th>
                    <th>Control<br><small>Supervisor</small></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $today = date('Y-m-d');
                for ($day = 1; $day <= $daysInMonth; $day++):
                    $e = $entries[$day] ?? null;
                    $rowDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isLocked = $rowDate > $today;
                    $dis = $isLocked ? 'disabled' : '';
                ?>
                <tr class="<?= $isLocked ? 'row-locked' : '' ?>">
                    <td><?= $day ?><?php if ($isLocked): ?> <span class="lock-icon" title="Locked, future date">&#128274;</span><?php endif; ?></td>
                    <td><input type="text" class="w-input" data-day="<?= $day ?>" data-field="ganti_air" value="<?= htmlspecialchars($e['ganti_air'] ?? '') ?>" <?= $dis ?>></td>
                    <td><input type="text" class="w-input" data-day="<?= $day ?>" data-field="temperatur_air" value="<?= htmlspecialchars($e['temperatur_air'] ?? '') ?>" <?= $dis ?>></td>
                    <td><input type="text" class="w-input" data-day="<?= $day ?>" data-field="penambahan_gildaon" value="<?= htmlspecialchars($e['penambahan_gildaon'] ?? '') ?>" <?= $dis ?>></td>
                    <td><input type="text" class="w-input" data-day="<?= $day ?>" data-field="total_acid" value="<?= htmlspecialchars($e['total_acid'] ?? '') ?>" <?= $dis ?>></td>
                    <td>
                        <select class="w-select" data-day="<?= $day ?>" data-field="checker_id" <?= $dis ?>>
                            <option value="">-</option>
                            <?php foreach ($foremen as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($e['checker_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="w-select" data-day="<?= $day ?>" data-field="supervisor_id" <?= $dis ?>>
                            <option value="">-</option>
                            <?php foreach ($supervisors as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($e['supervisor_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
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
</script>
<script src="assets/js/washing.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
