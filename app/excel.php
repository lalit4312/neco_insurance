<?php
declare(strict_types=1);

function excel_sheets(): array
{
    return [
        'Tournaments' => [
            'headers' => ['name', 'edition', 'description', 'logo_url', 'banner_url', 'venue', 'format', 'start_date', 'end_date', 'status'],
            'example' => ['Summer Cup 2026', '2026', 'Season description', '', '', 'Main Arena', 'league', '2026-06-01', '2026-06-30', 'draft'],
        ],
        'Teams' => [
            'headers' => ['tournament_name', 'tournament_edition', 'name', 'short_name', 'logo_url', 'coach', 'description', 'group_name', 'active'],
            'example' => ['Summer Cup 2026', '2026', 'Example FC', 'EX', '', 'Coach Name', '', 'Group A', 'yes'],
        ],
        'Players' => [
            'headers' => ['tournament_name', 'tournament_edition', 'team_name', 'name', 'jersey_number', 'position', 'photo_url', 'active'],
            'example' => ['Summer Cup 2026', '2026', 'Example FC', 'Player Name', '10', 'Forward', '', 'yes'],
        ],
        'Fixtures' => [
            'headers' => ['tournament_name', 'tournament_edition', 'home_team', 'away_team', 'kickoff_at', 'venue', 'round', 'status'],
            'example' => ['Summer Cup 2026', '2026', 'Example FC', 'Rival FC', '2026-06-05 18:30', 'Main Arena', 'Group A', 'scheduled'],
        ],
        'Results' => [
            'headers' => ['tournament_name', 'tournament_edition', 'kickoff_at', 'home_team', 'away_team', 'home_score', 'away_score', 'status'],
            'example' => ['Summer Cup 2026', '2026', '2026-06-05 18:30', 'Example FC', 'Rival FC', '3', '2', 'draft'],
        ],
        'Sponsors' => [
            'headers' => ['name', 'logo_url', 'website_url', 'tier', 'description', 'tournament_name', 'tournament_edition', 'active'],
            'example' => ['Example Sponsor', '', 'https://example.com', 'Gold', '', 'Summer Cup 2026', '2026', 'yes'],
        ],
    ];
}

function excel_template_bytes(): string
{
    $files = [];
    $sheets = ['Instructions' => [
        ['NECO FUTSAL CUP · BULK IMPORT TEMPLATE'],
        ['How to use this workbook'],
        ['1. Fill in the data sheets below. Row 1 contains the required column names; keep them unchanged.'],
        ['2. Delete each example row before uploading. The example row is recognized and never imported.'],
        ['3. Import order is Tournaments, Teams, Players, Fixtures, Results, then Sponsors.'],
        ['4. Use exact tournament and team names to connect related rows. Fill in tournament_edition where editions share the same name.'],
        ['5. Dates: YYYY-MM-DD. Kick-off: YYYY-MM-DD HH:MM (24-hour time). Scores and jersey numbers: whole numbers from 0 to 99.'],
        ['6. Use yes or no for active. Allowed formats: league, knockout, group-stage, group-knockout.'],
        ['7. Tournament status: draft, published, completed. Fixture status: scheduled, cancelled. Result status: draft, published.'],
        ['8. URLs must begin with https:// or http://. Leave optional cells empty.'],
        ['9. Blank rows are ignored. Duplicate rows and rows with invalid or missing linked records are reported, not overwritten.'],
        ['10. Each valid row is committed independently; fix failed rows and upload them again without changing successful rows.'],
        [''],
        ['Sheet', 'Required columns', 'Example row (for guidance only)'],
    ]];
    foreach (excel_sheets() as $name => $definition) {
        $example = $definition['example'];
        $sheets[$name] = [$definition['headers'], $example];
    }

    $sheetNames = array_keys($sheets);
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach ($sheetNames as $index => $_name) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $contentTypes .= '</Types>';
    $files['[Content_Types].xml'] = $contentTypes;
    $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>';
    $relationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sheetNames as $index => $name) {
        $sheetNumber = $index + 1;
        $workbook .= '<sheet name="' . excel_xml($name) . '" sheetId="' . $sheetNumber . '" r:id="rId' . $sheetNumber . '"/>';
        $relationships .= '<Relationship Id="rId' . $sheetNumber . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNumber . '.xml"/>';
    }
    $workbook .= '</sheets><calcPr calcId="191029"/></workbook>';
    $relationships .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $files['xl/workbook.xml'] = $workbook;
    $files['xl/_rels/workbook.xml.rels'] = $relationships;
    $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF101C2C"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    foreach ($sheets as $index => $rows) {
        $sheetNumber = array_search($index, $sheetNames, true) + 1;
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $sheetRows .= '<row r="' . $rowNumber . '">';
            foreach ($row as $columnIndex => $value) {
                if ($value === '') {
                    continue;
                }
                $reference = excel_column_name($columnIndex + 1) . $rowNumber;
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $sheetRows .= '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">'
                    . excel_xml((string)$value) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
        }
        $files['xl/worksheets/sheet' . $sheetNumber . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><sheetData>' . $sheetRows . '</sheetData></worksheet>';
    }
    return zip_create($files);
}

