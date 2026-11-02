<?php

declare(strict_types=1);

use Modules\Table\Tests\Support\TestUsersTable;
use Modules\Table\Tests\Support\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Owner']));
});

it('streams a selected table export as csv', function (): void {
    User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
    User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.test']);

    $response = $this->withHeader('Accept', 'application/octet-stream')
        ->post(TestUsersTable::exportUrl('Pipeline Export'), [
            'columnMap' => [
                'name'  => ['isEnabled' => true, 'label' => 'Full name'],
                'email' => ['isEnabled' => true, 'label' => 'Email'],
            ],
            'formats' => ['csv'],
        ]);

    $response->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition');

    expect($response->streamedContent())
        ->toStartWith("\xEF\xBB\xBF")
        ->toContain('"Full name",Email')
        ->toContain('Alice,alice@example.test')
        ->toContain('Bob,bob@example.test');
});

it('streams multiple selected formats in one archive', function (): void {
    User::factory()->create(['name' => 'Alice']);

    $response = $this->withHeader('Accept', 'application/octet-stream')
        ->post(TestUsersTable::exportUrl('Pipeline Export'), [
            'columnMap' => ['name' => ['isEnabled' => true, 'label' => 'Full name']],
            'formats'   => ['csv', 'xlsx'],
        ]);

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition');

    expect($response->streamedContent())->not->toBeEmpty();
});

it('writes identical csv content for direct downloads and archives', function (): void {
    User::factory()->create(['name' => "Zoë, \"Finance\"\nTeam", 'email' => 'zoe@example.test']);
    $payload = [
        'columnMap' => [
            'name'  => ['isEnabled' => true, 'label' => 'Full name'],
            'email' => ['isEnabled' => true, 'label' => 'Email'],
        ],
    ];
    $download = $this->withHeader('Accept', 'application/octet-stream')
        ->post(TestUsersTable::exportUrl('Pipeline Export'), [...$payload, 'formats' => ['csv']]);
    $download->assertSuccessful();
    $csv     = $download->streamedContent();
    $archive = $this->withHeader('Accept', 'application/octet-stream')
        ->post(TestUsersTable::exportUrl('Pipeline Export'), [...$payload, 'formats' => ['csv', 'xlsx']]);
    $archive->assertSuccessful();

    $path = tempnam(sys_get_temp_dir(), 'export-parity-');
    $zip  = new ZipArchive;
    try {
        file_put_contents($path, $archive->streamedContent());
        expect($zip->open($path))->toBeTrue();
        $csvEntry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (str_ends_with($zip->getNameIndex($index), '.csv')) {
                $csvEntry = $zip->getFromIndex($index);
            }
        }
        expect($csvEntry)->toBe($csv)
            ->and($csv)->toStartWith("\xEF\xBB\xBF")
            ->toContain("\"Zoë, \"\"Finance\"\"\nTeam\"");
    } finally {
        if ($zip->status === ZipArchive::ER_OK && $zip->numFiles > 0) {
            $zip->close();
        }
        unlink($path);
    }
});
