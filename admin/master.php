<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$types = [
    'department' => ['table' => 'm_department', 'label' => 'Department', 'has_sort' => true],
    'shift'      => ['table' => 'm_shift',      'label' => 'Shift',      'has_sort' => true],
];

$type = $_GET['type'] ?? 'department';
if (!isset($types[$type])) {
    $type = 'department';
}
$cfg = $types[$type];
$table = $cfg['table'];

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Name cannot be empty.';
    } else {
        if ($cfg['has_sort']) {
            if ($id !== '') {
                $stmt = $pdo->prepare("UPDATE {$table} SET name = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $sort_order, (int)$id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO {$table} (name, sort_order) VALUES (?, ?)");
                $stmt->execute([$name, $sort_order]);
            }
        } else {
            if ($id !== '') {
                $stmt = $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
                $stmt->execute([$name, (int)$id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO {$table} (name) VALUES (?)");
                $stmt->execute([$name]);
            }
        }
        header("Location: master.php?type={$type}&saved=1");
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE {$table} SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    header("Location: master.php?type={$type}");
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        header("Location: master.php?type={$type}&deleted=1");
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this data is already used in a checklist item / checksheet. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$orderBy = $cfg['has_sort'] ? 'sort_order, id' : 'name';
$rows = $pdo->query("SELECT * FROM {$table} ORDER BY {$orderBy}")->fetchAll();

$base_url = '../';
$active_nav = 'config-' . $type;
$page_title = $cfg['label'];
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-row">
        <label><?= htmlspecialchars($cfg['label']) ?> Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
    </div>

    <?php if ($cfg['has_sort']): ?>
    <div class="form-row">
        <label>Order</label>
        <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? 0) ?>">
    </div>
    <?php endif; ?>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="master.php?type=<?= $type ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <?php if ($cfg['has_sort']): ?><th>Order</th><?php endif; ?>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <?php if ($cfg['has_sort']): ?><td><?= (int)$row['sort_order'] ?></td><?php endif; ?>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="master.php?type=<?= $type ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="master.php?type=<?= $type ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="master.php?type=<?= $type ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this data?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No data yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
