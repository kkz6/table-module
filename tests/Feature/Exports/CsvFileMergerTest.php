<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Table\Exports\CsvFileMerger;
use Modules\Table\Models\TableExport;

it('merges headers and parts into one file with a UTF-8 BOM', function (): void {
    Storage::fake('local');

    $export = TableExport::factory()->create([
        'file_disk' => 'local',
        'file_name' => 'merged',
    ]);

    Storage::disk('local')->put($export->getPartsDirectory().'/headers.csv', "Full name\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000001.csv', "Aiko\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000002.csv', "Ben\n");

    $path = app(CsvFileMerger::class)->merge($export);

    expect($path)->toBe($export->getFileDirectory().'/merged.csv');

    $content = Storage::disk('local')->get($path);

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(substr($content, 3))->toBe("Full name\nAiko\nBen\n");
});

it('writes parts in lexicographic order', function (): void {
    Storage::fake('local');

    $export = TableExport::factory()->create([
        'file_disk' => 'local',
        'file_name' => 'ordered',
    ]);

    Storage::disk('local')->put($export->getPartsDirectory().'/headers.csv', "Name\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000002.csv', "Charlie\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000001.csv', "Aiko\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000003.csv', "Ben\n");

    $content = Storage::disk('local')->get(app(CsvFileMerger::class)->merge($export));

    expect(substr($content, 3))->toBe("Name\nAiko\nCharlie\nBen\n");
});

it('returns the correct destination path', function (): void {
    Storage::fake('local');

    $export = TableExport::factory()->create([
        'file_disk' => 'local',
        'file_name' => 'my-export',
    ]);

    Storage::disk('local')->put($export->getPartsDirectory().'/headers.csv', "Col\n");

    $path = app(CsvFileMerger::class)->merge($export);

    expect($path)->toBe($export->getFileDirectory().'/my-export.csv');
    expect(Storage::disk('local')->exists($path))->toBeTrue();
});
