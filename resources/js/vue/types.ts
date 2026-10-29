/** Vue counterpart of the table module's serialized PHP contract. */
export interface TableColumn {
    attribute: string;
    header: string;
    sortable?: boolean;
    toggleable?: boolean;
    stickable?: boolean;
    visibleByDefault?: boolean;
    alignment?: 'left' | 'center' | 'right';
    headerClass?: string;
    cellClass?: string;
    wrap?: boolean;
    type?: string;
}
export interface FilterState {
    enabled: boolean;
    clause?: string;
    value?: unknown;
}
export interface FilterDefinition {
    attribute: string;
    label: string;
    type?: string;
    multiple?: boolean;
    clauses?: string[];
    options?: Array<{ value: string | number; label: string }>;
}
export interface TableState {
    search?: string | null;
    sort?: string | null;
    perPage: number;
    page?: number;
    columns: Record<string, boolean>;
    filters: Record<string, FilterState>;
    sticky: string[];
}
export interface TableResults<T> {
    data: T[];
    total: number;
    per_page?: number;
    current_page?: number;
    last_page?: number;
    from?: number | null;
    to?: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    last_page_url?: string;
    links?: Array<{ url: string | null; label: string; page: number | null; active: boolean }>;
}
export interface TableAction {
    id?: string | number;
    label: string;
    icon?: string;
    url?: string;
    authorized?: boolean;
    asBulkAction?: boolean;
    asRowAction?: boolean;
    confirmationRequired?: boolean;
    confirmationTitle?: string;
    confirmationMessage?: string;
    variant?: 'default' | 'destructive' | 'outline' | 'ghost';
    isCustom?: boolean;
    isLink?: boolean;
    isAction?: boolean;
    showLabel?: boolean;
}
export interface TableResource<T extends object = Record<string, unknown>> {
    name: string;
    columns: TableColumn[];
    filters: FilterDefinition[];
    results: TableResults<T>;
    state: TableState;
    pagination: boolean;
    paginationType?: string | null;
    perPageOptions: number[];
    defaultPerPage?: number;
    defaultSort?: string | null;
    debounceTime?: number;
    hasRecords?: boolean;
    hasSearch: boolean;
    hasFilters?: boolean;
    hasToggleableColumns: boolean;
    hasBulkActions?: boolean;
    hasExports?: boolean;
    hasExportsThatLimitsToSelectedRows?: boolean;
    actions?: TableAction[];
    exports?: Array<{ url: string; label: string }>;
    tabs?: Array<{ value: string; label: string; count?: number | null; filters: Record<string, unknown> }>;
    quickFilters?: string[];
    quickFilterLabels?: Record<string, string>;
    stickyHeader?: boolean;
    autofocus?: string;
    emptyState?: unknown;
}
export function staticTable<T extends object>(name: string, columns: TableColumn[], data: T[]): TableResource<T> {
    return {
        name,
        columns,
        filters: [],
        results: { data, total: data.length },
        state: {
            search: '',
            sort: null,
            perPage: data.length || 15,
            columns: Object.fromEntries(columns.map((column) => [column.attribute, true])),
            filters: {},
            sticky: [],
        },
        pagination: false,
        perPageOptions: [],
        hasSearch: false,
        hasToggleableColumns: false,
    };
}
