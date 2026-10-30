import qs from 'qs';
import { filterQuery } from '../filterQuery';
import type { Key, TableAction, TableResource, TableRowData, TableState, TableTab, TableUrl } from './types';
export function activeTab(tabs: TableTab[], state: TableState): string {
    return (
        [...tabs]
            .sort((a, b) => Object.keys(b.filters).length - Object.keys(a.filters).length)
            .find((tab) =>
                Object.entries(tab.filters).every(([key, preset]) => {
                    if (preset === null) return !state.filters[key]?.enabled;
                    const expected =
                        typeof preset === 'object' && preset && 'value' in preset
                            ? (preset as { value: unknown; clause?: string })
                            : { value: preset, clause: 'equals' };
                    const current = state.filters[key];
                    return (
                        current?.enabled &&
                        current.clause === (expected.clause ?? 'equals') &&
                        (Array.isArray(current.value) || Array.isArray(expected.value)
                            ? JSON.stringify(current.value) === JSON.stringify(expected.value)
                            : String(current.value) === String(expected.value))
                    );
                }),
            )?.value ?? ''
    );
}
export function queryFor<T extends object>(resource: TableResource<T>, state: TableState, search = ''): string {
    const tab = resource.tabs?.find((tab) => tab.value === activeTab(resource.tabs ?? [], state));
    const filters = Object.fromEntries(
        Object.entries(state.filters)
            .filter(([key, item]) => item.enabled && !Object.keys(tab?.filters ?? {}).includes(key))
            .map(([key, item]) => [key, filterQuery(item)])
            .filter(([, value]) => value !== null),
    );
    const columns = resource.columns.filter((column) => state.columns[column.attribute] !== false).map((column) => column.attribute);
    const defaults = resource.columns.filter((column) => column.visibleByDefault !== false).map((column) => column.attribute);
    const data = {
        search: state.search || null,
        sort: state.sort || null,
        perPage: state.perPage,
        page: state.page ?? 1,
        cursor: state.cursor ?? null,
        filters,
        columns: columns.join(',') === defaults.join(',') ? [] : columns,
        sticky: state.sticky,
        view: tab?.value || null,
    };
    const existing = qs.parse(search, { ignoreQueryPrefix: true });
    return qs.stringify(resource.name === 'default' ? { ...existing, ...data } : { ...existing, [resource.name]: data }, { skipNulls: true });
}
export function rowKey(row: TableRowData, index = 0): Key {
    return row._primary_key ?? row.id ?? index;
}
export function actionState(row: TableRowData | undefined, index: number): TableUrl | undefined {
    const state = row?._actions?.[String(index)];
    return typeof state === 'string' ? { url: state } : (state ?? undefined);
}
export function selectionActions(actions: TableAction[], rows: TableRowData[], selected: Key[], total: number) {
    const all = selected.includes('*'),
        single = !all && selected.length === 1;
    const selectedRows = rows.filter((row, index) => row._is_selectable !== false && (all || selected.includes(rowKey(row, index))));
    return actions.flatMap((action, index) => {
        if (!action.authorized || (!action.asBulkAction && !(single && action.asRowAction))) return [];
        const eligible = selectedRows.filter((row) => !actionState(row, index)?.hidden && !actionState(row, index)?.disabled);
        const unknown = all ? total > rows.length : selected.some((key) => !rows.some((row, index) => rowKey(row, index) === key));
        if (!eligible.length && !unknown) return [];
        if (single && !action.asBulkAction && !eligible.length) return [];
        const keys = all
            ? ['*']
            : selected.filter(
                  (key) => eligible.some((row, index) => rowKey(row, index) === key) || !rows.some((row, index) => rowKey(row, index) === key),
              );
        return [{ action, index, keys, item: single ? eligible[0] : undefined, partial: !all && keys.length < selected.length }];
    });
}
