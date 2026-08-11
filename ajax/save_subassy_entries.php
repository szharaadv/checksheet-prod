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

$normalizeCheck = function ($v) {
    $v = strtoupper(trim((string)$v));
    return in_array($v, ['OK', 'NG'], true) ? $v : null;
};

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO t_subassy_entry
            (department_id, tanggal, surface_outside, parting_line, surface_upper, cleanliness, checker_id, supervisor_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            surface_outside = VALUES(surface_outside),
            parting_line = VALUES(parting_line),
            surface_upper = VALUES(surface_upper),
            cleanliness = VALUES(cleanliness),
            checker_id = VALUES(checker_id),
            supervisor_id = VALUES(supervisor_id)'
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
        $stmt->execute([
            $department_id,
            $tanggal,
            $normalizeCheck($row['surface_outside'] ?? null),
            $normalizeCheck($row['parting_line'] ?? null),
            $normalizeCheck($row['surface_upper'] ?? null),
            $normalizeCheck($row['cleanliness'] ?? null),
            $row['checker_id'] ?: null,
            $row['supervisor_id'] ?: null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
