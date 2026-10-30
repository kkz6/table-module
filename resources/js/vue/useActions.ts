import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch, type Ref } from 'vue';
import { downloadExportResponse } from '../download';
import { actionState, rowKey, selectionActions } from './state';
import type { ExportPayload, Key, TableAction, TableExport, TableResource, TableRowData, TableUrl } from './types';
export interface ActionContext {
    action: TableAction;
    keys: Key[];
    item?: TableRowData;
    index: number;
}
export function useActions<T extends object>(
    resource: Ref<TableResource<T>>,
    callbacks: {
        success: (context: ActionContext) => void;
        error: (context: ActionContext, error: unknown) => void;
        custom: (context: ActionContext, finish: () => void) => boolean;
        modal: (url: TableUrl) => boolean;
    },
) {
    const selected = ref<Key[]>([]),
        busy = ref(false),
        error = ref(''),
        message = ref('');
    const confirm = ref<ActionContext>(),
        exportContext = ref<TableExport>();
    const rows = computed(() => {
        const column = resource.value.columns.find((column) => ['action', 'actions'].includes(column.type ?? ''));
        return resource.value.results.data.map((item) => {
            const row = item as TableRowData;
            return column && column.attribute !== '_actions'
                ? { ...row, _actions: (row[column.attribute] as TableRowData['_actions']) ?? row._actions }
                : row;
        });
    });
    watch(
        () => JSON.stringify(resource.value.state),
        () => {
            selected.value = [];
        },
    );
    watch(
        () => rows.value.map((row) => rowKey(row)).join(','),
        () => {
            selected.value = [];
        },
    );
    const all = computed(() => selected.value.includes('*'));
    const eligible = computed(() => rows.value.filter((row) => row._is_selectable !== false));
    const count = computed(() => (all.value ? (resource.value.results.total ?? eligible.value.length) : selected.value.length));
    const available = computed(() =>
        selectionActions(resource.value.actions ?? [], rows.value, selected.value, resource.value.results.total ?? rows.value.length),
    );
    function isSelected(row: TableRowData): boolean {
        return row._is_selectable !== false && (all.value || selected.value.includes(rowKey(row)));
    }
    function toggle(row: TableRowData): void {
        if (busy.value || row._is_selectable === false) return;
        const key = rowKey(row);
        if (all.value) selected.value = eligible.value.map((row) => rowKey(row)).filter((id) => id !== key);
        else selected.value = selected.value.includes(key) ? selected.value.filter((id) => id !== key) : [...selected.value, key];
    }
    function togglePage(value: boolean | 'indeterminate'): void {
        selected.value = value ? eligible.value.map((row) => rowKey(row)) : [];
    }
    function safeUrl(value: string): string {
        const url = new URL(value, window.location.href);
        if (!['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)) throw new Error('This link cannot be opened.');
        return url.href;
    }
    function visit(url: TableUrl | string): void {
        const target = typeof url === 'string' ? { url } : url;
        if (!target.url || target.hidden || target.disabled) return;
        const href = safeUrl(target.url);
        if (target.asDownload || target.openInNewTab) {
            const a = document.createElement('a');
            a.href = href;
            if (target.asDownload) a.download = '';
            if (target.openInNewTab) a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.click();
            return;
        }
        if (target.modal && callbacks.modal(target)) return;
        router.visit(href, { preserveState: !!target.preserveState, preserveScroll: !!target.preserveScroll });
    }
    async function errorMessage(cause: unknown): Promise<string> {
        if (axios.isAxiosError(cause)) {
            const payload = cause.response?.data;
            const data =
                payload instanceof Blob
                    ? await payload.text().then((text) => {
                          try {
                              return JSON.parse(text);
                          } catch {
                              return null;
                          }
                      })
                    : payload;
            const first = Object.values(data?.errors ?? {})[0];
            return Array.isArray(first) ? String(first[0]) : (data?.message ?? 'The request could not be completed. Please try again.');
        }
        return cause instanceof Error ? cause.message : 'The request could not be completed.';
    }
    async function perform(context: ActionContext): Promise<void> {
        if (busy.value) return;
        const { action, item, index, keys } = context;
        if (!action.authorized || actionState(item, index)?.hidden || actionState(item, index)?.disabled) return;
        error.value = '';
        message.value = '';
        confirm.value = undefined;
        try {
            if (action.isLink || action.asDownload) {
                const target = actionState(item, index);
                if (target?.url) visit({ ...target, asDownload: action.asDownload || target.asDownload });
                return;
            }
            busy.value = true;
            if (action.isCustom) {
                const finish = () => {
                    busy.value = false;
                    selected.value = [];
                };
                if (!callbacks.custom(context, finish)) {
                    finish();
                    throw new Error('This action has not been connected yet.');
                }
                return;
            }
            if (!action.url) throw new Error('This action is unavailable.');
            const response = await axios.post(action.url, { keys, json: true });
            selected.value = [];
            callbacks.success(context);
            if (response.data?.targetUrl) router.visit(safeUrl(response.data.targetUrl), { preserveState: true, preserveScroll: true });
            else router.reload({ only: resource.value.reloadProps?.length ? resource.value.reloadProps : undefined });
        } catch (cause) {
            busy.value = false;
            error.value = await errorMessage(cause);
            callbacks.error(context, cause);
        } finally {
            if (!action.isCustom) busy.value = false;
        }
    }
    function requestAction(action: TableAction, index: number, item?: TableRowData, keys?: Key[]): void {
        const context = { action, index, item, keys: keys ?? (item ? [rowKey(item)] : selected.value) };
        if (action.confirmationRequired) confirm.value = context;
        else void perform(context);
    }
    function exportUrl(item: TableExport): string {
        const url = new URL(item.url, window.location.href);
        if (item.limitToSelectedRows && selected.value.length) url.searchParams.set('keys', selected.value.join(','));
        return url.href;
    }
    async function exportData(item: TableExport, payload?: ExportPayload): Promise<boolean> {
        if (busy.value || item.authorized === false) return false;
        error.value = '';
        message.value = '';
        busy.value = true;
        try {
            const url = exportUrl(item);
            const response = await axios.request({
                method: item.asDownload ? 'get' : 'post',
                url,
                data: payload ?? {},
                responseType: item.asDownload || item.hasExporter ? 'blob' : 'json',
                headers: item.hasExporter ? { Accept: 'application/octet-stream' } : undefined,
            });
            if (response.data instanceof Blob) downloadExportResponse(response, 'export');
            else if (response.data?.targetUrl) visit(response.data.targetUrl);
            else message.value = response.data?.dialogMessage ?? response.data?.dialogTitle ?? 'Your export is being prepared.';
            selected.value = [];
            exportContext.value = undefined;
            return true;
        } catch (cause) {
            error.value = await errorMessage(cause);
            return false;
        } finally {
            busy.value = false;
        }
    }
    function requestExport(item: TableExport): void {
        if (item.hasExporter) exportContext.value = item;
        else void exportData(item);
    }
    return {
        selected,
        busy,
        error,
        message,
        confirm,
        exportContext,
        all,
        count,
        available,
        eligible,
        isSelected,
        toggle,
        togglePage,
        perform,
        requestAction,
        visit,
        requestExport,
        exportData,
    };
}
