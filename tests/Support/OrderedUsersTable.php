<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

/**
 * Test fixture: table whose resource() carries an explicit orderByDesc clause.
 * Used in PrepareExportJobTest to verify that chunkById() receives a reorder()ed query.
 */
class OrderedUsersTable extends Table
{
    public function resource(): Builder|string
    {
        return User::query()->orderByDesc('name');
    }

    public function columns(): array
    {
        return [];
    }

    public function exports(): array
    {
        return [
            Export::make(label: 'Ordered Export')->exporter(TestUserExporter::class)->chunkSize(2),
        ];
    }
}
