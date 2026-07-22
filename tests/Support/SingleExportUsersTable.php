<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

class SingleExportUsersTable extends Table
{
    public function resource(): EloquentBuilder|string
    {
        return User::class;
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name'),
            TextColumn::make('email'),
        ];
    }

    public function export(): Export
    {
        return Export::make(label: 'Users Export');
    }
}
