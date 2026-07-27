<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Table\Exports\Jobs\CreateCsvFileJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUserExporter;

it('merges parts into a single csv file with BOM at the top level', function (): void {
    Storage::fake('local');

    $tableExport = TableExport::factory()->create([
        'exporter'  => TestUserExporter::class,
        'file_disk' => 'local',
        'file_name' => 'people',
        'total_rows' => 2,
    ]);

    Storage::disk('local')->put($tableExport->getPartsDirectory().'/headers.csv', "Full name\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000001.csv', "Aiko\n");
    Storage::disk('local')->put($tableExport->getPartsDirectory().'/0000000000000002.csv', "Ben\n");

    (new CreateCsvFileJob($tableExport))->handle();

    $csvPath = $tableExport->getFileDirectory().'/people.csv';

    expect(Storage::disk('local')->exists($csvPath))->toBeTrue();

    $content = Storage::disk('local')->get($csvPath);

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($content)->toContain('Full name')
        ->and($content)->toContain('Aiko')
        ->and($content)->toContain('Ben');
});

it('has the expected job configuration', function (): void {
    $job = new CreateCsvFileJob(TableExport::factory()->make());

    expect($job->tries)->toBe(3)
        ->and($job->maxExceptions)->toBe(0)
        ->and($job->backoff)->toBe([30, 60, 300]);
});
