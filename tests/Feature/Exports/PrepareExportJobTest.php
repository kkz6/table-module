<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Table\Exports\Jobs\ExportRowsJob;
use Modules\Table\Exports\Jobs\PrepareExportJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\AuthScopedUsersTable;
use Modules\Table\Tests\Support\JoinedUsersTable;
use Modules\Table\Tests\Support\OrderedUsersTable;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;

it('writes the headers csv and fans out chunk jobs', function (): void {
    Storage::fake('local');
    User::factory()->count(5)->create();

    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 5,
    ]);

    [$job, $batch] = (new PrepareExportJob($tableExport, TestUsersTable::make(), 1, ['name' => 'Full name'], [], 2))->withFakeBatch();
    $job->handle();

    expect(Storage::disk('local')->get($tableExport->getPartsDirectory().'/headers.csv'))
        ->toContain('Full name');

    expect($batch->added)->toHaveCount(3)
        ->and($batch->added[0])->toBeInstanceOf(ExportRowsJob::class)
        ->and($batch->added[0]->page)->toBe(1)
        ->and($batch->added[0]->keys)->toHaveCount(2);
});

it('caps fanned-out keys at total_rows', function (): void {
    Storage::fake('local');
    User::factory()->count(5)->create();

    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 3,
    ]);

    [$job, $batch] = (new PrepareExportJob($tableExport, TestUsersTable::make(), 1, ['name' => 'Full name'], [], 2))->withFakeBatch();
    $job->handle();

    $totalKeys = array_sum(array_map(fn (ExportRowsJob $j): int => count($j->keys), $batch->added));

    expect($totalKeys)->toBe(3)
        ->and($batch->added)->toHaveCount(2);
});

it('sets auth as the export owner before building the exporter query', function (): void {
    Storage::fake('local');

    $owner = User::factory()->create();
    User::factory()->create();

    $tableExport = TableExport::factory()->for($owner)->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 1,
    ]);

    auth()->logout();

    [$job, $batch] = (new PrepareExportJob($tableExport, AuthScopedUsersTable::make(), 0, ['name' => 'Full name'], [], 1))->withFakeBatch();
    $job->handle();

    $allKeys = array_merge(...array_map(fn (ExportRowsJob $j): array => $j->keys, $batch->added));

    expect($allKeys)->toContain($owner->id)->toHaveCount(1);
});

it('fans out all keys without duplicates even when resource has an orderBy', function (): void {
    Storage::fake('local');
    $users = User::factory()->count(5)->create();

    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 5,
    ]);

    [$job, $batch] = (new PrepareExportJob($tableExport, OrderedUsersTable::make(), 0, ['name' => 'Full name'], [], 2))->withFakeBatch();
    $job->handle();

    $allKeys = array_merge(...array_map(fn (ExportRowsJob $j): array => $j->keys, $batch->added));

    expect($allKeys)->toHaveCount(5)
        ->and(array_unique($allKeys))->toHaveCount(5)
        ->and(array_diff($users->pluck('id')->all(), $allKeys))->toBeEmpty();
});

it('fans out without an ambiguous key when the resource joins another table', function (): void {
    Storage::fake('local');
    $users = User::factory()->count(5)->create();

    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 5,
    ]);

    [$job, $batch] = (new PrepareExportJob($tableExport, JoinedUsersTable::make(), 0, ['name' => 'Full name'], [], 2))->withFakeBatch();
    $job->handle();

    $allKeys = array_merge(...array_map(fn (ExportRowsJob $j): array => $j->keys, $batch->added));

    expect($allKeys)->toHaveCount(5)
        ->and(array_diff($users->pluck('id')->all(), $allKeys))->toBeEmpty();
});
