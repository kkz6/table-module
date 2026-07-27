<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\Exporter;
use Modules\User\Models\User;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Test fixture: exporter that returns a custom italic cell style and renames the
 * sheet via configureXlsxSheet. Used in CreateXlsxFileJobTest.
 */
class StyledUserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
        ];
    }

    public function getXlsxCellStyle(): ?array
    {
        return ['font' => ['italic' => true]];
    }

    public function configureXlsxSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Styled Sheet');
    }
}
