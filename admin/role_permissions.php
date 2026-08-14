<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/permissions.php';
$pdo = get_db();

$catalog = permission_catalog();
$error = null;

$editableRoles = get_editable_roles($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    // Posted as perm[role][perm_key] = 1 for every checked box.
    $posted = $_POST['perm'] ?? [];

    $stmt = $pdo->prepare(
        'INSERT INTO m_role_permission (role, perm_key, allowed) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
    );

    $pdo->beginTransaction();
    foreach ($editableRoles as $role) {
        foreach (array_keys($catalog) as $key) {
            $allowed = !empty($posted[$role['name']][$key]) ? 1 : 0;
            $stmt->execute([$role['name'], $key, $allowed]);
        }
    }
    $pdo->commit();

    header('Location: role_permissions.php?saved=1');
    exit;
}

$roles = get_active_roles($pdo);
$matrix = get_role_permissions($pdo);

// Group permissions for display, keeping catalog order.
$grouped = [];
foreach ($catalog as $key => $meta) {
    $grouped[$meta[2]][$key] = $meta;
}

$base_url = '../';
$active_nav = 'config-roles';
$page_title = 'Role Permissions';
$page_subtitle = 'Management';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Permissions saved.</div><?php endif; ?>

<div class="section-head">
    <h2>Role Permissions</h2>
    <p>Control what each role can access. Roles marked full access always have every permission. <a href="manage_roles.php">Manage roles</a> to add one or change that flag.</p>
</div>

<form method="post">
    <input type="hidden" name="action" value="save">

    <div class="matrix-card">
        <div class="matrix-card-head">Access Matrix</div>
        <div class="table-scroll">
            <table class="perm-matrix">
                <thead>
                    <tr>
                        <th class="perm-col">Permission</th>
                        <?php foreach ($roles as $role): ?>
                            <th class="role-col"><span class="role-chip role-<?= htmlspecialchars($role['name']) ?>"><?= strtoupper(htmlspecialchars($role['label'])) ?></span></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped as $group => $perms): ?>
                        <tr class="group-row"><td colspan="<?= count($roles) + 1 ?>"><?= htmlspecialchars($group) ?></td></tr>
                        <?php foreach ($perms as $key => $meta): ?>
                            <tr>
                                <td class="perm-col">
                                    <div class="perm-label"><?= htmlspecialchars($meta[0]) ?></div>
                                    <code class="perm-key"><?= htmlspecialchars($key) ?></code>
                                </td>
                                <?php foreach ($roles as $role): ?>
                                    <td class="role-col">
                                        <?php if ($role['is_full_access']): ?>
                                            <input type="checkbox" checked disabled title="<?= htmlspecialchars($role['label']) ?> always has full access">
                                        <?php else: ?>
                                            <input type="checkbox"
                                                   name="perm[<?= htmlspecialchars($role['name']) ?>][<?= htmlspecialchars($key) ?>]"
                                                   value="1"
                                                   <?= !empty($matrix[$role['name']][$key]) ? 'checked' : '' ?>>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="matrix-actions">
        <a href="<?= $base_url ?>index.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn">Save Permissions</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
