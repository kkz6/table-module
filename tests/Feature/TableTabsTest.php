<?php

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Modules\Blog\Models\Tag;
use Modules\Table\Columns\TextColumn;
use Modules\Table\Filters\Clause;
use Modules\Table\Filters\SetFilter;
use Modules\Table\Filters\TextFilter;
use Modules\Table\Tab;
use Modules\Table\Table;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
});

class ScopedTabFixture extends Table
{
    public function resource(): Builder|string
    {
        return Tag::query()->where('name', 'like', 'Visible %');
    }

    public function columns(): array
    {
        return [TextColumn::make('name')->searchable(), TextColumn::make('slug')];
    }

    public function filters(): array
    {
        return [SetFilter::make('name', 'Status')->options(['Visible active'=>'Active', 'Visible paused'=>'Paused'])->default('Visible active', Clause::Equals), TextFilter::make('slug', 'Group')];
    }

    public function tabs(): array
    {
        return [Tab::make('all', 'All')->filters(['name'=>null])->countUsing(fn (Builder $q) =>$q->count()), Tab::make('active', 'Active')->filters(['name'=>'Visible active'])->countUsing(fn (Builder $q) =>$q->where('name', 'Visible active')->count()), Tab::make('paused', 'Paused')->filters(['name'=>'Visible paused'])->countUsing(fn (Builder $q) =>$q->where('name', 'Visible paused')->count())];
    }
}
function tabFixtureRecords(): void
{
    Tag::create(['name'=>'Visible active', 'slug'=>'visible-active']);
    Tag::create(['name'=>'Visible paused', 'slug'=>'visible-paused']);
    Tag::create(['name'=>'Hidden paused', 'slug'=>'hidden-paused']);
}
test('tab counts preserve base scopes and ignore only the preset filter', function () {
    tabFixtureRecords();
    $data=ScopedTabFixture::make()->toArray();
    expect($data['results']['total'])->toBe(1)->and(array_column($data['tabs'], 'count'))->toBe([2, 1, 1])->and($data['tabs'][0]['filters'])->toBe(['name'=>null]);
});
test('tab counts follow search without leaking out of scope records', function () {
    tabFixtureRecords();
    $data=ScopedTabFixture::make()->setRequest(Request::create('/', parameters: ['search'=>'paused']))->toArray();
    expect($data['results']['total'])->toBe(0)->and(array_column($data['tabs'], 'count'))->toBe([1, 0, 1])->and($data['hasRecords'])->toBeTrue();
});
test('tab facets retain unrelated filters', function () {
    tabFixtureRecords();
    $data=ScopedTabFixture::make()->setRequest(Request::create('/', parameters: ['filters'=>['slug'=>['enabled'=>true, 'clause'=>'equals', 'value'=>'visible-paused']]]))->toArray();
    expect(array_column($data['tabs'], 'count'))->toBe([1, 0, 1]);
});
test('the all preset can disable a default filter and restore all permitted records', function () {
    tabFixtureRecords();
    $data=ScopedTabFixture::make()->setRequest(Request::create('/', parameters: ['filters'=>['name'=>['enabled'=>false]]]))->toArray();
    expect($data['results']['total'])->toBe(2)->and($data['state']['filters']['name']['enabled'])->toBeFalse();
});
test('an empty scoped collection has no records and zero tab counts', function () {
    Tag::create(['name'=>'Hidden active', 'slug'=>'hidden-active']);
    $data=ScopedTabFixture::make()->toArray();
    expect($data['hasRecords'])->toBeFalse()->and(array_column($data['tabs'], 'count'))->toBe([0, 0, 0]);
});
test('tables without presets serialize no tab or quick filter configuration', function () {
    $data=Table::build(Tag::query(), [TextColumn::make('name')])->toArray();
    expect($data['tabs'])->toBe([])->and($data['quickFilters'])->toBe([]);
});
