<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, d.name AS department_name, fm.name AS foreman_name, sv.name AS supervisor_name
     FROM t_fopump_header h
     JOIN m_department d ON d.id = h.department_id
     LEFT JOIN m_checker fm ON fm.id = h.foreman_id
     LEFT JOIN m_checker sv ON sv.id = h.supervisor_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    header('Location: view_fopump_checksheets.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM t_fopump_detail WHERE header_id = ? ORDER BY row_no');
$stmt->execute([$id]);
$details = [];
foreach ($stmt->fetchAll() as $d) { $details[(int)$d['row_no']] = $d; }

$num = fn($v) => is_numeric(trim((string)$v)) ? (float)$v : 0;
$fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0';
$totalProd = $totalAssy = $totalExport = 0;
foreach ($details as $d) {
    $totalProd += $num($d['prod_qty']);
    $totalAssy += $num($d['assy_qty']);
    $totalExport += $num($d['export_qty']);
}

$rowCount = 12;

// Only show as many NO rows as the data actually needs, instead of always
// rendering all 12 — this also keeps the table short enough to avoid an
// inner scrollbar in the common case.
$lastFilledRow = 0;
foreach ($details as $rowNo => $d) {
    $hasValue = trim((string)($d['prod_model'] ?? '')) !== '' || trim((string)($d['prod_qty'] ?? '')) !== ''
        || trim((string)($d['assy_model'] ?? '')) !== '' || trim((string)($d['assy_qty'] ?? '')) !== ''
        || trim((string)($d['export_model'] ?? '')) !== '' || trim((string)($d['export_qty'] ?? '')) !== '';
    if ($hasValue) $lastFilledRow = max($lastFilledRow, $rowNo);
}
$displayRowCount = min($rowCount, max(1, $lastFilledRow));

$backHref = 'view_fopump_checksheets.php' . (isset($_GET['back']) && $_GET['back'] !== '' ? '?' . $_GET['back'] : '');
$backQuery = isset($_GET['back']) && $_GET['back'] !== '' ? '&back=' . $_GET['back'] : '';

// Next report for the same department, by date (falls back to id for same-date reports).
$stmt = $pdo->prepare(
    'SELECT id FROM t_fopump_header
     WHERE department_id = ? AND (tanggal > ? OR (tanggal = ? AND id > ?))
     ORDER BY tanggal ASC, id ASC LIMIT 1'
);
$stmt->execute([$header['department_id'], $header['tanggal'], $header['tanggal'], $id]);
$nextId = $stmt->fetchColumn();

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'Checksheet Detail';
$page_subtitle = $header['department_name'] . ' · Daily report FO pump assy · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="dept-context">
        <a href="<?= htmlspecialchars($backHref) ?>" class="dept-switch-link">&larr; Back to list</a>
        <?php if ($nextId): ?>
            <a href="view_fopump_checksheet_detail.php?id=<?= (int) $nextId . $backQuery ?>" class="dept-switch-link dept-switch-link-next">Next &rarr;</a>
        <?php else: ?>
            <span class="dept-switch-link dept-switch-link-disabled dept-switch-link-next">Next &rarr;</span>
        <?php endif; ?>
    </div>

    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div>
        </div>
        <div class="field-block">
            <label>Employee</label>
            <div class="static-value"><?= htmlspecialchars($header['employee'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Working Time</label>
            <div class="static-value"><?= htmlspecialchars($header['working_time'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Shift</label>
            <div class="static-value"><?= htmlspecialchars($header['shift'] ?: '-') ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="assy-table fopump-table">
            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th colspan="2">FO Pump Production</th>
                    <th colspan="2">To Assembly Line</th>
                    <th colspan="2">To Sparepart PTC</th>
                </tr>
                <tr>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= $displayRowCount; $i++): $r = $details[$i] ?? null; ?>
                <tr>
                    <td class="row-no"><?= $i ?></td>
                    <td><?= htmlspecialchars($r['prod_model'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['prod_qty'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['assy_model'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['assy_qty'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['export_model'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['export_qty'] ?? '') ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Total</td>
                    <td></td><td class="f-total"><?= htmlspecialchars($fmt($totalProd)) ?></td>
                    <td></td><td class="f-total"><?= htmlspecialchars($fmt($totalAssy)) ?></td>
                    <td></td><td class="f-total"><?= htmlspecialchars($fmt($totalExport)) ?></td>
                </tr>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Convert</td>
                    <td></td><td><?= htmlspecialchars($header['convert_prod'] ?: '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['convert_assy'] ?: '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['convert_export'] ?: '-') ?></td>
                </tr>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Acumulation</td>
                    <td></td><td><?= htmlspecialchars($header['accum_prod'] ?: '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['accum_assy'] ?: '-') ?></td>
                    <td></td><td><?= htmlspecialchars($header['accum_export'] ?: '-') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="form-grid-top" style="margin-top:16px;">
        <div class="field-block">
            <label>Operator</label>
            <div class="static-value"><?= htmlspecialchars($header['operator_name'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <div class="static-value"><?= htmlspecialchars($header['foreman_name'] ?: '-') ?></div>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <div class="static-value"><?= htmlspecialchars($header['supervisor_name'] ?: '-') ?></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
