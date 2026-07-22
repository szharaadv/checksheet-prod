<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$departments = $pdo->query(
    "SELECT d.* FROM m_department d
     JOIN m_checksheet_section s ON s.department_id = d.id
     WHERE s.route = 'washing_liquid_list.php' AND s.is_active = 1
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
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare(
    "SELECT e.*, ck.name AS checker_name, sv.name AS supervisor_name
     FROM t_washing_entry e
     LEFT JOIN m_checker ck ON ck.id = e.checker_id
     LEFT JOIN m_checker sv ON sv.id = e.supervisor_id
     WHERE e.department_id = ? AND e.tanggal BETWEEN ? AND ?
     ORDER BY e.tanggal"
);
$stmt->execute([$selected_department_id, $monthStart, $monthEnd]);
$rows = $stmt->fetchAll();

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'View Checksheets';
$page_subtitle = 'Washing Machine Liquid Monitoring · Monthly log';
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

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Ganti Air (Kuras)</th>
            <th>Temperatur Air (&deg;C)</th>
            <th>Penambahan Gildaon YM08</th>
            <th>Total Acid</th>
            <th>Checker</th>
            <th>Control</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td>
                <?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))) ?>
                <?php if ($row['updated_at']): ?><br><small style="color:#8b93a1;">Saved <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['updated_at']))) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['ganti_air'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['temperatur_air'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['penambahan_gildaon'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['total_acid'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['checker_name'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['supervisor_name'] ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No entries logged for this month yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
