<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT h.*, v.name AS variant_name, v.std_label_1, v.std_label_2
     FROM t_fpcs_header h LEFT JOIN m_fpcs_variant v ON v.id = h.variant_id
     WHERE h.id = ?"
);
$stmt->execute([$id]);
$header = $stmt->fetch();
if (!$header) {
    header('Location: view_fpcheck_checksheets.php');
    exit;
}

$section_id = (int)$header['section_id'];
$twoStd = !empty($header['std_label_2']);

$points = $pdo->prepare('SELECT * FROM m_fpcs_point WHERE section_id = ? ORDER BY sort_order, id');
$points->execute([$section_id]);
$points = $points->fetchAll();

$std = [];
if ($header['variant_id']) {
    $s = $pdo->prepare('SELECT * FROM m_fpcs_standard WHERE variant_id = ?');
    $s->execute([$header['variant_id']]);
    foreach ($s->fetchAll() as $r) $std[(int)$r['point_id']] = $r;
}

$cols = $pdo->prepare('SELECT * FROM t_fpcs_column WHERE header_id = ? ORDER BY col_index');
$cols->execute([$id]);
$cols = $cols->fetchAll();

$cells = [];
$c = $pdo->prepare('SELECT * FROM t_fpcs_cell WHERE header_id = ?');
$c->execute([$id]);
foreach ($c->fetchAll() as $r) $cells[(int)$r['point_id']][(int)$r['col_index']] = $r['value'];

$back = $_GET['back'] ?? '';
$backHref = 'view_fpcheck_checksheets.php' . ($back ? '?' . htmlspecialchars($back) : '');

$base_url = '';
$active_nav = 'view-checksheets';
$page_title = 'FO Pump Assy Check Sheet';
$page_subtitle = 'F-FIP-01 · ' . date('d/m/Y', strtotime($header['tanggal']));
require __DIR__ . '/includes/app_top.php';
?>

<p style="margin:0 0 14px;"><a href="<?= $backHref ?>" class="dept-switch-link">&larr; Back to list</a></p>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block"><label>Date</label><div class="static-value"><?= htmlspecialchars(date('d/m/Y', strtotime($header['tanggal']))) ?></div></div>
        <div class="field-block"><label>Type</label><div class="static-value"><?= htmlspecialchars($header['variant_name'] ?: '-') ?></div></div>
        <div class="field-block"><label>Model</label><div class="static-value"><?= htmlspecialchars($header['model'] ?: '-') ?></div></div>
        <div class="field-block"><label>P. Code</label><div class="static-value"><?= htmlspecialchars($header['p_code'] ?: '-') ?></div></div>
        <div class="field-block"><label>Part No.</label><div class="static-value"><?= htmlspecialchars($header['part_no'] ?: '-') ?></div></div>
        <div class="field-block"><label>Prod. Date</label><div class="static-value"><?= htmlspecialchars($header['prod_date'] ?: '-') ?></div></div>
        <div class="field-block"><label>Check Method</label><div class="static-value"><?= htmlspecialchars($header['check_method'] ?: '-') ?></div></div>
        <div class="field-block"><label>Checker</label><div class="static-value"><?= htmlspecialchars($header['checker'] ?: '-') ?></div></div>
        <div class="field-block"><label>Foreman</label><div class="static-value"><?= htmlspecialchars($header['foreman'] ?: '-') ?></div></div>
        <div class="field-block"><label>Supervisor</label><div class="static-value"><?= htmlspecialchars($header['supervisor'] ?: '-') ?></div></div>
    </div>

    <div class="table-wrap">
        <table class="assy-table fpcheck-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width:44px;">NO</th>
                    <th rowspan="2" style="min-width:200px;">CHECK POINT</th>
                    <th rowspan="2"><?= htmlspecialchars($header['std_label_1'] ?: 'STANDARD') ?></th>
                    <?php if ($twoStd): ?><th rowspan="2"><?= htmlspecialchars($header['std_label_2']) ?></th><?php endif; ?>
                    <th colspan="<?= max(1, count($cols)) ?>">CHECKING NUMBER</th>
                </tr>
                <tr>
                    <?php foreach ($cols as $col): ?><th style="padding:6px;"><?= htmlspecialchars($col['label'] ?: '-') ?></th><?php endforeach; ?>
                    <?php if (!$cols): ?><th>-</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($points as $p): ?>
                    <?php if ($p['row_type'] === 'group'): ?>
                        <tr class="fp-group"><td class="fp-no"><?= htmlspecialchars($p['no']) ?></td><td class="fp-point"><?= htmlspecialchars($p['check_point']) ?></td><td colspan="<?= ($twoStd ? 2 : 1) + max(1, count($cols)) ?>"></td></tr>
                    <?php else: $s = $std[$p['id']] ?? []; ?>
                        <tr>
                            <td class="fp-no"><?= htmlspecialchars($p['no']) ?></td>
                            <td class="fp-point"><?= htmlspecialchars($p['check_point']) ?></td>
                            <td class="fp-std"><?= htmlspecialchars($s['std_1'] ?? '') ?></td>
                            <?php if ($twoStd): ?><td class="fp-std"><?= htmlspecialchars($s['std_2'] ?? '') ?></td><?php endif; ?>
                            <?php foreach ($cols as $col): ?>
                                <td><?= htmlspecialchars($cells[$p['id']][$col['col_index']] ?? '') ?></td>
                            <?php endforeach; ?>
                            <?php if (!$cols): ?><td></td><?php endif; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>
