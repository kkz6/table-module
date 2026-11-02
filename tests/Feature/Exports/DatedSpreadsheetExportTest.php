<?php

declare(strict_types=1);

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\Table\Exports\DatedSpreadsheetExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

it('writes a dated spreadsheet with the preset styles and supplied header range', function () {
    $this->travelTo(now()->setDate(2026, 1, 2)->startOfDay());
    $export      = DatedSpreadsheetExport::make('Download', 'Report', 'Report', 'A1:B1');
    $spreadsheet = new class($export->getEvents()) implements FromArray, WithEvents
    {
        public function __construct(private array $events) {}

        public function array(): array
        {
            return [['Name', 'Value', 'Unstyled'], ['日本', 42, 'Text']];
        }

        public function registerEvents(): array
        {
            return $this->events;
        }
    };
    $path = tempnam(sys_get_temp_dir(), 'dated-export-');

    try {
        file_put_contents($path, ExcelFacade::raw($spreadsheet, Excel::XLSX));
        $workbook = IOFactory::load($path);
        $sheet    = $workbook->getActiveSheet();

        expect($export->getFilename())->toBe('Report2026-01-02.xlsx')
            ->and($export->getLabel())->toBe('Download')
            ->and($export->shouldLimitToFilteredRows())->toBeTrue()
            ->and($export->shouldLimitToSelectedRows())->toBeTrue()
            ->and($workbook->getProperties()->getCreator())->toBe('System')
            ->and($sheet->getTitle())->toBe('Report 2026-01-02')
            ->and($sheet->toArray(formatData: false))->toBe([['Name', 'Value', 'Unstyled'], ['日本', 42, 'Text']])
            ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
            ->and($sheet->getStyle('B1')->getFont()->getBold())->toBeTrue()
            ->and($sheet->getStyle('C1')->getFont()->getBold())->toBeFalse()
            ->and($sheet->getStyle('A2')->getFont()->getBold())->toBeFalse()
            ->and($sheet->getStyle('A2')->getAlignment()->getHorizontal())->toBe(Alignment::HORIZONTAL_CENTER)
            ->and($sheet->getStyle('A2')->getAlignment()->getVertical())->toBe(Alignment::VERTICAL_CENTER)
            ->and($sheet->getStyle('A2')->getAlignment()->getWrapText())->toBeTrue();
    } finally {
        unlink($path);
    }
});
