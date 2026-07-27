<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Table\Events\ExportReady;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;

it('runs the full pipeline inline on the sync queue', function (): void {
    Storage::fake('local');
    Event::fake([ExportReady::class]);
    $this->actingAs(User::factory()->create(['name' => 'Me']));
    User::factory()->count(4)->sequence(fn ($s) => ['name' => 'User '.$s->index])->create();

    $url = TestUsersTable::asyncExportUrl('Pipeline Export');

    $this->postJson($url, [
        'columnMap' => [
            'name'  => ['isEnabled' => true, 'label' => 'Full name'],
            'email' => ['isEnabled' => true, 'label' => ''],
        ],
        'formats' => ['csv', 'xlsx'],
    ])->assertSuccessful();

    $export = TableExport::query()->sole();

    expect($export->completed_at)->not->toBeNull()
        ->and($export->total_rows)->toBe(5)
        ->and($export->processed_rows)->toBe(5)
        ->and($export->successful_rows)->toBe(5);

    // merged csv + xlsx at top level; parts/ subdir with headers + 3 chunks (chunkSize=2, 5 users)
    expect(Storage::disk('local')->files($export->getFileDirectory()))->toHaveCount(2);
    expect(Storage::disk('local')->files($export->getPartsDirectory()))->toHaveCount(4);

    $csvPath = $export->getFileDirectory().'/'.$export->file_name.'.csv';
    expect(Storage::disk('local')->exists($csvPath))->toBeTrue();
    expect(Storage::disk('local')->exists($export->getFileDirectory().'/'.$export->file_name.'.xlsx'))->toBeTrue();

    $csv = Storage::disk('local')->get($csvPath);
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($csv)->toContain('Full name')->toContain('Email address'); // blank label falls back to column label

    Event::assertDispatched(ExportReady::class);
});

it('respects selected rows via the keys parameter', function (): void {
    Storage::fake('local');
    Event::fake([ExportReady::class]);
    $this->actingAs(User::factory()->create(['name' => 'Owner']));
    $users = User::factory()->count(4)->create();

    $url  = TestUsersTable::asyncExportUrl('Pipeline Selected');
    $keys = $users->take(2)->pluck('id')->join(',');

    $this->postJson($url.'&keys='.$keys, [
        'columnMap' => ['name' => ['isEnabled' => true, 'label' => '']],
        'formats'   => ['csv'],
    ])->assertSuccessful();

    $export = TableExport::query()->sole();

    expect($export->total_rows)->toBe(2)
        ->and($export->processed_rows)->toBe(2)
        ->and($export->successful_rows)->toBe(2);

    Event::assertDispatched(ExportReady::class);
});

it('still serves the legacy synchronous export', function (): void {
    $this->actingAs(User::factory()->create());
    $url = TestUsersTable::exportUrl('Legacy Export');

    $this->get($url)->assertSuccessful()->assertDownload();
});
