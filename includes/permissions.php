<?php
/**
 * Central catalog of roles & permissions + helpers.
 *
 * superadmin  -> always has every permission (never stored, forced in code).
 * admin, user -> permissions read from m_role_permission and editable via
 *                admin/role_permissions.php.
 *
 * To add a new permission, add one entry to permission_catalog() and (once)
 * a default row per role in db/migration_roles.sql.
 */
require_once __DIR__ . '/../config/db.php';

const APP_ROLES = ['superadmin', 'admin', 'user'];

/** Roles whose access can actually be toggled in the matrix. */
const EDITABLE_ROLES = ['admin', 'user'];

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
 * superadmin is always fully allowed; admin/user come from the DB
 * (falling back to false for any permission with no stored row).
 */
function get_role_permissions(PDO $pdo): array
{
    $matrix = [];
    $keys = array_keys(permission_catalog());

    foreach ($keys as $k) {
        $matrix['superadmin'][$k] = true;
        $matrix['admin'][$k] = false;
        $matrix['user'][$k] = false;
    }

    $rows = $pdo->query('SELECT role, perm_key, allowed FROM m_role_permission')->fetchAll();
    foreach ($rows as $r) {
        if (isset($matrix[$r['role']]) && array_key_exists($r['perm_key'], $matrix[$r['role']])) {
            $matrix[$r['role']][$r['perm_key']] = (bool)$r['allowed'];
        }
    }
    return $matrix;
}

/** True if the given role has the given permission. */
function role_can(PDO $pdo, string $role, string $perm): bool
{
    if ($role === 'superadmin') {
        return true;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = get_role_permissions($pdo);
    }
    return !empty($cache[$role][$perm]);
}
