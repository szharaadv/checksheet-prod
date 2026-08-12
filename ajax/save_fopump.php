<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$department_id = (int)($input['department_id'] ?? 0);
$tanggal       = $input['tanggal'] ?? '';
$rows          = $input['rows'] ?? [];

// Validate date: must be a real date, not in the future (backdate allowed).
$today = date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
$validDate = $d && $d->format('Y-m-d') === $tanggal;

if (!$department_id || !$validDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing department or invalid date.']);
    exit;
}
if ($tanggal > $today) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot save a report for a future date.']);
    exit;
}

$nz = fn($v) => ($v === '' || $v === null) ? null : $v;

try {
    $pdo->beginTransaction();

    $params = [
        $nz($input['employee'] ?? null),
        $nz($input['working_time'] ?? null),
        $nz($input['shift'] ?? null),
        $nz($input['operator_name'] ?? null),
        ($input['foreman_id'] ?? null) ?: null,
        ($input['supervisor_id'] ?? null) ?: null,
        $nz($input['convert_prod'] ?? null),
        $nz($input['convert_assy'] ?? null),
        $nz($input['convert_export'] ?? null),
        $nz($input['accum_prod'] ?? null),
        $nz($input['accum_assy'] ?? null),
        $nz($input['accum_export'] ?? null),
    ];

    // Upsert header by (department_id, tanggal).
    $stmt = $pdo->prepare('SELECT id FROM t_fopump_header WHERE department_id = ? AND tanggal = ?');
    $stmt->execute([$department_id, $tanggal]);
    $header_id = (int)$stmt->fetchColumn();

    if ($header_id) {
        $stmt = $pdo->prepare(
            'UPDATE t_fopump_header SET
                employee=?, working_time=?, shift=?, operator_name=?, foreman_id=?, supervisor_id=?,
                convert_prod=?, convert_assy=?, convert_export=?, accum_prod=?, accum_assy=?, accum_export=?
             WHERE id=?'
        );
        $stmt->execute(array_merge($params, [$header_id]));
        $pdo->prepare('DELETE FROM t_fopump_detail WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fopump_header
                (department_id, tanggal, employee, working_time, shift, operator_name, foreman_id, supervisor_id,
                 convert_prod, convert_assy, convert_export, accum_prod, accum_assy, accum_export)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute(array_merge([$department_id, $tanggal], $params));
        $header_id = (int)$pdo->lastInsertId();
    }

    $detailStmt = $pdo->prepare(
        'INSERT INTO t_fopump_detail
            (header_id, row_no, prod_model, prod_qty, assy_model, assy_qty, export_model, export_qty)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($rows as $row) {
        $row_no = (int)($row['row_no'] ?? 0);
        if ($row_no < 1) continue;
        $detailStmt->execute([
            $header_id,
            $row_no,
            $nz($row['prod_model'] ?? null),
            $nz($row['prod_qty'] ?? null),
            $nz($row['assy_model'] ?? null),
            $nz($row['assy_qty'] ?? null),
            $nz($row['export_model'] ?? null),
            $nz($row['export_qty'] ?? null),
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
