<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Export;
use Modules\Table\Table;
use Modules\User\Models\User;

class TestUsersTable extends Table
{
    public function resource(): Builder|string
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

    public function exports(): array
    {
        return [
            Export::make(label: 'Legacy Export', filename: 'legacy.xlsx'),
            Export::make(label: 'Pipeline Export')->exporter(TestUserExporter::class)->chunkSize(2),
            Export::make(label: 'Tiny Export')->exporter(TestUserExporter::class)->maxRows(2),
            Export::make(label: 'No Mapping Export')->exporter(TestUserExporter::class)->columnMapping(false)->options(['tone' => 'formal']),
            Export::make(label: 'Forbidden Export', authorize: false)->exporter(TestUserExporter::class),
            Export::make(label: 'Pipeline Selected')->exporter(TestUserExporter::class)->chunkSize(2)->limitToSelectedRows(),
        ];
    }

    public static function exportUrl(string $label): string
    {
        return collect(static::make()->toArray()['exports'])->firstWhere('label', $label)['url'];
    }
}
