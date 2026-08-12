<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$departments = $pdo->query(
    "SELECT d.* FROM m_department d
     JOIN m_checksheet_section s ON s.department_id = d.id
     WHERE s.route = 'subassy_list.php' AND s.is_active = 1
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
     FROM t_subassy_entry e
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
$page_subtitle = 'Sub Assembly · Jig for guiden assembly oil seal starting shaft';
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

<?php
$deptName = '';
foreach ($departments as $d) { if ($d['id'] == $selected_department_id) { $deptName = $d['name']; break; } }
$backQuery = $_SERVER['QUERY_STRING'] ?? '';
?>
<div class="cs-card-list">
    <?php foreach ($rows as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($row['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($deptName) ?></div>
            <div class="cs-card-meta">
                Checker <?= htmlspecialchars($row['checker_name'] ?: '-') ?>
                <?php if ($row['updated_at']): ?> &middot; last saved <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['updated_at']))) ?><?php endif; ?>
            </div>
        </div>
        <span class="cs-status cs-status-submitted">Saved</span>
        <a href="view_subassy_checksheet_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty-state">No entries logged for this month yet.</div><?php endif; ?>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
