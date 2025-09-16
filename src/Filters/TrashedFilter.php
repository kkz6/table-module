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
        ?array $meta = null, bool $applyUnwrapped = false,
        mixed $hidden = false
    ): static {
        return parent::make($attribute, $label ?? 'Trashed', true, $clauses, $applyUsing, $validateUsing, $meta, $applyUnwrapped)
            ->options([
                'all'             => 'All Records',
                'without_trashed' => 'Without Trashed',
                'withTrashed'     => 'With Trashed',
                'only_trashed'    => 'Only Trashed',
            ])
            ->withoutClause()
            ->applyUsing(function (Builder $query, string $attribute, $clause, mixed $value) {
                match ($value) {
                    'withTrashed'     => $query->withTrashed(),
                    'only_trashed'    => $query->onlyTrashed(),
                    'without_trashed' => $query->withoutTrashed(),
                    default           => null,
                };
            });
    }
}
