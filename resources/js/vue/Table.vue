<script setup lang="ts" generic="T extends object">
import { Link } from '@inertiajs/vue3';
import { Badge, Button, Checkbox, Table as ShadcnTable, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@workspace/ui';
import { SearchX } from 'lucide-vue-next';
import { computed, ref, toRef } from 'vue';
import { badgeTone } from '../../../../shared/resources/js/lib/status-colors';
import Pagination from './Pagination.vue';
import TableHeaderDropdown from './TableHeaderDropdown.vue';
import TableToolbar from './TableToolbar.vue';
import type { TableColumn, TableResource } from './types';
import { useTable } from './useTable';
const props = withDefaults(defineProps<{ resource: TableResource<T>; emptyTitle?: string; emptyDescription?: string }>(), {
    emptyTitle: 'No results',
    emptyDescription: 'Try adjusting your search or filters.',
});
const emit = defineEmits<{ rowClick: [item: T, column: TableColumn] }>();
const table = useTable(toRef(props, 'resource'));
const selected = ref<Array<string | number>>([]);
const selectable = computed(() => props.resource.hasBulkActions || props.resource.hasExportsThatLimitsToSelectedRows);
const visible = table.visibleColumns;
function value(item: T, attribute: string): unknown {
    if (Object.prototype.hasOwnProperty.call(item, attribute)) return (item as Record<string, unknown>)[attribute];
    return attribute
        .split('.')
        .reduce<unknown>((value, key) => (value && typeof value === 'object' ? (value as Record<string, unknown>)[key] : undefined), item);
}
function columnUrl(item: T, attribute: string): string | undefined {
    const urls = value(item, '_column_urls') as Record<string, unknown> | undefined;
    const url = urls?.[attribute];
    return typeof url === 'string' ? url : typeof url === 'object' && url && 'url' in url ? String(url.url) : undefined;
}
function display(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object' && value && 'value' in value) return display(value.value);
    return Array.isArray(value) ? value.map(display).join(', ') : String(value);
}
function badgeVariant(value: unknown) {
    return badgeTone(value && typeof value === 'object' && 'variant' in value ? value.variant : undefined);
}
function badge(value: unknown): boolean {
    return !!value && typeof value === 'object' && 'variant' in value && 'value' in value;
}
function key(item: T, index: number): string | number {
    return (value(item, '_primary_key') ?? value(item, 'id') ?? index) as string | number;
}
function toggle(item: T, index: number): void {
    const id = key(item, index);
    selected.value = selected.value.includes(id) ? selected.value.filter((key) => key !== id) : [...selected.value, id];
}
function toggleAll(checked: boolean | 'indeterminate'): void {
    selected.value = checked ? props.resource.results.data.map(key) : [];
}
function sort(column: TableColumn): string | null {
    return table.state.value.sort === column.attribute ? 'asc' : table.state.value.sort === '-' + column.attribute ? 'desc' : null;
}
function findColumn(attribute: string): TableColumn | undefined {
    return props.resource.columns.find((column) => column.attribute === attribute);
}
</script>
<template>
    <div
        class="it-wrapper relative min-w-0"
        :aria-busy="table.isNavigating.value"
    >
        <fieldset
            class="min-w-0 space-y-4 transition-opacity"
            :class="{ 'opacity-50': table.isNavigating.value }"
        >
            <TableToolbar
                :resource="resource"
                :state="table.state.value"
                :busy="table.isNavigating.value"
                @search="table.setSearch"
                @toggle-column="
                    (attribute) => {
                        const item = findColumn(attribute);
                        if (item) table.toggleColumn(item);
                    }
                "
                @filter="table.setFilter"
                @remove-filter="table.removeFilter"
                @clear="table.clearFilters"
            >
                <slot name="toolbar" />
            </TableToolbar>
            <div class="rounded-md border border-gray-200 dark:border-zinc-700">
                <ShadcnTable class="it-table w-full caption-bottom bg-white text-left transition-opacity dark:bg-zinc-900 dark:text-zinc-300">
                    <TableHeader
                        v-if="resource.results.data.length"
                        class="it-table-head"
                    >
                        <TableRow class="border-b border-gray-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                            <TableHead
                                v-if="selectable"
                                class="w-8 px-2"
                            >
                                <Checkbox
                                    :model-value="selected.length === resource.results.data.length ? true : selected.length ? 'indeterminate' : false"
                                    aria-label="Select all rows"
                                    @update:model-value="toggleAll"
                                />
                            </TableHead>
                            <TableHead
                                v-for="column in visible"
                                :key="column.attribute"
                                :data-column="column.attribute"
                                :aria-sort="
                                    column.sortable
                                        ? sort(column) === 'asc'
                                            ? 'ascending'
                                            : sort(column) === 'desc'
                                              ? 'descending'
                                              : 'none'
                                        : undefined
                                "
                                class="text-muted-foreground h-10 px-2 align-middle text-sm font-medium first:ps-4 last:pe-4"
                                :class="{ 'text-right': column.alignment === 'right', 'text-center': column.alignment === 'center' }"
                            >
                                <TableHeaderDropdown
                                    v-if="column.sortable || column.toggleable || column.stickable"
                                    :column="column"
                                    :sort="sort(column)"
                                    :sticky="table.state.value.sticky.includes(column.attribute)"
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
                                    <SearchX class="text-muted-foreground mb-2 size-7" />
                                    <p class="font-medium">{{ emptyTitle }}</p>
                                    <p class="text-muted-foreground text-sm whitespace-normal">{{ emptyDescription }}</p>
                                    <Button
                                        v-if="table.state.value.search || Object.values(table.state.value.filters).some((filter) => filter.enabled)"
                                        variant="outline"
                                        size="sm"
                                        class="mt-2"
                                        @click="table.clearFilters"
                                    >
                                        Clear search &amp; filters
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="(item, index) in resource.results.data"
                            :key="key(item, index)"
                            class="group border-b border-gray-200 dark:border-zinc-700"
                            :data-state="selected.includes(key(item, index)) ? 'selected' : undefined"
                        >
                            <TableCell
                                v-if="selectable"
                                class="w-8 px-2"
                            >
                                <Checkbox
                                    :model-value="selected.includes(key(item, index))"
                                    :aria-label="`Select row ${index + 1}`"
                                    @update:model-value="toggle(item, index)"
                                />
                            </TableCell>
                            <TableCell
                                v-for="column in visible"
                                :key="column.attribute"
                                :data-column="column.attribute"
                                class="p-2 align-middle first:ps-4 last:pe-4 dark:text-zinc-300"
                                :class="[
                                    column.cellClass,
                                    {
                                        'text-right': column.alignment === 'right',
                                        'text-center': column.alignment === 'center',
                                        'whitespace-normal': column.wrap,
                                    },
                                ]"
                                @click="emit('rowClick', item, column)"
                            >
                                <slot
                                    :name="`cell-${column.attribute}`"
                                    :item="item"
                                    :value="value(item, column.attribute)"
                                    :column="column"
                                >
                                    <Link
                                        v-if="columnUrl(item, column.attribute)"
                                        :href="columnUrl(item, column.attribute)!"
                                        class="font-medium hover:underline"
                                    >
                                        {{ display(value(item, column.attribute)) }}
                                    </Link>
                                    <Badge
                                        v-else-if="badge(value(item, column.attribute))"
                                        :variant="badgeVariant(value(item, column.attribute))"
                                        class="it-badge gap-1"
                                    >
                                        {{ display(value(item, column.attribute)) }}
                                    </Badge>
                                    <span v-else>{{ display(value(item, column.attribute)) }}</span>
                                </slot>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </ShadcnTable>
            </div>
            <Pagination
                v-if="resource.pagination"
                :name="resource.name"
                :meta="resource.results"
                :options="resource.perPageOptions"
                :per-page="table.state.value.perPage"
                :busy="table.isNavigating.value"
                @visit="table.visit"
                @change="table.setPerPage"
            />
        </fieldset>
    </div>
</template>
