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
         WHERE d.id = ? AND d.is_active = 1 AND s.route = 'fpcheck_list.php' AND s.is_active = 1"
    );
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}
if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'fpcheck_list.php';
require_once __DIR__ . '/includes/auth.php';
require_section_access($pdo, (int) $department['id'], 'fpcheck_list.php');

$section_id = (int)$pdo->query("SELECT id FROM m_checksheet_section WHERE route='fpcheck_list.php' AND department_id=" . (int)$department['id'])->fetchColumn();

$variants = $pdo->prepare('SELECT * FROM m_fpcs_variant WHERE section_id = ? AND is_active = 1 ORDER BY sort_order, id');
$variants->execute([$section_id]);
$variants = $variants->fetchAll();

$points = $pdo->prepare('SELECT * FROM m_fpcs_point WHERE section_id = ? AND is_active = 1 ORDER BY sort_order, id');
$points->execute([$section_id]);
$points = $points->fetchAll();

// standards keyed by variant_id -> point_id -> [std_1, std_2]
$standards = [];
$stmt = $pdo->prepare(
    'SELECT st.* FROM m_fpcs_standard st
     JOIN m_fpcs_variant v ON v.id = st.variant_id
     WHERE v.section_id = ?'
);
$stmt->execute([$section_id]);
foreach ($stmt->fetchAll() as $s) {
    $standards[(int)$s['variant_id']][(int)$s['point_id']] = ['std_1' => $s['std_1'], 'std_2' => $s['std_2']];
}

$today = date('Y-m-d');
$tanggal = $_GET['date'] ?? $today;
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal || $tanggal > $today) {
    $tanggal = $today;
}

$COL_COUNT = 6;
$COL_PLACEHOLDERS = ['1', '11', '21', '31', '41', '51'];

// Load an existing draft to continue editing.
$draft = null;
$draftCols = [];
$draftCells = [];
$draft_id = (int)($_GET['draft_id'] ?? 0);
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_fpcs_header WHERE id = ? AND section_id = ? AND status = 'draft'");
    $stmt->execute([$draft_id, $section_id]);
    $draft = $stmt->fetch();
    if ($draft) {
        $tanggal = $draft['tanggal'];
        $stmt = $pdo->prepare('SELECT col_index, label FROM t_fpcs_column WHERE header_id = ? ORDER BY col_index');
        $stmt->execute([$draft_id]);
        foreach ($stmt->fetchAll() as $c) $draftCols[(int)$c['col_index']] = $c['label'];
        $stmt = $pdo->prepare('SELECT point_id, col_index, value FROM t_fpcs_cell WHERE header_id = ?');
        $stmt->execute([$draft_id]);
        foreach ($stmt->fetchAll() as $c) $draftCells[(int)$c['point_id']][(int)$c['col_index']] = $c['value'];
    } else {
        $draft_id = 0;
    }
}

$base_url = '';
$active_nav = 'checksheet';
$page_title = 'Production Check Sheet - FO Pump Assy Check';
$page_subtitle = $department['name'] . ' · Daily check sheet (F-FIP-01)';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fpcheck_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly value="<?= htmlspecialchars($tanggal) ?>" max="<?= $today ?>">
        </div>
        <div class="field-block">
            <label>Type</label>
            <select id="f_variant">
                <?php foreach ($variants as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $draft && $draft['variant_id'] == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block"><label>Model</label><input type="text" id="f_model" value="<?= htmlspecialchars($draft['model'] ?? '') ?>"></div>
        <div class="field-block"><label>P. Code</label><input type="text" id="f_pcode" value="<?= htmlspecialchars($draft['p_code'] ?? '') ?>"></div>
        <div class="field-block"><label>Part No.</label><input type="text" id="f_partno" value="<?= htmlspecialchars($draft['part_no'] ?? '') ?>"></div>
        <div class="field-block"><label>Prod. Date</label><input type="text" id="f_proddate" value="<?= htmlspecialchars($draft['prod_date'] ?? '') ?>"></div>
        <div class="field-block"><label>Check Method</label><input type="text" id="f_method" value="<?= htmlspecialchars($draft['check_method'] ?? '1pc / 10 production') ?>"></div>
        <div class="field-block"><label>Checker</label><input type="text" id="f_checker" value="<?= htmlspecialchars($draft['checker'] ?? '') ?>"></div>
        <div class="field-block"><label>Foreman</label><input type="text" id="f_foreman" value="<?= htmlspecialchars($draft['foreman'] ?? '') ?>"></div>
        <div class="field-block"><label>Supervisor</label><input type="text" id="f_supervisor" value="<?= htmlspecialchars($draft['supervisor'] ?? '') ?>"></div>
    </div>

    <div class="table-wrap">
        <table id="fpcheck-table" class="assy-table fpcheck-table">
            <colgroup id="fpcheck-cols"></colgroup>
            <thead id="fpcheck-head"></thead>
            <tbody id="fpcheck-body"></tbody>
        </table>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <button type="button" class="btn btn-submit" id="btn-submit">Submit</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const SECTION_ID = <?= json_encode($section_id) ?>;
    const VARIANTS = <?= json_encode($variants) ?>;
    const POINTS = <?= json_encode($points) ?>;
    const STANDARDS = <?= json_encode($standards, JSON_FORCE_OBJECT) ?>;
    const COL_COUNT = <?= json_encode($COL_COUNT) ?>;
    const COL_PLACEHOLDERS = <?= json_encode($COL_PLACEHOLDERS) ?>;
    const DRAFT_ID = <?= json_encode($draft_id ?: null) ?>;
    const DRAFT_COLS = <?= json_encode($draftCols, JSON_FORCE_OBJECT) ?>;
    const DRAFT_CELLS = <?= json_encode($draftCells, JSON_FORCE_OBJECT) ?>;
</script>
<script src="assets/js/holiday-calendar.js?v=<?= @filemtime(__DIR__ . '/assets/js/holiday-calendar.js') ?: 1 ?>"></script>
<script src="assets/js/fpcheck.js?v=<?= @filemtime(__DIR__ . '/assets/js/fpcheck.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
