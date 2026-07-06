<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\Exporter;
use Modules\User\Models\User;

/**
 * Test fixture: exporter whose column returns the currently-authenticated user ID.
 * Used exclusively in ExportRowsJobTest to verify auth()->setUser() is called.
 */
class AuthProbeUserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->formatStateUsing(fn (): string => (string) auth()->id()),
        ];
    }
}
