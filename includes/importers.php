<?php
require_once __DIR__ . '/import_lib.php';
require_once __DIR__ . '/xlsx_lib.php';

/** Number of production-line rows (NO 1..N) on the FO Pump Assy check sheet / template. */
if (!defined('FOPUMP_ROW_COUNT')) define('FOPUMP_ROW_COUNT', 12);

/**
 * Registry of importable sections, keyed by section route.
 * Each entry: label, template (CSV column order), note, and importer fn name.
 * Every importer fn signature: fn(PDO $pdo, int $department_id, array $rows): array
 *   returns ['created'=>int, 'updated'=>int, 'skipped'=>int, 'errors'=>string[]]
 */
function import_registry(): array
{
    return [
        'washing_liquid_list.php' => [
            'label'    => 'Washing Machine Liquid Monitoring',
            'template' => ['tanggal', 'ganti_air', 'temperatur_air', 'penambahan_gildaon', 'total_acid', 'checker', 'control'],
            'note'     => 'One row per date. checker = Foreman name, control = Supervisor name (must match Checked By master).',
            'fn'       => 'import_washing',
            'export'   => 'export_washing',
            'groups'   => [
                ['label' => 'One row per date (matches the check sheet form)',
                 'cols'  => ['tanggal', 'ganti_air', 'temperatur_air', 'penambahan_gildaon', 'total_acid', 'checker', 'control']],
            ],
        ],
        'subassy_list.php' => [
            'label'    => 'Sub Assembly',
            'template' => ['tanggal', 'surface_outside', 'parting_line', 'surface_upper', 'cleanliness', 'checker', 'control'],
            'note'     => 'One row per date. Check columns accept OK / NG (also V / X). checker = Foreman, control = Supervisor.',
            'fn'       => 'import_subassy',
            'export'   => 'export_subassy',
            'groups'   => [
                ['label' => 'One row per date (matches the check sheet form)',
                 'cols'  => ['tanggal', 'surface_outside', 'parting_line', 'surface_upper', 'cleanliness', 'checker', 'control']],
            ],
        ],
        'fopump_list.php' => [
            'label'    => 'FO Pump Assy',
            // Columns mirror the F-FIP-03 form / the on-screen check sheet
            // (fopump_list.php). One CSV row = one production line (NO 1..FOPUMP_ROW_COUNT);
            // Date/Employee/Shift/signatures/Convert/Acumulation repeat on
            // each row of the same date. Total is a read-only, auto-summed
            // field on the form (not stored) — it's excluded from the fillable
            // template and only appears when exporting existing data.
            'template' => ['Date', 'Employee', 'Working Time', 'Shift', 'NO',
                           'FO Pump Production Model', 'FO Pump Production Quantity',
                           'To Assembly Line Model', 'To Assembly Line Quantity',
                           'To Sparepart PTC Model', 'To Sparepart PTC Quantity',
                           'Total Production', 'Total Assembly', 'Total Export',
                           'Convert Production', 'Convert Assembly', 'Convert Export',
                           'Acumulation Production', 'Acumulation Assembly', 'Acumulation Export',
                           'Operator', 'Foreman', 'Supervisor'],
            'note'     => 'One row per production line (NO 1..' . FOPUMP_ROW_COUNT . '). Rows with the same Date form one report; Date/Employee/Shift/Convert/Acumulation/signatures repeat on each. Foreman/Supervisor must match an existing name in Checked By master data (Foreman/Supervisor role) — the form uses a dropdown, not free text. Total is auto-computed by the form and not saved, so it is left out of the fillable template. You can also upload the original F-FIP-03 .xlsx report(s) directly (one or many files, each with one or many month sheets) — no re-typing needed.',
            'fn'       => 'import_fopump',
            'normalize'    => 'fopump_normalize_csv_rows',
            'core'         => 'import_fopump_rows',
            'xlsx_extract' => 'fopump_extract_from_xlsx',
            'xlsx_template' => 'fopump_build_template_xlsx',
            'export'   => 'export_fopump',
            'groups'   => [
                ['label' => 'Header — repeat on every row of the same Date',
                 'cols'  => ['Date', 'Employee', 'Working Time', 'Shift']],
                ['label' => 'Production line detail — one row per NO (1-' . FOPUMP_ROW_COUNT . ')',
                 'cols'  => ['NO', 'FO Pump Production Model', 'FO Pump Production Quantity',
                             'To Assembly Line Model', 'To Assembly Line Quantity',
                             'To Sparepart PTC Model', 'To Sparepart PTC Quantity']],
                ['label' => 'Convert & Acumulation — fill once per Date (repeat on every row)',
                 'cols'  => ['Convert Production', 'Convert Assembly', 'Convert Export',
                             'Acumulation Production', 'Acumulation Assembly', 'Acumulation Export']],
                ['label' => 'Signatures — fill once per Date (repeat on every row)',
                 'cols'  => ['Operator', 'Foreman', 'Supervisor']],
                ['label' => 'Auto-computed by the form — leave blank, only shown when exporting',
                 'cols'  => ['Total Production', 'Total Assembly', 'Total Export'],
                 'readonly' => true],
            ],
        ],
        'assembly_list.php' => [
            'label'    => 'Torque (Daily Torque)',
            'template' => ['tanggal', 'model', 'checker', 'mark_crank_shaft', 'mark_conrod', 'mark_fo_pump',
                           'no_cyl_block', 'no_engine', 'detail_model', 'checking_item', 'actual_result', 'consumable_item'],
            'note'     => 'One row per checking item. Rows with the same tanggal + model form one sheet; header fields repeat. checking_item must match the Checking Item master for that model.',
            'fn'       => 'import_torque',
            'export'   => 'export_torque',
            'groups'   => [
                ['label' => 'Header — repeat on every row of the same tanggal + model',
                 'cols'  => ['tanggal', 'model', 'checker', 'mark_crank_shaft', 'mark_conrod', 'mark_fo_pump',
                             'no_cyl_block', 'no_engine', 'detail_model']],
                ['label' => 'Checklist detail — one row per checking item',
                 'cols'  => ['checking_item', 'actual_result', 'consumable_item']],
            ],
        ],
        'painting_list.php' => [
            'label'    => 'Painting Checklist',
            // Columns match the PowerApps export layout exactly (Condition, Date,
            // Checking Item, Metode Pengecekkan, Standard Min./Max., Satuan, Shift,
            // Jam, Tank/Tube, Actual Result, Category, Checked By :). Descriptive
            // columns (Metode Pengecekkan, Standard Min./Max., Satuan, Tank/Tube)
            // come from master data and aren't editable on the check sheet form —
            // ignored on import — but are still included in the downloadable
            // template (left blank) to match the original layout exactly.
            'template' => ['Condition', 'Date', 'Checking Item', 'Metode Pengecekkan', 'Standard Min.', 'Standard Max.',
                           'Satuan', 'Shift', 'Jam', 'Tank/Tube', 'Actual Result', 'Category', 'Checked By :'],
            'note'     => 'One row per checking item. Rows with the same Date + Condition + Jam form one sheet; header fields repeat. Condition / Checking Item must match master data. Descriptive columns (Metode, Standard, Satuan, Tank/Tube) come from master data and are ignored on import even though they\'re included in the template — leave them blank or filled in, either way.',
            'fn'       => 'import_painting',
            'export'   => 'export_painting',
            'template_include_readonly' => true,
            'groups'   => [
                ['label' => 'Header — repeat on every row of the same Date + Condition + Jam',
                 'cols'  => ['Condition', 'Date', 'Shift', 'Jam', 'Checked By :']],
                ['label' => 'Checklist detail — one row per checking item',
                 'cols'  => ['Checking Item', 'Actual Result', 'Category']],
                ['label' => 'Reference from master data — included in the template layout, ignored on import',
                 'cols'  => ['Metode Pengecekkan', 'Standard Min.', 'Standard Max.', 'Satuan', 'Tank/Tube'],
                 'readonly' => true],
            ],
        ],
    ];
}

