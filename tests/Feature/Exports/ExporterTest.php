<?php

declare(strict_types=1);

use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\User\Models\User;

it('maps a record through the column map in order', function () {
    $user     = User::factory()->create(['name' => 'Aiko', 'email' => 'aiko@example.com']);
    $export   = TableExport::factory()->create(['exporter' => TestUserExporter::class]);
    $exporter = $export->getExporter(['email' => 'Mail', 'name' => 'Name'], []);

    expect($exporter($user))->toBe(['aiko@example.com', 'Aiko']);
});

it('throws instead of writing a blank cell when a selected column no longer resolves', function () {
    $user     = User::factory()->create();
    $export   = TableExport::factory()->create(['exporter' => TestUserExporter::class]);
    $exporter = $export->getExporter(['name' => 'Name', 'gone' => 'Gone'], []);

    expect(fn () => $exporter($user))->toThrow(RuntimeException::class);
});

it('generates a default file name', function () {
    $export = TableExport::factory()->create(['exporter' => TestUserExporter::class]);

    expect($export->getExporter()->getFileName($export))->toBe('export-'.$export->getKey().'-users');
});

it('exposes default formats, delimiter and disk', function () {
    expect(TestUserExporter::getFormats())->toBe([ExportFormat::Csv, ExportFormat::Xlsx])
        ->and(TestUserExporter::getCsvDelimiter())->toBe(',')
        ->and(TableExport::factory()->create(['exporter' => TestUserExporter::class])->getExporter()->getFileDisk())->toBe('local');
});

it('provides job tuning defaults and no cross-chunk locking', function () {
    $exporter = TableExport::factory()->create(['exporter' => TestUserExporter::class])->getExporter();

    // Chunk jobs write distinct part files and update counters transactionally, so
    // no WithoutOverlapping lock is applied (a shared lock would busy-loop siblings).
    expect($exporter->getJobMiddleware())->toBe([])
        ->and($exporter->getJobBackoff())->toBe([60, 120, 300, 600])
        ->and($exporter->getJobTags())->not->toBeEmpty();
});

it('builds the completed toast body from row arithmetic', function () {
    $export = TableExport::factory()->create(['exporter' => TestUserExporter::class, 'total_rows' => 10, 'successful_rows' => 8]);

    expect(TestUserExporter::getCompletedToastBody($export))->toContain('8')->toContain('2');
});

it('derives a pluralized model label for the toast title', function () {
    expect(TestUserExporter::getModelLabel())->toBe('Users');
});
