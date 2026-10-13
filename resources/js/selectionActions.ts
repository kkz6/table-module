import type { TableAction } from './types/actions';

export interface SelectionRow {
    _primary_key: string | number;
    _is_selectable?: boolean;
    _actions?: Record<string, string | { hidden?: boolean; disabled?: boolean; url?: string } | null>;
}

/** Keep original action indexes: row eligibility is keyed by the server's action order. */
export function getSelectionActions(actions: TableAction[], rows: SelectionRow[], selected: (string | number)[], total: number) {
    if (!selected.length) return [];

    const all = selected.includes('*');
    const single = !all && selected.length === 1;
    const selectedRows = all ? rows.filter((row) => row._is_selectable !== false) : rows.filter((row) => selected.includes(row._primary_key));

    return actions.flatMap((action, index) => {
        if (!action.authorized || (!action.asBulkAction && !(single && action.asRowAction))) return [];
        const eligible = selectedRows.filter((row) => {
            const state = row._actions?.[String(index)];
            return row._is_selectable !== false && !(typeof state === 'object' && state && (state.hidden || state.disabled));
        });
        // Off-page eligibility is enforced by the server, never inferred from a single page.
        const unknown = all ? total > rows.length : selected.some((key) => !rows.some((row) => row._primary_key === key));
        if (!eligible.length && !unknown) return [];
        if (single && !action.asBulkAction && !eligible.length) return [];

        const keys = all
            ? ['*']
            : selected.filter((key) => eligible.some((row) => row._primary_key === key) || !rows.some((row) => row._primary_key === key));
        return [{ action, index, keys, item: single ? eligible[0] : undefined, partial: !all && keys.length < selected.length }];
    });
}
