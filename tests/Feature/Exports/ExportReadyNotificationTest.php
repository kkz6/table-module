<?php

declare(strict_types=1);

use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;
use Modules\Table\Notifications\ExportReadyNotification;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\User\Models\User;

it('persists via the database channel only (realtime is a separate broadcast event)', function (): void {
    $user   = User::factory()->create();
    $export = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 3,
        'successful_rows' => 3,
        'processed_rows'  => 3,
        'user_id'         => $user->id,
    ]);

    $notification = new ExportReadyNotification($export, [ExportFormat::Csv, ExportFormat::Xlsx], 'Test Users');

    expect($notification->via($user))->toBe(['database']);
});

it('builds the correct payload for a successful export', function (): void {
    $user   = User::factory()->create();
    $export = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 3,
        'successful_rows' => 3,
        'processed_rows'  => 3,
        'user_id'         => $user->id,
    ]);

    $notification = new ExportReadyNotification($export, [ExportFormat::Csv, ExportFormat::Xlsx], 'Test Users');
    $payload      = $notification->toArray($user);

    expect($payload['type'])->toBe('export_ready')
        ->and($payload['exportId'])->toBe($export->getKey())
        ->and($payload['title'])->toContain('Test Users')
        ->and($payload['severity'])->toBe('success')
        ->and($payload['downloads'])->toHaveCount(2)
        ->and($payload['downloads'][0]['format'])->toBe('csv')
        ->and($payload['downloads'][1]['format'])->toBe('xlsx')
        ->and($payload['downloads'][0]['url'])->toContain('signature=');
});

it('omits downloads when all rows failed', function (): void {
    $user   = User::factory()->create();
    $export = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 3,
        'successful_rows' => 0,
        'processed_rows'  => 3,
        'user_id'         => $user->id,
    ]);

    $notification = new ExportReadyNotification($export, [ExportFormat::Csv], 'Test Users');
    $payload      = $notification->toArray($user);

    expect($payload['severity'])->toBe('error')
        ->and($payload['downloads'])->toBeEmpty();
});

it('includes downloads when some rows succeeded', function (): void {
    $user   = User::factory()->create();
    $export = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 5,
        'successful_rows' => 3,
        'processed_rows'  => 5,
        'user_id'         => $user->id,
    ]);

    $notification = new ExportReadyNotification($export, [ExportFormat::Csv], 'Test Users');
    $payload      = $notification->toArray($user);

    expect($payload['severity'])->toBe('warning')
        ->and($payload['downloads'])->toHaveCount(1)
        ->and($payload['downloads'][0]['format'])->toBe('csv');
});