function excel_xml(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function excel_column_name(int $column): string
{
    $name = '';
    while ($column > 0) {
        $remainder = ($column - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $column = intdiv($column - 1, 26);
    }
    return $name;
}

function zip_create(array $files): string
{
    $body = '';
    $centralDirectory = '';
    foreach ($files as $name => $contents) {
        $compressed = gzdeflate($contents, 6);
        if ($compressed === false) {
            throw new RuntimeException('Unable to build the Excel template.');
        }
        $crc = crc32($contents);
        $size = strlen($contents);
        $compressedSize = strlen($compressed);
        $offset = strlen($body);
        $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, $compressedSize, $size, strlen($name), 0)
            . $name . $compressed;
        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, 0, 8, 0, 0, $crc, $compressedSize, $size,
            strlen($name), 0, 0, 0, 0, 0, $offset
        ) . $name;
    }
    $directoryOffset = strlen($body);
    $directorySize = strlen($centralDirectory);
    $entries = count($files);
    return $body . $centralDirectory
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries, $directorySize, $directoryOffset, 0);
}

function excel_upload_sheets(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('The uploaded Excel file could not be read.');
    }
    $size = filesize($path);
    if ($size === false || $size < 22 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Upload an .xlsx file smaller than 5 MB.');
    }
    $archive = file_get_contents($path);
    if ($archive === false) {
        throw new RuntimeException('The uploaded Excel file could not be read.');
    }
    return zip_read_xlsx($archive);
}

function excel_upload_limit_bytes(): int
{
    $limits = [5 * 1024 * 1024];
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $value = trim((string)ini_get($setting));
        if ($value === '') {
            continue;
        }
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)$/i', $value, $match)) {
            continue;
        }
        $multiplier = match (strtoupper($match[2])) {
            'G' => 1024 * 1024 * 1024,
            'M' => 1024 * 1024,
            'K' => 1024,
            default => 1,
        };
        $limits[] = (int)((float)$match[1] * $multiplier);
    }
    return max(1, min($limits));
}

function excel_upload_limit_label(): string
{
    $bytes = excel_upload_limit_bytes();
    return $bytes % (1024 * 1024) === 0
        ? (string)($bytes / (1024 * 1024)) . ' MB'
        : number_format($bytes / 1024 / 1024, 1) . ' MB';
}

