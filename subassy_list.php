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
         WHERE d.id = ? AND d.is_active = 1 AND s.route = 'subassy_list.php' AND s.is_active = 1"
    );
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'subassy_list.php';
require_once __DIR__ . '/includes/auth.php';
require_section_access($pdo, (int) $department['id'], 'subassy_list.php');

// Checked By entries scoped strictly to this section — keeps Sub
// Assembly's Operator/Supervisor lists from mixing with Torque/Washing/FO
// Pump Assy's, which share the same department_id. Routing lives entirely
// in admin/users.php now; unassigned m_checker rows are stale leftovers,
// not a fallback to show here.
$stmt = $pdo->prepare(
    "SELECT c.* FROM m_checker c
     WHERE c.department_id = ? AND c.is_active = 1 AND c.role = 'operator'
       AND c.section_id = (SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = 'subassy_list.php')
     ORDER BY c.name"
);
$stmt->execute([$department['id'], $department['id']]);
$operators = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT c.* FROM m_checker c
     WHERE c.department_id = ? AND c.is_active = 1 AND c.role = 'supervisor'
       AND c.section_id = (SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = 'subassy_list.php')
     ORDER BY c.name"
);
$stmt->execute([$department['id'], $department['id']]);
$supervisors = $stmt->fetchAll();

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

$stmt = $pdo->prepare(
    'SELECT * FROM t_subassy_entry WHERE department_id = ? AND tanggal BETWEEN ? AND ?'
);
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt->execute([$department['id'], $monthStart, $monthEnd]);
$entries = [];
foreach ($stmt->fetchAll() as $e) {
    $entries[(int)date('j', strtotime($e['tanggal']))] = $e;
}

$checkCols = [
    'surface_outside' => 'Surface Out Side',
    'parting_line'    => 'Parting Line',
    'surface_upper'   => 'Surface Upper Side',
    'cleanliness'     => 'Cleanliness',
];

$base_url = '';
$active_nav = 'checksheet';
$page_title = 'Production Check Sheet - Sub Assembly';
$page_subtitle = $department['name'] . ' · Jig for guiden assembly oil seal starting shaft';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'subassy_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <?php require_once __DIR__ . '/includes/check_guide.php'; render_check_guide($pdo, 'subassy_list.php'); ?>
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
        <table id="subassy-table" class="washing-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <?php foreach ($checkCols as $label): ?>
                        <th><?= htmlspecialchars($label) ?><br><small>V = OK / X = NG</small></th>
                    <?php endforeach; ?>
                    <th>Checker<br><small>Operator</small></th>
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
                    <?php foreach ($checkCols as $field => $label): ?>
                        <td>
                            <select class="w-select" data-day="<?= $day ?>" data-field="<?= $field ?>" <?= $dis ?>>
                                <option value="">-</option>
                                <option value="OK" <?= ($e[$field] ?? '') === 'OK' ? 'selected' : '' ?>>&#10003; OK</option>
                                <option value="NG" <?= ($e[$field] ?? '') === 'NG' ? 'selected' : '' ?>>&#10007; NG</option>
                            </select>
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <select class="w-select" data-day="<?= $day ?>" data-field="checker_id" <?= $dis ?>>
                            <option value="">-</option>
                            <?php foreach ($operators as $c): ?>
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
<script src="assets/js/subassy.js?v=<?= @filemtime(__DIR__ . '/assets/js/subassy.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
