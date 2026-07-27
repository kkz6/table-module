<?php

declare(strict_types=1);

use Modules\Table\Columns\TextColumn;
use Modules\Table\Export;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Exports\Options\CheckboxOption;
use Modules\Table\Exports\Options\SelectOption;
use Modules\Table\Exports\Options\TextOption;
use Modules\Table\Tests\Support\ConfiguredSingleExportUsersTable;
use Modules\Table\Tests\Support\SingleExportUsersTable;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('marks an export as pipeline when an exporter is attached', function (): void {
    $export = Export::make(label: 'X')->exporter(TestUserExporter::class);

    expect($export->hasExporter())->toBeTrue()
        ->and($export->hasColumnMapping())->toBeTrue()
        ->and(Export::make(label: 'Y')->hasExporter())->toBeFalse();
});

it('serializes pipeline metadata to the frontend payload', function (): void {
    $table   = TestUsersTable::make();
    $payload = collect($table->toArray()['exports'])->firstWhere('label', 'Pipeline Export');

    expect($payload['hasColumnMapping'])->toBeTrue()
        ->and($payload['asDownload'])->toBeFalse()
        ->and($payload['formats'])->toBe(['csv', 'xlsx'])
        ->and($payload['columns'])->toBe([
            ['name' => 'name', 'label' => 'Name', 'enabledByDefault' => true],
            ['name' => 'email', 'label' => 'Email address', 'enabledByDefault' => true],
            ['name' => 'roles_count', 'label' => 'Roles count', 'enabledByDefault' => false],
        ])
        ->and($payload['url'])->toContain('/stream-export/');
});

it('keeps the legacy payload shape for non-exporter exports', function (): void {
    $payload = collect(TestUsersTable::make()->toArray()['exports'])->firstWhere('label', 'Legacy Export');

    expect($payload)->not->toHaveKey('columns')
        ->and($payload['asDownload'])->toBeTrue()
        ->and($payload['url'])->toContain('/export/');
});

it('normalizes the singular table export into the table export payload', function (): void {
    $table   = SingleExportUsersTable::make();
    $payload = $table->toArray();

    expect($payload['exports'])->toHaveCount(1)
        ->and($payload['exports'][0]['label'])->toBe('Users Export')
        ->and($payload['exports'][0]['url'])->toContain('/stream-export/');
});

it('uses the table-backed exporter for every singular table export', function (): void {
    $payload = ConfiguredSingleExportUsersTable::make()->toArray()['exports'][0];

    expect($payload['hasExporter'])->toBeTrue()
        ->and($payload['url'])->toContain('/stream-export/');
});

it('preserves an explicitly configured exporter on a singular table export', function (): void {
    $table = new class extends SingleExportUsersTable
    {
        public function export(): Export
        {
            return Export::make(label: 'Custom Export')->exporter(TestUserExporter::class);
        }
    };

    $payload = $table->toArray()['exports'][0];

    expect($payload['hasExporter'])->toBeTrue()
        ->and($payload['columns'])->toHaveCount(3);
});

it('can explicitly select a simple export for a singular table', function (): void {
    $table = new class extends SingleExportUsersTable
    {
        public function export(): Export
        {
            return Export::make(label: 'Simple Export', filename: 'users.xlsx')
                ->dynamicExport(false);
        }
    };

    $payload = $table->toArray()['exports'][0];

    expect($payload)->not->toHaveKey('hasExporter')
        ->and($payload['asDownload'])->toBeTrue()
        ->and($payload['url'])->toContain('/export/');
});

it('uses a clear API for export columns that are unchecked by default', function (): void {
    $column = TextColumn::make('name')->includeInExportByDefault(false);

    expect($column->isIncludedInExportByDefault())->toBeFalse()
        ->and($column->exportEnabledByDefault())->toBe($column)
        ->and($column->isIncludedInExportByDefault())->toBeTrue();
});