function zip_read_xlsx(string $archive): array
{
    $eocdPosition = strrpos($archive, "PK\x05\x06");
    if ($eocdPosition === false || strlen($archive) - $eocdPosition < 22) {
        throw new RuntimeException('This file is not a valid .xlsx workbook.');
    }
    $eocd = unpack('vdisk/vdirectory_disk/ventries_disk/ventries/Vdirectory_size/Vdirectory_offset/vcomment_length', substr($archive, $eocdPosition + 4, 18));
    if (!$eocd || $eocd['disk'] !== 0 || $eocd['directory_disk'] !== 0
        || $eocd['entries_disk'] !== $eocd['entries'] || $eocd['entries'] < 1 || $eocd['entries'] > 60
        || $eocd['directory_offset'] + $eocd['directory_size'] > $eocdPosition
        || strlen($archive) - $eocdPosition - 22 !== $eocd['comment_length']) {
        throw new RuntimeException('The Excel workbook has an invalid or unsupported ZIP directory.');
    }

    $files = [];
    $offset = $eocd['directory_offset'];
    $uncompressedTotal = 0;
    for ($index = 0; $index < $eocd['entries']; $index++) {
        if (substr($archive, $offset, 4) !== "PK\x01\x02" || strlen($archive) - $offset < 46) {
            throw new RuntimeException('The Excel workbook contains an invalid ZIP entry.');
        }
        $header = unpack(
            'vversion_made/vversion_needed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length/vcomment_length/vdisk/vinternal/Vexternal/Vlocal_offset',
            substr($archive, $offset + 4, 42)
        );
        if (!$header) {
            throw new RuntimeException('The Excel workbook contains an invalid ZIP entry.');
        }
        $entryLength = 46 + $header['name_length'] + $header['extra_length'] + $header['comment_length'];
        if ($entryLength > strlen($archive) - $offset) {
            throw new RuntimeException('The Excel workbook contains a truncated ZIP entry.');
        }
        $name = substr($archive, $offset + 46, $header['name_length']);
        $offset += $entryLength;
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')
            || ($header['flags'] & 0x2041) !== 0 || !in_array($header['method'], [0, 8], true)
            || $header['uncompressed_size'] > 8 * 1024 * 1024) {
            throw new RuntimeException('The Excel workbook contains an unsupported or unsafe ZIP entry.');
        }
        $uncompressedTotal += $header['uncompressed_size'];
        if ($uncompressedTotal > 24 * 1024 * 1024) {
            throw new RuntimeException('The Excel workbook expands beyond the 24 MB safety limit.');
        }
        $localOffset = $header['local_offset'];
        if ($localOffset >= $eocd['directory_offset'] || substr($archive, $localOffset, 4) !== "PK\x03\x04" || strlen($archive) - $localOffset < 30) {
            throw new RuntimeException('The Excel workbook contains a broken ZIP entry.');
        }
        $local = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed_size/Vuncompressed_size/vname_length/vextra_length', substr($archive, $localOffset + 4, 26));
        if (!$local || $local['method'] !== $header['method'] || $local['name_length'] !== $header['name_length']) {
            throw new RuntimeException('The Excel workbook contains inconsistent ZIP metadata.');
        }
        $dataOffset = $localOffset + 30 + $local['name_length'] + $local['extra_length'];
        if ($dataOffset + $header['compressed_size'] > $eocd['directory_offset']) {
            throw new RuntimeException('The Excel workbook contains a truncated ZIP entry.');
        }
        $localName = substr($archive, $localOffset + 30, $local['name_length']);
        if ($localName !== $name) {
            throw new RuntimeException('The Excel workbook contains inconsistent ZIP filenames.');
        }
        $compressed = substr($archive, $dataOffset, $header['compressed_size']);
        $contents = $header['method'] === 8 ? @gzinflate($compressed, 8 * 1024 * 1024) : $compressed;
        if ($contents === false || strlen($contents) !== $header['uncompressed_size']
            || sprintf('%u', crc32($contents)) !== sprintf('%u', $header['crc'])) {
            throw new RuntimeException('The Excel workbook contains a damaged ZIP entry.');
        }
        if (!str_ends_with($name, '/')) {
            if (isset($files[$name])) {
                throw new RuntimeException('The Excel workbook contains duplicate ZIP filenames.');
            }
            $files[$name] = $contents;
        }
    }
    if ($offset !== $eocd['directory_offset'] + $eocd['directory_size']) {
        throw new RuntimeException('The Excel workbook ZIP directory is malformed.');
    }
    if (!isset($files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'])) {
        throw new RuntimeException('Upload an Excel workbook saved in the .xlsx format.');
    }
    return xlsx_parse_sheets($files);
}

