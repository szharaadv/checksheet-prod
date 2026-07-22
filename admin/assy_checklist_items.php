<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$departments = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' ORDER BY sort_order, id")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $model_id = (int)($_POST['model_id'] ?? 0);
    $checking_item = trim($_POST['checking_item'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $standard_min = trim($_POST['standard_min'] ?? '');
    $standard_max = trim($_POST['standard_max'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($checking_item === '' || !$model_id) {
        $error = 'Model and Checking Item are required.';
    } else {
        $params = [$model_id, $checking_item, $standard ?: null, $standard_min ?: null, $standard_max ?: null, $sort_order];

        if ($id !== '') {
            $stmt = $pdo->prepare(
                'UPDATE m_assy_checklist_item SET model_id=?, checking_item=?, standard=?, standard_min=?, standard_max=?, sort_order=?
                 WHERE id=?'
            );
            $stmt->execute(array_merge($params, [(int)$id]));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO m_assy_checklist_item (model_id, checking_item, standard, standard_min, standard_max, sort_order)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute($params);
        }

        $stmt2 = $pdo->prepare('SELECT department_id FROM m_assy_model WHERE id = ?');
        $stmt2->execute([$model_id]);
        $redirectDepartment = $stmt2->fetchColumn();
        header("Location: assy_checklist_items.php?department_id={$redirectDepartment}&model_id={$model_id}&saved=1");
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_assy_checklist_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: assy_checklist_items.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&model_id=' . (int)($_GET['model_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_assy_checklist_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: assy_checklist_items.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&model_id=' . (int)($_GET['model_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this item already has checksheet data. Deactivate it instead.';
    }
}

$selected_department_id = (int)($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_assy_checklist_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $stmt2 = $pdo->prepare('SELECT department_id FROM m_assy_model WHERE id = ?');
        $stmt2->execute([$editRow['model_id']]);
        $selected_department_id = (int)$stmt2->fetchColumn();
    }
}

$stmt = $pdo->prepare('SELECT * FROM m_assy_model WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_department_id]);
$models = $stmt->fetchAll();

$selected_model_id = (int)($_GET['model_id'] ?? ($editRow['model_id'] ?? ($models[0]['id'] ?? 0)));

$stmt = $pdo->prepare('SELECT * FROM m_assy_checklist_item WHERE model_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_model_id]);
$items = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-assy-checklist';
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

    <label>Model:</label>
    <select name="model_id" onchange="this.form.submit()">
        <?php foreach ($models as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $m['id'] == $selected_model_id ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Model</label>
            <select name="model_id" required>
                <?php foreach ($models as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == ($editRow['model_id'] ?? $selected_model_id) ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Checking Item</label>
            <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Standard (description, optional)</label>
            <input type="text" name="standard" placeholder="e.g. M6X20 = 1.1±0.2 Kg.m" value="<?= htmlspecialchars($editRow['standard'] ?? '') ?>">
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
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($items) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="assy_checklist_items.php?department_id=<?= $selected_department_id ?>&model_id=<?= $selected_model_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Checking Item</th>
            <th>Standard</th>
            <th>Min</th>
            <th>Max</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['checking_item']) ?></td>
            <td><?= htmlspecialchars($item['standard'] ?: '-') ?></td>
            <td><?= htmlspecialchars($item['standard_min'] ?? '-') ?></td>
            <td><?= htmlspecialchars($item['standard_max'] ?? '-') ?></td>
            <td><?= $item['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="assy_checklist_items.php?department_id=<?= $selected_department_id ?>&model_id=<?= $selected_model_id ?>&action=edit&id=<?= $item['id'] ?>">Edit</a>
                <a href="assy_checklist_items.php?department_id=<?= $selected_department_id ?>&model_id=<?= $selected_model_id ?>&action=toggle&id=<?= $item['id'] ?>"><?= $item['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="assy_checklist_items.php?department_id=<?= $selected_department_id ?>&model_id=<?= $selected_model_id ?>&action=delete&id=<?= $item['id'] ?>" onclick="return confirm('Delete this item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="empty">No checking items for this model yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
