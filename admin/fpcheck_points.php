<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$section = $pdo->query(
    "SELECT s.id, s.name, s.department_id FROM m_checksheet_section s WHERE s.route = 'fpcheck_list.php' AND s.is_active = 1 LIMIT 1"
)->fetch();
if (!$section) {
    http_response_code(404);
    exit('FO Pump Assy Check Sheet section not found. Run its migration first.');
}
$section_id = (int)$section['id'];
$error = null;

$rowTypes = ['item' => 'Item', 'group' => 'Group header'];
$inputTypes = ['truefalse' => 'TRUE/FALSE', 'okng' => 'OK/NG', 'number' => 'Number', 'text' => 'Text'];

// ---- Save a point (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_point') {
    $pid = (int)($_POST['point_id'] ?? 0);
    $cp = trim($_POST['check_point'] ?? '');
    if ($cp === '') { $error = 'Check point label is required.'; }
    else {
        $data = [
            trim($_POST['no'] ?? '') ?: null, $cp,
            array_key_exists($_POST['row_type'] ?? '', $rowTypes) ? $_POST['row_type'] : 'item',
            array_key_exists($_POST['input_type'] ?? '', $inputTypes) ? $_POST['input_type'] : 'text',
            (int)($_POST['sort_order'] ?? 0),
        ];
        if ($pid) {
            $stmt = $pdo->prepare('UPDATE m_fpcs_point SET no=?, check_point=?, row_type=?, input_type=?, sort_order=? WHERE id=? AND section_id=?');
            $stmt->execute(array_merge($data, [$pid, $section_id]));
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_fpcs_point (no, check_point, row_type, input_type, sort_order, section_id) VALUES (?,?,?,?,?,?)');
            $stmt->execute(array_merge($data, [$section_id]));
        }
        header('Location: fpcheck_points.php?saved=1' . (isset($_POST['variant_id']) ? '&variant_id=' . (int)$_POST['variant_id'] : ''));
        exit;
    }
}

// ---- Delete a point ----
if (($_GET['action'] ?? '') === 'delete_point' && isset($_GET['id'])) {
    $pdo->prepare('DELETE FROM m_fpcs_point WHERE id=? AND section_id=?')->execute([(int)$_GET['id'], $section_id]);
    header('Location: fpcheck_points.php?deleted=1');
    exit;
}

// ---- Save a variant (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_variant') {
    $vid = (int)($_POST['variant_id_edit'] ?? 0);
    $name = trim($_POST['v_name'] ?? '');
    if ($name === '') { $error = 'Variant name is required.'; }
    else {
        $data = [$name, trim($_POST['std_label_1'] ?? '') ?: null, trim($_POST['std_label_2'] ?? '') ?: null, (int)($_POST['v_sort'] ?? 0)];
        if ($vid) {
            $pdo->prepare('UPDATE m_fpcs_variant SET name=?, std_label_1=?, std_label_2=?, sort_order=? WHERE id=? AND section_id=?')
                ->execute(array_merge($data, [$vid, $section_id]));
        } else {
            $pdo->prepare('INSERT INTO m_fpcs_variant (name, std_label_1, std_label_2, sort_order, section_id) VALUES (?,?,?,?,?)')
                ->execute(array_merge($data, [$section_id]));
        }
        header('Location: fpcheck_points.php?saved=1');
        exit;
    }
}

// ---- Delete a variant ----
if (($_GET['action'] ?? '') === 'delete_variant' && isset($_GET['id'])) {
    $pdo->prepare('DELETE FROM m_fpcs_variant WHERE id=? AND section_id=?')->execute([(int)$_GET['id'], $section_id]);
    header('Location: fpcheck_points.php?deleted=1');
    exit;
}

// ---- Save standards for a variant (matrix) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_standards') {
    $vid = (int)($_POST['variant_id'] ?? 0);
    $s1 = $_POST['std_1'] ?? [];
    $s2 = $_POST['std_2'] ?? [];
    if ($vid) {
        $up = $pdo->prepare(
            'INSERT INTO m_fpcs_standard (variant_id, point_id, std_1, std_2) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE std_1=VALUES(std_1), std_2=VALUES(std_2)'
        );
        foreach ($s1 as $pid => $val) {
            $pid = (int)$pid;
            $v1 = trim((string)$val) ?: null;
            $v2 = trim((string)($s2[$pid] ?? '')) ?: null;
            $up->execute([$vid, $pid, $v1, $v2]);
        }
    }
    header('Location: fpcheck_points.php?saved=1&variant_id=' . $vid);
    exit;
}

