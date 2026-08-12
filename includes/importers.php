<?php
require_once __DIR__ . '/import_lib.php';

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
        ],
        'subassy_list.php' => [
            'label'    => 'Sub Assembly',
            'template' => ['tanggal', 'surface_outside', 'parting_line', 'surface_upper', 'cleanliness', 'checker', 'control'],
            'note'     => 'One row per date. Check columns accept OK / NG (also V / X). checker = Foreman, control = Supervisor.',
            'fn'       => 'import_subassy',
            'export'   => 'export_subassy',
        ],
        'fopump_list.php' => [
            'label'    => 'FO Pump Assy',
            'template' => ['tanggal', 'row_no', 'prod_model', 'prod_qty', 'assy_model', 'assy_qty', 'export_model', 'export_qty',
                           'employee', 'working_time', 'shift', 'operator', 'foreman', 'supervisor',
                           'convert_prod', 'convert_assy', 'convert_export', 'accum_prod', 'accum_assy', 'accum_export'],
            'note'     => 'Multiple rows per date (one per production line, row_no 1..9). Header fields (employee, totals, signatures) repeat on each row of the same date.',
            'fn'       => 'import_fopump',
            'export'   => 'export_fopump',
        ],
        'assembly_list.php' => [
            'label'    => 'Torque (Daily Torque)',
            'template' => ['tanggal', 'model', 'checker', 'mark_crank_shaft', 'mark_conrod', 'mark_fo_pump',
                           'no_cyl_block', 'no_engine', 'detail_model', 'checking_item', 'actual_result', 'consumable_item'],
            'note'     => 'One row per checking item. Rows with the same tanggal + model form one sheet; header fields repeat. checking_item must match the Checking Item master for that model.',
            'fn'       => 'import_torque',
            'export'   => 'export_torque',
        ],
        'painting_list.php' => [
            'label'    => 'Painting Checklist',
            // Columns match the PowerApps export layout. Descriptive columns
            // (Metode Pengecekkan, Standard Min./Max., Satuan, Tank/Tube) come
            // from master data — ignored on import, filled on export.
            'template' => ['Condition', 'Date', 'Checking Item', 'Metode Pengecekkan', 'Standard Min.', 'Standard Max.',
                           'Satuan', 'Shift', 'Jam', 'Tank/Tube', 'Actual Result', 'Category', 'Checked By :'],
            'note'     => 'One row per checking item. Rows with the same Date + Condition + Jam form one sheet; header fields repeat. Condition / Checking Item must match master data. Descriptive columns (Metode, Standard, Satuan, Tank/Tube) are ignored on import.',
            'fn'       => 'import_painting',
            'export'   => 'export_painting',
        ],
    ];
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

function import_fopump(PDO $pdo, int $dept, array $rows): array
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
        if (!empty($first['checker']) && !$checker) $res['errors'][] = "Row $line: checker '{$first['checker']}' not found (saved blank).";

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
        if (!empty($first['checker']) && !$checker) $res['errors'][] = "Row $line: checker '{$first['checker']}' not found (saved blank).";
        $shift_id = null;
        if (!empty($first['shift'])) {
            $selShift->execute([$first['shift']]);
            $shift_id = (int)$selShift->fetchColumn() ?: null;
            if (!$shift_id) $res['errors'][] = "Row $line: shift '{$first['shift']}' not found (saved blank).";
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
    $out = [];
    foreach ($stmt->fetchAll() as $h) {
        $base = [
            'employee' => $h['employee'], 'working_time' => $h['working_time'], 'shift' => $h['shift'],
            'operator' => $h['operator_name'], 'foreman' => $h['foreman_name'], 'supervisor' => $h['supervisor_name'],
            'convert_prod' => $h['convert_prod'], 'convert_assy' => $h['convert_assy'], 'convert_export' => $h['convert_export'],
            'accum_prod' => $h['accum_prod'], 'accum_assy' => $h['accum_assy'], 'accum_export' => $h['accum_export'],
        ];
        $dstmt->execute([$h['id']]);
        $details = $dstmt->fetchAll();
        if (!$details) $details = [['row_no' => '', 'prod_model' => '', 'prod_qty' => '', 'assy_model' => '', 'assy_qty' => '', 'export_model' => '', 'export_qty' => '']];
        foreach ($details as $d) {
            $out[] = array_merge([
                'tanggal' => $h['tanggal'], 'row_no' => $d['row_no'],
                'prod_model' => $d['prod_model'], 'prod_qty' => $d['prod_qty'],
                'assy_model' => $d['assy_model'], 'assy_qty' => $d['assy_qty'],
                'export_model' => $d['export_model'], 'export_qty' => $d['export_qty'],
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
