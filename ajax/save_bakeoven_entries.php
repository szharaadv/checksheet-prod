<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$department_id = (int)($input['department_id'] ?? 0);
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);
$rows = $input['rows'] ?? [];

if (!$department_id || !$month || !$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing department, month, or year.']);
    exit;
}

$timeKeys = ['0700', '0900', '1100', '1300', '1400', '1630'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO t_bakeoven_month (department_id, month, year, asst_foreman_id, foreman_id, supervisor_id, keterangan)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            asst_foreman_id = VALUES(asst_foreman_id),
            foreman_id = VALUES(foreman_id),
            supervisor_id = VALUES(supervisor_id),
            keterangan = VALUES(keterangan)'
    );
    $stmt->execute([
        $department_id,
        $month,
        $year,
        $input['asst_foreman_id'] ?: null,
        $input['foreman_id'] ?: null,
        $input['supervisor_id'] ?: null,
        ($input['keterangan'] ?? '') !== '' ? $input['keterangan'] : null,
    ]);

    $cols = implode(', ', array_map(fn($k) => "t_$k", $timeKeys));
    $placeholders = implode(', ', array_fill(0, count($timeKeys), '?'));
    $updateCols = implode(', ', array_map(fn($k) => "t_$k = VALUES(t_$k)", $timeKeys));

    $stmt = $pdo->prepare(
        "INSERT INTO t_bakeoven_entry (department_id, tanggal, $cols, checker_id)
         VALUES (?, ?, $placeholders, ?)
         ON DUPLICATE KEY UPDATE $updateCols, checker_id = VALUES(checker_id)"
    );

    $today = date('Y-m-d');
    foreach ($rows as $row) {
        $day = (int)($row['day'] ?? 0);
        if ($day < 1 || $day > 31) {
            continue;
        }
        $tanggal = sprintf('%04d-%02d-%02d', $year, $month, $day);
        if ($tanggal > $today) {
            continue;
        }

        $params = [$department_id, $tanggal];
        foreach ($timeKeys as $k) {
            $v = $row['t_' . $k] ?? '';
            $params[] = $v !== '' ? $v : null;
        }
        $params[] = $row['checker_id'] ?: null;
        $stmt->execute($params);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