it('uses exportable to include or exclude a column from exports', function (): void {
    $column = TextColumn::make('id')->exportable(false);

    expect($column->shouldBeExported())->toBeFalse()
        ->and($column->exportable())->toBe($column)
        ->and($column->shouldBeExported())->toBeTrue();
});

it('builds the exporter query with modifyQuery hooks applied', function (): void {
    User::factory()->count(3)->create();
    $blocked = User::factory()->create(['name' => 'BLOCKED']);

    $table  = TestUsersTable::make();
    $export = $table->getExportById(1);   // pipeline export (index 1)
    $export->modifyQueryUsing(fn ($query) => $query->where('name', '!=', 'BLOCKED'));

    expect($export->buildExporterQuery()->pluck('name'))->not->toContain('BLOCKED');
});

it('evaluates chunk size, max rows, formats and options', function (): void {
    $export = Export::make(label: 'X')
        ->exporter(TestUserExporter::class)
        ->chunkSize(fn (): int => 50)
        ->maxRows(1000)
        ->formats([ExportFormat::Csv])
        ->options(['tone' => 'formal']);

    expect($export->getChunkSize())->toBe(50)
        ->and($export->getMaxRows())->toBe(1000)
        ->and($export->getFormats())->toBe([ExportFormat::Csv])
        ->and($export->getExportOptions())->toBe(['tone' => 'formal']);
});

it('uses exporter class formats when formats are not explicitly set', function (): void {
    $export = Export::make(label: 'X')->exporter(TestUserExporter::class);

    expect($export->getFormats())->toBe([ExportFormat::Csv, ExportFormat::Xlsx]);
});

it('returns empty array when no exporter is set', function (): void {
    $export = Export::make(label: 'X');

    expect($export->getFormats())->toBe([]);
});

it('serializes option form fields', function (): void {
    $select = SelectOption::make('tone')->label('Tone')->options(['formal' => 'Formal'])->default('formal');

    expect($select->toArray())->toBe([
        'type'    => 'select',
        'name'    => 'tone',
        'label'   => 'Tone',
        'default' => 'formal',
        'options' => ['formal' => 'Formal'],
    ]);

    $text = TextOption::make('note');

    expect($text->toArray())->toBe([
        'type'    => 'text',
        'name'    => 'note',
        'label'   => 'Note',
        'default' => null,
    ]);

    $checkbox = CheckboxOption::make('include_archived')->label('Include archived');

    expect($checkbox->toArray())->toBe([
        'type'    => 'checkbox',
        'name'    => 'include_archived',
        'label'   => 'Include archived',
        'default' => null,
    ]);
});

it('derives label from name when not explicitly set for option fields', function (): void {
    expect(TextOption::make('my_field')->getLabel())->toBe('My field')
        ->and(SelectOption::make('export_format')->getLabel())->toBe('Export format');
});

it('defaults chunk size to 100 when not configured', function (): void {
    $export = Export::make(label: 'X')->exporter(TestUserExporter::class);

    expect($export->getChunkSize())->toBe(100);
});

it('defaults column mapping to true and can be toggled', function (): void {
    $export = Export::make(label: 'X')->exporter(TestUserExporter::class);

    expect($export->hasColumnMapping())->toBeTrue();

    $export->columnMapping(false);

    expect($export->hasColumnMapping())->toBeFalse();
});

it('pipeline export url uses stream-export route', function (): void {
    $table  = TestUsersTable::make();
    $export = $table->getExportById(1); // pipeline export

    expect($export->getExportUrl())->toContain('/stream-export/');
});

it('enableVisibleTableColumnsByDefault defaults to false and can be toggled', function (): void {
    $export = Export::make(label: 'X')->exporter(TestUserExporter::class);

    expect($export->shouldEnableVisibleTableColumnsByDefault())->toBeFalse();

    $export->enableVisibleTableColumnsByDefault();

    expect($export->shouldEnableVisibleTableColumnsByDefault())->toBeTrue();
});
