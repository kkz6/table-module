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
 *
 * Customization:
 * You can override these methods in your table class to customize behavior:
 * - handleForceDelete($model) - Customize force delete logic (e.g., handle foreign keys)
 * - handleRestore($model) - Customize restore logic
 * - getForceDeleteConfirmTitle() - Customize confirmation dialog title
 * - getForceDeleteConfirmMessage() - Customize confirmation dialog message
 * - getRestoreConfirmTitle() - Customize restore confirmation title
 * - getRestoreConfirmMessage() - Customize restore confirmation message
 * - getForceDeleteAction($canManage) - Completely customize the force delete action
 * - getRestoreAction($canManage) - Completely customize the restore action
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
            $this->getForceDeleteAction($canManage),
            $this->getRestoreAction($canManage),
        ];
    }

    /**
     * Get the force delete action.
     * Override this method to customize the force delete behavior.
     */
    protected function getForceDeleteAction(bool $canManage): Action
    {
        return Action::make(
            label: 'Force Delete',
            handle: fn(Model $model) => $this->handleForceDelete($model),
            icon: 'Trash2',
            hidden: fn(Model $model) => ! $model->trashed() || ! $canManage,
        )->asButton()->variant(\Modules\Table\Enums\Variant::Destructive)->confirm(
            title: $this->getForceDeleteConfirmTitle(),
            message: $this->getForceDeleteConfirmMessage(),
            confirmButton: 'Delete Forever',
            cancelButton: 'Cancel'
        );
    }

    /**
     * Get the restore action.
     * Override this method to customize the restore behavior.
     */
    protected function getRestoreAction(bool $canManage): Action
    {
        return Action::make(
            label: 'Restore',
            handle: fn(Model $model) => $this->handleRestore($model),
            icon: 'RotateCcw',
            hidden: fn(Model $model) => ! $model->trashed() || ! $canManage,
        )->asButton()->variant(\Modules\Table\Enums\Variant::Success)->confirm(
            title: $this->getRestoreConfirmTitle(),
            message: $this->getRestoreConfirmMessage(),
            confirmButton: 'Restore',
            cancelButton: 'Cancel'
        );
    }

    /**
     * Handle the force delete action.
     * Override this method to customize force delete behavior (e.g., handle foreign key constraints).
     */
    protected function handleForceDelete(Model $model): mixed
    {
        return $model->forceDelete();
    }

    /**
     * Handle the restore action.
     * Override this method to customize restore behavior.
     */
    protected function handleRestore(Model $model): mixed
    {
        return $model->restore();
    }

    /**
     * Get the force delete confirmation title.
     * Override this method to customize the confirmation dialog title.
     */
    protected function getForceDeleteConfirmTitle(): string
    {
        return 'Permanently Delete Record';
    }

    /**
     * Get the force delete confirmation message.
     * Override this method to customize the confirmation dialog message.
     */
    protected function getForceDeleteConfirmMessage(): string
    {
        return 'Are you sure you want to permanently delete this record? This action cannot be undone.';
    }

    /**
     * Get the restore confirmation title.
     * Override this method to customize the confirmation dialog title.
     */
    protected function getRestoreConfirmTitle(): string
    {
        return 'Restore Record';
    }

    /**
     * Get the restore confirmation message.
     * Override this method to customize the confirmation dialog message.
     */
    protected function getRestoreConfirmMessage(): string
    {
        return 'Are you sure you want to restore this record?';
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
