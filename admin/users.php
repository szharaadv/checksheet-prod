<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
$pdo = get_db();

require_permission('users.manage');

$roles = get_active_roles($pdo);
$roleNames = array_column($roles, 'name');

$checkerRoles = $pdo->query('SELECT * FROM m_checker_role WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$checkerRoleNames = array_column($checkerRoles, 'name');

$sections = $pdo->query(
    'SELECT s.*, d.name AS department_name FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order'
)->fetchAll();
$sectionIds = array_column($sections, 'id');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $role = in_array($_POST['role'] ?? '', $roleNames, true) ? $_POST['role'] : ($roleNames[count($roleNames) - 1] ?? 'user');
    $checkerRole = in_array($_POST['checker_role'] ?? '', $checkerRoleNames, true) ? $_POST['checker_role'] : null;
    $postedSectionIds = array_map('intval', $_POST['section_ids'] ?? []);
    $selectedSectionIds = array_values(array_intersect($postedSectionIds, $sectionIds));

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE m_user SET name = ?, role = ?, checker_role = ? WHERE id = ?');
                $stmt->execute([$name, $role, $checkerRole, (int)$id]);
                $userId = (int)$id;
                $pdo->prepare('DELETE FROM m_user_section WHERE user_id = ?')->execute([$userId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_user (name, role, checker_role) VALUES (?, ?, ?)');
                $stmt->execute([$name, $role, $checkerRole]);
                $userId = (int) $pdo->lastInsertId();
            }
            if ($selectedSectionIds) {
                $ins = $pdo->prepare('INSERT INTO m_user_section (user_id, section_id) VALUES (?, ?)');
                foreach ($selectedSectionIds as $sid) {
                    $ins->execute([$userId, $sid]);
                }
            }
            $pdo->commit();
            header('Location: users.php?saved=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'A user with that name already exists.';
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_user SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: users.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM m_user WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: users.php?deleted=1');
    exit;
}

$editRow = null;
$editSectionIds = [];
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_user WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $stmt = $pdo->prepare('SELECT section_id FROM m_user_section WHERE user_id = ?');
        $stmt->execute([(int)$_GET['id']]);
        $editSectionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

// Coming from Checked By's "Add as User" shortcut — prefill the Add form.
$prefillName = $editRow ? '' : trim($_GET['prefill_name'] ?? '');
$prefillCheckerRole = $editRow ? '' : ($_GET['prefill_checker_role'] ?? '');

$rows = $pdo->query('SELECT * FROM m_user ORDER BY name')->fetchAll();

$allRoles = get_all_roles($pdo);
$roleLabels = array_column($allRoles, 'label', 'name');

$allCheckerRoles = $pdo->query('SELECT * FROM m_checker_role')->fetchAll();
$checkerRoleLabels = array_column($allCheckerRoles, 'label', 'name');

// Preload every user's assigned sections for the list table (name -> "Painting, FO Pump Assy").
$sectionsByUser = [];
$stmt = $pdo->query(
    'SELECT us.user_id, s.name, d.name AS department_name FROM m_user_section us
     JOIN m_checksheet_section s ON s.id = us.section_id
     JOIN m_department d ON d.id = s.department_id
     ORDER BY d.sort_order, s.sort_order'
);
foreach ($stmt as $r) {
    $sectionsByUser[$r['user_id']][] = $r['department_name'] . ' · ' . $r['name'];
}

$base_url = '../';
$active_nav = 'config-users';
$page_title = 'Users';
$page_subtitle = 'Management';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>
<?php if ($prefillName): ?><div class="alert alert-ok">Adding <?= htmlspecialchars($prefillName) ?> from Checked By — pick their App Role and sections below, then Add.</div><?php endif; ?>

<p class="admin-form-hint">Assign an app role and (optionally) a Checked By role to each person here — e.g. Rinaldi / Supervisor / Admin. <a href="manage_roles.php">Manage roles</a> if you need more than the defaults.</p>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? $prefillName) ?>" required>
        </div>
        <div class="form-row">
            <label>Checked By Role (optional)</label>
            <select name="checker_role">
                <?php $curCheckerRole = $editRow['checker_role'] ?? $prefillCheckerRole; ?>
                <option value="" <?= $curCheckerRole === '' ? 'selected' : '' ?>>- (none)</option>
                <?php foreach ($checkerRoles as $cr): ?>
                    <option value="<?= htmlspecialchars($cr['name']) ?>" <?= $curCheckerRole === $cr['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cr['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>App Role</label>
            <select name="role">
                <?php $curRole = $editRow['role'] ?? 'user'; ?>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r['name']) ?>" <?= $curRole === $r['name'] ? 'selected' : '' ?>><?= htmlspecialchars($r['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label>Sections this person can check (leave empty + Super Admin-like role = full access anyway)</label>
        <?php
        $sectionsByDept = [];
        foreach ($sections as $s) {
            $sectionsByDept[$s['department_name']][] = $s;
        }
        ?>
        <?php foreach ($sectionsByDept as $deptName => $deptSections): ?>
            <div style="margin-bottom: 8px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #8b93a1; margin-bottom: 4px;"><?= htmlspecialchars($deptName) ?></div>
                <div style="display: flex; flex-wrap: wrap; gap: 4px 16px;">
                    <?php foreach ($deptSections as $s): ?>
                        <label style="font-weight: 400; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="section_ids[]" value="<?= $s['id'] ?>" <?= in_array((int)$s['id'], $editSectionIds, true) ? 'checked' : '' ?> style="width: auto;">
                            <?= htmlspecialchars($s['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="users.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Checked By Role</th>
            <th>App Role</th>
            <th>Sections</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['checker_role'] ? htmlspecialchars($checkerRoleLabels[$row['checker_role']] ?? $row['checker_role']) : '-' ?></td>
            <td><span class="role-chip role-<?= htmlspecialchars($row['role']) ?>"><?= $roleLabels[$row['role']] ?? htmlspecialchars($row['role']) ?></span></td>
            <td><?= !empty($sectionsByUser[$row['id']]) ? htmlspecialchars(implode(', ', $sectionsByUser[$row['id']])) : ($row['role'] && role_has_full_access($pdo, $row['role']) ? '<em style="color:#aeb4bd;">all (full access)</em>' : '-') ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="users.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="users.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="users.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this user?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No users assigned yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
