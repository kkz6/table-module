<?php

declare(strict_types=1);

use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\Exporter;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;
use Modules\User\Models\User;

function makeExporterDouble(array $columnMap = []): Exporter
{
    $export = TableExport::factory()->create();

    return new class($export, $columnMap, ['tone' => 'formal']) extends Exporter
    {
        public static function getColumns(): array
        {
            return [];
        }

        public function useRecord(?\Illuminate\Database\Eloquent\Model $record): void
        {
            $this->record = $record;
        }
    };
}

it('resolves state from a model attribute', function () {
    $user             = User::factory()->create(['name' => 'Aiko']);
    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $column = ExportColumn::make('name')->exporter($exporter);

    expect($column->getFormattedState())->toBe('Aiko');
});

it('resolves dot-notation state across to-many relationships', function () {
    $user = User::factory()->create();
    $user->assignRole(\Spatie\Permission\Models\Role::create(['name' => 'alpha', 'guard_name' => 'web']));
    $user->assignRole(\Spatie\Permission\Models\Role::create(['name' => 'beta', 'guard_name' => 'web']));

    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $column = ExportColumn::make('roles.name')->exporter($exporter);

    expect($column->getFormattedState())->toBe('alpha, beta');
});

it('uses a state closure with named injections', function () {
    $user             = User::factory()->create(['name' => 'Aiko']);
    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $column = ExportColumn::make('custom')
        ->state(fn (User $record, array $options): string => $record->name.'-'.$options['tone'])
        ->exporter($exporter);

    expect($column->getFormattedState())->toBe('Aiko-formal');
});

it('applies the format pipeline', function () {
    $user             = User::factory()->create(['name' => 'Aiko']);
    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $formatted = ExportColumn::make('name')
        ->formatStateUsing(fn ($state) => strtoupper($state))
        ->prefix('[')
        ->suffix(']')
        ->exporter($exporter)
        ->getFormattedState();

    expect($formatted)->toBe('[AIKO]');

    $limited = ExportColumn::make('custom')
        ->state(fn () => 'abcdef')
        ->limit(3)
        ->exporter($exporter)
        ->getFormattedState();

    expect($limited)->toBe('abc...');
});

it('falls back to default when state is blank', function () {
    $user             = User::factory()->create(['name' => '']);
    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $column = ExportColumn::make('name')
        ->default('n/a')
        ->exporter($exporter);

    expect($column->getFormattedState())->toBe('n/a');
});

it('serializes array state as json when listAsJson', function () {
    $user = User::factory()->create();
    $user->assignRole(\Spatie\Permission\Models\Role::create(['name' => 'alpha', 'guard_name' => 'web']));
    $user->assignRole(\Spatie\Permission\Models\Role::create(['name' => 'beta', 'guard_name' => 'web']));

    $exporter         = makeExporterDouble();
    $exporter->useRecord($user);

    $column = ExportColumn::make('roles.name')
        ->listAsJson()
        ->exporter($exporter);

    expect($column->getFormattedState())->toBe('["alpha","beta"]');
});

it('splits string state on a separator', function () {
    $exporter = makeExporterDouble();

    $column = ExportColumn::make('custom')
        ->state(fn () => 'a,b')
        ->separator(',')
        ->exporter($exporter);

    expect($column->getFormattedState())->toBe('a, b');
});

it('derives label from name and allows override', function () {
    expect(ExportColumn::make('created_at')->getLabel())->toBe('Created at')
        ->and(ExportColumn::make('x')->label('Custom')->getLabel())->toBe('Custom');
});

it('is enabled by default unless disabled', function () {
    expect(ExportColumn::make('a')->isEnabledByDefault())->toBeTrue()
        ->and(ExportColumn::make('a')->enabledByDefault(false)->isEnabledByDefault())->toBeFalse();
});

it('converts backed enums and booleans to exportable strings', function () {
    $exporter = makeExporterDouble();

    $enumFormatted = ExportColumn::make('custom')
        ->state(fn () => ExportFormat::Csv)
        ->exporter($exporter)
        ->getFormattedState();

    expect($enumFormatted)->toBe('csv');

    $boolTrueFormatted = ExportColumn::make('custom')
        ->state(fn () => true)
        ->exporter($exporter)
        ->getFormattedState();

    expect($boolTrueFormatted)->toBe('True');

    $boolFalseFormatted = ExportColumn::make('custom')
        ->state(fn () => false)
        ->exporter($exporter)
        ->getFormattedState();

    expect($boolFalseFormatted)->toBe('False');
});
