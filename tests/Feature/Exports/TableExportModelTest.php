<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Modules\Table\Models\TableExport;
use Modules\User\Models\User;

it('computes the failed rows count', function () {
    $export = TableExport::factory()->create(['total_rows' => 10, 'successful_rows' => 7]);

    expect($export->getFailedRowsCount())->toBe(3);
});

it('belongs to a user', function () {
    $user   = User::factory()->create();
    $export = TableExport::factory()->for($user)->create();

    expect($export->user)->toBeInstanceOf(User::class)
        ->and($export->user->id)->toBe($user->id);
});

it('builds the file directory from its key', function () {
    $export = TableExport::factory()->create();

    expect($export->getFileDirectory())->toBe('table-exports/'.$export->getKey());
});

it('deletes its file directory', function () {
    Storage::fake('local');
    $export = TableExport::factory()->create(['file_disk' => 'local']);
    Storage::disk('local')->put($export->getFileDirectory().'/headers.csv', 'Name');

    $export->deleteFileDirectory();

    expect(Storage::disk('local')->exists($export->getFileDirectory().'/headers.csv'))->toBeFalse();
});

it('casts completed_at and row counters', function () {
    $export = TableExport::factory()->completed()->create();

    expect($export->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($export->total_rows)->toBeInt();
});

it('shows authenticated users only their own export records', function (): void {
    $owner       = User::factory()->create();
    $other       = User::factory()->create();
    $ownExport   = TableExport::factory()->for($owner)->create();
    $otherExport = TableExport::factory()->for($other)->create();

    $this->actingAs($owner);

    expect(TableExport::query()->pluck('id')->all())->toBe([$ownExport->id])
        ->and(TableExport::query()->find($otherExport->id))->toBeNull();
});

it('rejects cross-owner export creation and owner changes', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($owner);

    expect(fn () => TableExport::factory()->for($other)->create())
        ->toThrow(AuthorizationException::class);

    $export = TableExport::factory()->for($owner)->create();

    expect(fn () => $export->forceFill(['user_id' => $other->id])->save())
        ->toThrow(AuthorizationException::class);
});
