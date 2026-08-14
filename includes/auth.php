<?php
/**
 * Current-user / session layer.
 *
 * There's no password-based login. Identity is picked from the local Users
 * list (admin/users.php, table m_user) via section_login.php — a per-section
 * "who are you?" name picker triggered by require_section_access() below.
 * Whatever sets $_SESSION['auth_user'] populates:
 *
 *     $_SESSION['auth_user'] = [
 *         'name'   => 'Rinaldi',
 *         'role'   => 'admin',   // app role name, e.g. from m_user.role
 *         'avatar' => '',        // optional photo URL ('' = initials)
 *     ];
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/permissions.php';

// Until someone picks their identity via section_login.php, fall back to a
// demo full-access user so admin pages stay reachable. Flip to false to
// require everyone to go through section_login.php first.
const AUTH_DEV_FALLBACK = true;

const AUTH_DEV_USER = [
    'name'   => 'SINTIARA PUTRI ZHARADIVA',
    'role'   => 'superadmin',
    'avatar' => '',
];

/** The logged-in user (with 'id' = m_user.id, or null if not in that table), or null if nobody is authenticated. */
function current_user(): ?array
{
    if (!empty($_SESSION['auth_user']) && !empty($_SESSION['auth_user']['name'])) {
        $u = $_SESSION['auth_user'];
        $name = $u['name'];
        $validRoles = array_column(get_active_roles(get_db()), 'name');
        $role = in_array($u['role'] ?? '', $validRoles, true) ? $u['role'] : (in_array('user', $validRoles, true) ? 'user' : ($validRoles[0] ?? 'user'));

        // The local Users list (admin/users.php) is the source of truth for
        // role + active status whenever the picked name matches a row there.
        $stmt = get_db()->prepare('SELECT id, role, is_active FROM m_user WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $localUser = $stmt->fetch();
        $userId = null;
        if ($localUser) {
            if (!$localUser['is_active']) {
                return null;
            }
            $role = $localUser['role'];
            $userId = (int) $localUser['id'];
        }

        return [
            'id'     => $userId,
            'name'   => $name,
            'role'   => $role,
            'avatar' => $u['avatar'] ?? '',
        ];
    }
    return AUTH_DEV_FALLBACK ? (AUTH_DEV_USER + ['id' => null]) : null;
}

/** Role of the current user, or null if not logged in. */
function current_role(): ?string
{
    return current_user()['role'] ?? null;
}

/** Redirect to ONE Yadin login if nobody is authenticated. */
function require_login(): void
{
    if (current_user() === null) {
        // TODO: point this at the ONE Yadin login URL once known.
        header('Location: ' . base_prefix() . 'index.php');
        exit;
    }
}

/** Guard a page behind a permission; sends to "no access" if lacking it. */
function require_permission(string $perm): void
{
    require_login();
    if (!current_can($perm)) {
        http_response_code(403);
        header('Location: ' . base_prefix() . 'soon.php?feature=No+access+for+your+role');
        exit;
    }
}

/** True if the given user (from current_user()) may open the given section. */
function user_has_section_access(PDO $pdo, ?array $user, int $sectionId): bool
{
    if ($user === null) {
        return false;
    }
    if (role_has_full_access($pdo, $user['role'] ?? null)) {
        return true;
    }
    if (empty($user['id'])) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM m_user_section WHERE user_id = ? AND section_id = ?');
    $stmt->execute([$user['id'], $sectionId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Guard a check sheet entry page behind per-section access. Call right
 * after $department is resolved (department_id + route identify the
 * m_checksheet_section row). Redirects to the name-picker if the current
 * user isn't authorized, preserving department_id so it can send them
 * back in afterward.
 */
function require_section_access(PDO $pdo, int $departmentId, string $route): void
{
    $stmt = $pdo->prepare('SELECT id FROM m_checksheet_section WHERE department_id = ? AND route = ? AND is_active = 1');
    $stmt->execute([$departmentId, $route]);
    $sectionId = $stmt->fetchColumn();
    if (!$sectionId) {
        return; // no section row to gate against — let the page render as before
    }

    if (!user_has_section_access($pdo, current_user(), (int) $sectionId)) {
        header('Location: ' . base_prefix() . 'section_login.php?section_id=' . (int) $sectionId . '&department_id=' . $departmentId);
        exit;
    }
}

/** True if the current user has the given permission. */
function current_can(string $perm): bool
{
    $role = current_role();
    return $role !== null && role_can(get_db(), $role, $perm);
}

/** Initials for the avatar fallback, e.g. "SINTIARA PUTRI" -> "SP". */
function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0][0] ?? '';
    $last  = count($parts) > 1 ? end($parts)[0] : ($parts[0][1] ?? '');
    return strtoupper($first . $last);
}

/** Best-effort relative prefix so redirects work from root and admin/ pages. */
function base_prefix(): string
{
    return (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../' : '';
}
