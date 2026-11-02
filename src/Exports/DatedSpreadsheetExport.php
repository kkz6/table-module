<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\BeforeSheet;
use Modules\Table\Export;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

final class DatedSpreadsheetExport
{
    public static function make(string $label, string $filenamePrefix, string $sheetTitle, string $headerRange): Export
    {
        return Export::make(
            label: $label,
            filename: $filenamePrefix.now()->format('Y-m-d').'.xlsx',
            events: [
                BeforeExport::class => static function (BeforeExport $event): void {
                    $event->getDelegate()->getDefaultStyle()->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $event->writer->getProperties()->setCreator('System');
                },
                BeforeSheet::class => static function (BeforeSheet $event) use ($sheetTitle): void {
                    $event->getDelegate()->setTitle($sheetTitle.' '.now()->format('Y-m-d'));
                },
                AfterSheet::class => static function (AfterSheet $event) use ($headerRange): void {
                    $event->getDelegate()->getStyle($headerRange)->getFont()->setBold(true);
                },
            ],
            limitToFilteredRows: true,
            limitToSelectedRows: true,
        );
    }
}
