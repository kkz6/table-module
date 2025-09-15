<?php

declare(strict_types=1);

namespace Modules\Table\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Table\Action;
use Modules\Table\Contracts\SoftDeletableTable;
use Modules\Table\Filters\TrashedFilter;

/**
 * Trait HasSoftDeleteActions
 *
 * Provides soft delete functionality for tables including:
 * - Automatic trashed filter
 * - Force Delete and Restore actions
 *
 * Usage:
 * - Add this trait to your Table class
 * - Implement SoftDeletableTable interface
 * - The system will automatically add actions and filters
 */
trait HasSoftDeleteActions
{
    /**
     * Get soft delete actions for the table.
     * These will be automatically added if the table implements SoftDeletableTable.
     */
    public function getSoftDeleteActions(): array
    {
        if (! $this instanceof SoftDeletableTable) {
            return [];
        }

        $canManage = $this->canManageSoftDeletes();

        return [
            Action::make(
                label: 'Force Delete',
                handle: fn(Model $model) => $model->forceDelete(),
                icon: 'Trash2',
                hidden: fn(Model $model) => ! $model->trashed() || ! $canManage,
            )->asButton()->variant(\Modules\Table\Enums\Variant::Destructive)->confirm(
                title: 'Permanently Delete Record',
                message: 'Are you sure you want to permanently delete this record? This action cannot be undone.',
                confirmButton: 'Delete Forever',
                cancelButton: 'Cancel'
            ),

            Action::make(
                label: 'Restore',
                handle: fn(Model $model) => $model->restore(),
                icon: 'RotateCcw',
                hidden: fn(Model $model) => ! $model->trashed() || ! $canManage,
            )->asButton()->variant(\Modules\Table\Enums\Variant::Success)->confirm(
                title: 'Restore Record',
                message: 'Are you sure you want to restore this record?',
                confirmButton: 'Restore',
                cancelButton: 'Cancel'
            ),
        ];
    }

    /**
     * Get trashed filter for the table.
     * This will be automatically added if the table implements SoftDeletableTable.
     */
    public function getSoftDeleteFilter(): ?TrashedFilter
    {
        if (! $this instanceof SoftDeletableTable) {
            return null;
        }

        $canManage = $this->canManageSoftDeletes();

        return TrashedFilter::make('trashed', 'Deleted records')
            ->hidden(! $canManage);
    }

    /**
     * Check if the current model uses soft deletes.
     */
    protected function modelUsesSoftDeletes(): bool
    {
        try {
            $resource = $this->resource();

            // Handle both Builder and string returns
            if (is_string($resource)) {
                $model = new $resource;
            } else {
                $model = $resource->getModel();
            }

            return in_array(SoftDeletes::class, class_uses_recursive($model));
        } catch (\Exception $e) {
            return false;
        }
    }
}
