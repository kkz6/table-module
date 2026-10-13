<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Table;
use Modules\Table\Tests\Support\TableFixtureRecord;
use Modules\Table\Tests\Support\User;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    TableFixtureRecord::createSchema();
});
test('an empty base collection is identified separately from available filters', function () {
    $data=Table::build(TableFixtureRecord::query(), [TextColumn::make('name')])->toArray();
    expect($data['hasRecords'])->toBeFalse()->and($data['results']['total'])->toBe(0);
});
test('archived records preserve access to collection controls', function () {
    $article=TableFixtureRecord::create(['name' => 'Archived record']);
    $article->delete();
    $data=Table::build(TableFixtureRecord::query(), [TextColumn::make('name')])->toArray();
    expect($data['hasRecords'])->toBeTrue()->and($data['results']['total'])->toBe(0);
});
test('collection presence preserves base query scopes', function () {
    TableFixtureRecord::create(['name'=>'Hidden tag', 'slug'=>'hidden-tag']);
    $data=Table::build(TableFixtureRecord::query()->where('name', 'Visible tag'), [TextColumn::make('name')])->toArray();
    expect($data['hasRecords'])->toBeFalse();
});
