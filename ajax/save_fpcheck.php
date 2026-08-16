<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$section_id = (int)($input['section_id'] ?? 0);
$variant_id = (int)($input['variant_id'] ?? 0) ?: null;
$header_id  = (int)($input['header_id'] ?? 0);
$status     = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$tanggal    = $input['tanggal'] ?? '';
$columns    = $input['columns'] ?? [];
$cells      = $input['cells'] ?? [];

$today = date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
$validDate = $d && $d->format('Y-m-d') === $tanggal;

if (!$section_id || !$validDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing section or invalid date.']);
    exit;
}
if ($tanggal > $today) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot save a future-dated check sheet.']);
    exit;
}

$nz = fn($v) => (trim((string)($v ?? '')) === '') ? null : trim((string)$v);

try {
    $pdo->beginTransaction();

    $params = [
        $variant_id, $tanggal,
        $nz($input['model'] ?? null), $nz($input['p_code'] ?? null), $nz($input['part_no'] ?? null),
        $nz($input['prod_date'] ?? null), $nz($input['check_method'] ?? null),
        $nz($input['checker'] ?? null), $nz($input['foreman'] ?? null), $nz($input['supervisor'] ?? null),
    ];

    // Update an existing draft (only drafts are editable); otherwise insert new.
    $existingDraft = false;
    if ($header_id) {
        $chk = $pdo->prepare("SELECT status FROM t_fpcs_header WHERE id = ? AND section_id = ?");
        $chk->execute([$header_id, $section_id]);
        $existingDraft = $chk->fetchColumn() === 'draft';
    }

    if ($existingDraft) {
        $stmt = $pdo->prepare(
            'UPDATE t_fpcs_header
             SET variant_id=?, tanggal=?, model=?, p_code=?, part_no=?, prod_date=?, check_method=?, checker=?, foreman=?, supervisor=?, status=?
             WHERE id=?'
        );
        $stmt->execute(array_merge($params, [$status, $header_id]));
        $pdo->prepare('DELETE FROM t_fpcs_column WHERE header_id=?')->execute([$header_id]);
        $pdo->prepare('DELETE FROM t_fpcs_cell WHERE header_id=?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fpcs_header
                (section_id, variant_id, tanggal, model, p_code, part_no, prod_date, check_method, checker, foreman, supervisor, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute(array_merge([$section_id], $params, [$status]));
        $header_id = (int)$pdo->lastInsertId();
    }

    $colStmt = $pdo->prepare('INSERT INTO t_fpcs_column (header_id, col_index, label) VALUES (?,?,?)');
    foreach ($columns as $c) {
        $ci = (int)($c['col_index'] ?? 0);
        if ($ci < 1) continue;
        $colStmt->execute([$header_id, $ci, $nz($c['label'] ?? null)]);
    }

    $cellStmt = $pdo->prepare('INSERT INTO t_fpcs_cell (header_id, point_id, col_index, value) VALUES (?,?,?,?)');
    foreach ($cells as $cell) {
        $pid = (int)($cell['point_id'] ?? 0);
        $ci = (int)($cell['col_index'] ?? 0);
        $val = $nz($cell['value'] ?? null);
        if ($pid < 1 || $ci < 1 || $val === null) continue;
        $cellStmt->execute([$header_id, $pid, $ci, $val]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