/** Columns marked readonly in a section's groups (reference/computed — excluded from the fillable template). */
function import_readonly_cols(array $cfg): array
{
    $cols = [];
    foreach ($cfg['groups'] ?? [] as $g) {
        if (!empty($g['readonly'])) $cols = array_merge($cols, $g['cols']);
    }
    return $cols;
}

/**
 * The columns for the downloadable blank template. Normally the full template
 * minus readonly/reference columns (e.g. FO Pump Assy's auto-computed Total),
 * but a section can set 'template_include_readonly' => true to keep the full
 * column layout in the template anyway (e.g. Painting, to match the original
 * PowerApps export layout exactly — those columns are still ignored on import).
 */
function import_fillable_cols(array $cfg): array
{
    if (!empty($cfg['template_include_readonly'])) {
        return $cfg['template'];
    }
    $ro = import_readonly_cols($cfg);
    return array_values(array_filter($cfg['template'], fn($c) => !in_array($c, $ro, true)));
}

/** Group rows by a composite key built from the given columns. Preserves order. */
function import_group(array $rows, array $keyCols): array
{
    $groups = [];
    foreach ($rows as $i => $r) {
        $key = implode('||', array_map(fn($c) => strtolower(trim((string)($r[$c] ?? ''))), $keyCols));
        $groups[$key]['rows'][] = ['_line' => $i + 2, 'data' => $r]; // +2: header + 1-index
    }
    return $groups;
}

// ---------------------------------------------------------------------------
// Type A: one row per date
// ---------------------------------------------------------------------------