// ---- Load ----
$variants = $pdo->prepare('SELECT * FROM m_fpcs_variant WHERE section_id=? ORDER BY sort_order, id');
$variants->execute([$section_id]);
$variants = $variants->fetchAll();

$selectedVariantId = (int)($_GET['variant_id'] ?? ($variants[0]['id'] ?? 0));
$selectedVariant = null;
foreach ($variants as $v) if ($v['id'] == $selectedVariantId) $selectedVariant = $v;

$points = $pdo->prepare('SELECT * FROM m_fpcs_point WHERE section_id=? ORDER BY sort_order, id');
$points->execute([$section_id]);
$points = $points->fetchAll();

$std = [];
if ($selectedVariantId) {
    $s = $pdo->prepare('SELECT * FROM m_fpcs_standard WHERE variant_id=?');
    $s->execute([$selectedVariantId]);
    foreach ($s->fetchAll() as $r) $std[(int)$r['point_id']] = $r;
}

$editPoint = null;
if (($_GET['action'] ?? '') === 'edit_point' && isset($_GET['id'])) {
    foreach ($points as $p) if ($p['id'] == (int)$_GET['id']) $editPoint = $p;
}
$editVariant = null;
if (($_GET['action'] ?? '') === 'edit_variant' && isset($_GET['id'])) {
    foreach ($variants as $v) if ($v['id'] == (int)$_GET['id']) $editVariant = $v;
}

$twoStd = $selectedVariant && !empty($selectedVariant['std_label_2']);

$base_url = '../';
$active_nav = 'config-fpcheck';
$page_title = 'FO Pump Check Sheet — Setup';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<h3 style="margin:6px 0 8px;font:600 15px Inter,sans-serif;">Standards per Type</h3>
<form method="get" class="filter-form">
    <label>Type:</label>
    <select name="variant_id" onchange="this.form.submit()">
        <?php foreach ($variants as $v): ?>
            <option value="<?= $v['id'] ?>" <?= $v['id'] == $selectedVariantId ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedVariant): ?>
<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_standards">
    <input type="hidden" name="variant_id" value="<?= $selectedVariantId ?>">
    <div class="table-scroll">
    <table class="admin-table">
        <thead><tr><th>NO</th><th>Check Point</th><th><?= htmlspecialchars($selectedVariant['std_label_1'] ?: 'Standard') ?></th><?php if ($twoStd): ?><th><?= htmlspecialchars($selectedVariant['std_label_2']) ?></th><?php endif; ?></tr></thead>
        <tbody>
            <?php foreach ($points as $p): ?>
                <?php if ($p['row_type'] === 'group'): ?>
                    <tr><td><?= htmlspecialchars($p['no']) ?></td><td colspan="<?= $twoStd ? 3 : 2 ?>" style="text-align:left;font-weight:700;background:#f4f5f7;"><?= htmlspecialchars($p['check_point']) ?></td></tr>
                <?php else: ?>
                    <tr>
                        <td><?= htmlspecialchars($p['no']) ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($p['check_point']) ?></td>
                        <td><input type="text" name="std_1[<?= $p['id'] ?>]" value="<?= htmlspecialchars($std[$p['id']]['std_1'] ?? '') ?>" style="width:100%;"></td>
                        <?php if ($twoStd): ?><td><input type="text" name="std_2[<?= $p['id'] ?>]" value="<?= htmlspecialchars($std[$p['id']]['std_2'] ?? '') ?>" style="width:100%;"></td><?php endif; ?>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="form-row"><button type="submit" class="btn">Save Standards</button></div>
</form>
<?php endif; ?>

