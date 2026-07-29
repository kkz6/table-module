<?php

declare(strict_types=1);

use Modules\Table\Exports\ExportColumn;
use Modules\User\Models\User;
use Spatie\Permission\Models\Role;

it('applies withCount for counts columns', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::create(['name' => 'alpha', 'guard_name' => 'web']));

    $query = User::query();
    ExportColumn::make('roles_count')->counts('roles')->applyRelationshipAggregates($query);

    expect($query->whereKey($user->id)->first()->roles_count)->toBe(1);
});

it('applies withExists', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::create(['name' => 'alpha', 'guard_name' => 'web']));

    $query = User::query();
    ExportColumn::make('roles_exists')->exists('roles')->applyRelationshipAggregates($query);

    expect($query->whereKey($user->id)->first()->roles_exists)->toBeTrue();
});

it('eager loads dot-notation relationships', function () {
    User::factory()->create();

    $query = User::query();
    ExportColumn::make('roles.name')->applyEagerLoading($query);

    $user = $query->first();

    expect($user->relationLoaded('roles'))->toBeTrue();
});

it('does not eager load plain attributes', function () {
    User::factory()->create();

    $query = User::query();
    ExportColumn::make('name')->applyEagerLoading($query);

    $user = $query->first();

    expect($user->getRelations())->toBeEmpty();
});

it('applies relationship aggregate', function (string $method, string $column, \Closure $castActual, \Closure $expected): void {
    $user  = User::factory()->create();
    $role1 = Role::create(['name' => 'beta', 'guard_name' => 'web']);
    $role2 = Role::create(['name' => 'gamma', 'guard_name' => 'web']);
    $user->assignRole($role1);
    $user->assignRole($role2);

    $query = User::query();
    ExportColumn::make($column)->{$method}('roles', 'id')->applyRelationshipAggregates($query);

    $model = $query->whereKey($user->id)->first();

    expect($castActual($model->{$column}))->toBe($expected($role1->id, $role2->id));
})->with([
    'avg' => ['avg', 'roles_avg_id', fn ($v) => (float) $v, fn ($r1, $r2) => (float) (($r1 + $r2) / 2)],
    'min' => ['min', 'roles_min_id', fn ($v) => (int) $v, fn ($r1, $r2) => min($r1, $r2)],
    'max' => ['max', 'roles_max_id', fn ($v) => (int) $v, fn ($r1, $r2) => max($r1, $r2)],
    'sum' => ['sum', 'roles_sum_id', fn ($v) => (int) $v, fn ($r1, $r2) => $r1 + $r2],
]);

it('does not eager load when aggregates are configured', function () {
    User::factory()->create();

    $query = User::query();
    ExportColumn::make('roles.name')->counts('roles')->applyEagerLoading($query);

    $user = $query->first();

    // aggregates take precedence; no eager loading should occur
    expect($user->relationLoaded('roles'))->toBeFalse();
});
