<?php
/**
 * Shared helpers for CSV data import (migrating PowerApps data in).
 */

/** Read a CSV file into rows of assoc arrays keyed by normalised header names. */
function csv_read(string $path): array
{
    $rows = [];
    if (($h = fopen($path, 'r')) === false) {
        return $rows;
    }
    // Strip UTF-8 BOM if present on the first field.
    $header = null;
    while (($data = fgetcsv($h, 0, ',')) !== false) {
        if ($header === null) {
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0] ?? '');
            $header = array_map(fn($c) => strtolower(trim((string)$c)), $data);
            continue;
        }
        // Skip fully empty lines.
        if (count(array_filter($data, fn($c) => trim((string)$c) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            if ($col === '') continue;
            $row[$col] = isset($data[$i]) ? trim((string)$data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($h);
    return $rows;
}

/** Parse a date string in several common formats; returns Y-m-d or null. */
function import_parse_date(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd/m/y', 'Y/m/d', 'j/n/Y', 'n/j/Y'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        if ($d && $d->format($fmt) === $s) {
            return $d->format('Y-m-d');
        }
    }
    // Last resort: let strtotime try (handles e.g. "1 Aug 2026").
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Parse a time string (HH:MM[:SS]); returns H:i:s or null. */
function import_parse_time(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    foreach (['H:i:s', 'H:i', 'G:i'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s);
        if ($d) return $d->format('H:i:s');
    }
    return null;
}

/** Resolve a checker/foreman/supervisor by name within a department. */
function import_resolve_checker(PDO $pdo, int $department_id, ?string $name, ?string $role = null): ?int
{
    $name = trim((string)$name);
    if ($name === '') return null;
    $sql = 'SELECT id FROM m_checker WHERE department_id = ? AND LOWER(name) = LOWER(?) AND is_active = 1';
    $params = [$department_id, $name];
    if ($role !== null) {
        $sql .= ' AND role = ?';
        $params[] = $role;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/** Normalise an OK/NG check value. Returns 'OK', 'NG', or null. */
function import_norm_okng(?string $v): ?string
{
    $v = strtoupper(trim((string)$v));
    if ($v === '') return null;
    if (in_array($v, ['OK', 'O', 'V', '✓', 'GOOD', 'G'], true)) return 'OK';
    if (in_array($v, ['NG', 'X', '✗', 'BAD', 'N'], true)) return 'NG';
    return in_array($v, ['OK', 'NG'], true) ? $v : null;
}

/** Empty string to null. */
function import_nz($v)
{
    return ($v === '' || $v === null) ? null : $v;
}

/**
 * Read a value from a CSV row by trying several header aliases (case/space
 * tolerant). Returns the first non-empty match, or '' if none present.
 * Row keys are already lowercased/trimmed by csv_read().
 */
function import_val(array $row, array $aliases): string
{
    foreach ($aliases as $a) {
        $k = strtolower(trim($a));
        if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') {
            return trim((string)$row[$k]);
        }
    }
    return '';
}