function import_washing(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $sel = $pdo->prepare('SELECT id FROM t_washing_entry WHERE department_id=? AND tanggal=?');
    $up = $pdo->prepare('INSERT INTO t_washing_entry (department_id,tanggal,ganti_air,temperatur_air,penambahan_gildaon,total_acid,checker_id,supervisor_id)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE ganti_air=VALUES(ganti_air),temperatur_air=VALUES(temperatur_air),penambahan_gildaon=VALUES(penambahan_gildaon),total_acid=VALUES(total_acid),checker_id=VALUES(checker_id),supervisor_id=VALUES(supervisor_id)');

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $tgl = import_parse_date($r['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid/blank tanggal '" . ($r['tanggal'] ?? '') . "'."; continue; }
        $sel->execute([$dept, $tgl]);
        $existed = (bool)$sel->fetchColumn();
        $checker = import_resolve_checker($pdo, $dept, $r['checker'] ?? '', 'foreman');
        $control = import_resolve_checker($pdo, $dept, $r['control'] ?? '', 'supervisor');
        if (!empty($r['checker']) && !$checker) $res['errors'][] = "Row $line: checker '{$r['checker']}' not found (saved blank).";
        if (!empty($r['control']) && !$control) $res['errors'][] = "Row $line: control '{$r['control']}' not found (saved blank).";
        $up->execute([$dept, $tgl, import_nz($r['ganti_air'] ?? ''), import_nz($r['temperatur_air'] ?? ''),
            import_nz($r['penambahan_gildaon'] ?? ''), import_nz($r['total_acid'] ?? ''), $checker, $control]);
        $existed ? $res['updated']++ : $res['created']++;
    }
    return $res;
}

function import_subassy(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $sel = $pdo->prepare('SELECT id FROM t_subassy_entry WHERE department_id=? AND tanggal=?');
    $up = $pdo->prepare('INSERT INTO t_subassy_entry (department_id,tanggal,surface_outside,parting_line,surface_upper,cleanliness,checker_id,supervisor_id)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE surface_outside=VALUES(surface_outside),parting_line=VALUES(parting_line),surface_upper=VALUES(surface_upper),cleanliness=VALUES(cleanliness),checker_id=VALUES(checker_id),supervisor_id=VALUES(supervisor_id)');

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $tgl = import_parse_date($r['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid/blank tanggal '" . ($r['tanggal'] ?? '') . "'."; continue; }
        $sel->execute([$dept, $tgl]);
        $existed = (bool)$sel->fetchColumn();
        $checker = import_resolve_checker($pdo, $dept, $r['checker'] ?? '', 'foreman');
        $control = import_resolve_checker($pdo, $dept, $r['control'] ?? '', 'supervisor');
        if (!empty($r['checker']) && !$checker) $res['errors'][] = "Row $line: checker '{$r['checker']}' not found (saved blank).";
        if (!empty($r['control']) && !$control) $res['errors'][] = "Row $line: control '{$r['control']}' not found (saved blank).";
        $up->execute([$dept, $tgl, import_norm_okng($r['surface_outside'] ?? ''), import_norm_okng($r['parting_line'] ?? ''),
            import_norm_okng($r['surface_upper'] ?? ''), import_norm_okng($r['cleanliness'] ?? ''), $checker, $control]);
        $existed ? $res['updated']++ : $res['created']++;
    }
    return $res;
}

// ---------------------------------------------------------------------------
// Type B: FO Pump — header + detail, grouped by date
// ---------------------------------------------------------------------------

/** Field-alias map for FO Pump (supports the F-FIP-03 header layout). */
function fopump_aliases(): array
{
    return [
        'tanggal'        => ['date', 'tanggal'],
        'employee'       => ['employee'],
        'working_time'   => ['working time', 'working_time'],
        'shift'          => ['shift'],
        'row_no'         => ['no', 'no.', 'row_no'],
        'prod_model'     => ['fo pump production model', 'production model', 'prod_model'],
        'prod_qty'       => ['fo pump production quantity', 'production quantity', 'prod_qty'],
        'assy_model'     => ['to assembly line model', 'assembly model', 'assy_model'],
        'assy_qty'       => ['to assembly line quantity', 'assembly quantity', 'assy_qty'],
        'export_model'   => ['to sparepart ptc model', 'to export ysp model', 'export model', 'export_model'],
        'export_qty'     => ['to sparepart ptc quantity', 'to export ysp quantity', 'export quantity', 'export_qty'],
        'operator'       => ['operator', 'operator_name'],
        'foreman'        => ['foreman'],
        'supervisor'     => ['supervisor'],
        'convert_prod'   => ['convert production', 'convert_prod'],
        'convert_assy'   => ['convert assembly', 'convert_assy'],
        'convert_export' => ['convert export', 'convert_export'],
        'accum_prod'     => ['acumulation production', 'accumulation production', 'accum_prod'],
        'accum_assy'     => ['acumulation assembly', 'accumulation assembly', 'accum_assy'],
        'accum_export'   => ['acumulation export', 'accumulation export', 'accum_export'],
    ];
}

/** Map raw CSV rows (alias-header keys) to the internal field names used by import_fopump_rows(). */
function fopump_normalize_csv_rows(array $rows): array
{
    $A = fopump_aliases();
    foreach ($rows as &$r) {
        $norm = [];
        foreach ($A as $field => $aliases) {
            $norm[$field] = import_val($r, $aliases);
        }
        $r = $norm;
    }
    unset($r);
    return $rows;
}

function import_fopump(PDO $pdo, int $dept, array $rows): array
{
    return import_fopump_rows($pdo, $dept, fopump_normalize_csv_rows($rows));
}

/**
 * Core FO Pump import, operating on already-normalised rows (internal field
 * names: tanggal, employee, working_time, shift, row_no, prod_model, prod_qty,
 * assy_model, assy_qty, export_model, export_qty, operator, foreman, supervisor,
 * convert_prod, convert_assy, convert_export, accum_prod, accum_assy, accum_export).
 * Shared by the CSV importer (import_fopump) and the .xlsx block reader
 * (fopump_extract_from_xlsx).
 */
function import_fopump_rows(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $groups = import_group($rows, ['tanggal']);

    $selH = $pdo->prepare('SELECT id FROM t_fopump_header WHERE department_id=? AND tanggal=?');
    $insH = $pdo->prepare('INSERT INTO t_fopump_header (department_id,tanggal,employee,working_time,shift,operator_name,foreman_id,supervisor_id,convert_prod,convert_assy,convert_export,accum_prod,accum_assy,accum_export)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $updH = $pdo->prepare('UPDATE t_fopump_header SET employee=?,working_time=?,shift=?,operator_name=?,foreman_id=?,supervisor_id=?,convert_prod=?,convert_assy=?,convert_export=?,accum_prod=?,accum_assy=?,accum_export=? WHERE id=?');
    $delD = $pdo->prepare('DELETE FROM t_fopump_detail WHERE header_id=?');
    $insD = $pdo->prepare('INSERT INTO t_fopump_detail (header_id,row_no,prod_model,prod_qty,assy_model,assy_qty,export_model,export_qty) VALUES (?,?,?,?,?,?,?,?)');

    foreach ($groups as $g) {
        $first = $g['rows'][0]['data'];
        $line = $g['rows'][0]['_line'];
        $tgl = import_parse_date($first['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid tanggal."; continue; }

        // Header values: take from the first row that has them.
        $h = fn($col) => (function () use ($g, $col) {
            foreach ($g['rows'] as $rr) { $v = trim((string)($rr['data'][$col] ?? '')); if ($v !== '') return $v; }
            return null;
        })();
        $foreman = import_resolve_checker($pdo, $dept, $h('foreman'), 'foreman');
        $supervisor = import_resolve_checker($pdo, $dept, $h('supervisor'), 'supervisor');

        $params = [$h('employee'), $h('working_time'), $h('shift'), $h('operator'), $foreman, $supervisor,
            $h('convert_prod'), $h('convert_assy'), $h('convert_export'), $h('accum_prod'), $h('accum_assy'), $h('accum_export')];

        $selH->execute([$dept, $tgl]);
        $hid = (int)$selH->fetchColumn();
        if ($hid) {
            $updH->execute(array_merge($params, [$hid]));
            $delD->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute(array_merge([$dept, $tgl], $params));
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        $auto = 0;
        foreach ($g['rows'] as $rr) {
            $d = $rr['data'];
            $hasLine = trim((string)($d['prod_model'] ?? '')) !== '' || trim((string)($d['prod_qty'] ?? '')) !== ''
                || trim((string)($d['assy_model'] ?? '')) !== '' || trim((string)($d['assy_qty'] ?? '')) !== ''
                || trim((string)($d['export_model'] ?? '')) !== '' || trim((string)($d['export_qty'] ?? '')) !== '';
            if (!$hasLine) continue;
            $rowno = (int)($d['row_no'] ?? 0);
            if ($rowno < 1) $rowno = ++$auto; else $auto = max($auto, $rowno);
            $insD->execute([$hid, $rowno, import_nz($d['prod_model'] ?? ''), import_nz($d['prod_qty'] ?? ''),
                import_nz($d['assy_model'] ?? ''), import_nz($d['assy_qty'] ?? ''),
                import_nz($d['export_model'] ?? ''), import_nz($d['export_qty'] ?? '')]);
        }
    }
    return $res;
}

/**
 * Read the printed F-FIP-03 "Daily Report FO Pump Assy" layout straight from an
 * .xlsx file (no template re-typing needed) — one or more months per file, one
 * block per date. Each block is located by its "NO" / "FO PUMP PRODUCTION"
 * header row (the anchor); every other field is read relative to that row, so
 * this tolerates blank/skipped weekend rows and minor spacing differences:
 *
 *   anchor-1  : Date (B), Employee (D), Working Time (F)
 *   anchor+1  : NO 1..9 data rows (B/C prod model+qty, D/E assy model+qty, F/G export model+qty)
 *   anchor+11 : Total row (ignored — recomputed on export)
 *   anchor+12 : Convert row (C/E/G)
 *   anchor+13 : Acumulation row (C/E/G)
 *
 * Operator/Foreman/Supervisor are signed by hand (inserted images) in the
 * source file, not typed text, so they come back blank from this reader.
 * Returns rows normalised the same way as import_fopump_rows() expects.
 */
function fopump_extract_from_xlsx(string $path): array
{
    $sheets = xlsx_read_workbook($path);
    $rows = [];
    foreach ($sheets as $sheet) {
        $cells = $sheet['cells'];
        foreach ($cells as $rowNum => $cols) {
            $colA = strtoupper(trim($cols['A'] ?? ''));
            $colB = strtoupper(trim($cols['B'] ?? ''));
            if ($colA !== 'NO' || strpos($colB, 'FO PUMP') === false) continue;
            $anchor = $rowNum;

            $dateRow = $cells[$anchor - 1] ?? [];
            $dateRaw = $dateRow['B'] ?? '';
            $tanggal = is_numeric($dateRaw) ? xlsx_serial_to_date((float) $dateRaw) : (import_parse_date((string) $dateRaw) ?? '');
            if ($tanggal === '') continue; // not a real block (or unreadable date) — skip

            $base = [
                'tanggal'      => $tanggal,
                'employee'     => $dateRow['D'] ?? '',
                'working_time' => $dateRow['F'] ?? '',
                'shift'        => '',
                'operator'     => '',
                'foreman'      => '',
                'supervisor'   => '',
            ];

            // Locate the "Total" row by scanning down from the data rows (col A
            // == "Total"), instead of assuming a fixed number of NO rows — this
            // way both the legacy 9-row F-FIP-03 scans and our own generated
            // template (currently FOPUMP_ROW_COUNT rows) parse correctly.
            $totalRow = null;
            for ($r = $anchor + 2; $r <= $anchor + 60; $r++) {
                if (strtoupper(trim($cells[$r]['A'] ?? '')) === 'TOTAL') { $totalRow = $r; break; }
            }
            if ($totalRow === null) continue; // malformed/unrecognised block — skip
            $lineCount = $totalRow - $anchor - 2; // number of NO data rows actually present in this file

            // Convert=Total+1, Acumulation=Total+2, signature labels=Total+3, values=Total+4.
            $convertRow = $cells[$totalRow + 1] ?? [];
            $accumRow = $cells[$totalRow + 2] ?? [];
            $base['convert_prod'] = $convertRow['C'] ?? '';
            $base['convert_assy'] = $convertRow['E'] ?? '';
            $base['convert_export'] = $convertRow['G'] ?? '';
            $base['accum_prod'] = $accumRow['C'] ?? '';
            $base['accum_assy'] = $accumRow['E'] ?? '';
            $base['accum_export'] = $accumRow['G'] ?? '';
            // Operator/Foreman/Supervisor row (labels at Total+3, values at
            // Total+4 in our own generated template — see fopump_build_template_xlsx()).
            // On the original printed F-FIP-03 these are hand-signed images, so
            // real scanned files simply have no text here and this stays blank.
            $opRow = $cells[$totalRow + 4] ?? [];
            $base['operator'] = $opRow['B'] ?? '';
            $base['foreman'] = $opRow['D'] ?? '';
            $base['supervisor'] = $opRow['F'] ?? '';

            $any = false;
            for ($no = 1; $no <= $lineCount; $no++) {
                $d = $cells[$anchor + 1 + $no] ?? [];
                $line = array_merge($base, [
                    'row_no'       => (string) $no,
                    'prod_model'   => $d['B'] ?? '',
                    'prod_qty'     => $d['C'] ?? '',
                    'assy_model'   => $d['D'] ?? '',
                    'assy_qty'     => $d['E'] ?? '',
                    'export_model' => $d['F'] ?? '',
                    'export_qty'   => $d['G'] ?? '',
                ]);
                if ($line['prod_model'] === '' && $line['prod_qty'] === '' && $line['assy_model'] === ''
                    && $line['assy_qty'] === '' && $line['export_model'] === '' && $line['export_qty'] === '') {
                    continue; // skip empty NO rows entirely
                }
                $rows[] = $line;
                $any = true;
            }
            if (!$any) {
                // No production lines for this date. If nothing else was filled in
                // either (employee/shift/convert — e.g. a blank Sat/Sun block left
                // untouched in the template), skip the date entirely rather than
                // importing an empty report. Acumulation/Total are excluded from
                // this check since they're auto-computed formulas that carry a
                // value forward even on a day nothing was typed. Operator/Foreman/
                // Supervisor are excluded too: fopump_build_template_xlsx() pre-fills
                // default names on every day block, so they can't be used to tell a
                // worked day apart from a blank one.
                $hasOtherData = false;
                foreach (['employee', 'working_time', 'shift',
                          'convert_prod', 'convert_assy', 'convert_export'] as $k) {
                    if (trim((string) ($base[$k] ?? '')) !== '') { $hasOtherData = true; break; }
                }
                if ($hasOtherData) {
                    $rows[] = array_merge($base, ['row_no' => '1', 'prod_model' => '', 'prod_qty' => '',
                        'assy_model' => '', 'assy_qty' => '', 'export_model' => '', 'export_qty' => '']);
                }
            }
        }
    }
    return $rows;
}

/**
 * Build a blank .xlsx template that mirrors the printed F-FIP-03 block layout
 * (Date/Employee/Working Time row, NO 1-N table grouped by Model/Quantity,
 * Total/Convert/Acumulation rows, Operator/Foreman/Supervisor row) instead of
 * one wide flat row per line — this is what fopump_extract_from_xlsx() reads
 * back in, so "download, fill in Excel exactly like the paper form, upload"
 * round-trips cleanly. N = FOPUMP_ROW_COUNT production-line rows per block.
 *
 * One block is generated per calendar day of the given month (1 .. last day,
 * per the real $year/$month calendar — e.g. 28/29/30/31 as appropriate), with
 * the Date cell already filled in, so the user only has to type production
 * data, not build the block structure or work out dates themselves.
 */
function fopump_build_template_xlsx(?int $year = null, ?int $month = null): string
{
    $year = $year ?: (int) date('Y');
    $month = $month ?: (int) date('n');
    $month = max(1, min(12, $month));
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
    $n = FOPUMP_ROW_COUNT;

    $rows = [];
    $B = fn($v) => ['v' => $v, 'b' => true];
    $N = fn($v = '') => ['v' => $v];

    $rows[1] = ['A' => $B("Daily Report - FO Pump Assy — $monthLabel"),
                'D' => $N("One block per day (1-$daysInMonth), Date pre-filled to the $year calendar. Total = SUM of that day's Quantity; Acumulation = previous day's Acumulation + that day's Total.")];

    $merges = [];
    // Rows used per block: date row + anchor row + sub-header + N data rows +
    // Total + Convert + Acumulation + signature labels + signature values,
    // plus one blank spacer row before the next block.
    $blockHeight = $n + 9;
    $anchor1 = 3;

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $anchor = $anchor1 + ($day - 1) * $blockHeight;
        $tanggal = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // Date / Employee / Working Time — read from row (anchor-1): B=date, D=employee, F=working time.
        $rows[$anchor - 1] = [
            'A' => $B('Date :'), 'B' => $N($tanggal),
            'C' => $B('Employee:'), 'D' => $N(''),
            'E' => $B('Working Time:'), 'F' => $N(''),
        ];
        // Anchor row: A=NO, B:C="FO PUMP PRODUCTION", D:E="TO ASSEMBLY LINE", F:G="TO SPAREPART PTC".
        $rows[$anchor] = [
            'A' => $B('NO'), 'B' => $B('FO PUMP PRODUCTION'), 'D' => $B('TO ASSEMBLY LINE'), 'F' => $B('TO SPAREPART PTC'),
        ];
        $rows[$anchor + 1] = [
            'B' => $B('Model'), 'C' => $B('Quantity'), 'D' => $B('Model'), 'E' => $B('Quantity'), 'F' => $B('Model'), 'G' => $B('Quantity'),
        ];
        for ($no = 1; $no <= $n; $no++) {
            $r = $anchor + 1 + $no;
            $rows[$r] = ['A' => $N((string) $no), 'B' => $N(''), 'C' => $N(''), 'D' => $N(''), 'E' => $N(''), 'F' => $N(''), 'G' => $N('')];
        }
        // Total — auto-summed from the N Quantity cells of this block.
        $qtyFirst = $anchor + 2;
        $qtyLast = $anchor + 1 + $n;
        $totalRow = $anchor + $n + 2;
        $accumRow = $anchor + $n + 4;
        // Each group (FO Pump Production / To Assembly Line / To Sparepart PTC)
        // gets its own independent Total/Acumulation.
        $rows[$totalRow] = [
            'A' => $B('Total'),
            'C' => ['f' => "SUM(C$qtyFirst:C$qtyLast)"],
            'E' => ['f' => "SUM(E$qtyFirst:E$qtyLast)"],
            'G' => ['f' => "SUM(G$qtyFirst:G$qtyLast)"],
        ];
        $rows[$anchor + $n + 3] = ['A' => $B('Convert'), 'C' => $N(''), 'E' => $N(''), 'G' => $N('')];
        // Acumulation — running total: previous day's Acumulation + this day's Total (first block has no previous day).
        if ($day === 1) {
            $rows[$accumRow] = [
                'A' => $B('Acumulation'),
                'C' => ['f' => "C$totalRow"], 'E' => ['f' => "E$totalRow"], 'G' => ['f' => "G$totalRow"],
            ];
        } else {
            $prevAnchor = $anchor - $blockHeight;
            $prevAccumRow = $prevAnchor + $n + 4;
            $rows[$accumRow] = [
                'A' => $B('Acumulation'),
                'C' => ['f' => "C$prevAccumRow+C$totalRow"],
                'E' => ['f' => "E$prevAccumRow+E$totalRow"],
                'G' => ['f' => "G$prevAccumRow+G$totalRow"],
            ];
        }
        $rows[$anchor + $n + 5] = ['B' => $B('Operator'), 'D' => $B('Foreman'), 'F' => $B('Supervisor')];
        // Operator/Foreman/Supervisor default to "Reza Kurnia S"/"Trisna"/"Mita" on
        // every day block. Operator is stored as free text either way; Foreman and
        // Supervisor must still match an existing name in Checked By master data
        // (matching role) to link on import.
        $rows[$anchor + $n + 6] = ['B' => $N('Reza Kurnia S'), 'D' => $N('Trisna'), 'F' => $N('Mita')];

        $merges[] = "B$anchor:C$anchor";
        $merges[] = "D$anchor:E$anchor";
        $merges[] = "F$anchor:G$anchor";
    }

    $colWidths = ['A' => 8, 'B' => 16, 'C' => 12, 'D' => 16, 'E' => 12, 'F' => 16, 'G' => 12];

    return xlsx_write_workbook($rows, $merges, $colWidths, 'FO Pump Assy', 'G');
}

// ---------------------------------------------------------------------------
// Type C: Torque & Painting — header + dynamic checklist detail
// ---------------------------------------------------------------------------

function import_torque(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $groups = import_group($rows, ['tanggal', 'model']);

    $selH = $pdo->prepare('SELECT id FROM t_assy_header WHERE department_id=? AND tanggal=? AND model_id=?');
    $insH = $pdo->prepare('INSERT INTO t_assy_header (tanggal,department_id,model_id,mark_crank_shaft,mark_conrod,mark_fo_pump,no_cyl_block,no_engine,detail_model,checker_id,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,\'submitted\')');
    $updH = $pdo->prepare('UPDATE t_assy_header SET mark_crank_shaft=?,mark_conrod=?,mark_fo_pump=?,no_cyl_block=?,no_engine=?,detail_model=?,checker_id=?,status=\'submitted\' WHERE id=?');
    $delD = $pdo->prepare('DELETE FROM t_assy_detail WHERE header_id=?');
    $insD = $pdo->prepare('INSERT INTO t_assy_detail (header_id,checklist_item_id,actual_result,consumable_item) VALUES (?,?,?,?)');
    $selItem = $pdo->prepare('SELECT id FROM m_assy_checklist_item WHERE model_id=? AND LOWER(checking_item)=LOWER(?) LIMIT 1');
    $selModel = $pdo->prepare('SELECT id FROM m_assy_model WHERE department_id=? AND LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');

    foreach ($groups as $g) {
        $first = $g['rows'][0]['data'];
        $line = $g['rows'][0]['_line'];
        $tgl = import_parse_date($first['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid tanggal."; continue; }
        $selModel->execute([$dept, $first['model'] ?? '']);
        $model_id = (int)$selModel->fetchColumn();
        if (!$model_id) { $res['skipped']++; $res['errors'][] = "Row $line: model '" . ($first['model'] ?? '') . "' not found."; continue; }

        $checker = import_resolve_checker($pdo, $dept, $first['checker'] ?? '');
        if (!$checker) {
            // checker is required (NOT NULL) on t_assy_header — a blank or
            // unmatched name can't just be "saved blank" like elsewhere, or the
            // insert/update fails and aborts the whole import batch.
            $res['skipped']++;
            $res['errors'][] = "Row $line: checker '" . ($first['checker'] ?? '') . "' not found or blank — checker is required, row skipped.";
            continue;
        }

        $params = [import_nz($first['mark_crank_shaft'] ?? ''), import_nz($first['mark_conrod'] ?? ''), import_nz($first['mark_fo_pump'] ?? ''),
            import_nz($first['no_cyl_block'] ?? ''), import_nz($first['no_engine'] ?? ''), import_nz($first['detail_model'] ?? ''), $checker];

        $selH->execute([$dept, $tgl, $model_id]);
        $hid = (int)$selH->fetchColumn();
        if ($hid) {
            $updH->execute(array_merge($params, [$hid]));
            $delD->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute([$tgl, $dept, $model_id, $params[0], $params[1], $params[2], $params[3], $params[4], $params[5], $params[6]]);
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        foreach ($g['rows'] as $rr) {
            $item = trim((string)($rr['data']['checking_item'] ?? ''));
            if ($item === '') continue;
            $selItem->execute([$model_id, $item]);
            $iid = (int)$selItem->fetchColumn();
            if (!$iid) { $res['errors'][] = "Row {$rr['_line']}: checking_item '$item' not found for model '{$first['model']}' (skipped)."; continue; }
            $insD->execute([$hid, $iid, import_nz($rr['data']['actual_result'] ?? ''), import_nz($rr['data']['consumable_item'] ?? '')]);
        }
    }
    return $res;
}

/** Field-alias map for Painting (supports the PowerApps header layout). */
function painting_aliases(): array
{
    return [
        'date'          => ['date', 'tanggal'],
        'condition'     => ['condition', 'kondisi'],
        'checking_item' => ['checking item', 'checking_item'],
        'shift'         => ['shift'],
        'jam'           => ['jam', 'time', 'waktu'],
        'actual_result' => ['actual result', 'actual_result', 'actual'],
        'category'      => ['category', 'kategori'],
        'checker'       => ['checked by :', 'checked by', 'checker'],
    ];
}

function import_painting(PDO $pdo, int $dept, array $rows): array
{
    $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    $A = painting_aliases();
    // Normalise each row into internal keys so grouping/lookup is uniform.
    foreach ($rows as &$r) {
        $r = [
            'tanggal'       => import_val($r, $A['date']),
            'condition'     => import_val($r, $A['condition']),
            'checking_item' => import_val($r, $A['checking_item']),
            'shift'         => import_val($r, $A['shift']),
            'jam'           => import_val($r, $A['jam']),
            'actual_result' => import_val($r, $A['actual_result']),
            'category'      => import_val($r, $A['category']),
            'checker'       => import_val($r, $A['checker']),
        ];
    }
    unset($r);
    $groups = import_group($rows, ['tanggal', 'condition', 'jam']);

    $selH = $pdo->prepare('SELECT id FROM t_checksheet_header WHERE department_id=? AND tanggal=? AND condition_id=? AND jam <=> ?');
    $insH = $pdo->prepare('INSERT INTO t_checksheet_header (tanggal,condition_id,department_id,checker_id,jam,shift_id,status) VALUES (?,?,?,?,?,?,\'submitted\')');
    $updH = $pdo->prepare('UPDATE t_checksheet_header SET checker_id=?,shift_id=?,status=\'submitted\' WHERE id=?');
    $delD = $pdo->prepare('DELETE FROM t_checksheet_detail WHERE header_id=?');
    $insD = $pdo->prepare('INSERT INTO t_checksheet_detail (header_id,checklist_item_id,actual_result,category) VALUES (?,?,?,?)');
    $selCond = $pdo->prepare('SELECT id FROM m_condition WHERE department_id=? AND LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');
    $selItem = $pdo->prepare('SELECT id FROM m_checklist_item WHERE condition_id=? AND LOWER(checking_item)=LOWER(?) LIMIT 1');
    $selShift = $pdo->prepare('SELECT id FROM m_shift WHERE LOWER(name)=LOWER(?) AND is_active=1 LIMIT 1');

    foreach ($groups as $g) {
        $first = $g['rows'][0]['data'];
        $line = $g['rows'][0]['_line'];
        $tgl = import_parse_date($first['tanggal'] ?? '');
        if (!$tgl) { $res['skipped']++; $res['errors'][] = "Row $line: invalid tanggal."; continue; }
        $selCond->execute([$dept, $first['condition'] ?? '']);
        $cond_id = (int)$selCond->fetchColumn();
        if (!$cond_id) { $res['skipped']++; $res['errors'][] = "Row $line: condition '" . ($first['condition'] ?? '') . "' not found."; continue; }

        $jam = import_parse_time($first['jam'] ?? '');
        $checker = import_resolve_checker($pdo, $dept, $first['checker'] ?? '');
        if (!$checker) {
            // Checked By is required (NOT NULL) on t_checksheet_header — a blank or
            // unmatched name can't just be "saved blank" like elsewhere, or the
            // insert/update fails and aborts the whole import batch.
            $res['skipped']++;
            $res['errors'][] = "Row $line: Checked By '" . ($first['checker'] ?? '') . "' not found or blank — Checked By is required, row skipped.";
            continue;
        }
        $shift_id = null;
        if (!empty($first['shift'])) {
            $selShift->execute([$first['shift']]);
            $shift_id = (int)$selShift->fetchColumn() ?: null;
        }
        if (!$shift_id) {
            // shift is required (NOT NULL) on t_checksheet_header — a blank or
            // unmatched shift name can't just be "saved blank", or the
            // insert/update fails and aborts the whole import batch.
            $res['skipped']++;
            $res['errors'][] = "Row $line: shift '" . ($first['shift'] ?? '') . "' not found or blank — shift is required, row skipped.";
            continue;
        }

        $selH->execute([$dept, $tgl, $cond_id, $jam]);
        $hid = (int)$selH->fetchColumn();
        if ($hid) {
            $updH->execute([$checker, $shift_id, $hid]);
            $delD->execute([$hid]);
            $res['updated']++;
        } else {
            $insH->execute([$tgl, $cond_id, $dept, $checker, $jam, $shift_id]);
            $hid = (int)$pdo->lastInsertId();
            $res['created']++;
        }

        foreach ($g['rows'] as $rr) {
            $item = trim((string)($rr['data']['checking_item'] ?? ''));
            if ($item === '') continue;
            $selItem->execute([$cond_id, $item]);
            $iid = (int)$selItem->fetchColumn();
            if (!$iid) { $res['errors'][] = "Row {$rr['_line']}: checking_item '$item' not found for condition '{$first['condition']}' (skipped)."; continue; }
            $insD->execute([$hid, $iid, import_nz($rr['data']['actual_result'] ?? ''), import_nz($rr['data']['category'] ?? '')]);
        }
    }
    return $res;
}

// ===========================================================================
// EXPORT — each returns a list of assoc rows keyed by the section's template
// column headers (same order/labels as import_registry()['template']).
// ===========================================================================

function export_washing(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT e.tanggal, e.ganti_air, e.temperatur_air, e.penambahan_gildaon, e.total_acid,
                ck.name AS checker, sv.name AS control
         FROM t_washing_entry e
         LEFT JOIN m_checker ck ON ck.id = e.checker_id
         LEFT JOIN m_checker sv ON sv.id = e.supervisor_id
         WHERE e.department_id = ? ORDER BY e.tanggal'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'tanggal' => $r['tanggal'], 'ganti_air' => $r['ganti_air'], 'temperatur_air' => $r['temperatur_air'],
            'penambahan_gildaon' => $r['penambahan_gildaon'], 'total_acid' => $r['total_acid'],
            'checker' => $r['checker'], 'control' => $r['control'],
        ];
    }
    return $out;
}

function export_subassy(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT e.tanggal, e.surface_outside, e.parting_line, e.surface_upper, e.cleanliness,
                ck.name AS checker, sv.name AS control
         FROM t_subassy_entry e
         LEFT JOIN m_checker ck ON ck.id = e.checker_id
         LEFT JOIN m_checker sv ON sv.id = e.supervisor_id
         WHERE e.department_id = ? ORDER BY e.tanggal'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'tanggal' => $r['tanggal'], 'surface_outside' => $r['surface_outside'], 'parting_line' => $r['parting_line'],
            'surface_upper' => $r['surface_upper'], 'cleanliness' => $r['cleanliness'],
            'checker' => $r['checker'], 'control' => $r['control'],
        ];
    }
    return $out;
}

function export_fopump(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, fm.name AS foreman_name, sv.name AS supervisor_name
         FROM t_fopump_header h
         LEFT JOIN m_checker fm ON fm.id = h.foreman_id
         LEFT JOIN m_checker sv ON sv.id = h.supervisor_id
         WHERE h.department_id = ? ORDER BY h.tanggal'
    );
    $stmt->execute([$dept]);
    $dstmt = $pdo->prepare('SELECT * FROM t_fopump_detail WHERE header_id = ? ORDER BY row_no');
    $num = fn($v) => is_numeric(trim((string)$v)) ? (float)$v : 0;
    $fmt = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.') ?: '0';
    $out = [];
    foreach ($stmt->fetchAll() as $h) {
        $dstmt->execute([$h['id']]);
        $details = $dstmt->fetchAll();
        $tp = $ta = $te = 0;
        foreach ($details as $d) {
            $tp += $num($d['prod_qty']);
            $ta += $num($d['assy_qty']);
            $te += $num($d['export_qty']);
        }
        $base = [
            'Employee' => $h['employee'], 'Working Time' => $h['working_time'], 'Shift' => $h['shift'],
            'Total Production' => $fmt($tp), 'Total Assembly' => $fmt($ta), 'Total Export' => $fmt($te),
            'Convert Production' => $h['convert_prod'], 'Convert Assembly' => $h['convert_assy'], 'Convert Export' => $h['convert_export'],
            'Acumulation Production' => $h['accum_prod'], 'Acumulation Assembly' => $h['accum_assy'], 'Acumulation Export' => $h['accum_export'],
            'Operator' => $h['operator_name'], 'Foreman' => $h['foreman_name'], 'Supervisor' => $h['supervisor_name'],
        ];
        if (!$details) $details = [['row_no' => '', 'prod_model' => '', 'prod_qty' => '', 'assy_model' => '', 'assy_qty' => '', 'export_model' => '', 'export_qty' => '']];
        foreach ($details as $d) {
            $out[] = array_merge([
                'Date' => $h['tanggal'], 'NO' => $d['row_no'],
                'FO Pump Production Model' => $d['prod_model'], 'FO Pump Production Quantity' => $d['prod_qty'],
                'To Assembly Line Model' => $d['assy_model'], 'To Assembly Line Quantity' => $d['assy_qty'],
                'To Sparepart PTC Model' => $d['export_model'], 'To Sparepart PTC Quantity' => $d['export_qty'],
            ], $base);
        }
    }
    return $out;
}

function export_torque(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT h.*, m.name AS model_name, ck.name AS checker_name,
                d.actual_result, d.consumable_item, ci.checking_item, ci.sort_order AS so
         FROM t_assy_header h
         JOIN t_assy_detail d ON d.header_id = h.id
         JOIN m_assy_checklist_item ci ON ci.id = d.checklist_item_id
         JOIN m_assy_model m ON m.id = h.model_id
         LEFT JOIN m_checker ck ON ck.id = h.checker_id
         WHERE h.department_id = ? ORDER BY h.tanggal, h.id, ci.sort_order'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'tanggal' => $r['tanggal'], 'model' => $r['model_name'], 'checker' => $r['checker_name'],
            'mark_crank_shaft' => $r['mark_crank_shaft'], 'mark_conrod' => $r['mark_conrod'], 'mark_fo_pump' => $r['mark_fo_pump'],
            'no_cyl_block' => $r['no_cyl_block'], 'no_engine' => $r['no_engine'], 'detail_model' => $r['detail_model'],
            'checking_item' => $r['checking_item'], 'actual_result' => $r['actual_result'], 'consumable_item' => $r['consumable_item'],
        ];
    }
    return $out;
}

function export_painting(PDO $pdo, int $dept): array
{
    $stmt = $pdo->prepare(
        'SELECT h.tanggal, h.jam, c.name AS cond_name, sh.name AS shift_name, ck.name AS checker_name,
                ci.checking_item, ci.metode_pengecekan, ci.standard_min, ci.standard_max, ci.satuan, ci.tank_tube, ci.sort_order AS so,
                d.actual_result, d.category
         FROM t_checksheet_header h
         JOIN t_checksheet_detail d ON d.header_id = h.id
         JOIN m_checklist_item ci ON ci.id = d.checklist_item_id
         JOIN m_condition c ON c.id = h.condition_id
         LEFT JOIN m_shift sh ON sh.id = h.shift_id
         LEFT JOIN m_checker ck ON ck.id = h.checker_id
         WHERE h.department_id = ? ORDER BY h.tanggal, h.id, ci.sort_order'
    );
    $stmt->execute([$dept]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[] = [
            'Condition' => $r['cond_name'], 'Date' => $r['tanggal'], 'Checking Item' => $r['checking_item'],
            'Metode Pengecekkan' => $r['metode_pengecekan'], 'Standard Min.' => $r['standard_min'], 'Standard Max.' => $r['standard_max'],
            'Satuan' => $r['satuan'], 'Shift' => $r['shift_name'], 'Jam' => $r['jam'] ? substr($r['jam'], 0, 5) : '',
            'Tank/Tube' => $r['tank_tube'], 'Actual Result' => $r['actual_result'], 'Category' => $r['category'],
            'Checked By :' => $r['checker_name'],
        ];
    }
    return $out;
}
