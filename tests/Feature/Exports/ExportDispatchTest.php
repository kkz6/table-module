<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Modules\Table\Exports\Jobs\CompleteExportJob;
use Modules\Table\Exports\Jobs\CreateCsvFileJob;
use Modules\Table\Exports\Jobs\CreateXlsxFileJob;
use Modules\Table\Exports\Jobs\PrepareExportJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('creates a table export row and dispatches the chain', function (): void {
    Bus::fake();
    User::factory()->count(3)->create();

    $response = $this->postJson(TestUsersTable::asyncExportUrl('Pipeline Export'), [
        'columnMap' => [
            'name' => ['isEnabled' => true, 'label' => 'Full name'],
            'email' => ['isEnabled' => false, 'label' => ''],
        ],
        'formats' => ['csv', 'xlsx'],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('started', true)
        ->assertJsonPath('totalRows', 4);

    $export = TableExport::query()->sole();
    expect($export->exporter)->toBe(\Modules\Table\Tests\Support\TestUserExporter::class)
        ->and($export->total_rows)->toBe(4)
        ->and($export->file_disk)->toBe('local')
        ->and($export->file_name)->not->toBeEmpty()
        ->and($export->user_id)->toBe(auth()->id());

    Bus::assertChained([
        Bus::chainedBatch(fn ($batch) => $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof PrepareExportJob
            && $batch->jobs->first()->columnMap === ['name' => 'Full name']),
        CreateCsvFileJob::class,
        CreateXlsxFileJob::class,
        CompleteExportJob::class,   // always last
    ]);
});

it('places the xlsx job before completion when xlsx is the only format', function (): void {
    Bus::fake();
    $this->postJson(TestUsersTable::asyncExportUrl('Pipeline Export'), [
        'columnMap' => ['name' => ['isEnabled' => true, 'label' => '']],
        'formats' => ['xlsx'],
    ])->assertSuccessful();

    Bus::assertChained([
        Bus::chainedBatch(fn ($batch) => true),
        CreateXlsxFileJob::class,  // xlsx only → no CreateCsvFileJob
        CompleteExportJob::class,
    ]);
});

it('rejects when no column is enabled', function (): void {
    Bus::fake();
    $this->postJson(TestUsersTable::asyncExportUrl('Pipeline Export'), [
        'columnMap' => ['name' => ['isEnabled' => false, 'label' => '']],
        'formats' => ['csv'],
    ])->assertUnprocessable();

    Bus::assertNothingDispatched();
    expect(TableExport::count())->toBe(0);
});

it('rejects unknown formats and unknown columns are ignored', function (): void {
    Bus::fake();
    $this->postJson(TestUsersTable::asyncExportUrl('Pipeline Export'), [
        'columnMap' => ['hacked' => ['isEnabled' => true, 'label' => 'x'], 'name' => ['isEnabled' => true, 'label' => '']],
        'formats' => ['pdf'],
    ])->assertUnprocessable();
});

it('aborts when max rows is exceeded', function (): void {
    Bus::fake();
    User::factory()->count(3)->create();
    $url = TestUsersTable::asyncExportUrl('Tiny Export');

    $this->postJson($url, [
        'columnMap' => ['name' => ['isEnabled' => true, 'label' => '']],
        'formats' => ['csv'],
    ])->assertUnprocessable();

    Bus::assertNothingDispatched();
});

it('uses default-enabled columns when column mapping is disabled', function (): void {
    Bus::fake();
    $url = TestUsersTable::asyncExportUrl('No Mapping Export');

    $this->postJson($url, [
        'formats' => ['csv'],
    ])->assertSuccessful();

    Bus::assertChained([
        Bus::chainedBatch(fn ($batch) => $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof PrepareExportJob
            && $batch->jobs->first()->columnMap === ['name' => 'Name', 'email' => 'Email address']),
        CreateCsvFileJob::class,
        CompleteExportJob::class,
    ]);
});

it('merges static options under request options', function (): void {
    Bus::fake();
    $url = TestUsersTable::asyncExportUrl('No Mapping Export');

    $this->postJson($url, [
        'formats' => ['csv'],
        'options' => ['tone' => 'casual', 'extra' => 'yes'],
    ])->assertSuccessful();

    Bus::assertChained([
        Bus::chainedBatch(fn ($batch) => $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof PrepareExportJob
            && $batch->jobs->first()->options === ['tone' => 'casual', 'extra' => 'yes']),
        CreateCsvFileJob::class,
        CompleteExportJob::class,
    ]);
});

it('requires authentication and authorization', function (): void {
    auth()->logout();
    $this->postJson(TestUsersTable::asyncExportUrl('Pipeline Export'), [])->assertUnauthorized();

    $this->actingAs(User::factory()->create());
    $this->postJson(TestUsersTable::asyncExportUrl('Forbidden Export'), [])->assertForbidden();
});

it('leaves the legacy sync export untouched', function (): void {
    $this->get(TestUsersTable::exportUrl('Legacy Export'))->assertSuccessful();
});
