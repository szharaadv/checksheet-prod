<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
$pdo = get_db();

// Read-only view of who's actually routed to the section the user last had
// open — lets them cross-check their routing decisions in admin/users.php
// against what really shows up on that section's check sheet. Editing
// happens only in Users; this page has no add/edit/delete of its own.
$departmentId = (int)($_SESSION['department_id'] ?? 0);
$route = $_SESSION['section_route'] ?? '';

$section = null;
if ($departmentId && $route) {
    $stmt = $pdo->prepare(
        'SELECT s.*, d.name AS department_name FROM m_checksheet_section s
         JOIN m_department d ON d.id = s.department_id
         WHERE s.department_id = ? AND s.route = ? AND s.is_active = 1'
    );
    $stmt->execute([$departmentId, $route]);
    $section = $stmt->fetch();
}

$rows = [];
if ($section) {
    $stmt = $pdo->prepare(
        "SELECT c.*, cr.label AS role_label FROM m_checker c
         LEFT JOIN m_checker_role cr ON cr.name = c.role
         WHERE c.section_id = ?
         ORDER BY c.name"
    );
    $stmt->execute([$section['id']]);
    $rows = $stmt->fetchAll();
}

$base_url = '../';
$active_nav = 'config-checked-by-view';
$page_title = 'Checked By';
$page_subtitle = 'Master Data';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (!$section): ?>
    <div class="empty-state">Open a check sheet section first, then come back here.</div>
<?php else: ?>
    <p class="admin-form-hint">
        Who's routed to <strong><?= htmlspecialchars($section['department_name']) ?> · <?= htmlspecialchars($section['name']) ?></strong> right now —
        pulled straight from what actually shows up on that check sheet's Checked By dropdowns. To change this, edit sections in <a href="users.php">Users</a>, not here.
    </p>

    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= $row['role'] ? htmlspecialchars($row['role_label'] ?? ucfirst($row['role'])) : '-' ?></td>
                <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="3" class="empty">Nobody is routed to this section yet. Assign it to someone in <a href="users.php">Users</a>.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
