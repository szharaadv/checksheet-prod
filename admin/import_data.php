<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/importers.php';
$pdo = get_db();

$registry = import_registry();

// Sections that support import (present in the registry), with department info.
$stmt = $pdo->query(
    "SELECT s.id, s.name, s.route, s.department_id, d.name AS dept_name
     FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1
     ORDER BY d.sort_order, s.sort_order"
);
$allSections = array_filter($stmt->fetchAll(), fn($s) => isset($registry[$s['route']]));

$selected_section_id = (int)($_GET['section_id'] ?? ($_POST['section_id'] ?? 0));
// Default to the section the user currently has open (contextual).
if (!$selected_section_id && !empty($_SESSION['section_route'])) {
    foreach ($allSections as $s) {
        if ($s['route'] === $_SESSION['section_route']
            && (empty($_SESSION['department_id']) || $s['department_id'] == $_SESSION['department_id'])) {
            $selected_section_id = $s['id'];
            break;
        }
    }
}
$section = null;
foreach ($allSections as $s) {
    if ($s['id'] == $selected_section_id) { $section = $s; break; }
}
if (!$section && $allSections) {
    $section = reset($allSections);
    $selected_section_id = $section['id'];
}

// ---- Template download (headers only) ----
if (($_GET['action'] ?? '') === 'template' && $section) {
    $cfg = $registry[$section['route']];
    $filename = 'template_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($section['name'])) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it cleanly
    fputcsv($out, $cfg['template']);
    fclose($out);
    exit;
}

// ---- Export existing data (same columns as the template) ----
if (($_GET['action'] ?? '') === 'export' && $section) {
    $cfg = $registry[$section['route']];
    $cols = $cfg['template'];
    $rows = isset($cfg['export']) && function_exists($cfg['export'])
        ? ($cfg['export'])($pdo, (int)$section['department_id'])
        : [];
    $filename = 'export_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($section['name'])) . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($c) => $r[$c] ?? '', $cols));
    }
    fclose($out);
    exit;
}

$result = null;
$error = null;

// ---- Import upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import' && $section) {
    $cfg = $registry[$section['route']];
    $supportsXlsx = isset($cfg['xlsx_extract']) && function_exists($cfg['xlsx_extract']);
    try {
        $names = $_FILES['csv']['name'] ?? [];
        $tmpPaths = $_FILES['csv']['tmp_name'] ?? [];
        $errors = $_FILES['csv']['error'] ?? [];
        if (!is_array($names)) { $names = [$names]; $tmpPaths = [$tmpPaths]; $errors = [$errors]; }
        $names = array_values(array_filter($names, fn($n) => $n !== ''));
        if (!$names) {
            throw new RuntimeException('Please choose at least one file to upload.');
        }

        $allRows = [];
        foreach ($names as $i => $name) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Upload failed for '$name'.");
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'csv' || $ext === 'txt') {
                $raw = csv_read($tmpPaths[$i]);
                $allRows = array_merge($allRows, isset($cfg['normalize']) ? ($cfg['normalize'])($raw) : $raw);
            } elseif ($ext === 'xlsx' && $supportsXlsx) {
                $allRows = array_merge($allRows, ($cfg['xlsx_extract'])($tmpPaths[$i]));
            } elseif ($ext === 'xlsx') {
                throw new RuntimeException("'$name': this section does not support .xlsx upload — please use the CSV template.");
            } else {
                throw new RuntimeException("'$name': only .csv" . ($supportsXlsx ? ' or .xlsx' : '') . ' files are accepted.');
            }
        }
        if (!$allRows) {
            throw new RuntimeException('No data rows found (or headers/layout do not match the template).');
        }
        $fn = isset($cfg['core']) && function_exists($cfg['core']) ? $cfg['core'] : $cfg['fn'];
        $pdo->beginTransaction();
        $result = $fn($pdo, (int)$section['department_id'], $allRows);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$base_url = '../';
$active_nav = 'config-import';
$page_title = 'Import Data';
$page_subtitle = 'Migrate PowerApps data (CSV)';
require __DIR__ . '/../includes/app_top.php';
$cfg = $section ? $registry[$section['route']] : null;
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($result): ?>
    <div class="alert alert-ok">
        Import finished for <strong><?= htmlspecialchars($section['dept_name'] . ' · ' . $section['name']) ?></strong>:
        <strong><?= $result['created'] ?></strong> created,
        <strong><?= $result['updated'] ?></strong> updated,
        <strong><?= $result['skipped'] ?></strong> skipped.
    </div>
    <?php if (!empty($result['errors'])): ?>
        <div class="alert alert-error" style="max-height:280px;overflow:auto;">
            <strong><?= count($result['errors']) ?> warning(s):</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                <?php foreach ($result['errors'] as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<form method="get" class="filter-form">
    <label>Section:</label>
    <select name="section_id" onchange="this.form.submit()">
        <?php foreach ($allSections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_section_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['dept_name'] . ' · ' . $s['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($section): ?>
<div class="admin-form">
    <h3 style="margin:0 0 6px;font:600 15px Inter,sans-serif;">1. Download the template</h3>
    <p style="margin:0 0 10px;color:#6b7280;font-size:13px;"><?= htmlspecialchars($cfg['note']) ?></p>
    <p style="margin:0 0 12px;">
        <a class="btn" href="import_data.php?section_id=<?= $selected_section_id ?>&action=template">Download CSV Template</a>
    </p>
    <p style="margin:0 0 6px;color:#6b7280;font-size:12.5px;">Columns:</p>
    <div style="overflow-x:auto;">
        <code style="display:inline-block;background:#f4f5f7;border:1px solid #e3e5e9;border-radius:6px;padding:8px 10px;font-size:12.5px;white-space:nowrap;">
            <?= htmlspecialchars(implode(', ', $cfg['template'])) ?>
        </code>
    </div>

    <h3 style="margin:22px 0 8px;font:600 15px Inter,sans-serif;">2. Upload the filled <?= isset($cfg['xlsx_extract']) ? 'CSV or the original .xlsx report(s)' : 'CSV' ?></h3>
    <?php if (isset($cfg['xlsx_extract'])): ?>
        <p style="margin:0 0 10px;color:#6b7280;font-size:13px;">You can select several files at once (e.g. one .xlsx per month, or several daily files) — they'll all be imported together.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
        <div class="form-row">
            <input type="file" name="csv[]" accept="<?= isset($cfg['xlsx_extract']) ? '.csv,.xlsx,text/csv' : '.csv,text/csv' ?>" multiple required>
        </div>
        <div class="form-row">
            <button type="submit" class="btn" onclick="return confirm('Import this CSV into <?= htmlspecialchars($section['name']) ?>? Existing entries on the same date will be updated.')">Import Now</button>
        </div>
    </form>
    <p style="margin:14px 0 0;color:#8b93a1;font-size:12px;">
        Dates accept <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>. Rows are matched to their date &mdash; existing dates are updated, new dates are created. Backdated dates are allowed.
    </p>

    <h3 style="margin:24px 0 8px;font:600 15px Inter,sans-serif;">Export existing data</h3>
    <p style="margin:0 0 12px;color:#6b7280;font-size:13px;">Download all saved <?= htmlspecialchars($section['name']) ?> data as CSV (same columns as the template &mdash; good for backup or re-import).</p>
    <p style="margin:0;">
        <a class="btn btn-secondary" href="import_data.php?section_id=<?= $selected_section_id ?>&action=export">Export to CSV</a>
    </p>
</div>
<?php else: ?>
    <div class="empty">No importable sections found.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
