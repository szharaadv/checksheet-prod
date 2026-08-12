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

/**
 * Minimal .xlsx writer (no external library) — enough to produce a
 * block-layout template that mirrors a printed report, for the user to fill
 * in Excel and re-upload. Uses inline strings (no sharedStrings part needed).
 *
 * @param array $rows       [rowNum => [colLetter => ['v' => string, 'b' => bool bold] | ['f' => 'SUM(C1:C2)', 'b' => bool]]]
 *                           'f' (formula, without leading '=') takes priority over 'v' if both are set.
 * @param array $merges     list of "B1:C1" style ranges
 * @param array $colWidths  [colLetter => width] (default 12 for unlisted columns up to $lastCol)
 * @param string $lastCol   rightmost column letter used, for default width sizing
 */
function xlsx_write_workbook(array $rows, array $merges = [], array $colWidths = [], string $sheetName = 'Sheet1', string $lastCol = 'G'): string
{
    $esc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $colLetters = [];
    for ($c = 'A'; $c <= $lastCol; $c++) { $colLetters[] = $c; if ($c === $lastCol) break; }
    $colsXml = '<cols>';
    foreach ($colLetters as $i => $c) {
        $w = $colWidths[$c] ?? 12;
        $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $maxRow = $rows ? max(array_keys($rows)) : 1;
    $sheetDataXml = '';
    for ($r = 1; $r <= $maxRow; $r++) {
        if (empty($rows[$r])) continue;
        $sheetDataXml .= '<row r="' . $r . '">';
        foreach ($rows[$r] as $col => $cell) {
            $style = !empty($cell['b']) ? ' s="1"' : '';
            if (isset($cell['f']) && $cell['f'] !== '') {
                // Formula cell: no t= attribute (defaults to numeric result), Excel computes/caches <v> on open.
                $sheetDataXml .= '<c r="' . $col . $r . '"' . $style . '><f>' . $esc($cell['f']) . '</f></c>';
                continue;
            }
            $v = $cell['v'] ?? '';
            if ($v === '') continue;
            $sheetDataXml .= '<c r="' . $col . $r . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
        }
        $sheetDataXml .= '</row>';
    }

    $mergeXml = '';
    if ($merges) {
        $mergeXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $m) { $mergeXml .= '<mergeCell ref="' . $esc($m) . '"/>'; }
        $mergeXml .= '</mergeCells>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . $colsXml
        . '<sheetData>' . $sheetDataXml . '</sheetData>'
        . $mergeXml
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '</styleSheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '<calcPr fullCalcOnLoad="1"/>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $rootRelsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
    $bytes = file_get_contents($tmp);
    unlink($tmp);
    return $bytes;
}
