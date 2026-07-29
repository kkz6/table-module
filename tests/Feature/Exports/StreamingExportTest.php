<?php

declare(strict_types=1);

use Modules\Table\Tests\Support\TestUsersTable;
use Modules\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['name' => 'Owner']));
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
