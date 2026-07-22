<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$departments = $pdo->query('SELECT * FROM m_department ORDER BY sort_order, id')->fetchAll();

$selected_department_id = (int)($_GET['department_id'] ?? ($_SESSION['department_id'] ?? 0));

if ($selected_department_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? ORDER BY sort_order, id');
    $stmt->execute([$selected_department_id]);
} else {
    $stmt = $pdo->query('SELECT * FROM m_condition ORDER BY sort_order, id');
}
$conditions = $stmt->fetchAll();

$selected_condition_id = (int)($_GET['condition_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = ["h.status = 'submitted'", 'h.tanggal BETWEEN ? AND ?'];
$params = [$date_from, $date_to];

if ($selected_department_id) {
    $where[] = 'h.department_id = ?';
    $params[] = $selected_department_id;
}
if ($selected_condition_id) {
    $where[] = 'h.condition_id = ?';
    $params[] = $selected_condition_id;
}

$sql = 'SELECT h.*, d.name AS department_name, c.name AS condition_name, ck.name AS checker_name, s.name AS shift_name
        FROM t_checksheet_header h
        JOIN m_department d ON d.id = h.department_id
        JOIN m_condition c ON c.id = h.condition_id
        JOIN m_checker ck ON ck.id = h.checker_id
        JOIN m_shift s ON s.id = h.shift_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view submitted checksheet results';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="form-row">
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="form-row">
            <label>Department</label>
            <select name="department_id">
                <option value="0">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Condition</label>
            <select name="condition_id">
                <option value="0">All Conditions</option>
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_condition_id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn">Search</button>
    </div>
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($row['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['department_name']) ?> &middot; <?= htmlspecialchars($row['condition_name']) ?></div>
            <div class="cs-card-meta">Checked by <?= htmlspecialchars($row['checker_name']) ?> &middot; <?= htmlspecialchars($row['shift_name']) ?> &middot; <?= htmlspecialchars(substr($row['jam'], 0, 5)) ?></div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="view_checksheet_detail.php?id=<?= $row['id'] ?>" class="cs-view-btn">View &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No checksheets found for this date range / filter.</div><?php endif; ?>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
