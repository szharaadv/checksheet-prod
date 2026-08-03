<?php
/**
 * Current-user / session layer.
 *
 * -------------------------------------------------------------------------
 * ONE Yadin (SSO) INTEGRATION POINT
 * -------------------------------------------------------------------------
 * This app does NOT own the login screen. Authentication happens in ONE
 * Yadin. After ONE Yadin verifies the user, it must populate the session:
 *
 *     $_SESSION['auth_user'] = [
 *         'name'   => 'SINTIARA PUTRI ZHARADIVA', // display name from ONE Yadin
 *         'role'   => 'superadmin',               // superadmin | admin | user
 *         'avatar' => '',                         // optional photo URL ('' = initials)
 *     ];
 *
 * Once that hand-off is wired, set AUTH_DEV_FALLBACK to false below so
 * unauthenticated visitors are rejected instead of getting the demo user.
 * -------------------------------------------------------------------------
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/permissions.php';

// While ONE Yadin is not wired yet, show a demo user so the UI & role
// gating are testable. Flip to false once SSO populates $_SESSION['auth_user'].
const AUTH_DEV_FALLBACK = true;

const AUTH_DEV_USER = [
    'name'   => 'SINTIARA PUTRI ZHARADIVA',
    'role'   => 'superadmin',
    'avatar' => '',
];

/** The logged-in user, or null if nobody is authenticated. */
function current_user(): ?array
{
    if (!empty($_SESSION['auth_user']) && !empty($_SESSION['auth_user']['role'])) {
        $u = $_SESSION['auth_user'];
        return [
            'name'   => $u['name'] ?? 'User',
            'role'   => in_array($u['role'], APP_ROLES, true) ? $u['role'] : 'user',
            'avatar' => $u['avatar'] ?? '',
        ];
    }
    return AUTH_DEV_FALLBACK ? AUTH_DEV_USER : null;
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
