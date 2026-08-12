<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, d.name AS department_name, ck.name AS checker_name, sv.name AS supervisor_name
     FROM t_washing_entry e
     JOIN m_department d ON d.id = e.department_id
     LEFT JOIN m_checker ck ON ck.id = e.checker_id
     LEFT JOIN m_checker sv ON sv.id = e.supervisor_id
     WHERE e.id = ?'
);
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: view_washing_checksheets.php');
    exit;
}

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'Checksheet Detail';
$page_subtitle = $row['department_name'] . ' · ' . date('d/m/Y', strtotime($row['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="view_washing_checksheets.php" class="dept-switch-link">&larr; Back to list</a>
    </div>

    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="field-block">
            <label>Department</label>
            <div class="static-value"><?= htmlspecialchars($row['department_name']) ?></div>
        </div>
        <div class="field-block">
            <label>Ganti Air (Kuras)</label>
            <div class="static-value"><?= htmlspecialchars($row['ganti_air'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Temperatur Air (&deg;C)</label>
            <div class="static-value"><?= htmlspecialchars($row['temperatur_air'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Penambahan Gildaon YM08</label>
            <div class="static-value"><?= htmlspecialchars($row['penambahan_gildaon'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Total Acid</label>
            <div class="static-value"><?= htmlspecialchars($row['total_acid'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Checker (Foreman)</label>
            <div class="static-value"><?= htmlspecialchars($row['checker_name'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Control (Supervisor)</label>
            <div class="static-value"><?= htmlspecialchars($row['supervisor_name'] ?: '-') ?></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
