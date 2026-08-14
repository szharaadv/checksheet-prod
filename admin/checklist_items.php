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

/** Shared field grid for both the inline "Add" form and the "Edit" popup — keeps them in sync. */
function render_checklist_item_fields(?array $row, array $conditions, int $defaultConditionId, int $defaultSortOrder): void
{
    $v = fn($key, $default = '') => htmlspecialchars($row[$key] ?? $default);
    ?>
    <div class="form-grid">
        <div class="form-row">
            <label>Condition</label>
            <select name="condition_id" required>
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == ($row['condition_id'] ?? $defaultConditionId) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Checking Item</label>
            <input type="text" name="checking_item" value="<?= $v('checking_item') ?>" required>
        </div>

        <div class="form-row">
            <label>Checking Method</label>
            <input type="text" name="metode_pengecekan" value="<?= $v('metode_pengecekan', 'Visual') ?>">
        </div>

        <div class="form-row">
            <label>Standard Min.</label>
            <input type="text" name="standard_min" value="<?= $v('standard_min') ?>">
        </div>

        <div class="form-row">
            <label>Standard Max.</label>
            <input type="text" name="standard_max" value="<?= $v('standard_max') ?>">
        </div>

        <div class="form-row">
            <label>Tank/Tube</label>
            <input type="text" name="tank_tube" value="<?= $v('tank_tube', '-') ?>">
        </div>

        <div class="form-row">
            <label>Unit</label>
            <input type="text" name="satuan" value="<?= $v('satuan', '-') ?>">
        </div>

        <div class="form-row">
            <label>Actual Result Input Type</label>
            <select name="actual_input_type">
                <?php $curType = $row['actual_input_type'] ?? 'number'; ?>
                <option value="number" <?= $curType === 'number' ? 'selected' : '' ?>>Number</option>
                <option value="text" <?= $curType === 'text' ? 'selected' : '' ?>>Free Text</option>
                <option value="select" <?= $curType === 'select' ? 'selected' : '' ?>>Dropdown</option>
            </select>
        </div>

        <div class="form-row">
            <label>Dropdown Options (comma separated)</label>
            <input type="text" name="actual_options" placeholder="e.g. Not Leaking,Leaking" value="<?= $v('actual_options') ?>">
        </div>

        <div class="form-row">
            <label>Category Options (comma separated)</label>
            <input type="text" name="category_options" value="<?= $v('category_options', 'OK,NG') ?>">
        </div>

        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= $v('sort_order', (string) $defaultSortOrder) ?>">
        </div>
    </div>
    <?php
}

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

<div class="admin-form checklist-form">
    <h3 class="admin-form-title">Add Checking Item</h3>
    <p class="admin-form-hint">Adding for department: <?= htmlspecialchars($departments[array_search($selected_department_id, array_column($departments, 'id'))]['name'] ?? '') ?>. Change the Department filter above to pick a condition from another department.</p>
    <form method="post">
        <input type="hidden" name="action" value="save">
        <?php render_checklist_item_fields(null, $conditions, $selected_condition_id, count($items) + 1); ?>
        <div class="form-row">
            <button type="submit" class="btn">Add</button>
        </div>
    </form>
</div>

<?php if ($editRow): $cancelHref = "checklist_items.php?department_id=$selected_department_id&condition_id=$selected_condition_id"; ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3>Edit Checking Item</h3>
            <a href="<?= htmlspecialchars($cancelHref) ?>" class="modal-close" aria-label="Close">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id']) ?>">
            <?php render_checklist_item_fields($editRow, $conditions, $selected_condition_id, (int) $editRow['sort_order']); ?>
            <div class="form-row modal-actions">
                <a href="<?= htmlspecialchars($cancelHref) ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn">Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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
