<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\Exporter;
use Modules\User\Models\User;
use RuntimeException;

/**
 * Test fixture: exporter that throws for a record whose name equals 'BOOM'.
 * Used exclusively in ExportRowsJobTest to exercise the per-row catch block.
 */
class ThrowingUserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->formatStateUsing(function (string $state): string {
                if ($state === 'BOOM') {
                    throw new RuntimeException('Intentional test explosion for name=BOOM');
                }

                return $state;
            }),
        ];
    }
}
