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
    asDropdown?: boolean;
    trueIcon?: string;
    falseIcon?: string;
    exportable?: boolean;
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
    view?: string | null;
    cursor?: string | null;
    columns: Record<string, boolean>;
    filters: Record<string, FilterState>;
    sticky: string[];
}
export interface TableResults<T> {
    data: T[];
    total?: number;
    on_first_page?: boolean;
    on_last_page?: boolean;
    first_page_url?: string;
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
export type Key = string | number;
export interface TableUrl {
    url: string;
    hidden?: boolean;
    disabled?: boolean;
    openInNewTab?: boolean;
    asDownload?: boolean;
    preserveState?: boolean;
    preserveScroll?: boolean;
    modal?: boolean | object;
}
export interface TableRowData {
    _primary_key?: Key;
    id?: Key;
    _is_selectable?: boolean;
    _actions?: Record<string, string | TableUrl | null>;
    [key: string]: unknown;
}
export interface TableAction {
    id?: Key | null;
    label: string;
    icon?: string;
    url?: string | null;
    authorized: boolean;
    asBulkAction?: boolean;
    asRowAction?: boolean;
    isCustom?: boolean;
    isLink?: boolean;
    isAction?: boolean;
    asDownload?: boolean;
    confirmationRequired?: boolean;
    confirmationTitle?: string;
    confirmationMessage?: string;
    confirmationConfirmButton?: string;
    confirmationCancelButton?: string;
    showLabel?: boolean;
    variant?: 'default' | 'destructive' | 'outline' | 'ghost' | 'info' | 'warning' | 'success' | 'secondary' | 'link';
    type?: string;
    buttonClass?: string | null;
    linkClass?: string | null;
    meta?: Record<string, unknown> | null;
    dataAttributes?: Record<string, string> | null;
}
export interface TableExport {
    url: string;
    label: string;
    authorized?: boolean;
    limitToSelectedRows?: boolean;
    asDownload?: boolean;
    hasExporter?: boolean;
    resourceLabel?: string;
    hasColumnMapping?: boolean;
    enableVisibleTableColumnsByDefault?: boolean;
    columns?: { name: string; label: string; enabledByDefault: boolean }[];
    formats?: string[];
    optionsForm?: { name: string; label: string; type: 'text' | 'select' | 'checkbox'; default?: unknown; options?: Record<string, string> }[];
}
export interface ExportPayload {
    columnMap?: Record<string, { isEnabled: boolean; label: string }>;
    formats: string[];
    options: Record<string, unknown>;
}
export interface TableTab {
    icon?: string | null;
    tone?: string;
    value: string;
    label: string;
    count?: number | null;
    filters: Record<string, unknown>;
}
export interface SavedViews {
    data: { id: Key; title: string; state: TableState; deleteUrl: string }[];
    query: Record<string, unknown>;
    storeUrl: string;
}
export interface EmptyState {
    title?: string | null;
    message?: string | null;
    icon?: string | boolean | null;
    actions?: ({ label: string; icon?: string; variant?: TableAction['variant'] } & TableUrl)[];
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
    exports?: TableExport[];
    tabs?: TableTab[];
    quickFilters?: string[];
    quickFilterLabels?: Record<string, string>;
    stickyHeader?: boolean;
    autofocus?: string;
    emptyState?: EmptyState | boolean | null;
    views?: SavedViews | null;
    reloadProps?: string[];
    scrollPositionAfterPageChange?: string;
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
