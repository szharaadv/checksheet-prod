<?php
/**
 * Expects before include:
 * $base_url    - '' when included from root pages, '../' when included from admin/ pages
 * $active_nav  - one of: checksheet, config-department, config-condition,
 *                config-checklist, config-checker, config-shift
 */
$base_url = $base_url ?? '';
$active_nav = $active_nav ?? '';
$config_active = str_starts_with($active_nav, 'config-');

require_once __DIR__ . '/auth.php';
$me = current_user();

$pdo = get_db();

$session_department_type = 'checklist';
if (!empty($_SESSION['department_id'])) {
    $stmt = $pdo->prepare('SELECT form_type FROM m_department WHERE id = ?');
    $stmt->execute([$_SESSION['department_id']]);
    $session_department_type = $stmt->fetchColumn() ?: 'checklist';
}
$is_assy_context = $session_department_type === 'assembly';

$draft_count = $is_assy_context
    ? (int)$pdo->query("SELECT COUNT(*) FROM t_assy_header WHERE status = 'draft'")->fetchColumn()
    : (int)$pdo->query("SELECT COUNT(*) FROM t_checksheet_header WHERE status = 'draft'")->fetchColumn();

$checksheet_href = !empty($_SESSION['section_route'])
    ? $base_url . $_SESSION['section_route']
    : (!empty($_SESSION['department_id'])
        ? $base_url . ($is_assy_context ? 'assembly_list.php' : 'painting_list.php')
        : $base_url . 'index.php');

$sectionViewMap = [
    'painting_list.php'       => 'view_checksheets.php',
    'assembly_list.php'       => 'view_assy_checksheets.php',
    'washing_liquid_list.php' => 'view_washing_checksheets.php',
    'subassy_list.php'        => 'view_subassy_checksheets.php',
];
$view_href = $base_url . ($sectionViewMap[$_SESSION['section_route'] ?? ''] ?? ($is_assy_context ? 'view_assy_checksheets.php' : 'view_checksheets.php'));
$current_route = $_SESSION['section_route'] ?? '';
$is_washing_section = $current_route === 'washing_liquid_list.php';
// Monthly-log sections (Washing, Sub Assembly) share the same UX:
// no per-sheet drafts, and only the "Checked By" master data applies.
$is_monthly_monitor = in_array($current_route, ['washing_liquid_list.php', 'subassy_list.php'], true);
$drafts_href = $base_url . ($is_monthly_monitor
    ? 'soon.php?feature=My+Drafts+(not+applicable+to+this+check+sheet)'
    : ($is_assy_context ? 'my_assy_drafts.php' : 'my_drafts.php'));

function icon(string $name): string
{
    $icons = [
        'doc'      => '<path d="M6 2h9l5 5v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M15 2v5h5"/>',
        'folder'   => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6z"/>',
        'edit'     => '<path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M13 7l4 4"/>',
        'check'    => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 5-5"/>',
        'chart'    => '<path d="M4 20V10"/><path d="M11 20V4"/><path d="M18 20v-7"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.96 19a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.96a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1.04-1.56V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.56 1.04H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">CS</div>
        <div class="brand-text">
            <div class="brand-title">Check Sheet</div>
            <div class="brand-subtitle">Production Check Sheet</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Workspace</div>
        <a class="nav-item <?= $active_nav === 'checksheet' ? 'active' : '' ?>" href="<?= $checksheet_href ?>">
            <?= icon('doc') ?> Check Sheet
        </a>
        <a class="nav-item <?= $active_nav === 'view-checksheets' ? 'active' : '' ?>" href="<?= $view_href ?>">
            <?= icon('folder') ?> View Checksheets
        </a>
        <a class="nav-item <?= $is_monthly_monitor ? 'disabled' : ($active_nav === 'my-drafts' ? 'active' : '') ?>" href="<?= $drafts_href ?>">
            <?= icon('edit') ?> My Drafts
            <?php if (!$is_monthly_monitor && $draft_count > 0): ?><span class="nav-badge"><?= $draft_count ?></span><?php endif; ?>
        </a>

        <div class="nav-group-label">Master Data</div>
        <div class="nav-item nav-parent <?= $config_active ? 'active' : '' ?>" id="config-toggle">
            <?= icon('gear') ?> Configuration
            <span class="nav-chevron">&#8250;</span>
        </div>
        <div class="nav-submenu <?= $config_active ? 'open' : '' ?>" id="config-submenu">
            <?php if ($is_monthly_monitor): ?>
                <a class="nav-subitem <?= $active_nav === 'config-checker' ? 'active' : '' ?>" href="<?= $base_url ?>admin/checkers.php">Checked By</a>
            <?php elseif ($is_assy_context): ?>
                <a class="nav-subitem <?= $active_nav === 'config-assy-model' ? 'active' : '' ?>" href="<?= $base_url ?>admin/assy_models.php">Model</a>
                <a class="nav-subitem <?= $active_nav === 'config-assy-checklist' ? 'active' : '' ?>" href="<?= $base_url ?>admin/assy_checklist_items.php">Checking Item</a>
                <a class="nav-subitem <?= $active_nav === 'config-checker' ? 'active' : '' ?>" href="<?= $base_url ?>admin/checkers.php">Checked By</a>
            <?php else: ?>
                <a class="nav-subitem <?= $active_nav === 'config-condition' ? 'active' : '' ?>" href="<?= $base_url ?>admin/conditions.php">Condition</a>
                <a class="nav-subitem <?= $active_nav === 'config-checklist' ? 'active' : '' ?>" href="<?= $base_url ?>admin/checklist_items.php">Checking Item</a>
                <a class="nav-subitem <?= $active_nav === 'config-checker' ? 'active' : '' ?>" href="<?= $base_url ?>admin/checkers.php">Checked By</a>
                <a class="nav-subitem <?= $active_nav === 'config-shift' ? 'active' : '' ?>" href="<?= $base_url ?>admin/master.php?type=shift">Shift</a>
            <?php endif; ?>
        </div>

        <div class="nav-group-label">Management</div>
        <a class="nav-item disabled" href="<?= $base_url ?>soon.php?feature=Users">
            <?= icon('user') ?> Users
        </a>
        <a class="nav-item <?= $active_nav === 'config-roles' ? 'active' : '' ?>" href="<?= $base_url ?>admin/role_permissions.php">
            <?= icon('gear') ?> Role Permissions
        </a>
        <a class="nav-item disabled" href="<?= $base_url ?>soon.php?feature=Audit+Log">
            <?= icon('list') ?> Audit Log
        </a>
    </nav>

    <?php if ($me): ?>
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if (!empty($me['avatar'])): ?>
                <img src="<?= htmlspecialchars($me['avatar']) ?>" alt="">
            <?php else: ?>
                <span><?= htmlspecialchars(user_initials($me['name'])) ?></span>
            <?php endif; ?>
        </div>
        <div class="user-meta">
            <div class="user-name" title="<?= htmlspecialchars($me['name']) ?>"><?= htmlspecialchars($me['name']) ?></div>
            <div class="user-role"><?= htmlspecialchars($me['role']) ?></div>
        </div>
        <a class="user-logout" href="<?= $base_url ?>logout.php">Logout</a>
    </div>
    <?php endif; ?>
</aside>
<script>
document.getElementById('config-toggle').addEventListener('click', function () {
    document.getElementById('config-submenu').classList.toggle('open');
});
</script>
