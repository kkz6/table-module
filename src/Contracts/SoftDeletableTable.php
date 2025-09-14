<?php

declare(strict_types=1);

namespace Modules\Table\Contracts;

/**
 * Interface SoftDeletableTable
 * 
 * Implement this interface on tables that work with soft-deletable models.
 * The table system will automatically add soft delete actions and filters
 * based on the result of canManageSoftDeletes().
 */
interface SoftDeletableTable
{
    /**
     * Determine if the current user can manage soft deletes (view trashed, restore, force delete).
     * This method controls the visibility of:
     * - The trashed filter
     * - Delete, Force Delete, and Restore actions
     * 
     * @return bool
     */
    public function canManageSoftDeletes(): bool;
}
