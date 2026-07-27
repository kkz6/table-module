<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUserExporter;

it('prunes exports and their files older than the retention window, keeping recent ones', function (): void {
    Storage::fake('local');

    $old = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'created_at' => now()->subDays(20),
    ]);
    $recent = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'created_at' => now()->subDays(5),
    ]);

    Storage::disk('local')->put($old->getFileDirectory().'/export.csv', 'old');
    Storage::disk('local')->put($recent->getFileDirectory().'/export.csv', 'recent');

    $this->artisan('table:prune-exports')->assertSuccessful();

    expect(TableExport::find($old->id))->toBeNull()
        ->and(Storage::disk('local')->exists($old->getFileDirectory().'/export.csv'))->toBeFalse()
        ->and(TableExport::find($recent->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($recent->getFileDirectory().'/export.csv'))->toBeTrue();
});

it('respects a custom --days window', function (): void {
    Storage::fake('local');

    $export = TableExport::factory()->create([
        'exporter'   => TestUserExporter::class,
        'file_disk'  => 'local',
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('table:prune-exports', ['--days' => 7])->assertSuccessful();

    expect(TableExport::find($export->id))->toBeNull();
});
