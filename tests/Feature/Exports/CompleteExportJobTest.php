<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Table\Events\ExportReady;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Exports\Jobs\CompleteExportJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('touches completed_at, persists the notification, and broadcasts it', function (): void {
    Event::fake([ExportReady::class]);

    $tableExport = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 5,
        'successful_rows' => 5,
        'processed_rows'  => 5,
        'user_id'         => $this->user->id,
    ]);

    (new CompleteExportJob($tableExport, [ExportFormat::Csv, ExportFormat::Xlsx], 'Test Users'))->handle();

    expect($tableExport->refresh()->completed_at)->not->toBeNull();

    $row = $this->user->fresh()->notifications()->first();

    expect($row)->not->toBeNull()
        ->and($row->data['severity'])->toBe('success')
        ->and($row->data['downloads'])->toHaveCount(2);

    Event::assertDispatched(ExportReady::class, function (ExportReady $event) use ($row): bool {
        $payload = $event->broadcastWith();

        return $payload['id'] === $row->id
            && $payload['severity'] === 'success'
            && count($payload['downloads']) === 2
            && str_contains($payload['downloads'][0]['url'], 'signature=');
    });
});

it('broadcasts warning severity with partial failures', function (): void {
    Event::fake([ExportReady::class]);

    $tableExport = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 5,
        'successful_rows' => 3,
        'processed_rows'  => 5,
        'user_id'         => $this->user->id,
    ]);

    (new CompleteExportJob($tableExport, [ExportFormat::Csv, ExportFormat::Xlsx], 'Test Users'))->handle();

    Event::assertDispatched(ExportReady::class, function (ExportReady $event): bool {
        $payload = $event->broadcastWith();

        return $payload['severity'] === 'warning'
            && count($payload['downloads']) === 2;
    });
});

it('omits downloads and broadcasts error when everything failed', function (): void {
    Event::fake([ExportReady::class]);

    $tableExport = TableExport::factory()->create([
        'exporter'        => TestUserExporter::class,
        'total_rows'      => 5,
        'successful_rows' => 0,
        'processed_rows'  => 5,
        'user_id'         => $this->user->id,
    ]);

    (new CompleteExportJob($tableExport, [ExportFormat::Csv, ExportFormat::Xlsx], 'Test Users'))->handle();

    Event::assertDispatched(ExportReady::class, function (ExportReady $event): bool {
        $payload = $event->broadcastWith();

        return $payload['severity'] === 'error'
            && $payload['downloads'] === [];
    });
});