function xlsx_parse_sheets(array $files): array
{
    $workbook = excel_parse_xml($files['xl/workbook.xml']);
    $relationshipXml = excel_parse_xml($files['xl/_rels/workbook.xml.rels']);
    $relationshipMap = [];
    foreach ($relationshipXml->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
        $attributes = $relationship->attributes();
        $relationshipMap[(string)$attributes['Id']] = (string)$attributes['Target'];
    }
    $sharedStrings = [];
    if (isset($files['xl/sharedStrings.xml'])) {
        $sharedXml = excel_parse_xml($files['xl/sharedStrings.xml']);
        foreach ($sharedXml->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->si as $item) {
            $sharedStrings[] = excel_shared_string($item);
        }
    }

    $result = [];
    $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    foreach ($workbook->children($mainNamespace)->sheets->children($mainNamespace)->sheet as $sheet) {
        $attributes = $sheet->attributes();
        $sheetName = (string)$attributes['name'];
        $relationshipAttributes = $sheet->attributes($relationshipNamespace);
        $relationshipId = (string)$relationshipAttributes['id'];
        $target = $relationshipMap[$relationshipId] ?? '';
        if ($target === '' || str_contains($target, '..') || str_starts_with($target, '/')) {
            throw new RuntimeException('The Excel workbook contains an invalid worksheet reference.');
        }
        $sheetPath = 'xl/' . ltrim($target, '/');
        if (!isset($files[$sheetPath])) {
            throw new RuntimeException('The Excel worksheet "' . $sheetName . '" is missing.');
        }
        $sheetXml = excel_parse_xml($files[$sheetPath]);
        $rows = [];
        $rowCount = 0;
        foreach ($sheetXml->children($mainNamespace)->sheetData->children($mainNamespace)->row as $row) {
            if (++$rowCount > 5001) {
                throw new RuntimeException('Each worksheet may contain at most 5,000 rows.');
            }
            $cells = [];
            foreach ($row->children($mainNamespace)->c as $cell) {
                $cellAttributes = $cell->attributes();
                $reference = (string)$cellAttributes['r'];
                if (!preg_match('/^([A-Z]+)[0-9]+$/', $reference, $match)) {
                    throw new RuntimeException('The Excel worksheet contains an invalid cell reference.');
                }
                $column = excel_column_number($match[1]);
                if ($column > 100) {
                    throw new RuntimeException('Excel worksheets may use at most 100 columns.');
                }
                $type = (string)$cellAttributes['t'];
                $cellValue = '';
                $cellContent = $cell->children($mainNamespace);
                if (isset($cellContent->f)) {
                    throw new RuntimeException('Formula cells are not allowed in an import workbook. Paste their values instead.');
                }
                if ($type === 'inlineStr') {
                    $cellValue = excel_shared_string($cellContent->is);
                } elseif (isset($cellContent->v)) {
                    $raw = (string)$cellContent->v;
                    if ($type === 's') {
                        if (!ctype_digit($raw) || !array_key_exists((int)$raw, $sharedStrings)) {
                            throw new RuntimeException('The Excel workbook contains an invalid shared string reference.');
                        }
                        $cellValue = $sharedStrings[(int)$raw];
                    } elseif (in_array($type, ['', 'n', 'str', 'b', 'e', 'd'], true)) {
                        $cellValue = $raw;
                    } else {
                        throw new RuntimeException('The Excel workbook contains an unsupported cell type.');
                    }
                }
                $cells[$column] = $cellValue;
            }
            $rows[] = $cells;
        }
        $result[$sheetName] = $rows;
    }
    return $result;
}

