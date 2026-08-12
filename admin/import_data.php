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
    fputcsv($out, import_fillable_cols($cfg));
    fclose($out);
    exit;
}

// ---- Template download (Excel, block layout matching the check sheet / printed form) ----
if (($_GET['action'] ?? '') === 'xlsx_template' && $section) {
    $cfg = $registry[$section['route']];
    if (isset($cfg['xlsx_template']) && function_exists($cfg['xlsx_template'])) {
        $tplYear = (int)($_GET['year'] ?? 2026);
        $tplMonth = (int)($_GET['month'] ?? date('n'));
        $bytes = ($cfg['xlsx_template'])($tplYear, $tplMonth);
        $filename = 'template_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($section['name']))
            . '_' . $tplYear . '_' . str_pad((string)$tplMonth, 2, '0', STR_PAD_LEFT) . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }
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

<div class="import-page">

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

<div class="section-picker">
    <label for="section_id">Section</label>
    <form method="get" id="section-form">
        <select name="section_id" id="section_id" onchange="this.form.submit()">
            <?php foreach ($allSections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_section_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['dept_name'] . ' · ' . $s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($section): ?>
<div class="import-steps">

    <div class="import-card">
        <div class="import-step-head">
            <span class="import-step-num">1</span>
            <span class="import-step-title">Download the template</span>
        </div>
        <p class="import-note"><?= htmlspecialchars($cfg['note']) ?></p>

        <?php if (isset($cfg['xlsx_template'])):
            $tplMonth = (int) date('n');
            $tplYear = 2026;
            $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        ?>
            <form method="get" class="import-actions">
                <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
                <input type="hidden" name="action" value="xlsx_template">
                <select name="month" class="import-month-select">
                    <?php foreach ($monthNames as $i => $mName): ?>
                        <option value="<?= $i + 1 ?>" <?= ($i + 1) == $tplMonth ? 'selected' : '' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="year" class="import-month-select">
                    <?php for ($y = 2025; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $tplYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn">Download Excel Template</button>
            </form>
            <p class="import-alt">
                One block per day, dated to match the chosen month's real calendar (28-31 blocks depending on the month). Total is auto-summed from that day's Quantity cells, and Acumulation auto-adds the previous day's Acumulation to that day's Total — just fill in Model/Quantity, they calculate themselves in Excel.
                Prefer a flat spreadsheet? <a href="import_data.php?section_id=<?= $selected_section_id ?>&action=template">Download CSV Template</a> instead.
            </p>
        <?php else: ?>
            <div class="import-actions">
                <a class="btn" href="import_data.php?section_id=<?= $selected_section_id ?>&action=template">Download CSV Template</a>
            </div>
        <?php endif; ?>

        <details class="import-columns">
            <summary>Columns (grouped to match the check sheet form)</summary>
            <div class="col-groups">
                <?php foreach (($cfg['groups'] ?? [['label' => 'Columns', 'cols' => $cfg['template']]]) as $g): $ro = !empty($g['readonly']); ?>
                    <div class="col-group<?= $ro ? ' readonly' : '' ?>">
                        <div class="col-group-label">
                            <?= htmlspecialchars($g['label']) ?><?php if ($ro): ?> <span class="col-group-note">(not in the fillable template)</span><?php endif; ?>
                        </div>
                        <div class="col-chip-row">
                            <?php foreach ($g['cols'] as $c): ?>
                                <span class="col-chip"><?= htmlspecialchars($c) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <div class="import-card">
        <div class="import-step-head">
            <span class="import-step-num">2</span>
            <span class="import-step-title">Upload the filled <?= isset($cfg['xlsx_extract']) ? 'CSV or the original .xlsx report(s)' : 'CSV' ?></span>
        </div>
        <?php if (isset($cfg['xlsx_extract'])): ?>
            <p class="import-note">You can select several files at once (e.g. one .xlsx per month, or several daily files) — they'll all be imported together.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
            <div class="import-dropzone">
                <input type="file" name="csv[]" accept="<?= isset($cfg['xlsx_extract']) ? '.csv,.xlsx,text/csv' : '.csv,text/csv' ?>" multiple required>
            </div>
            <button type="submit" class="btn" onclick="return confirm('Import this CSV into <?= htmlspecialchars($section['name']) ?>? Existing entries on the same date will be updated.')">Import Now</button>
        </form>
        <p class="import-hint">
            Dates accept <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>. Rows are matched to their date — existing dates are updated, new dates are created. Backdated dates are allowed.
        </p>
    </div>

    <div class="import-card import-export">
        <div class="import-export-text">
            <div class="import-step-title">Export existing data</div>
            <p>Download all saved <?= htmlspecialchars($section['name']) ?> data as CSV (same columns as the template — good for backup or re-import).</p>
        </div>
        <a class="btn btn-secondary" href="import_data.php?section_id=<?= $selected_section_id ?>&action=export">Export to CSV</a>
    </div>

</div>
<?php else: ?>
    <div class="empty">No importable sections found.</div>
<?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>
