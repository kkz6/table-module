// Action-specific types for table operations

// Extended action interface for table operations (matches backend Action.php)
export interface TableAction {
    label: string;
    icon?: string; // Lucide icon name
    url?: string;
    authorized: boolean;
    isCustom: boolean;
    isLink: boolean;
    isAction: boolean;
    type: 'button' | 'link';
    variant: 'destructive' | 'default' | 'info' | 'success' | 'warning' | 'outline' | 'secondary' | 'ghost' | 'link';
    buttonClass?: string | null;
    linkClass?: string | null;
    style: string;
    asRowAction: boolean;
    asBulkAction: boolean;
    confirmationRequired: boolean;
    confirmationTitle?: string;
    confirmationMessage?: string;
    confirmationConfirmButton?: string;
    confirmationCancelButton?: string;
    showLabel: boolean;
    asDownload: boolean;
    dataAttributes?: Record<string, any> | null;
    meta?: Record<string, any> | null;
    id?: string | number | null;
}

// Export column / option / payload types for pipeline exports
export interface ExportColumnDef {
    name: string;
    label: string;
    enabledByDefault: boolean;
}

export interface ExportOptionField {
    type: 'text' | 'select' | 'checkbox';
    name: string;
    label: string;
    default?: unknown;
    options?: Record<string, string>;
}

export interface ExportModalPayload {
    columnMap?: Record<string, { isEnabled: boolean; label: string }>;
    formats: string[];
    options: Record<string, unknown>;
}

// Export-specific interface
export interface TableExport {
    url: string;
    label: string;
    resourceLabel?: string;
    limitToSelectedRows?: boolean;
    asDownload?: boolean;
    dataAttributes?: Record<string, any>;
    hasExporter?: boolean;
    hasColumnMapping?: boolean;
    enableVisibleTableColumnsByDefault?: boolean;
    columns?: ExportColumnDef[];
    formats?: string[];
    optionsForm?: ExportOptionField[];
    [key: string]: any;
}

// Action performance result types
export interface ActionSuccessResult {
    keys: (string | number)[];
    response: any;
}

export interface ActionErrorResult {
    keys: (string | number)[];
    error: any;
}

export interface CustomActionResult {
    keys: (string | number)[];
    onFinish: () => void;
}

// Export performance result types
export interface ExportSuccessResult {
    keys: (string | number)[];
    response: any;
}

export interface ExportErrorResult {
    keys: (string | number)[];
    error: any;
}

// Hook return type
export interface UseActionsReturn {
    allItemsAreSelected: boolean;
    hasActions: boolean;
    hasSelectedItems: boolean;
    isAllSelected: boolean;
    isPerformingAction: boolean;
    isSelected: (item: any) => boolean;
    performAction: (action: TableAction, keys?: (string | number)[] | null) => Promise<ActionSuccessResult | CustomActionResult>;
    performAsyncExport: (tableExport: TableExport, payload?: object) => Promise<ExportSuccessResult>;
    removeSelection: () => void;
    selectAll: (checked: boolean) => void;
    selectedItems: (string | number)[];
    setSelected: (items: (string | number)[]) => void;
    toggleItem: (id: string | number | '*') => void;
    toggleSelection: (item: any) => void;
}

// Actions component types
export interface ActionsProps {
    actions: TableAction[];
    keys: (string | number)[];
    performAction: (action: TableAction, keys?: (string | number)[] | null) => Promise<ActionSuccessResult | CustomActionResult>;
    performAsyncExport?: ((tableExport: TableExport, payload?: object) => Promise<ExportSuccessResult>) | null;
    item?: any;
    iconResolver: (icon: string) => React.ComponentType<any>;
    onSuccess?: ((action: TableAction, keys: (string | number)[]) => void) | null;
    onError?: ((action: TableAction, keys: (string | number)[], error: any) => void) | null;
    onHandle?: ((action: TableAction, keys: (string | number)[], onFinish: () => void) => void) | null;
    visibleColumns?: Record<string, boolean>;
    children: (context: { handle: (action: TableAction) => void; asyncExport: (tableExport: TableExport) => void }) => React.ReactNode;
}

// ActionsDropdown component types
export interface ActionsDropdownProps {
    actions: TableAction[];
    exports: TableExport[];
    selectedItems: (string | number)[];
    performAction: (action: TableAction, keys?: (string | number)[] | null) => Promise<ActionSuccessResult | CustomActionResult>;
    performAsyncExport: (tableExport: TableExport, payload?: object) => Promise<ExportSuccessResult>;
    iconResolver: (icon: string) => React.ComponentType<any>;
    onSuccess?: ((action: TableAction, keys: (string | number)[]) => void) | null;
    onError?: ((action: TableAction, keys: (string | number)[], error: any) => void) | null;
    onHandle?: ((action: TableAction, keys: (string | number)[], onFinish: () => void) => void) | null;
    visibleColumns?: Record<string, boolean>;
}

// Action types for different operations
export type ActionResult = ActionSuccessResult | ActionErrorResult | CustomActionResult;
export type ExportResult = ExportSuccessResult | ExportErrorResult;