function excel_parse_xml(string $xml): SimpleXMLElement
{
    if (strlen($xml) > 8 * 1024 * 1024) {
        throw new RuntimeException('The Excel workbook contains an oversized XML document.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$parsed) {
            throw new RuntimeException('The Excel workbook contains invalid XML.');
        }
        return $parsed;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function excel_shared_string(SimpleXMLElement $string): string
{
    $text = '';
    foreach ($string->xpath('.//*[local-name()="t"]') ?: [] as $part) {
        $text .= (string)$part;
    }
    return $text;
}

function excel_column_number(string $letters): int
{
    $number = 0;
    foreach (str_split($letters) as $letter) {
        $number = $number * 26 + ord($letter) - 64;
    }
    return $number;
}

function import_excel_workbook(PDO $pdo, array $user, array $sheets): array
{
    $definitions = excel_sheets();
    $records = array_fill_keys(array_keys($definitions), ['imported' => 0, 'failed' => 0]);
    $errors = [];
    $totalRows = 0;
    $validatedHeaders = [];
    $hasDataSheet = false;
    foreach ($sheets as $sheetName => $rows) {
        if (!isset($definitions[$sheetName]) && $sheetName !== 'Instructions' && $rows) {
            throw new RuntimeException('Unrecognized worksheet "' . $sheetName . '". Use the provided template.');
        }
        if (!isset($definitions[$sheetName])) {
            continue;
        }
        $hasDataSheet = true;
        $definition = $definitions[$sheetName];
        $headerRow = $rows[0] ?? [];
        $headers = [];
        foreach ($headerRow as $index => $header) {
            if (is_string($header)) {
                $normalized = strtolower(trim($header));
                if ($normalized !== '') {
                    if (isset($headers[$normalized])) {
                        throw new RuntimeException($sheetName . ' has duplicate column heading "' . $normalized . '".');
                    }
                    $headers[$normalized] = (int)$index;
                }
            }
        }
        $missingHeaders = array_values(array_diff($definition['headers'], array_keys($headers)));
        if ($missingHeaders) {
            throw new RuntimeException($sheetName . ' is missing column headings: ' . implode(', ', $missingHeaders) . '.');
        }
        $unknownHeaders = array_values(array_diff(array_keys($headers), $definition['headers']));
        if ($unknownHeaders) {
            throw new RuntimeException($sheetName . ' has unrecognized column headings: ' . implode(', ', $unknownHeaders) . '.');
        }
        $validatedHeaders[$sheetName] = $headers;
        foreach (array_slice($rows, 1, null, true) as $cells) {
            $values = [];
            foreach ($headers as $header => $index) {
                $values[$header] = trim((string)($cells[$index] ?? ''));
            }
            if (implode('', $values) !== '' && !excel_is_example_row(array_keys($values), $values, $definition['headers'], $definition['example'])) {
                $totalRows++;
            }
        }
    }
    if (!$hasDataSheet) {
        throw new RuntimeException('No supported data worksheets were found. Use the provided template.');
    }
    if ($totalRows > 5000) {
        throw new RuntimeException('The workbook contains more than 5,000 data rows. Split it into smaller uploads.');
    }
    $totalRows = 0;

    foreach ($sheets as $sheetName => $rows) {
        if (!isset($definitions[$sheetName])) {
            continue;
        }
        $definition = $definitions[$sheetName];
        $headers = $validatedHeaders[$sheetName];
        foreach (array_slice($rows, 1, null, true) as $rowIndex => $cells) {
            $values = [];
            foreach ($headers as $header => $index) {
                $values[$header] = trim((string)($cells[$index] ?? ''));
            }
            if (implode('', $values) === '' || excel_is_example_row(array_keys($values), $values, $definition['headers'], $definition['example'])) {
                continue;
            }
            $totalRows++;
            $excelRowNumber = $rowIndex + 1;
            try {
                $pdo->beginTransaction();
                import_excel_row($pdo, $user, $sheetName, $values);
                $pdo->commit();
                $records[$sheetName]['imported']++;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $records[$sheetName]['failed']++;
                $errors[] = ['sheet' => $sheetName, 'row' => $excelRowNumber, 'message' => $error instanceof RuntimeException
                    ? $error->getMessage()
                    : 'The database rejected this row. Check field lengths, linked records, and duplicate values.'];
                if (!$error instanceof RuntimeException && !($error instanceof PDOException && (string)$error->getCode() === '23000')) {
                    error_log('Excel bulk import row failed: ' . $error->getMessage());
                }
            }
        }
    }
    $imported = array_sum(array_column($records, 'imported'));
    $errorCount = count($errors);
    $auditError = '';
    if ($imported) {
        try {
            audit($pdo, $user, 'bulk_import', 'workbook', null);
        } catch (Throwable $error) {
            error_log('Excel bulk import audit failed: ' . $error->getMessage());
            $auditError = 'Records were imported, but the audit log could not be updated. Please notify a system administrator.';
        }
    }
    return ['records' => $records, 'errors' => $errors, 'error_count' => $errorCount, 'total' => $totalRows, 'imported' => $imported, 'audit_error' => $auditError];
}

function excel_is_example_row(array $headers, array $values, array $templateHeaders, array $example): bool
{
    foreach ($headers as $header) {
        $index = array_search($header, $templateHeaders, true);
        if ($index === false || ($values[$header] ?? '') !== (string)($example[$index] ?? '')) {
            return false;
        }
    }
    return true;
}

function import_excel_row(PDO $pdo, array $user, string $sheet, array $data): void
{
    $tables = [
        'Tournaments' => 'tournaments', 'Teams' => 'teams', 'Players' => 'players',
        'Fixtures' => 'matches', 'Results' => 'results', 'Sponsors' => 'sponsors',
    ];
    $table = $tables[$sheet];
    $clean = static function (string $key, bool $required = false) use ($data): ?string {
        $value = trim((string)($data[$key] ?? ''));
        if ($value === '') {
            if ($required) {
                throw new RuntimeException('Required field "' . $key . '" is empty.');
            }
            return null;
        }
        return $value;
    };
    $uuid = static fn(): string => uuid();
    $findTournament = static function (string $name, ?string $edition = null) use ($pdo): array {
        $query = 'SELECT id, name, edition FROM tournaments WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))';
        $params = [$name];
        if ($edition !== null) {
            $query .= ' AND LOWER(TRIM(COALESCE(edition, ""))) = LOWER(TRIM(?))';
            $params[] = $edition;
        }
        $matches = all($pdo, $query, $params);
        if (count($matches) !== 1) {
            throw new RuntimeException(count($matches) ? 'Tournament name is ambiguous; use a unique name and edition.' : 'Tournament "' . $name . '" was not found. Import it first or add it in the admin area.');
        }
        return $matches[0];
    };
    $findTeam = static function (string $name, string $tournamentId) use ($pdo): array {
        $matches = all($pdo, 'SELECT id, name FROM teams WHERE tournament_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))', [$tournamentId, $name]);
        if (count($matches) !== 1) {
            throw new RuntimeException(count($matches) ? 'Team name "' . $name . '" is ambiguous in this tournament.' : 'Team "' . $name . '" was not found in the selected tournament.');
        }
        return $matches[0];
    };
    $date = static function (?string $value, string $field, bool $withTime = false): ?string {
        if ($value === null) {
            return null;
        }
        $value = excel_date_value($value, $withTime);
        $format = $withTime ? ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d H:i:s'] : ['Y-m-d'];
        foreach ($format as $candidate) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $candidate, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))
                && $parsed->format($candidate) === $value) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }
        throw new RuntimeException('Invalid "' . $field . '"; use ' . ($withTime ? 'YYYY-MM-DD HH:MM' : 'YYYY-MM-DD') . '.');
    };
    $url = static function (?string $value, string $field): ?string {
        if ($value === null) {
            return null;
        }
        $parts = parse_url($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('Invalid "' . $field . '"; provide an HTTP or HTTPS URL.');
        }
        return $value;
    };
    $boolean = static function (?string $value, string $field): int {
        if ($value === null || in_array(strtolower($value), ['yes', 'true', '1'], true)) {
            return 1;
        }
        if (in_array(strtolower($value), ['no', 'false', '0'], true)) {
            return 0;
        }
        throw new RuntimeException('Invalid "' . $field . '"; use yes or no.');
    };
    $insert = static function (string $tableName, array $values) use ($pdo): void {
        $columns = array_keys($values);
        $quoted = array_map(static fn(string $column): string => "`{$column}`", $columns);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO `' . $tableName . '` (' . implode(', ', $quoted) . ', created_at, updated_at) VALUES ('
            . implode(', ', $placeholders) . ', NOW(), NOW())';
        $pdo->prepare($sql)->execute(array_values($values));
    };

    if ($sheet === 'Tournaments') {
        $name = $clean('name', true);
        $edition = $clean('edition');
        $format = $clean('format', true);
        $status = $clean('status', true);
        if (!in_array($format, ['league', 'knockout', 'group-stage', 'group-knockout'], true)) {
            throw new RuntimeException('Invalid tournament format; use league, knockout, group-stage, or group-knockout.');
        }
        if (!in_array($status, ['draft', 'published', 'completed'], true)) {
            throw new RuntimeException('Invalid tournament status; use draft, published, or completed.');
        }
        $duplicate = one(
            $pdo,
            'SELECT id FROM tournaments WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(edition, ""))) = LOWER(TRIM(COALESCE(?, "")))',
            [$name, $edition]
        );
        if ($duplicate) {
            throw new RuntimeException('A tournament with this name and edition already exists.');
        }
        $startDate = $date($clean('start_date'), 'start_date');
        $endDate = $date($clean('end_date'), 'end_date');
        if ($startDate && $endDate && $endDate < $startDate) {
            throw new RuntimeException('end_date must be on or after start_date.');
        }
        $insert($table, [
            'id' => $uuid(), 'name' => $name, 'edition' => $edition,
            'description' => $clean('description'), 'logo_url' => $url($clean('logo_url'), 'logo_url'),
            'banner_url' => $url($clean('banner_url'), 'banner_url'), 'venue' => $clean('venue'),
            'format' => $format, 'start_date' => $startDate, 'end_date' => $endDate, 'status' => $status,
        ]);
        return;
    }

    if ($sheet === 'Teams') {
        $tournament = $findTournament($clean('tournament_name', true), $clean('tournament_edition'));
        $name = $clean('name', true);
        if (one($pdo, 'SELECT id FROM teams WHERE tournament_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))', [$tournament['id'], $name])) {
            throw new RuntimeException('This team already exists in the selected tournament.');
        }
        $insert($table, [
            'id' => $uuid(), 'name' => $name, 'short_name' => $clean('short_name'),
            'logo_url' => $url($clean('logo_url'), 'logo_url'), 'coach' => $clean('coach'),
            'description' => $clean('description'), 'group_name' => $clean('group_name'),
            'tournament_id' => $tournament['id'], 'active' => $boolean($clean('active'), 'active'),
        ]);
        return;
    }

    if ($sheet === 'Players') {
        $tournament = $findTournament($clean('tournament_name', true), $clean('tournament_edition'));
        $team = $findTeam($clean('team_name', true), $tournament['id']);
        $name = $clean('name', true);
        if (one($pdo, 'SELECT id FROM players WHERE team_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))', [$team['id'], $name])) {
            throw new RuntimeException('This player already exists on the selected team.');
        }
        $jersey = $clean('jersey_number');
        if ($jersey !== null && (!ctype_digit($jersey) || (int)$jersey > 99)) {
            throw new RuntimeException('jersey_number must be a whole number from 0 to 99.');
        }
        if ($jersey !== null && one($pdo, 'SELECT id FROM players WHERE team_id = ? AND jersey_number = ?', [$team['id'], (int)$jersey])) {
            throw new RuntimeException('This jersey number is already assigned on the selected team.');
        }
        $insert($table, [
            'id' => $uuid(), 'name' => $name, 'jersey_number' => $jersey === null ? null : (int)$jersey,
            'position' => $clean('position'), 'photo_url' => $url($clean('photo_url'), 'photo_url'),
            'team_id' => $team['id'], 'active' => $boolean($clean('active'), 'active'),
        ]);
        return;
    }

    if ($sheet === 'Fixtures') {
        $tournament = $findTournament($clean('tournament_name', true), $clean('tournament_edition'));
        $home = $findTeam($clean('home_team', true), $tournament['id']);
        $away = $findTeam($clean('away_team', true), $tournament['id']);
        if ($home['id'] === $away['id']) {
            throw new RuntimeException('A team cannot play itself.');
        }
        $kickoff = $date($clean('kickoff_at', true), 'kickoff_at', true);
        $status = $clean('status', true);
        if (!in_array($status, ['scheduled', 'cancelled'], true)) {
            throw new RuntimeException('Fixture status must be scheduled or cancelled.');
        }
        if (one($pdo, 'SELECT id FROM matches WHERE tournament_id = ? AND kickoff_at = ? AND home_team_id = ? AND away_team_id = ?', [$tournament['id'], $kickoff, $home['id'], $away['id']])) {
            throw new RuntimeException('This fixture already exists.');
        }
        $insert($table, [
            'id' => $uuid(), 'tournament_id' => $tournament['id'], 'home_team_id' => $home['id'],
            'away_team_id' => $away['id'], 'kickoff_at' => $kickoff, 'venue' => $clean('venue'),
            'round' => $clean('round'), 'status' => $status,
        ]);
        return;
    }

    if ($sheet === 'Results') {
        $tournament = $findTournament($clean('tournament_name', true), $clean('tournament_edition'));
        $home = $findTeam($clean('home_team', true), $tournament['id']);
        $away = $findTeam($clean('away_team', true), $tournament['id']);
        $kickoff = $date($clean('kickoff_at', true), 'kickoff_at', true);
        $match = one(
            $pdo,
            'SELECT id FROM matches WHERE tournament_id = ? AND kickoff_at = ? AND home_team_id = ? AND away_team_id = ?',
            [$tournament['id'], $kickoff, $home['id'], $away['id']]
        );
        if (!$match) {
            throw new RuntimeException('The matching fixture was not found; import that fixture first.');
        }
        if (one($pdo, 'SELECT id FROM results WHERE match_id = ?', [$match['id']])) {
            throw new RuntimeException('A result already exists for this fixture.');
        }
        $homeScore = $clean('home_score', true);
        $awayScore = $clean('away_score', true);
        foreach (['home_score' => $homeScore, 'away_score' => $awayScore] as $field => $score) {
            if (!ctype_digit((string)$score) || (int)$score > 99) {
                throw new RuntimeException($field . ' must be a whole number from 0 to 99.');
            }
        }
        $status = $clean('status', true);
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new RuntimeException('Result status must be draft or published.');
        }
        $resultId = $uuid();
        $insert($table, [
            'id' => $resultId, 'match_id' => $match['id'], 'tournament_id' => $tournament['id'],
            'home_team_id' => $home['id'], 'away_team_id' => $away['id'],
            'home_score' => (int)$homeScore, 'away_score' => (int)$awayScore, 'status' => $status,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'created_by' => $user['id'], 'published_by' => $status === 'published' ? $user['id'] : null,
        ]);
        if ($status === 'published') {
            $pdo->prepare("UPDATE matches SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$match['id']]);
            audit($pdo, $user, 'publish', 'results', $resultId);
        }
        return;
    }

    if ($sheet === 'Sponsors') {
        $name = $clean('name', true);
        $tournamentName = $clean('tournament_name');
        $tournamentEdition = $clean('tournament_edition');
        $tournament = $tournamentName === null ? null : $findTournament($tournamentName, $tournamentEdition);
        $duplicate = $tournament
            ? one($pdo, 'SELECT id FROM sponsors WHERE tournament_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))', [$tournament['id'], $name])
            : one($pdo, 'SELECT id FROM sponsors WHERE tournament_id IS NULL AND LOWER(TRIM(name)) = LOWER(TRIM(?))', [$name]);
        if ($duplicate) {
            throw new RuntimeException('This sponsor already exists for the selected tournament.');
        }
        $insert($table, [
            'id' => $uuid(), 'name' => $name, 'logo_url' => $url($clean('logo_url'), 'logo_url'),
            'website_url' => $url($clean('website_url'), 'website_url'), 'tier' => $clean('tier'),
            'description' => $clean('description'), 'tournament_id' => $tournament['id'] ?? null,
            'active' => $boolean($clean('active'), 'active'),
        ]);
        return;
    }
}

function excel_date_value(string $value, bool $dateTime): string
{
    if (!is_numeric($value)) {
        return $value;
    }
    $serial = (float)$value;
    if ($serial < 1 || $serial > 2958465) {
        return $value;
    }
    $seconds = (int)round($serial * 86400);
    $converted = (new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC')))->modify('+' . $seconds . ' seconds');
    return $converted->format($dateTime ? 'Y-m-d H:i' : 'Y-m-d');
}
