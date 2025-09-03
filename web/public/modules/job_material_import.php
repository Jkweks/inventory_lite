<?php
/**
 * Parse rows from a fixed set of sheets in an XLSX/XLSM by reading the zip parts directly.
 *
 * @param string   $path               Path to the .xlsx/.xlsm
 * @param string[] $requiredSheets     Sheet tab names to read (exact match)
 * @param int      $skipHeaderRows     Skip all rows with 1-based index <= this number
 * @param callable|null $logger        function(string $msg): void for debug logs
 * @return array                       Flat array of [A,B,C] rows across all required sheets
 * @throws Exception
 */
function parse_job_materials_xlsx_refined(
    string $path,
    array $requiredSheets = [
        'Accessories','Accessories (2)','Accessories (3)',
        'Stock Lengths','Stock Lengths (2)','Stock Lengths (3)'
    ],
    int $skipHeaderRows = 10,
    ?callable $logger = null
): array {
    $log = $logger ?? static function(string $m) { /* no-op */ };

    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive extension not installed');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception("Unable to open spreadsheet: {$path}");
    }
    $log("Opened zip: {$path}");

    // --- Load shared strings (optional but common) ---
    $shared = [];
    $ssiXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssiXml !== false) {
        $sx = @simplexml_load_string($ssiXml);
        if ($sx && isset($sx->si)) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    // rich text runs
                    $text = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                    }
                    $shared[] = $text;
                }
            }
            $log("sharedStrings.xml loaded: ".count($shared)." entries");
        } else {
            $log("sharedStrings.xml present but could not parse.");
        }
    } else {
        $log("sharedStrings.xml not present (string cells may be inline or absent).");
    }

    // --- workbook + relationships mapping ---
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml     = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false) {
        $zip->close();
        throw new Exception('Missing xl/workbook.xml');
    }
    if ($relsXml === false) {
        $zip->close();
        throw new Exception('Missing xl/_rels/workbook.xml.rels');
    }

    $workbook = @simplexml_load_string($workbookXml);
    $rels     = @simplexml_load_string($relsXml);
    if (!$workbook) {
        $zip->close();
        throw new Exception('Failed to parse xl/workbook.xml');
    }
    if (!$rels) {
        $zip->close();
        throw new Exception('Failed to parse xl/_rels/workbook.xml.rels');
    }

    // Build relMap: rId -> Target
    $relMap = [];
    foreach ($rels->Relationship as $rel) {
        $relMap[(string)$rel['Id']] = (string)$rel['Target'];
    }
    $log("Relationships found: ".count($relMap));

    // Build sheets map: sheetName -> normalized zip path
    $sheets = [];
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    if (isset($workbook->sheets)) {
        foreach ($workbook->sheets->sheet as $sheet) {
            $name = (string)$sheet['name'];

            // robust r:id read
            $rid = (string)$sheet->attributes($nsR)->id;
            if ($rid === '') {
                $rid = (string)$sheet->attributes('r', true)['id'];
            }

            $target = $relMap[$rid] ?? null;
            if ($target === null || $rid === '') {
                $log("WARN: Sheet '{$name}' has missing/unknown relationship (rid='{$rid}').");
                continue;
            }

            // Normalize to a zip path under xl/
            $target = ltrim($target, '/');                 // remove any leading slash
            $zipPath = str_starts_with($target, 'xl/')     // avoid xl/xl/
                ? $target
                : "xl/{$target}";

            // Some producers store paths with different casing; try case-insensitive locate
            $exists = ($zip->locateName($zipPath, ZipArchive::FL_NOCASE) !== false);
            $log("Sheet '{$name}' => rid={$rid}, target='{$target}', path='{$zipPath}', exists=".($exists?'yes':'no'));

            if ($exists) {
                // Store the *actual* name in the zip if case-insensitive locate differs
                $index = $zip->locateName($zipPath, ZipArchive::FL_NOCASE);
                $realName = $zip->getNameIndex($index);
                $sheets[$name] = $realName ?? $zipPath;
            }
        }
    } else {
        $log('No <sheets> found in workbook.xml.');
    }

    // Report required sheet presence
    foreach ($requiredSheets as $req) {
        $log("Required '{$req}': ".(isset($sheets[$req]) ? 'FOUND' : 'MISSING'));
    }

    // --- Read rows from required sheets ---
    $rows = [];
    foreach ($requiredSheets as $sheetName) {
        if (!isset($sheets[$sheetName])) {
            $log("SKIP: Required sheet '{$sheetName}' not found in workbook.");
            continue;
        }

        $pathInZip = $sheets[$sheetName];
        $sheetXml  = $zip->getFromName($pathInZip);
        if ($sheetXml === false) {
            $log("SKIP: Zip entry missing: {$pathInZip} (sheet '{$sheetName}').");
            continue;
        }

        $sx = @simplexml_load_string($sheetXml);
        if (!$sx || !isset($sx->sheetData)) {
            $log("SKIP: Could not parse sheetData for '{$sheetName}'.");
            continue;
        }

        $extracted = 0;
        foreach ($sx->sheetData->row as $row) {
            $r = (int)$row['r'];
            if ($r <= $skipHeaderRows) {
                continue; // skip header rows
            }

            // Read columns A,B,C only (as in your original)
            $vals = ['A'=>null,'B'=>null,'C'=>null];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];                 // e.g., "B42"
                $col = preg_replace('/\d+/', '', $ref); // "B"
                if (!array_key_exists($col, $vals)) {
                    continue;
                }

                $v = (string)$c->v;
                // Resolve shared strings
                if ((string)$c['t'] === 's') {
                    $idx = (int)$v;
                    $v = $shared[$idx] ?? '';
                }
                // Inline strings (t="inlineStr") can appear as <is><t>...</t></is>; add if needed
                if ((string)$c['t'] === 'inlineStr' && isset($c->is->t)) {
                    $v = (string)$c->is->t;
                }

                $vals[$col] = $v;
            }

            $rows[] = [ $vals['A'], $vals['B'], $vals['C'] ];
            $extracted++;
        }
        $log("Read {$extracted} data row(s) from '{$sheetName}' (skipped <= {$skipHeaderRows}).");
    }

    $zip->close();
    return $rows;
}
