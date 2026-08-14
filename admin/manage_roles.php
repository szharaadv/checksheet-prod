<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
$pdo = get_db();

require_permission('users.manage');

$error = null;

// ============================================================
// App Roles (m_role) — used by admin/users.php "App Role" and the
// Role Permissions access matrix.
// ============================================================

function is_sole_full_access_role(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('SELECT is_full_access FROM m_role WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        return false;
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM m_role WHERE is_full_access = 1 AND is_active = 1')->fetchColumn();
    return $count <= 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_app_role') {
    $id = $_POST['id'] ?? '';
    $label = trim($_POST['label'] ?? '');
    $isFullAccess = !empty($_POST['is_full_access']) ? 1 : 0;

    if ($label === '') {
        $error = 'App role name is required.';
    } elseif (!$isFullAccess) {
        $fullAccessCount = (int) $pdo->query('SELECT COUNT(*) FROM m_role WHERE is_full_access = 1')->fetchColumn();
        $wasFullAccess = false;
        if ($id !== '') {
            $stmt = $pdo->prepare('SELECT is_full_access FROM m_role WHERE id = ?');
            $stmt->execute([(int)$id]);
            $wasFullAccess = (bool) $stmt->fetchColumn();
        }
        if ($wasFullAccess && $fullAccessCount <= 1) {
            $error = 'At least one app role must keep full access.';
        }
    }

    if (!$error) {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_role SET label = ?, is_full_access = ? WHERE id = ?');
            $stmt->execute([$label, $isFullAccess, (int)$id]);
        } else {
            $name = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
            if ($name === '') {
                $error = 'App role name is required.';
            } else {
                try {
                    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM m_role')->fetchColumn();
                    $stmt = $pdo->prepare('INSERT INTO m_role (name, label, is_full_access, sort_order) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$name, $label, $isFullAccess, $maxSort + 1]);
                } catch (PDOException $e) {
                    $error = 'An app role with that name already exists.';
                }
            }
        }
        if (!$error) {
            header('Location: manage_roles.php?saved=1');
            exit;
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle_app_role' && isset($_GET['id'])) {
    if (is_sole_full_access_role($pdo, (int)$_GET['id'])) {
        $error = 'Cannot deactivate the only full-access app role.';
    } else {
        $stmt = $pdo->prepare('UPDATE m_role SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: manage_roles.php');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'delete_app_role' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT name FROM m_role WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $name = $stmt->fetchColumn();

    $used = false;
    if ($name) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM m_user WHERE role = ?');
        $stmt->execute([$name]);
        $used = (int) $stmt->fetchColumn() > 0;
    }

    if (is_sole_full_access_role($pdo, (int)$_GET['id'])) {
        $error = 'Cannot delete the only full-access app role.';
    } elseif ($used) {
        $error = 'Cannot delete, this app role is assigned to a user. Reassign or deactivate it instead.';
    } else {
        $pdo->prepare('DELETE FROM m_role WHERE id = ?')->execute([(int)$_GET['id']]);
        $pdo->prepare('DELETE FROM m_role_permission WHERE role = ?')->execute([$name]);
        header('Location: manage_roles.php?deleted=1');
        exit;
    }
}

$editAppRole = null;
if (($_GET['action'] ?? '') === 'edit_app_role' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_role WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editAppRole = $stmt->fetch();
}

// ============================================================
// Checked By Roles (m_checker_role) — used by admin/users.php's
// "Checked By Role" field.
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_checker_role') {
    $id = $_POST['id'] ?? '';
    $label = trim($_POST['label'] ?? '');

    if ($label === '') {
        $error = 'Checked By role name is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_checker_role SET label = ? WHERE id = ?');
            $stmt->execute([$label, (int)$id]);
        } else {
            $name = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
            if ($name === '') {
                $error = 'Checked By role name is required.';
            } else {
                try {
                    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM m_checker_role')->fetchColumn();
                    $stmt = $pdo->prepare('INSERT INTO m_checker_role (name, label, sort_order) VALUES (?, ?, ?)');
                    $stmt->execute([$name, $label, $maxSort + 1]);
                } catch (PDOException $e) {
                    $error = 'A Checked By role with that name already exists.';
                }
            }
        }
        if (!$error) {
            header('Location: manage_roles.php?saved=1');
            exit;
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle_checker_role' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_checker_role SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: manage_roles.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete_checker_role' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_checker_role WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: manage_roles.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this role is already used by a Checked By entry. Deactivate it instead.';
    }
}

$editCheckerRole = null;
if (($_GET['action'] ?? '') === 'edit_checker_role' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_checker_role WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editCheckerRole = $stmt->fetch();
}

$appRoles = get_all_roles($pdo);
$checkerRoleRows = $pdo->query('SELECT * FROM m_checker_role ORDER BY sort_order, id')->fetchAll();

$base_url = '../';
$active_nav = 'config-manage-roles';
$page_title = 'Roles';
$page_subtitle = 'Management';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="section-head">
    <h2>App Roles</h2>
    <p>Assignable under <a href="users.php">Users</a> &middot; App Role, and shown as columns in <a href="role_permissions.php">Role Permissions</a>. "Full access" roles always have every permission, like Super Admin.</p>
</div>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_app_role">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editAppRole['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Role Name</label>
            <input type="text" name="label" value="<?= htmlspecialchars($editAppRole['label'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>&nbsp;</label>
            <label style="font-weight: 400; display: flex; align-items: center; gap: 6px;">
                <input type="checkbox" name="is_full_access" value="1" <?= !empty($editAppRole['is_full_access']) ? 'checked' : '' ?> style="width: auto;">
                Full access (like Super Admin)
            </label>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editAppRole ? 'Update' : 'Add' ?></button>
        <?php if ($editAppRole): ?><a href="manage_roles.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Role Name</th>
            <th>Full Access</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($appRoles as $row): ?>
        <tr>
            <td><span class="role-chip role-<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['label']) ?></span></td>
            <td><?= $row['is_full_access'] ? '<span class="badge badge-ok">Yes</span>' : '-' ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="manage_roles.php?action=edit_app_role&id=<?= $row['id'] ?>">Edit</a>
                <a href="manage_roles.php?action=toggle_app_role&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="manage_roles.php?action=delete_app_role&id=<?= $row['id'] ?>" onclick="return confirm('Delete this role?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$appRoles): ?><tr><td colspan="4" class="empty">No app roles yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<div class="section-head" style="margin-top: 32px;">
    <h2>Checked By Roles</h2>
    <p>Assignable under <a href="users.php">Users</a> &middot; Checked By Role (e.g. Foreman, Supervisor, Operator) for the Washing Machine Liquid Monitoring / Sub Assembly / FO Pump Assy check sheets.</p>
</div>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_checker_role">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editCheckerRole['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Role Name</label>
            <input type="text" name="label" value="<?= htmlspecialchars($editCheckerRole['label'] ?? '') ?>" required>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editCheckerRole ? 'Update' : 'Add' ?></button>
        <?php if ($editCheckerRole): ?><a href="manage_roles.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Role Name</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($checkerRoleRows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['label']) ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="manage_roles.php?action=edit_checker_role&id=<?= $row['id'] ?>">Edit</a>
                <a href="manage_roles.php?action=toggle_checker_role&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="manage_roles.php?action=delete_checker_role&id=<?= $row['id'] ?>" onclick="return confirm('Delete this role?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$checkerRoleRows): ?><tr><td colspan="3" class="empty">No Checked By roles yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
