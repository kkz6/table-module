<?php

declare(strict_types=1);

namespace Modules\Table\Tests\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
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

    public static function exportUrl(string $label, bool $queued = false): string
    {
        $table   = static::make();
        $exports = $table->toArray()['exports'];
        $url     = collect($exports)->firstWhere('label', $label)['url'];

        if (! $queued) {
            return $url;
        }

        $exportIndex = collect($exports)->search(fn (array $export): bool => $export['label'] === $label);

        return URL::signedRoute('inertia-tables.async-export', [
            'table'  => base64_encode(static::class),
            'name'   => $table->getName(),
            'export' => $exportIndex,
        ]);
    }

    public static function asyncExportUrl(string $label): string
    {
        return static::exportUrl($label, queued: true);
    }
}
