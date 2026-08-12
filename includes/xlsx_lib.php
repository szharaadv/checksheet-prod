<?php
/**
 * Minimal .xlsx reader (no external library). Reads cell values and returns
 * them as a plain [row => [colLetter => value]] grid per sheet, so callers
 * can locate fixed-layout report blocks by scanning for anchor labels.
 */

/** Convert an Excel date/time serial number to Y-m-d (assumes 1900 date system). */
function xlsx_serial_to_date(float $serial): string
{
    $unix = (int) round(($serial - 25569) * 86400);
    return gmdate('Y-m-d', $unix);
}

/**
 * Read every sheet of an .xlsx file.
 * Returns a list of ['name' => sheetName, 'cells' => [row => [col => value]]].
 * 'value' is the raw string/number as stored (dates stay as their numeric serial;
 * callers use xlsx_serial_to_date() where a column is known to hold a date).
 */
function xlsx_read_workbook(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Could not open '$path' as an .xlsx/.zip archive.");
    }

    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = simplexml_load_string($ssXml);
        if ($ss !== false) {
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) { $t .= (string) $r->t; }
                    $shared[] = $t;
                }
            }
        }
    }

    $wbXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml === false || $relsXml === false) {
        $zip->close();
        throw new RuntimeException("'$path' does not look like a valid .xlsx file.");
    }
    $wb = simplexml_load_string($wbXml);
    $wbRels = simplexml_load_string($relsXml);
    $rels = [];
    foreach ($wbRels->Relationship as $r) {
        $rels[(string) $r['Id']] = (string) $r['Target'];
    }
    $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $sheets = [];
    foreach ($wb->sheets->sheet as $s) {
        $rid = (string) $s->attributes($relNs)['id'];
        $target = $rels[$rid] ?? null;
        if (!$target) continue;
        if (strpos($target, '/') === false && strpos($target, 'worksheets') === false) {
            $target = 'worksheets/' . $target;
        }
        $sheetPath = 'xl/' . ltrim($target, '/');
        $sxml = $zip->getFromName($sheetPath);
        if ($sxml === false) continue;
        $sheetXml = simplexml_load_string($sxml);
        if ($sheetXml === false) continue;

        $cells = [];
        if (isset($sheetXml->sheetData->row)) {
            foreach ($sheetXml->sheetData->row as $row) {
                foreach ($row->c as $c) {
                    $ref = (string) $c['r'];
                    if ($ref === '') continue;
                    if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) continue;
                    $col = $m[1];
                    $rowNum = (int) $m[2];
                    $type = (string) $c['t'];
                    $val = null;
                    if (isset($c->is)) {
                        if (isset($c->is->t)) {
                            $val = (string) $c->is->t;
                        } else {
                            $val = '';
                            foreach ($c->is->r as $rr) { $val .= (string) $rr->t; }
                        }
                    } elseif (isset($c->v)) {
                        $v = (string) $c->v;
                        $val = $type === 's' ? ($shared[(int) $v] ?? '') : $v;
                    }
                    if ($val === null || trim((string) $val) === '') continue;
                    $cells[$rowNum][$col] = trim((string) $val);
                }
            }
        }
        $sheets[] = ['name' => (string) $s['name'], 'cells' => $cells];
    }
    $zip->close();
    return $sheets;
}
