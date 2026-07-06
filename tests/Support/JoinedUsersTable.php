<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

class JoinedUsersTable extends Table
{
    /**
     * A resource whose query joins another table that also has an "id" column,
     * so an unqualified key in chunkById would raise an "ambiguous column" error.
     */
    public function resource(): Builder|string
    {
        return User::query()->leftJoin('companies', 'users.company_id', '=', 'companies.id');
    }

    public function columns(): array
    {
        return [
            TextColumn::make('name'),
        ];
    }

    public function exports(): array
    {
        return [
            Export::make(label: 'Joined Export')
                ->exporter(TestUserExporter::class)
                ->chunkSize(2),
        ];
    }
}
