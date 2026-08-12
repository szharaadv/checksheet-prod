<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$departments = $pdo->query('SELECT * FROM m_department ORDER BY sort_order, id')->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $department_id = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['foreman', 'supervisor', 'operator'], true) ? $_POST['role'] : null;

    if ($name === '' || !$department_id) {
        $error = 'Department and Name are required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_checker SET department_id = ?, name = ?, role = ? WHERE id = ?');
            $stmt->execute([$department_id, $name, $role, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_checker (department_id, name, role) VALUES (?, ?, ?)');
            $stmt->execute([$department_id, $name, $role]);
        }
        header('Location: checkers.php?department_id=' . $department_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_checker SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: checkers.php?department_id=' . (int)($_GET['department_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_checker WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: checkers.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this name is already used in a checksheet. Deactivate it instead.';
    }
}

$selected_department_id = (int)($_GET['department_id'] ?? ($_SESSION['department_id'] ?? ($departments[0]['id'] ?? 0)));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_checker WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $selected_department_id = (int)$editRow['department_id'];
    }
}

$stmt = $pdo->prepare('SELECT * FROM m_checker WHERE department_id = ? ORDER BY name');
$stmt->execute([$selected_department_id]);
$rows = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-checker';
$page_title = 'Checked By';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="get" class="filter-form">
    <label>Filter Department:</label>
    <select name="department_id" onchange="this.form.submit()">
        <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Department</label>
            <select name="department_id" required>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == ($editRow['department_id'] ?? $selected_department_id) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Role (optional, used by Washing Machine Liquid Monitoring / FO Pump Assy)</label>
            <select name="role">
                <?php $curRole = $editRow['role'] ?? ''; ?>
                <option value="" <?= $curRole === '' ? 'selected' : '' ?>>- (General Checked By)</option>
                <option value="foreman" <?= $curRole === 'foreman' ? 'selected' : '' ?>>Foreman</option>
                <option value="supervisor" <?= $curRole === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                <option value="operator" <?= $curRole === 'operator' ? 'selected' : '' ?>>Operator</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="checkers.php?department_id=<?= $selected_department_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['role'] ? htmlspecialchars(ucfirst($row['role'])) : '-' ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="checkers.php?department_id=<?= $selected_department_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="checkers.php?department_id=<?= $selected_department_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="checkers.php?department_id=<?= $selected_department_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this data?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No data for this department yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