<h3 style="margin:26px 0 8px;font:600 15px Inter,sans-serif;"><?= $editPoint ? 'Edit Check Point' : 'Add Check Point' ?></h3>
<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_point">
    <input type="hidden" name="point_id" value="<?= htmlspecialchars($editPoint['id'] ?? '') ?>">
    <input type="hidden" name="variant_id" value="<?= $selectedVariantId ?>">
    <div class="form-grid">
        <div class="form-row"><label>NO</label><input type="text" name="no" value="<?= htmlspecialchars($editPoint['no'] ?? '') ?>"></div>
        <div class="form-row"><label>Check Point</label><input type="text" name="check_point" value="<?= htmlspecialchars($editPoint['check_point'] ?? '') ?>" required></div>
        <div class="form-row"><label>Row Type</label><select name="row_type"><?php foreach ($rowTypes as $k => $l): ?><option value="<?= $k ?>" <?= ($editPoint['row_type'] ?? 'item') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="form-row"><label>Input Type</label><select name="input_type"><?php foreach ($inputTypes as $k => $l): ?><option value="<?= $k ?>" <?= ($editPoint['input_type'] ?? 'text') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="form-row"><label>Order</label><input type="number" name="sort_order" value="<?= htmlspecialchars($editPoint['sort_order'] ?? (count($points) + 1)) ?>"></div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $editPoint ? 'Update' : 'Add' ?></button>
        <?php if ($editPoint): ?><a href="fpcheck_points.php?variant_id=<?= $selectedVariantId ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead><tr><th>NO</th><th>Check Point</th><th>Row</th><th>Input</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
        <?php foreach ($points as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['no']) ?></td>
            <td style="text-align:left;"><?= htmlspecialchars($p['check_point']) ?></td>
            <td><?= htmlspecialchars($rowTypes[$p['row_type']] ?? $p['row_type']) ?></td>
            <td><?= htmlspecialchars($inputTypes[$p['input_type']] ?? $p['input_type']) ?></td>
            <td><?= htmlspecialchars($p['sort_order']) ?></td>
            <td class="row-actions">
                <a href="fpcheck_points.php?variant_id=<?= $selectedVariantId ?>&action=edit_point&id=<?= $p['id'] ?>">Edit</a>
                <a href="fpcheck_points.php?action=delete_point&id=<?= $p['id'] ?>" onclick="return confirm('Delete this check point?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<h3 style="margin:26px 0 8px;font:600 15px Inter,sans-serif;"><?= $editVariant ? 'Edit Type' : 'Add Type' ?></h3>
<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_variant">
    <input type="hidden" name="variant_id_edit" value="<?= htmlspecialchars($editVariant['id'] ?? '') ?>">
    <div class="form-grid">
        <div class="form-row"><label>Name</label><input type="text" name="v_name" value="<?= htmlspecialchars($editVariant['name'] ?? '') ?>" required></div>
        <div class="form-row"><label>Standard column 1 label</label><input type="text" name="std_label_1" value="<?= htmlspecialchars($editVariant['std_label_1'] ?? '') ?>"></div>
        <div class="form-row"><label>Standard column 2 label (optional)</label><input type="text" name="std_label_2" value="<?= htmlspecialchars($editVariant['std_label_2'] ?? '') ?>" placeholder="leave empty for single standard column"></div>
        <div class="form-row"><label>Order</label><input type="number" name="v_sort" value="<?= htmlspecialchars($editVariant['sort_order'] ?? (count($variants) + 1)) ?>"></div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $editVariant ? 'Update' : 'Add' ?></button>
        <?php if ($editVariant): ?><a href="fpcheck_points.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead><tr><th>Name</th><th>Std col 1</th><th>Std col 2</th><th>Order</th><th>Action</th></tr></thead>
    <tbody>
        <?php foreach ($variants as $v): ?>
        <tr>
            <td><?= htmlspecialchars($v['name']) ?></td>
            <td><?= htmlspecialchars($v['std_label_1'] ?: '-') ?></td>
            <td><?= htmlspecialchars($v['std_label_2'] ?: '-') ?></td>
            <td><?= htmlspecialchars($v['sort_order']) ?></td>
            <td class="row-actions">
                <a href="fpcheck_points.php?action=edit_variant&id=<?= $v['id'] ?>">Edit</a>
                <a href="fpcheck_points.php?action=delete_variant&id=<?= $v['id'] ?>" onclick="return confirm('Delete this type and its standards?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
