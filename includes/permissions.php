<?php
/**
 * Central catalog of roles & permissions + helpers.
 *
 * Roles themselves are admin-manageable (see admin/manage_roles.php,
 * table m_role) rather than a fixed list. A role flagged is_full_access
 * always has every permission (forced in code, not stored in
 * m_role_permission). Other roles' permissions are read from
 * m_role_permission and editable via admin/role_permissions.php.
 *
 * To add a new permission, add one entry to permission_catalog(); it will
 * default to "not allowed" for every non-full-access role until toggled
 * on in the Role Permissions screen.
 */
require_once __DIR__ . '/../config/db.php';

/** All roles (active + inactive), ordered for display. */
function get_all_roles(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM m_role ORDER BY sort_order, id')->fetchAll();
}

/** Only active roles — what should appear in role-picker dropdowns. */
function get_active_roles(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM m_role WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
}

/** Roles whose access can actually be toggled in the matrix (not full-access). */
function get_editable_roles(PDO $pdo): array
{
    return array_values(array_filter(get_active_roles($pdo), fn($r) => !$r['is_full_access']));
}

/** True if the given role name is flagged full-access (bypasses section/permission restrictions). */
function role_has_full_access(PDO $pdo, ?string $role): bool
{
    if (!$role) {
        return false;
    }
    static $fullAccessNames = null;
    if ($fullAccessNames === null) {
        $fullAccessNames = array_column(array_filter(get_active_roles($pdo), fn($r) => $r['is_full_access']), 'name');
    }
    return in_array($role, $fullAccessNames, true);
}

/**
 * Ordered permission catalog.
 * perm_key => [label, description, group]
 */
function permission_catalog(): array
{
    return [
        // Workspace
        'workspace.checksheet' => ['Fill check sheets',   'Create & submit check sheets',        'Workspace'],
        'workspace.view'       => ['View checksheets',    'Browse submitted check sheets',       'Workspace'],
        'workspace.drafts'     => ['My Drafts',           'Access saved drafts',                 'Workspace'],
        // Master Data
        'masterdata.manage'    => ['Manage Master Data',  'Configuration: conditions, checking items, checked by, shift, models', 'Master Data'],
        // Management
        'users.manage'         => ['Manage users',        'Create, edit & deactivate users',     'Management'],
        'audit.view'           => ['View audit log',      'Read the activity audit log',         'Management'],
    ];
}

/**
 * Full access matrix as [role][perm_key] => bool.
 * Full-access roles are always fully allowed; everyone else comes from
 * the DB (falling back to false for any permission with no stored row).
 */
function get_role_permissions(PDO $pdo): array
{
    $matrix = [];
    $keys = array_keys(permission_catalog());
    $roles = get_active_roles($pdo);

    foreach ($roles as $role) {
        foreach ($keys as $k) {
            $matrix[$role['name']][$k] = (bool)$role['is_full_access'];
        }
    }

    $fullAccessNames = array_column(array_filter($roles, fn($r) => $r['is_full_access']), 'name');

    $rows = $pdo->query('SELECT role, perm_key, allowed FROM m_role_permission')->fetchAll();
    foreach ($rows as $r) {
        if (in_array($r['role'], $fullAccessNames, true)) {
            continue; // full-access roles ignore stored rows, stay always-true
        }
        if (isset($matrix[$r['role']]) && array_key_exists($r['perm_key'], $matrix[$r['role']])) {
            $matrix[$r['role']][$r['perm_key']] = (bool)$r['allowed'];
        }
    }
    return $matrix;
}

/** True if the given role has the given permission. */
function role_can(PDO $pdo, string $role, string $perm): bool
{
    static $cache = null;
    if ($cache === null) {
        $cache = get_role_permissions($pdo);
    }
    return !empty($cache[$role][$perm]);
}
