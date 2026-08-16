<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$departments = $pdo->query(
    "SELECT d.* FROM m_department d
     JOIN m_checksheet_section s ON s.department_id = d.id
     WHERE s.route = 'bakeoven_list.php' AND s.is_active = 1
     GROUP BY d.id
     ORDER BY d.sort_order"
)->fetchAll();

$selected_department_id = (int)($_GET['department_id'] ?? ($_SESSION['department_id'] ?? 0));
if (!in_array($selected_department_id, array_column($departments, 'id'))) {
    $selected_department_id = $departments[0]['id'] ?? 0;
}

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

$timeSlots = [
    '0700' => '7:00', '0900' => '9:00', '1100' => '11:00', '1300' => '13:00',
    '1400' => '14:00', '1630' => '16:30',
];
const BAKEOVEN_VIEW_TEMP_MIN = 160;
const BAKEOVEN_VIEW_TEMP_MAX = 165;

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare('SELECT * FROM t_bakeoven_entry WHERE department_id = ? AND tanggal BETWEEN ? AND ?');
$stmt->execute([$selected_department_id, $monthStart, $monthEnd]);
$entries = [];
foreach ($stmt->fetchAll() as $e) {
    $entries[(int)date('j', strtotime($e['tanggal']))] = $e;
}

$stmt = $pdo->prepare(
    "SELECT m.*, af.name AS asst_foreman_name, fm.name AS foreman_name, sv.name AS supervisor_name
     FROM t_bakeoven_month m
     LEFT JOIN m_checker af ON af.id = m.asst_foreman_id
     LEFT JOIN m_checker fm ON fm.id = m.foreman_id
     LEFT JOIN m_checker sv ON sv.id = m.supervisor_id
     WHERE m.department_id = ? AND m.month = ? AND m.year = ?"
);
$stmt->execute([$selected_department_id, $month, $year]);
$monthRow = $stmt->fetch();

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'View Checksheets';
$page_subtitle = 'Bake Oven Temperature · Standard 160&deg;C ~ 165&deg;C';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Department</label>
            <select name="department_id" onchange="this.form.submit()">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Month</label>
            <select name="month" onchange="this.form.submit()">
                <?php
                $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach ($monthNames as $i => $mName): ?>
                    <option value="<?= $i + 1 ?>" <?= ($i + 1) == $month ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Year</label>
            <select name="year" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<div class="checksheet-card">
    <div class="table-wrap">
        <table class="bakeoven-table">
            <thead>
                <tr>
                    <th class="bo-corner-cell">
                        <span class="bo-corner-text">Waktu<br>Pengecekan</span>
                    </th>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <th><?= $day ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeSlots as $key => $label): ?>
                <tr>
                    <td class="row-label"><?= $label ?></td>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $e = $entries[$day] ?? null;
                        $val = $e["t_$key"] ?? null;
                        $cls = '';
                        if ($val !== null && $val !== '') {
                            $cls = ($val < BAKEOVEN_VIEW_TEMP_MIN || $val > BAKEOVEN_VIEW_TEMP_MAX) ? 'temp-ng' : 'temp-ok';
                        }
                    ?>
                        <td><span class="temp-input <?= $cls ?>" style="display:inline-block;"><?= $val !== null && $val !== '' ? htmlspecialchars($val) : '-' ?></span></td>
                    <?php endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bakeoven-footer">
        <div class="field-block" style="flex:1;min-width:220px;">
            <label>Keterangan</label>
            <div><?= $monthRow && $monthRow['keterangan'] ? htmlspecialchars($monthRow['keterangan']) : '-' ?></div>
        </div>
        <div class="field-block">
            <label>Asst. Foreman</label>
            <div><?= $monthRow && $monthRow['asst_foreman_name'] ? htmlspecialchars($monthRow['asst_foreman_name']) : '-' ?></div>
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <div><?= $monthRow && $monthRow['foreman_name'] ? htmlspecialchars($monthRow['foreman_name']) : '-' ?></div>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <div><?= $monthRow && $monthRow['supervisor_name'] ? htmlspecialchars($monthRow['supervisor_name']) : '-' ?></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
