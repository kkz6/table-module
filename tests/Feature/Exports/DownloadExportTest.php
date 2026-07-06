<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Exports\Jobs\CreateXlsxFileJob;
use Modules\Table\Models\TableExport;
use Modules\Table\Tests\Support\TestUserExporter;
use Modules\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Create a completed export with part files on a faked local disk.
 */
function makeCompletedExport(): TableExport
{
    Storage::fake('local');

    $export = TableExport::factory()->for(auth()->user())->create([
        'exporter'        => TestUserExporter::class,
        'file_disk'       => 'local',
        'file_name'       => 'people',
        'total_rows'      => 2,
        'successful_rows' => 2,
        'completed_at'    => now(),
    ]);

    Storage::disk('local')->put($export->getPartsDirectory().'/headers.csv', "Full name\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000001.csv', "Aiko\n");
    Storage::disk('local')->put($export->getPartsDirectory().'/0000000000000002.csv', "Ben\n");

    Storage::disk('local')->put(
        $export->getFileDirectory().'/'.$export->file_name.'.csv',
        "\xEF\xBB\xBF"."Full name\nAiko\nBen\n",
    );

    return $export;
}

it('streams the concatenated csv to the owner', function (): void {
    $export   = makeCompletedExport();
    $response = $this->get(ExportFormat::Csv->getDownloadUrl($export));

    $response->assertSuccessful();
    expect($response->streamedContent())->toBe("\xEF\xBB\xBF"."Full name\nAiko\nBen\n")
        ->and($response->headers->get('content-disposition'))->toContain('people.csv');
});

it('serves the stored xlsx when present', function (): void {
    $export = makeCompletedExport();
    (new CreateXlsxFileJob($export, ['name' => 'Full name'], []))->handle();

    $this->get(ExportFormat::Xlsx->getDownloadUrl($export))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('builds the xlsx on the fly when the stored file is missing', function (): void {
    $export = makeCompletedExport();

    $this->get(ExportFormat::Xlsx->getDownloadUrl($export))->assertSuccessful();
});

it('forbids other users', function (): void {
    $export = makeCompletedExport();
    $url    = ExportFormat::Csv->getDownloadUrl($export);

    $this->actingAs(User::factory()->create());
    $this->get($url)->assertForbidden();
});

it('rejects a tampered signature', function (): void {
    $export = makeCompletedExport();
    $this->get(route('inertia-tables.exports.download', ['tableExport' => $export, 'format' => 'csv']))
        ->assertForbidden();
});

it('404s on unknown formats', function (): void {
    $export = makeCompletedExport();
    $url    = URL::signedRoute('inertia-tables.exports.download', ['tableExport' => $export, 'format' => 'pdf']);
    $this->get($url)->assertNotFound();
});

it('requires authentication', function (): void {
    $export = makeCompletedExport();
    $url    = ExportFormat::Csv->getDownloadUrl($export);
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

it('returns 404 for an incomplete export', function (): void {
    $export = makeCompletedExport();
    $export->update(['completed_at' => null]);

    $this->get(ExportFormat::Csv->getDownloadUrl($export))->assertNotFound();
});
