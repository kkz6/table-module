<script setup lang="ts" generic="T extends object">
import FormDialog from '@workspace/FormDialog.vue';
import { Button, Checkbox, Table as ShadcnTable, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@workspace/ui';
import { Download, Loader2, SearchX, X } from 'lucide-vue-next';
import { computed, getCurrentInstance, ref, toRef, type Component } from 'vue';
import CellValue from './CellValue.vue';
import ExportDialog from './ExportDialog.vue';
import Icon from './Icon.vue';
import Pagination from './Pagination.vue';
import RowActions from './RowActions.vue';
import { rowKey } from './state';
import TableHeaderDropdown from './TableHeaderDropdown.vue';
import TableToolbar from './TableToolbar.vue';
import type { Key, TableAction, TableColumn, TableResource, TableRowData, TableUrl } from './types';
import { useActions, type ActionContext } from './useActions';
import { useSticky } from './useSticky';
import { useTable } from './useTable';
const props = withDefaults(
    defineProps<{
        resource: TableResource<T>;
        emptyTitle?: string;
        emptyDescription?: string;
        iconResolver?: (name: string) => Component | undefined;
        showToolbar?: boolean;
    }>(),
    { emptyTitle: 'No results', emptyDescription: 'Try adjusting your search or filters.', iconResolver: undefined, showToolbar: true },
);
const emit = defineEmits<{
    rowClick: [item: T, column?: TableColumn];
    actionSuccess: [action: TableAction, keys: Key[]];
    actionError: [action: TableAction, keys: Key[], error: unknown];
    customAction: [action: TableAction, keys: Key[], finish: () => void, item?: TableRowData];
    visitModal: [url: TableUrl];
}>();
const instance = getCurrentInstance();
const table = useTable(toRef(props, 'resource'));
const actions = useActions(toRef(props, 'resource'), {
    success: (context) => emit('actionSuccess', context.action, context.keys),
    error: (context, error) => emit('actionError', context.action, context.keys, error),
    custom: (context, finish) => {
        if (!instance?.vnode.props?.onCustomAction) return false;
        emit('customAction', context.action, context.keys, finish, context.item);
        return true;
    },
    modal: (url) => {
        if (!instance?.vnode.props?.onVisitModal) return false;
        emit('visitModal', url);
        return true;
    },
});
const root = ref<HTMLElement>(),
    selected = actions.selected,
    actionBusy = actions.busy,
    actionError = actions.error,
    actionMessage = actions.message;
const busy = computed(() => table.isNavigating.value || actionBusy.value);
const selectable = computed(() => !!(props.resource.hasBulkActions || (props.showToolbar && props.resource.hasExportsThatLimitsToSelectedRows)));
const visible = table.visibleColumns;
const pinned = computed(() => table.state.value.sticky);
const sticky = useSticky(root, visible, pinned, selectable);
const exports = computed(() => (props.resource.exports ?? []).filter((item) => item.authorized !== false));
const filtered = computed(() => !!table.state.value.search || Object.values(table.state.value.filters).some((filter) => filter.enabled));
const allPage = computed(() => actions.eligible.value.length > 0 && actions.eligible.value.every((row) => actions.isSelected(row)));
const empty = computed(() => (props.resource.emptyState && typeof props.resource.emptyState === 'object' ? props.resource.emptyState : undefined));
const cellClass = (column: TableColumn) => [
    column.cellClass,
    {
        'text-right': column.alignment === 'right',
        'text-center': column.alignment === 'center',
        'whitespace-normal': column.wrap,
        'bg-background': pinned.value.includes(column.attribute),
    },
];
function asRow(item: T): TableRowData {
    return item as TableRowData;
}
function value(item: T, attribute: string): unknown {
    if (Object.prototype.hasOwnProperty.call(item, attribute)) return (item as Record<string, unknown>)[attribute];
    return attribute
        .split('.')
        .reduce<unknown>((value, key) => (value && typeof value === 'object' ? (value as Record<string, unknown>)[key] : undefined), item);
}
function cellUrl(item: T, attribute: string): TableUrl | undefined {
    const data = (value(item, '_column_urls') as Record<string, string | TableUrl> | undefined)?.[attribute];
    return typeof data === 'string' ? { url: data } : data;
}
function image(item: T, attribute: string) {
    return (value(item, '_column_images') as Record<string, InstanceType<typeof CellValue>['$props']['image']> | undefined)?.[attribute];
}
function sort(column: TableColumn): string | null {
    return table.state.value.sort === column.attribute ? 'asc' : table.state.value.sort === '-' + column.attribute ? 'desc' : null;
}
function toggleColumn(attribute: string): void {
    const column = props.resource.columns.find((column) => column.attribute === attribute);
    if (column) table.toggleColumn(column);
}
function rowClick(event: Event, item: T): void {
    if ((event.target as HTMLElement).closest('button,a,input,[role="checkbox"],[role="menuitem"]')) return;
    emit('rowClick', item);
    const target = value(item, '_row_url') as string | TableUrl | undefined;
    if (target && !instance?.vnode.props?.onRowClick) actions.visit(target);
}
function perform(action: TableAction, index: number, item: TableRowData): void {
    actions.requestAction(action, index, item);
}
defineExpose({ table, actions });
</script>
<template>
    <div
        ref="root"
        class="it-wrapper relative min-w-0"
        :aria-busy="busy"
    >
        <TableToolbar
            v-if="showToolbar"
            :resource="resource"
            :state="table.state.value"
            :busy="busy"
            @search="table.setSearch"
            @toggle-column="toggleColumn"
            @filter="table.setFilter"
            @remove-filter="table.removeFilter"
            @clear="table.clearFilters"
            @tab="table.setTab"
            @view="table.applyState"
            @export="actions.requestExport"
        >
            <slot
                name="toolbar"
                :table="table"
                :actions="actions"
            />
        </TableToolbar>
        <div
            v-if="actionError"
            role="alert"
            class="text-destructive border-destructive/25 bg-destructive/5 my-3 flex items-center justify-between rounded-md border p-3 text-sm"
        >
            {{ actionError }}
            <Button
                variant="ghost"
                size="icon"
                aria-label="Dismiss error"
                @click="actionError = ''"
            >
                <X class="size-4" />
            </Button>
        </div>
        <p
            v-if="actionMessage"
            role="status"
            class="bg-muted my-3 rounded-md p-3 text-sm"
        >
            {{ actionMessage }}
        </p>
        <div
            class="it-table-frame relative rounded-2xl px-2 pb-0"
            :class="{ 'mt-4': showToolbar, 'opacity-60': busy, 'pt-2': !resource.results.data.length, 'pb-2': !resource.pagination }"
        >
            <div
                v-if="busy"
                class="bg-background/70 pointer-events-none absolute top-0 right-0 z-40 flex items-center gap-2 rounded-bl-md px-3 py-2 text-xs"
                role="status"
            >
                <Loader2 class="size-4 animate-spin" />
                {{ actionBusy ? 'Working…' : 'Loading…' }}
            </div>
            <ShadcnTable
                class="it-table text-foreground w-full caption-bottom text-left"
                :style="resource.stickyHeader ? { maxHeight: '65vh', overflow: 'auto' } : undefined"
            >
                <TableHeader
                    v-if="resource.results.data.length"
                    class="it-table-head"
                    :class="{ 'sticky top-0 z-20': resource.stickyHeader }"
                >
                    <TableRow>
                        <TableHead
                            v-if="selectable"
                            data-selection-head
                            class="w-12 px-4"
                            :style="pinned.length ? { position: 'sticky', left: 0, zIndex: 15 } : undefined"
                        >
                            <Checkbox
                                :model-value="allPage ? true : selected.length ? 'indeterminate' : false"
                                :disabled="busy || !actions.eligible.value.length"
                                aria-label="Select all rows on this page"
                                @update:model-value="actions.togglePage"
                            />
                        </TableHead>
                        <TableHead
                            v-for="column in visible"
                            :key="column.attribute"
                            :data-column="column.attribute"
                            :style="sticky(column.attribute)"
                            :aria-sort="
                                column.sortable ? (sort(column) === 'asc' ? 'ascending' : sort(column) === 'desc' ? 'descending' : 'none') : undefined
                            "
                            class="h-10 px-4 align-middle text-sm font-medium"
                            :class="{ 'text-right': column.alignment === 'right', 'text-center': column.alignment === 'center' }"
                        >
                            <TableHeaderDropdown
                                v-if="column.sortable || (showToolbar && (column.toggleable || column.stickable))"
                                :column="showToolbar ? column : { ...column, toggleable: false, stickable: false }"
                                :sort="sort(column)"
                                :sticky="pinned.includes(column.attribute)"
                                @sort="table.setSort"
                                @toggle="table.toggleColumn(column)"
                                @sticky="table.toggleSticky(column)"
                            />
                            <span
                                v-else
                                :class="column.headerClass"
                            >
                                {{ column.header }}
                            </span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!resource.results.data.length">
                        <TableCell
                            :colspan="visible.length + (selectable ? 1 : 0)"
                            class="h-48 text-center"
                        >
                            <div class="flex flex-col items-center gap-2 py-8">
                                <Icon
                                    v-if="typeof empty?.icon === 'string'"
                                    :name="empty.icon"
                                    :resolver="iconResolver"
                                    class="text-muted-foreground mb-2 size-7"
                                />
                                <SearchX
                                    v-else-if="empty?.icon !== false"
                                    class="text-muted-foreground mb-2 size-7"
                                />
                                <p class="font-medium">{{ empty?.title || emptyTitle }}</p>
                                <p class="text-muted-foreground text-sm whitespace-normal">{{ empty?.message || emptyDescription }}</p>
                                <Button
                                    v-if="filtered"
                                    variant="outline"
                                    size="sm"
                                    class="mt-2"
                                    @click="table.clearFilters"
                                >
                                    Clear search &amp; filters
                                </Button>
                                <Button
                                    v-for="action in empty?.actions ?? []"
                                    :key="action.label"
                                    :variant="action.variant ?? 'outline'"
                                    size="sm"
                                    @click="actions.visit(action)"
                                >
                                    <Icon
                                        v-if="action.icon"
                                        :name="action.icon"
                                        :resolver="iconResolver"
                                        class="size-4"
                                    />
                                    {{ action.label }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="(item, index) in resource.results.data"
                        :key="rowKey(asRow(item), index)"
                        class="group"
                        :class="{ 'cursor-pointer': !!value(item, '_row_url') }"
                        :data-state="actions.isSelected(asRow(item)) ? 'selected' : undefined"
                        :tabindex="value(item, '_row_url') ? 0 : undefined"
                        @click="rowClick($event, item)"
                        @keydown.enter="
                            (event) => {
                                if (event.target === event.currentTarget) rowClick(event, item);
                            }
                        "
                    >
                        <TableCell
                            v-if="selectable"
                            class="w-12 px-4"
                            :style="pinned.length ? { position: 'sticky', left: 0, zIndex: 15 } : undefined"
                        >
                            <Checkbox
                                :model-value="actions.isSelected(asRow(item))"
                                :disabled="busy || asRow(item)._is_selectable === false"
                                :aria-label="`Select row ${index + 1}`"
                                @update:model-value="actions.toggle(asRow(item))"
                            />
                        </TableCell>
                        <TableCell
                            v-for="column in visible"
                            :key="column.attribute"
                            :data-column="column.attribute"
                            :style="sticky(column.attribute)"
                            class="px-4 py-3 align-middle text-sm"
                            :class="cellClass(column)"
                        >
                            <slot
                                :name="`cell-${column.attribute}`"
                                :item="item"
                                :value="value(item, column.attribute)"
                                :column="column"
                                :image="image(item, column.attribute)"
                                :table="table"
                                :actions="actions"
                            >
                                <RowActions
                                    v-if="column.type === 'action' || column.type === 'actions'"
                                    :item="{
                                        ...asRow(item),
                                        _actions: (value(item, column.attribute) as TableRowData['_actions']) ?? asRow(item)._actions,
                                    }"
                                    :actions="resource.actions ?? []"
                                    :busy="busy"
                                    :resolver="iconResolver"
                                    @action="perform"
                                />
                                <a
                                    v-else-if="
                                        cellUrl(item, column.attribute)?.url &&
                                        !cellUrl(item, column.attribute)?.hidden &&
                                        !cellUrl(item, column.attribute)?.disabled
                                    "
                                    :href="cellUrl(item, column.attribute)?.url"
                                    :target="cellUrl(item, column.attribute)?.openInNewTab ? '_blank' : undefined"
                                    rel="noopener noreferrer"
                                    class="font-normal hover:underline"
                                    @click.prevent="actions.visit(cellUrl(item, column.attribute)!)"
                                >
                                    <CellValue
                                        :value="value(item, column.attribute)"
                                        :column="column"
                                        :image="image(item, column.attribute)"
                                        :resolver="iconResolver"
                                    />
                                </a>
                                <CellValue
                                    v-else
                                    :value="value(item, column.attribute)"
                                    :column="column"
                                    :image="image(item, column.attribute)"
                                    :resolver="iconResolver"
                                />
                            </slot>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </ShadcnTable>
            <Pagination
                v-if="resource.pagination"
                class="px-4 py-3"
                :name="resource.name"
                :meta="resource.results"
                :type="resource.paginationType ?? 'full'"
                :options="resource.perPageOptions"
                :per-page="table.state.value.perPage"
                :busy="busy"
                @visit="table.visit"
                @change="table.setPerPage"
            />
        </div>
        <div
            v-if="selected.length"
            class="bg-card fixed bottom-6 left-1/2 z-40 flex w-max max-w-[calc(100%-2rem)] -translate-x-1/2 flex-wrap items-center gap-2 rounded-xl border px-3 py-2 shadow-lg"
            role="region"
            aria-label="Selected row actions"
        >
            <span
                class="px-2 text-sm font-medium tabular-nums"
                role="status"
            >
                {{ actions.all.value ? `All ${actions.count.value}` : actions.count.value }} selected
            </span>
            <Button
                v-if="!actions.all.value && allPage && (resource.results.total ?? 0) > selected.length"
                variant="ghost"
                size="sm"
                :disabled="busy"
                @click="selected = ['*']"
            >
                Select all {{ resource.results.total }} matching rows
            </Button>
            <Button
                v-for="entry in actions.available.value"
                :key="entry.index"
                :variant="entry.action.variant === 'destructive' ? 'destructiveGhost' : 'outline'"
                size="sm"
                :disabled="busy"
                :title="entry.partial ? `Applies to ${entry.keys.length} selected rows` : undefined"
                @click="actions.requestAction(entry.action, entry.index, entry.item, entry.keys)"
            >
                <Icon
                    v-if="entry.action.icon"
                    :name="entry.action.icon"
                    :resolver="iconResolver"
                    class="size-4"
                />
                {{ entry.action.label }}
                <span v-if="entry.partial">({{ entry.keys.length }})</span>
            </Button>
            <Button
                v-for="item in exports"
                :key="item.url"
                variant="outline"
                size="sm"
                :disabled="busy"
                @click="actions.requestExport(item)"
            >
                <Download class="size-4" />
                {{ item.label }}
            </Button>
            <span
                v-if="!actions.available.value.length && !exports.length"
                class="text-muted-foreground text-sm"
            >
                No available actions
            </span>
            <Button
                variant="ghost"
                size="sm"
                :disabled="busy"
                @click="selected = []"
            >
                <X class="size-4" />
                Clear selection
            </Button>
        </div>
        <FormDialog
            :open="!!actions.confirm.value"
            :title="actions.confirm.value?.action.confirmationTitle ?? 'Confirm action'"
            :description="actions.confirm.value?.action.confirmationMessage ?? 'Continue with this action?'"
            :busy="actionBusy"
            @close="actions.confirm.value = undefined"
            @submit="actions.confirm.value && actions.perform(actions.confirm.value as ActionContext)"
        >
            <template #footer>
                <Button
                    type="submit"
                    :variant="actions.confirm.value?.action.variant === 'destructive' ? 'destructive' : 'default'"
                    :disabled="actionBusy"
                >
                    {{ actions.confirm.value?.action.confirmationConfirmButton ?? 'Confirm' }}
                </Button>
            </template>
        </FormDialog>
        <ExportDialog
            :resource="actions.exportContext.value"
            :visible="table.state.value.columns"
            :busy="actionBusy"
            :error="actionError"
            @close="
                actions.exportContext.value = undefined;
                actionError = '';
            "
            @submit="(payload) => actions.exportContext.value && actions.exportData(actions.exportContext.value, payload)"
        />
    </div>
</template>

<style>
.it-table-frame {
    --table-chrome: var(--workspace-table-chrome, #f0f0f2);
    --table-surface: var(--card);
    --table-divider: #e4e4e7;
    background: var(--table-chrome);
}
.dark .it-table-frame {
    --table-chrome: var(--workspace-table-chrome, #121214);
    --table-surface: var(--card);
    --table-divider: #333337;
}
.it-table-frame [data-slot='table-container'] {
    isolation: isolate;
    border-radius: 0.75rem;
}
.it-table-frame .it-table {
    border-collapse: separate;
    border-spacing: 0;
}
.it-table-frame thead tr {
    background: var(--table-chrome);
}
.it-table-frame thead [data-slot='table-head'] {
    height: 2.75rem;
    background: var(--table-chrome);
    border-bottom: 0;
    color: var(--foreground);
}
.it-table-frame tbody {
    filter: drop-shadow(0 2px 1px rgb(0 0 0 / 0.04));
}
.it-table-frame tbody tr {
    background: var(--table-surface);
    outline: none;
}
/* A focused table row has no reliable native outline in Chromium. Keep a quiet
 * keyboard indicator inside its cells, without drawing blue lines on mouse use. */
.it-table-frame tbody tr:focus-visible > td {
    box-shadow:
        inset 0 2px 0 var(--ring),
        inset 0 -2px 0 var(--ring);
}
.it-table-frame tbody tr:hover {
    background: color-mix(in oklab, var(--muted) 45%, var(--table-surface));
}
.it-table-frame tbody tr[data-state='selected'] {
    background: color-mix(in oklab, var(--primary) 7%, var(--table-surface));
}
.it-table-frame tbody [data-slot='table-cell'] {
    height: 3.5rem;
    background: inherit;
    border-bottom: 1px solid var(--table-divider);
}
.it-table-frame tbody tr:first-child td {
    border-top: 1px solid var(--table-divider);
}
.it-table-frame tbody td:first-child {
    border-left: 1px solid var(--table-divider);
}
.it-table-frame tbody td:last-child {
    border-right: 1px solid var(--table-divider);
}
.it-table-frame tbody tr:first-child td:first-child {
    border-start-start-radius: 0.75rem;
}
.it-table-frame tbody tr:first-child td:last-child {
    border-start-end-radius: 0.75rem;
}
.it-table-frame tbody tr:last-child td:first-child {
    border-end-start-radius: 0.75rem;
}
.it-table-frame tbody tr:last-child td:last-child {
    border-end-end-radius: 0.75rem;
}
.it-table-frame tbody tr:last-child td {
    border-bottom: 1px solid var(--table-divider);
}
</style>
