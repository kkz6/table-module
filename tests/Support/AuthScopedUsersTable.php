<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

/**
 * Test fixture: table that scopes resource() to the currently-authenticated user's id.
 * Used in PrepareExportJobTest to verify auth context is set before buildExporterQuery().
 */
class AuthScopedUsersTable extends Table
{
    public function resource(): Builder|string
    {
        return User::query()->whereKey(auth()->id());
    }

    public function columns(): array
    {
        return [];
    }

    public function exports(): array
    {
        return [
            Export::make(label: 'Scoped Export')->exporter(TestUserExporter::class)->chunkSize(1),
        ];
    }
}
