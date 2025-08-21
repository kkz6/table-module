<?php

declare(strict_types=1);

namespace Modules\Table\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Table\Exception\UnsupportedClauseException;

class TrashedFilter extends Filter
{
    public function __construct(
        string $attribute,
        ?string $label = null,
        bool $nullable = false,
        ?array $clauses = null,
        ?\Closure $applyUsing = null,
        ?\Closure $validateUsing = null,
        ?array $meta = null,
        bool $applyUnwrapped = true,
        \Closure|bool|null $hidden = null
    ) {
        parent::__construct(
            attribute: $attribute,
            label: $label,
            nullable: $nullable,
            clauses: $clauses,
            applyUsing: $applyUsing,
            validateUsing: $validateUsing,
            meta: $meta,
            applyUnwrapped: $applyUnwrapped,
            hidden: $hidden,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function apply(Builder $resource, string $attribute, Clause $clause, mixed $value): void
    {
        // Check if the model uses SoftDeletes trait
        if (! $this->modelUsesSoftDeletes($resource)) {
            return; // Silently ignore if model doesn't support soft deletes
        }

        match ($clause) {
            Clause::WithTrashed    => $this->applyWithTrashed($resource),
            Clause::OnlyTrashed    => $this->applyOnlyTrashed($resource),
            Clause::WithoutTrashed => $this->applyWithoutTrashed($resource),
            default                => throw UnsupportedClauseException::for($clause),
        };
    }

    /**
     * Check if the model uses the SoftDeletes trait.
     */
    protected function modelUsesSoftDeletes(Builder $resource): bool
    {
        // Use method_exists to check if the soft delete methods are available
        return method_exists($resource, 'withTrashed') &&
            method_exists($resource, 'onlyTrashed') &&
            method_exists($resource, 'withoutTrashed');
    }

    /**
     * Apply the withTrashed scope to include soft deleted records.
     */
    protected function applyWithTrashed(Builder $resource): void
    {
        // Use dynamic method call to avoid static analysis issues
        if (method_exists($resource, 'withTrashed')) {
            call_user_func([$resource, 'withTrashed']);
        }
    }

    /**
     * Apply the onlyTrashed scope to show only soft deleted records.
     */
    protected function applyOnlyTrashed(Builder $resource): void
    {
        // Use dynamic method call to avoid static analysis issues
        if (method_exists($resource, 'onlyTrashed')) {
            call_user_func([$resource, 'onlyTrashed']);
        }
    }

    /**
     * Apply the withoutTrashed scope to exclude soft deleted records.
     */
    protected function applyWithoutTrashed(Builder $resource): void
    {
        // Use dynamic method call to avoid static analysis issues
        if (method_exists($resource, 'withoutTrashed')) {
            call_user_func([$resource, 'withoutTrashed']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value, Clause $clause, Builder $resource): mixed
    {
        return null; // No value validation needed for trashed filter
    }

    /**
     * {@inheritDoc}
     */
    public static function defaultClauses(): array
    {
        return [
            Clause::WithoutTrashed,
            Clause::WithTrashed,
            Clause::OnlyTrashed,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function default(mixed $value, ?Clause $clause = null): static
    {
        $this->defaultValue  = null;
        $this->defaultClause = $clause ?? Clause::WithoutTrashed;

        return $this;
    }

    /**
     * Set the filter to show only trashed records by default.
     */
    public function onlyTrashed(): static
    {
        return $this->default(null, Clause::OnlyTrashed);
    }

    /**
     * Set the filter to show all records (including trashed) by default.
     */
    public function withTrashed(): static
    {
        return $this->default(null, Clause::WithTrashed);
    }

    /**
     * Set the filter to show only non-trashed records by default.
     */
    public function withoutTrashed(): static
    {
        return $this->default(null, Clause::WithoutTrashed);
    }
}
