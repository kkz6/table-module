<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Table\Exports\Jobs\CreateXlsxFileJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\StyledUserExporter;
use Modules\Table\Tests\Support\TestUserExporter;

it('assembles an xlsx from headers and part files', function (): void {
    Storage::fake('local');
    $tableExport = TableExport::factory()->create([
        'exporter'  => TestUserExporter::class,
        'file_disk' => 'local',
        'file_name' => 'my-export',
        'total_rows' => 2,
    ]);
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/headers.csv', "Full name\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000001.csv', "Aiko\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000002.csv', "Ben\n");

    (new CreateXlsxFileJob($tableExport, ['name' => 'Full name'], []))->handle();

    $path = $tableExport->getFileDirectory().'/my-export.xlsx';
    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('local')->path($path));
    $sheet       = $spreadsheet->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Full name')
        ->and($sheet->getCell('A2')->getValue())->toBe('Aiko')
        ->and($sheet->getCell('A3')->getValue())->toBe('Ben')
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue();
});

it('auto-sizes columns and centers every cell on both axes', function (): void {
    Storage::fake('local');
    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'file_name'  => 'sized-export',
        'total_rows' => 1,
    ]);
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/headers.csv', "Employee Full Name,ID\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000001.csv', "Aiko,1\n");

    (new CreateXlsxFileJob($tableExport, ['a' => 'Employee Full Name', 'b' => 'ID'], []))->handle();

    $path        = $tableExport->getFileDirectory().'/sized-export.xlsx';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('local')->path($path));
    $sheet       = $spreadsheet->getActiveSheet();

    // Auto-size bakes a concrete, content-driven width at save time (wider header → wider column).
    $wideColumn   = $sheet->getColumnDimension('A')->getWidth(); // "Employee Full Name"
    $narrowColumn = $sheet->getColumnDimension('B')->getWidth(); // "ID"

    expect($narrowColumn)->toBeGreaterThan(0.0)
        ->and($wideColumn)->toBeGreaterThan($narrowColumn);

    // Excel sort/filter dropdowns enabled across the full data range.
    expect($sheet->getAutoFilter()->getRange())->toBe('A1:B2');

    $header = $sheet->getStyle('A1')->getAlignment();
    $data   = $sheet->getStyle('A2')->getAlignment();

    expect($header->getHorizontal())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->and($header->getVertical())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
        ->and($data->getHorizontal())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->and($data->getVertical())->toBe(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
});

it('stores formula-like cell values as plain strings to prevent injection', function (): void {
    Storage::fake('local');
    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'file_name'  => 'formula-export',
        'total_rows' => 1,
    ]);
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/headers.csv', "Formula\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000001.csv', '"=SUM(A1:A9)"'."\n");

    (new CreateXlsxFileJob($tableExport, ['formula' => 'Formula'], []))->handle();

    $path        = $tableExport->getFileDirectory().'/formula-export.xlsx';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('local')->path($path));
    $sheet       = $spreadsheet->getActiveSheet();

    expect($sheet->getCell('A2')->getValue())->toBe('=SUM(A1:A9)')
        ->and($sheet->getCell('A2')->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
});

it('applies custom cell style and sheet hook from an overriding exporter', function (): void {
    Storage::fake('local');
    $tableExport = TableExport::factory()->create([
        'exporter'  => StyledUserExporter::class,
        'file_disk' => 'local',
        'file_name' => 'styled-export',
        'total_rows' => 1,
    ]);
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/headers.csv', "Full name\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000001.csv', "Chiara\n");

    (new CreateXlsxFileJob($tableExport, ['name' => 'Full name'], []))->handle();

    $path        = $tableExport->getFileDirectory().'/styled-export.xlsx';
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('local')->path($path));
    $sheet       = $spreadsheet->getActiveSheet();

    expect($sheet->getStyle('A2')->getFont()->getItalic())->toBeTrue()
        ->and($sheet->getTitle())->toBe('Styled Sheet');
});
