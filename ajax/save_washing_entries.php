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

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO t_washing_entry
            (department_id, tanggal, ganti_air, temperatur_air, penambahan_gildaon, total_acid, checker_id, supervisor_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ganti_air = VALUES(ganti_air),
            temperatur_air = VALUES(temperatur_air),
            penambahan_gildaon = VALUES(penambahan_gildaon),
            total_acid = VALUES(total_acid),
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
            $row['ganti_air'] ?: null,
            $row['temperatur_air'] ?: null,
            $row['penambahan_gildaon'] ?: null,
            $row['total_acid'] ?: null,
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
