import { EmptyState } from '@shared/components/ui/empty-state';
import { LucideIcon } from 'lucide-react';
import Table from '../../TableComponent';
import type { Action, FilterState, TableConfig } from '../../types';

interface InertiaTableWrapperProps<T = any> {
    resource: TableConfig<T>;
    emptyState?: {
        title: string;
        description?: string;
        icons?: LucideIcon[];
        action?: {
            label: string;
            onClick: () => void;
            disabled?: boolean;
        };
    };
    onCustomAction?: (action: Action, items: (string | number)[], onFinish?: () => void) => void;
    onActionSuccess?: (action: Action, keys: (string | number)[]) => void;
    onActionError?: (action: Action, keys: (string | number)[], error: unknown) => void;
    onRowClick?: (item: T, column: any) => void;
}

export function InertiaTableWrapper<T = any>({
    resource,
    emptyState,
    onCustomAction,
    onActionSuccess,
    onActionError,
    onRowClick,
}: InertiaTableWrapperProps<T>) {
    // Available filter definitions are not the same as existing records.
    const shouldShowEmptyState =
        resource.results?.total === 0 &&
        !resource.state.search &&
        (resource.hasRecords === false ||
            (resource.hasRecords === undefined &&
                !resource.hasFilters &&
                !Object.values(resource.state.filters).some((filter: FilterState) => filter.enabled)));

    if (shouldShowEmptyState && emptyState) {
        return (
            <div className="py-3">
                <EmptyState title={emptyState.title} description={emptyState.description} icons={emptyState.icons} action={emptyState.action} />
            </div>
        );
    }

    return (
        <Table
            resource={resource}
            onCustomAction={onCustomAction as any}
            onActionSuccess={onActionSuccess as any}
            onActionError={onActionError as any}
            onRowClick={onRowClick as any}
        />
    );
}
