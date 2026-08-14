<?php
require_once __DIR__ . '/../config/db.php';
$pdo = get_db();

$sections = $pdo->query(
    'SELECT s.*, d.name AS department_name FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order'
)->fetchAll();
$sectionsById = [];
foreach ($sections as $s) {
    $sectionsById[$s['id']] = $s;
}

$roles = $pdo->query('SELECT * FROM m_checker_role WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $section_id = (int)($_POST['section_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = in_array($_POST['role'] ?? '', array_column($roles, 'name'), true) ? $_POST['role'] : null;

    if ($name === '' || !isset($sectionsById[$section_id])) {
        $error = 'Section and Name are required.';
    } else {
        $department_id = (int) $sectionsById[$section_id]['department_id'];
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_checker SET department_id = ?, section_id = ?, name = ?, role = ? WHERE id = ?');
            $stmt->execute([$department_id, $section_id, $name, $role, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_checker (department_id, section_id, name, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$department_id, $section_id, $name, $role]);
        }
        header('Location: checkers.php?section_id=' . $section_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_checker SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: checkers.php?section_id=' . (int)($_GET['section_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_checker WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: checkers.php?section_id=' . (int)($_GET['section_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this name is already used in a checksheet. Deactivate it instead.';
    }
}

$defaultSectionId = $sections[0]['id'] ?? 0;
if (!empty($_SESSION['section_route'])) {
    foreach ($sections as $s) {
        if ($s['route'] === $_SESSION['section_route']) {
            $defaultSectionId = $s['id'];
            break;
        }
    }
}
$selected_section_id = (int)($_GET['section_id'] ?? $defaultSectionId);
if (!isset($sectionsById[$selected_section_id])) {
    $selected_section_id = $defaultSectionId;
}
$selected_department_id = $sectionsById[$selected_section_id]['department_id'] ?? 0;

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_checker WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow && $editRow['section_id']) {
        $selected_section_id = (int)$editRow['section_id'];
    }
}

// Entries with no section assigned yet still show on every section of
// their department, so they stay findable until routed to a specific one.
$stmt = $pdo->prepare('SELECT * FROM m_checker WHERE department_id = ? AND (section_id = ? OR section_id IS NULL) ORDER BY name');
$stmt->execute([$selected_department_id, $selected_section_id]);
$rows = $stmt->fetchAll();

$roleLabels = [];
foreach ($pdo->query('SELECT name, label FROM m_checker_role') as $r) {
    $roleLabels[$r['name']] = $r['label'];
}

// Names already registered as Users (for the "Add as User" shortcut below).
$existingUserNames = $pdo->query('SELECT name FROM m_user')->fetchAll(PDO::FETCH_COLUMN);

$sectionsByDept = [];
foreach ($sections as $s) {
    $sectionsByDept[$s['department_name']][] = $s;
}

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
    <label>Filter Section:</label>
    <select name="section_id" onchange="this.form.submit()">
        <?php foreach ($sectionsByDept as $deptName => $deptSections): ?>
            <optgroup label="<?= htmlspecialchars($deptName) ?>">
                <?php foreach ($deptSections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_section_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
</form>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Section</label>
            <select name="section_id" required>
                <?php foreach ($sectionsByDept as $deptName => $deptSections): ?>
                    <optgroup label="<?= htmlspecialchars($deptName) ?>">
                        <?php foreach ($deptSections as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == ($editRow['section_id'] ?? $selected_section_id) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Role (optional, used by Washing Machine Liquid Monitoring / FO Pump Assy) &middot; <a href="manage_roles.php">manage roles</a></label>
            <select name="role">
                <?php $curRole = $editRow['role'] ?? ''; ?>
                <option value="" <?= $curRole === '' ? 'selected' : '' ?>>- (General Checked By)</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r['name']) ?>" <?= $curRole === $r['name'] ? 'selected' : '' ?>><?= htmlspecialchars($r['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="checkers.php?section_id=<?= $selected_section_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Section</th>
            <th>Role</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['section_id'] ? htmlspecialchars($sectionsById[$row['section_id']]['name'] ?? '') : '<em style="color:#aeb4bd;">unassigned (shows on all)</em>' ?></td>
            <td><?= $row['role'] ? htmlspecialchars($roleLabels[$row['role']] ?? ucfirst($row['role'])) : '-' ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="checkers.php?section_id=<?= $selected_section_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="checkers.php?section_id=<?= $selected_section_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="checkers.php?section_id=<?= $selected_section_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this data?')" class="danger">Delete</a>
                <?php if (!in_array($row['name'], $existingUserNames, true)): ?>
                    <a href="users.php?prefill_name=<?= urlencode($row['name']) ?>&prefill_checker_role=<?= urlencode($row['role'] ?? '') ?>">Add as User</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No data for this section yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
