<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Modules\Blog\Models\Article;
use Modules\Blog\Models\Tag;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Table;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
});
test('an empty base collection is identified separately from available filters', function () {
    $data=Table::build(Tag::query(), [TextColumn::make('name')])->toArray();
    expect($data['hasRecords'])->toBeFalse()->and($data['results']['total'])->toBe(0);
});
test('archived records preserve access to collection controls', function () {
    $article=Article::factory()->create();
    $article->delete();
    $data=Table::build(Article::query(), [TextColumn::make('title')])->toArray();
    expect($data['hasRecords'])->toBeTrue()->and($data['results']['total'])->toBe(0);
});
test('collection presence preserves base query scopes', function () {
    Tag::create(['name'=>'Hidden tag', 'slug'=>'hidden-tag']);
    $data=Table::build(Tag::query()->where('name', 'Visible tag'), [TextColumn::make('name')])->toArray();
    expect($data['hasRecords'])->toBeFalse();
});
