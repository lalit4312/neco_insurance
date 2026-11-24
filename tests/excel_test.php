<?php
declare(strict_types=1);

require __DIR__ . '/../app/excel.php';

final class ExcelTemplateTestPDO extends PDO
{
    public function __construct()
    {
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$path = tempnam(sys_get_temp_dir(), 'neco-xlsx-');
if ($path === false) {
    throw new RuntimeException('Unable to create a temporary workbook for testing.');
}

try {
    $bytes = excel_template_bytes();
    expect(file_put_contents($path, $bytes) === strlen($bytes), 'The template could not be written.');
    $sheets = excel_upload_sheets($path);
    expect(array_keys($sheets) === array_merge(['Instructions'], array_keys(excel_sheets())), 'The template worksheets are incomplete or out of order.');

    foreach (excel_sheets() as $name => $definition) {
        $headerRow = $sheets[$name][0] ?? [];
        foreach ($definition['headers'] as $index => $header) {
            expect(($headerRow[$index + 1] ?? null) === $header, $name . ' is missing the "' . $header . '" heading.');
        }
        $exampleRow = $sheets[$name][1] ?? [];
        $values = [];
        foreach ($definition['headers'] as $index => $header) {
            $values[$header] = (string)($exampleRow[$index + 1] ?? '');
        }
        expect(excel_is_example_row(array_keys($values), $values, $definition['headers'], $definition['example']), $name . ' example row was not recognized.');
    }

    $summary = import_excel_workbook(new ExcelTemplateTestPDO(), ['id' => 'test'], $sheets);
    expect($summary['total'] === 0 && $summary['imported'] === 0 && $summary['error_count'] === 0, 'Template example rows must not be imported.');
    expect(excel_date_value('45000.5', true) === '2023-03-15 12:00', 'Excel date serial conversion is incorrect.');

    unset($sheets['Teams'][0][1]);
    try {
        import_excel_workbook(new ExcelTemplateTestPDO(), ['id' => 'test'], $sheets);
        throw new RuntimeException('A workbook with a missing heading was accepted.');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'missing column headings'), 'Missing headings should produce a specific validation error.');
    }

    file_put_contents($path, 'not an xlsx archive');
    try {
        excel_upload_sheets($path);
        throw new RuntimeException('A non-Excel file was accepted.');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), '.xlsx'), 'An invalid workbook should produce a clear error.');
    }

    echo "Excel template, parser, example-row, date, and validation tests passed." . PHP_EOL;
} finally {
    unlink($path);
}
