<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Generator;
use Modules\Table\Models\TableExport;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class XlsxFileBuilder
{
    /**
     * Read headers.csv then all other *.csv part files (lexicographic order),
     * write each row into a single Xlsx worksheet, apply header/cell styles,
     * call the exporter sheet hook, and save to $localPath.
     *
     * ⚠️ Memory: PhpSpreadsheet loads the entire workbook into memory (~1 KB/cell).
     * For large tables, configure a low {@see Export::maxRows()} limit or restrict
     * available formats to CSV-only via {@see Export::formats()}.
     */
    public function write(TableExport $export, Exporter $exporter, string $localPath): void
    {
        $disk = $export->getFileDisk();
        $partsDirectory = $export->getPartsDirectory();
        $delimiter = $exporter::getCsvDelimiter();

        $rows = (function () use ($disk, $partsDirectory, $delimiter): Generator {
            $paths = [$partsDirectory.'/headers.csv', ...collect($disk->files($partsDirectory))
                ->filter(fn (string $file): bool => str_ends_with($file, '.csv') && ! str_ends_with($file, 'headers.csv'))
                ->sort()
                ->values()
                ->all()];

            foreach ($paths as $path) {
                $stream = $disk->readStream($path);

                if (! is_resource($stream)) {
                    continue;
                }

                try {
                    while (($row = fgetcsv($stream, null, $delimiter)) !== false) {
                        yield $row;
                    }
                } finally {
                    fclose($stream);
                }
            }
        })();

        $this->writeRows($rows, $exporter, $localPath);
    }

    /**
     * Write rows to an XLSX workbook without requiring an intermediate table export.
     *
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function writeRows(iterable $rows, Exporter $exporter, string $localPath): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;

        /** @var IValueBinder $previousBinder */
        $previousBinder = Cell::getValueBinder();
        Cell::setValueBinder(new StringValueBinder);

        try {
            foreach ($rows as $row) {
                $sheet->fromArray($row, null, 'A'.$rowIndex++, true);
            }

            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();

            // Center every cell on both axes, then auto-size each column to its content.
            // Applied before the exporter hooks so a custom exporter can still override.
            $sheet->getStyle('A1:'.$highestColumn.$highestRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $lastColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            for ($index = 1; $index <= $lastColumnIndex; $index++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
            }

            // Excel sort/filter dropdowns on the header, spanning the full data range.
            $sheet->setAutoFilter('A1:'.$highestColumn.$highestRow);

            if (($headerStyle = $exporter->getXlsxHeaderStyle()) !== null) {
                $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray($headerStyle);
            }

            if (($cellStyle = $exporter->getXlsxCellStyle()) !== null && $highestRow > 1) {
                $sheet->getStyle('A2:'.$highestColumn.$highestRow)->applyFromArray($cellStyle);
            }

            $exporter->configureXlsxSheet($sheet);

            (new Xlsx($spreadsheet))->save($localPath);
        } finally {
            Cell::setValueBinder($previousBinder);
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Write the export to a temp file and return its path.
     * The caller is responsible for unlinking the file when done.
     */
    public function writeToTemp(TableExport $export, Exporter $exporter): string
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'table-export-');
        $this->write($export, $exporter, $tmp);

        return $tmp;
    }
}
