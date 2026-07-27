<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Modules\Table\Exports\Jobs\ExportRowsJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\AuthProbeUserExporter;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\Table\Tests\Support\ThrowingUserExporter;
use Modules\User\Models\User;

it('writes a csv part with mapped and formatted rows', function (): void {
    Storage::fake('local');
    $users       = User::factory()->count(2)->sequence(['name' => 'Aiko'], ['name' => 'Ben'])->create();
    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 2,
    ]);

    (new ExportRowsJob($tableExport, TestUsersTable::make(), 1, $users->pluck('id')->all(), ['name' => 'Full name'], [], 1))->handle();

    $part = Storage::disk('local')->get($tableExport->getPartsDirectory().'/0000000000000001.csv');
    expect($part)->toContain('Aiko')->toContain('Ben')->not->toContain('Full name');

    expect($tableExport->refresh())
        ->processed_rows->toBe(2)
        ->successful_rows->toBe(2);
});

it('counts a throwing row as processed but not successful', function (): void {
    Exceptions::fake();
    Storage::fake('local');

    $users       = User::factory()->count(2)->sequence(['name' => 'BOOM'], ['name' => 'Safe'])->create();
    $tableExport = TableExport::factory()->create([
        'exporter'   => ThrowingUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 2,
    ]);

    (new ExportRowsJob($tableExport, TestUsersTable::make(), 1, $users->pluck('id')->all(), ['name' => 'Name'], [], 1))->handle();

    $part = Storage::disk('local')->get($tableExport->getPartsDirectory().'/0000000000000001.csv');
    expect($part)->toContain('Safe')->not->toContain('BOOM');

    expect($tableExport->refresh())
        ->processed_rows->toBe(2)
        ->successful_rows->toBe(1);

    Exceptions::assertReported(RuntimeException::class);
});

it('authenticates as the export owner during mapping', function (): void {
    Storage::fake('local');

    $owner       = User::factory()->create();
    $subject     = User::factory()->create(['name' => 'Subject']);
    $tableExport = TableExport::factory()->create([
        'exporter'   => AuthProbeUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 1,
        'user_id'    => $owner->id,
    ]);

    (new ExportRowsJob($tableExport, TestUsersTable::make(), 1, [$subject->id], ['name' => 'Name'], [], 1))->handle();

    $part = Storage::disk('local')->get($tableExport->getPartsDirectory().'/0000000000000001.csv');
    expect($part)->toContain((string) $owner->id);
});

it('clamps counters to total_rows under repeated runs', function (): void {
    Storage::fake('local');

    $users       = User::factory()->count(2)->create();
    $tableExport = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'total_rows' => 2,
    ]);

    $job = new ExportRowsJob($tableExport, TestUsersTable::make(), 1, $users->pluck('id')->all(), ['name' => 'Full name'], [], 1);
    $job->handle();
    $job->handle();

    expect($tableExport->refresh())
        ->processed_rows->toBe(2)
        ->successful_rows->toBe(2);
});
