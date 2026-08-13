<?php
require_once __DIR__ . '/config/db.php';
$pdo = get_db();

if (isset($_GET['department_id'])) {
    $_SESSION['department_id'] = (int)$_GET['department_id'];
}
$department_id = $_SESSION['department_id'] ?? null;

$department = null;
if ($department_id) {
    $stmt = $pdo->prepare(
        "SELECT d.* FROM m_department d
         JOIN m_checksheet_section s ON s.department_id = d.id
         WHERE d.id = ? AND d.is_active = 1 AND s.route = 'fopump_list.php' AND s.is_active = 1"
    );
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'fopump_list.php';

$stmt = $pdo->prepare("SELECT * FROM m_checker WHERE department_id = ? AND is_active = 1 AND role = 'foreman' ORDER BY name");
$stmt->execute([$department['id']]);
$foremen = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM m_checker WHERE department_id = ? AND is_active = 1 AND role = 'supervisor' ORDER BY name");
$stmt->execute([$department['id']]);
$supervisors = $stmt->fetchAll();

// Selected date (backdate allowed, no future).
$today = date('Y-m-d');
$tanggal = $_GET['date'] ?? $today;
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal || $tanggal > $today) {
    $tanggal = $today;
}

// Load existing report for that date (if any).
$header = null;
$details = [];
$stmt = $pdo->prepare('SELECT * FROM t_fopump_header WHERE department_id = ? AND tanggal = ?');
$stmt->execute([$department['id'], $tanggal]);
$header = $stmt->fetch();
if ($header) {
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_detail WHERE header_id = ? ORDER BY row_no');
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $details[(int)$row['row_no']] = $row;
    }
}

$rowCount = 12;

$base_url = '';
$active_nav = 'checksheet';
$page_title = 'Production Check Sheet - FO Pump Assy';
$page_subtitle = $department['name'] . ' · Daily report FO pump assy';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fopump_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="date" id="f_tanggal" value="<?= htmlspecialchars($tanggal) ?>" max="<?= $today ?>"
                   onchange="window.location.href='fopump_list.php?date=' + this.value">
        </div>
        <div class="field-block">
            <label>Employee</label>
            <input type="text" id="f_employee" value="<?= htmlspecialchars($header['employee'] ?? '') ?>">
        </div>
        <div class="field-block">
            <label>Working Time</label>
            <input type="text" id="f_working_time" value="<?= htmlspecialchars($header['working_time'] ?? '') ?>">
        </div>
        <div class="field-block">
            <label>Shift</label>
            <input type="text" id="f_shift" value="<?= htmlspecialchars($header['shift'] ?? '') ?>">
        </div>
    </div>

    <div class="table-wrap">
        <table id="fopump-table" class="assy-table fopump-table">
            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th colspan="2">FO Pump Production</th>
                    <th colspan="2">To Assembly Line</th>
                    <th colspan="2">To Sparepart PTC</th>
                </tr>
                <tr>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= $rowCount; $i++): $r = $details[$i] ?? null; ?>
                <tr>
                    <td class="row-no"><?= $i ?></td>
                    <td><input type="text" class="f-input" data-row="<?= $i ?>" data-field="prod_model" value="<?= htmlspecialchars($r['prod_model'] ?? '') ?>"></td>
                    <td><input type="text" class="f-input f-qty" data-row="<?= $i ?>" data-field="prod_qty" data-group="prod" value="<?= htmlspecialchars($r['prod_qty'] ?? '') ?>"></td>
                    <td><input type="text" class="f-input" data-row="<?= $i ?>" data-field="assy_model" value="<?= htmlspecialchars($r['assy_model'] ?? '') ?>"></td>
                    <td><input type="text" class="f-input f-qty" data-row="<?= $i ?>" data-field="assy_qty" data-group="assy" value="<?= htmlspecialchars($r['assy_qty'] ?? '') ?>"></td>
                    <td><input type="text" class="f-input" data-row="<?= $i ?>" data-field="export_model" value="<?= htmlspecialchars($r['export_model'] ?? '') ?>"></td>
                    <td><input type="text" class="f-input f-qty" data-row="<?= $i ?>" data-field="export_qty" data-group="export" value="<?= htmlspecialchars($r['export_qty'] ?? '') ?>"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Total</td>
                    <td></td><td class="f-total" data-group="prod">0</td>
                    <td></td><td class="f-total" data-group="assy">0</td>
                    <td></td><td class="f-total" data-group="export">0</td>
                </tr>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Convert</td>
                    <td></td><td><input type="text" id="convert_prod" value="<?= htmlspecialchars($header['convert_prod'] ?? '') ?>"></td>
                    <td></td><td><input type="text" id="convert_assy" value="<?= htmlspecialchars($header['convert_assy'] ?? '') ?>"></td>
                    <td></td><td><input type="text" id="convert_export" value="<?= htmlspecialchars($header['convert_export'] ?? '') ?>"></td>
                </tr>
                <tr class="fopump-summary">
                    <td class="sum-label" colspan="1">Acumulation</td>
                    <td></td><td><input type="text" id="accum_prod" value="<?= htmlspecialchars($header['accum_prod'] ?? '') ?>"></td>
                    <td></td><td><input type="text" id="accum_assy" value="<?= htmlspecialchars($header['accum_assy'] ?? '') ?>"></td>
                    <td></td><td><input type="text" id="accum_export" value="<?= htmlspecialchars($header['accum_export'] ?? '') ?>"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="form-grid-top" style="margin-top:16px;">
        <div class="field-block">
            <label>Operator</label>
            <input type="text" id="f_operator" value="<?= htmlspecialchars($header['operator_name'] ?? '') ?>">
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <select id="f_foreman">
                <option value="">-</option>
                <?php foreach ($foremen as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($header['foreman_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <select id="f_supervisor">
                <option value="">-</option>
                <?php foreach ($supervisors as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($header['supervisor_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-submit" id="btn-submit">Save Entries</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const REPORT_DATE = <?= json_encode($tanggal) ?>;
    const ROW_COUNT = <?= json_encode($rowCount) ?>;
</script>
<script src="assets/js/fopump.js?v=<?= @filemtime(__DIR__ . '/assets/js/fopump.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
