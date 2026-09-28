<?php

declare(strict_types=1);

namespace Modules\Table\Models\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasAuthenticatedOwnerScope
{
    public static function bootHasAuthenticatedOwnerScope(): void
    {
        static::addGlobalScope('authenticated-owner', function (Builder $query): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $query->where($query->getModel()->qualifyColumn('user_id'), $userId);
            }
        });

        static::creating(function (self $model): void {
            $model->guardAuthenticatedOwner();
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('user_id') && Auth::id() !== null) {
                throw new AuthorizationException('The record owner cannot be changed.');
            }
        });
    }

    private function guardAuthenticatedOwner(): void
    {
        $userId = Auth::id();

        if ($userId === null) {
            return;
        }

        if ($this->getAttribute('user_id') === null) {
            $this->setAttribute('user_id', $userId);

            return;
        }

        if ((int) $this->getAttribute('user_id') !== (int) $userId) {
            throw new AuthorizationException('The record must belong to the authenticated user.');
        }
    }
}
