<?php

declare(strict_types=1);

namespace Modules\Table\Filters;

use Illuminate\Database\Eloquent\Builder;

class TrashedFilter extends SetFilter
{
    public static function make(
        string $attribute = 'trashed',
        ?string $label = null,
        bool $nullable = false,
        ?array $clauses = null,
        \Closure|callable|null $applyUsing = null,
        \Closure|callable|null $validateUsing = null,
        ?array $meta = null,
        bool $applyUnwrapped = false,
        mixed $hidden = false
    ): static {
        $filter = parent::make($attribute, $label ?? 'Trashed', true, $clauses, $applyUsing, $validateUsing, $meta, true);

        // @phpstan-ignore return.type
        return $filter
            ->options([
                'all' => 'All Records',
                'without_trashed' => 'Without Trashed',
                'withTrashed' => 'With Trashed',
                'only_trashed' => 'Only Trashed',
            ])
            ->withoutClause()
            ->applyUsing(function (Builder $query, string $attribute, $clause, mixed $value) {
                match ($value) {
                    'all' => $query->withTrashed(), // @phpstan-ignore method.notFound
                    'withTrashed' => $query->withTrashed(), // @phpstan-ignore method.notFound
                    'only_trashed' => $query->onlyTrashed(), // @phpstan-ignore method.notFound
                    'without_trashed' => $query->withoutTrashed(), // @phpstan-ignore method.notFound
                    default => $query->withoutTrashed(), // @phpstan-ignore method.notFound
                };
            }, true);
    }
}
