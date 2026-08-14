<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

$section_id = (int)($_GET['section_id'] ?? $_POST['section_id'] ?? 0);
$department_id = (int)($_GET['department_id'] ?? $_POST['department_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, d.name AS department_name FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.id = ? AND s.department_id = ? AND s.is_active = 1"
);
$stmt->execute([$section_id, $department_id]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: index.php');
    exit;
}

// Already identified and authorized this session — skip the picker.
if (user_has_section_access($pdo, current_user(), $section_id)) {
    header('Location: ' . $section['route'] . '?department_id=' . $department_id);
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pick') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM m_user WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $picked = $stmt->fetch();

    if (!$picked) {
        $error = 'User not found.';
    } else {
        $candidate = ['id' => (int) $picked['id'], 'name' => $picked['name'], 'role' => $picked['role']];
        if (!user_has_section_access($pdo, $candidate, $section_id)) {
            $error = htmlspecialchars($picked['name']) . ' is not assigned to this section.';
        } else {
            $_SESSION['auth_user'] = ['name' => $picked['name'], 'role' => $picked['role'], 'avatar' => ''];
            header('Location: ' . $section['route'] . '?department_id=' . $department_id);
            exit;
        }
    }
}

// The picker only shows names actually routed to THIS section (plus
// full-access users, who can always get in) — so each section's list is
// its own small, relevant set instead of the whole company.
$fullAccessNames = array_column(array_filter(get_active_roles($pdo), fn($r) => $r['is_full_access']), 'name');
$fullAccessPlaceholders = $fullAccessNames ? implode(',', array_fill(0, count($fullAccessNames), '?')) : "''";
$stmt = $pdo->prepare(
    "SELECT DISTINCT u.* FROM m_user u
     LEFT JOIN m_user_section us ON us.user_id = u.id AND us.section_id = ?
     WHERE u.is_active = 1 AND (us.user_id IS NOT NULL OR u.role IN ($fullAccessPlaceholders))
     ORDER BY u.name"
);
$stmt->execute(array_merge([$section_id], $fullAccessNames));
$users = $stmt->fetchAll();

$base_url = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Sheet - Who are you?</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
<div class="landing">
    <div class="landing-brand">
        <div class="brand-mark">CS</div>
        <div class="brand-text">
            <div class="brand-title">Check Sheet</div>
            <div class="brand-subtitle">Production Check Sheet</div>
        </div>
    </div>

    <h1><?= htmlspecialchars($section['department_name']) ?> &middot; <?= htmlspecialchars($section['name']) ?></h1>
    <p class="landing-hint">Who's checking this section? Pick your name to continue.</p>

    <?php if ($error): ?><div class="alert alert-error" style="max-width: 420px; margin: 0 auto 16px;"><?= $error ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="pick">
        <input type="hidden" name="section_id" value="<?= $section_id ?>">
        <input type="hidden" name="department_id" value="<?= $department_id ?>">
        <div class="dept-grid">
            <?php foreach ($users as $u): ?>
                <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="dept-card" style="border: none; cursor: pointer; font: inherit;">
                    <div class="dept-icon"><?= strtoupper(substr($u['name'], 0, 2)) ?></div>
                    <div class="dept-name"><?= htmlspecialchars($u['name']) ?></div>
                    <div class="dept-go">Continue &rarr;</div>
                </button>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <p class="empty">Nobody is routed to this section yet. Assign it to someone in <a href="admin/users.php">Users</a>.</p>
            <?php endif; ?>
        </div>
    </form>

    <p class="landing-hint" style="margin-top:28px;"><a href="select_section.php?department_id=<?= $department_id ?>" class="dept-switch-link">&larr; Back</a></p>
</div>
</body>
</html>
