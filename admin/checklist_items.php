<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$departments = $pdo->query('SELECT * FROM m_department ORDER BY sort_order, id')->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $condition_id = (int)($_POST['condition_id'] ?? 0);
    $checking_item = trim($_POST['checking_item'] ?? '');
    $metode_pengecekan = trim($_POST['metode_pengecekan'] ?? '');
    $standard_min = trim($_POST['standard_min'] ?? '');
    $standard_max = trim($_POST['standard_max'] ?? '');
    $tank_tube = trim($_POST['tank_tube'] ?? '') ?: '-';
    $satuan = trim($_POST['satuan'] ?? '') ?: '-';
    $actual_input_type = $_POST['actual_input_type'] ?? 'number';
    $actual_options = trim($_POST['actual_options'] ?? '');
    $category_options = trim($_POST['category_options'] ?? '') ?: 'OK,NG';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($checking_item === '' || !$condition_id) {
        $error = 'Condition and Checking Item are required.';
    } else {
        $params = [
            $condition_id, $checking_item, $metode_pengecekan ?: '-', $standard_min ?: null, $standard_max ?: null,
            $tank_tube, $satuan, $actual_input_type, $actual_options ?: null, $category_options, $sort_order,
        ];

        if ($id !== '') {
            $stmt = $pdo->prepare(
                'UPDATE m_checklist_item SET condition_id=?, checking_item=?, metode_pengecekan=?, standard_min=?,
                 standard_max=?, tank_tube=?, satuan=?, actual_input_type=?, actual_options=?, category_options=?, sort_order=?
                 WHERE id=?'
            );
            $stmt->execute(array_merge($params, [(int)$id]));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO m_checklist_item
                 (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan,
                  actual_input_type, actual_options, category_options, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute($params);
        }

        $stmt2 = $pdo->prepare('SELECT department_id FROM m_condition WHERE id = ?');
        $stmt2->execute([$condition_id]);
        $redirectDepartment = $stmt2->fetchColumn();
        header("Location: checklist_items.php?department_id={$redirectDepartment}&condition_id={$condition_id}&saved=1");
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_checklist_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: checklist_items.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&condition_id=' . (int)($_GET['condition_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_checklist_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: checklist_items.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&condition_id=' . (int)($_GET['condition_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this item already has checksheet data. Deactivate it instead.';
    }
}

$selected_department_id = (int)($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_checklist_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $stmt2 = $pdo->prepare('SELECT department_id FROM m_condition WHERE id = ?');
        $stmt2->execute([$editRow['condition_id']]);
        $selected_department_id = (int)$stmt2->fetchColumn();
    }
}

$stmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_department_id]);
$conditions = $stmt->fetchAll();

$selected_condition_id = (int)($_GET['condition_id'] ?? ($editRow['condition_id'] ?? ($conditions[0]['id'] ?? 0)));

$stmt = $pdo->prepare('SELECT * FROM m_checklist_item WHERE condition_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_condition_id]);
$items = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-checklist';
$page_title = 'Checking Item';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="get" class="filter-form">
    <label>Department:</label>
    <select name="department_id" onchange="this.form.submit()">
        <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Condition:</label>
    <select name="condition_id" onchange="this.form.submit()">
        <?php foreach ($conditions as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_condition_id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<form method="post" class="admin-form checklist-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Condition</label>
            <select name="condition_id" required>
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == ($editRow['condition_id'] ?? $selected_condition_id) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Adding for department: <?= htmlspecialchars($departments[array_search($selected_department_id, array_column($departments, 'id'))]['name'] ?? '') ?>. Change the Department filter above to pick a condition from another department.</small>
        </div>

        <div class="form-row">
            <label>Checking Item</label>
            <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Checking Method</label>
            <input type="text" name="metode_pengecekan" value="<?= htmlspecialchars($editRow['metode_pengecekan'] ?? 'Visual') ?>">
        </div>

        <div class="form-row">
            <label>Standard Min.</label>
            <input type="text" name="standard_min" value="<?= htmlspecialchars($editRow['standard_min'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Standard Max.</label>
            <input type="text" name="standard_max" value="<?= htmlspecialchars($editRow['standard_max'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Tank/Tube</label>
            <input type="text" name="tank_tube" value="<?= htmlspecialchars($editRow['tank_tube'] ?? '-') ?>">
        </div>

        <div class="form-row">
            <label>Unit</label>
            <input type="text" name="satuan" value="<?= htmlspecialchars($editRow['satuan'] ?? '-') ?>">
        </div>

        <div class="form-row">
            <label>Actual Result Input Type</label>
            <select name="actual_input_type">
                <?php $curType = $editRow['actual_input_type'] ?? 'number'; ?>
                <option value="number" <?= $curType === 'number' ? 'selected' : '' ?>>Number</option>
                <option value="text" <?= $curType === 'text' ? 'selected' : '' ?>>Free Text</option>
                <option value="select" <?= $curType === 'select' ? 'selected' : '' ?>>Dropdown</option>
            </select>
        </div>

        <div class="form-row">
            <label>Dropdown Options (comma separated)</label>
            <input type="text" name="actual_options" placeholder="e.g. Not Leaking,Leaking" value="<?= htmlspecialchars($editRow['actual_options'] ?? '') ?>">
        </div>

        <div class="form-row">
            <label>Category Options (comma separated)</label>
            <input type="text" name="category_options" value="<?= htmlspecialchars($editRow['category_options'] ?? 'OK,NG') ?>">
        </div>

        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($items) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Checking Item</th>
            <th>Method</th>
            <th>Min</th>
            <th>Max</th>
            <th>Tank/Tube</th>
            <th>Unit</th>
            <th>Input</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['checking_item']) ?></td>
            <td><?= htmlspecialchars($item['metode_pengecekan']) ?></td>
            <td><?= htmlspecialchars($item['standard_min'] ?? '-') ?></td>
            <td><?= htmlspecialchars($item['standard_max'] ?? '-') ?></td>
            <td><?= htmlspecialchars($item['tank_tube']) ?></td>
            <td><?= htmlspecialchars($item['satuan']) ?></td>
            <td><?= htmlspecialchars($item['actual_input_type']) ?></td>
            <td><?= $item['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=edit&id=<?= $item['id'] ?>">Edit</a>
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=toggle&id=<?= $item['id'] ?>"><?= $item['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=delete&id=<?= $item['id'] ?>" onclick="return confirm('Delete this item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="9" class="empty">No checking items for this condition yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
