<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\Exporter;
use Modules\User\Models\User;

class TestUserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('email')->label('Email address'),
            ExportColumn::make('roles_count')->counts('roles')->enabledByDefault(false),
        ];
    }
}
