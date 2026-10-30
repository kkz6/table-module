<script setup lang="ts" generic="T extends object">
import {
    Button,
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    Input,
    Select,
} from '@workspace/ui';
import { Download, Eye, Funnel, LayoutGrid, Search, X } from 'lucide-vue-next';
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed } from 'vue';
import Icon from './Icon.vue';
import SavedViews from './SavedViews.vue';
import { activeTab } from './state';
import TableFilter from './TableFilter.vue';
import type { FilterDefinition, TableExport, TableResource, TableState, TableTab } from './types';
const props = defineProps<{ resource: TableResource<T>; state: TableState; busy?: boolean }>();
const emit = defineEmits<{
    search: [value: string];
    toggleColumn: [attribute: string];
    filter: [filter: FilterDefinition, clause: string, value: unknown];
    removeFilter: [filter: FilterDefinition];
    clear: [];
    tab: [tab: TableTab];
    export: [resource: TableExport];
    view: [state: TableState];
}>();
const tabs = computed<TableTab[]>(() => {
    const configured = props.resource.tabs ?? [];
    if (configured.some((tab) => tab.label.toLowerCase() === 'all')) return configured;
    const keys = [...new Set(configured.flatMap((tab) => Object.keys(tab.filters)))];
    return [{ value: '__all', label: 'All', filters: Object.fromEntries(keys.map((key) => [key, null])) }, ...configured];
});
const selected = computed(() => activeTab(tabs.value, props.state));
const managed = computed(() => new Set((props.resource.tabs ?? []).flatMap((tab) => Object.keys(tab.filters))));
const represented = computed(() => Object.keys(props.resource.tabs?.find((tab) => tab.value === selected.value)?.filters ?? {}));
const active = computed(() =>
    props.resource.filters.filter((filter) => props.state.filters[filter.attribute]?.enabled && !represented.value.includes(filter.attribute)),
);
const filters = computed(() => props.resource.filters.filter((filter) => !managed.value.has(filter.attribute)));
const quick = computed(() =>
    filters.value.filter((filter) => (props.resource.quickFilters ?? []).includes(filter.attribute) && filter.options?.length && !filter.multiple),
);
const exports = computed(() => (props.resource.exports ?? []).filter((item) => item.authorized !== false));
</script>
<template>
    <div class="it-toolbar bg-card overflow-hidden rounded-lg border">
        <div class="flex flex-wrap items-center justify-between gap-3 p-3 sm:p-4">
            <div class="flex min-w-0 flex-1 basis-full flex-wrap items-center gap-2 sm:basis-0">
                <div
                    v-if="resource.hasSearch"
                    class="relative w-full sm:w-72"
                >
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        aria-hidden="true"
                    />
                    <Input
                        :model-value="state.search || ''"
                        placeholder="Search…"
                        aria-label="Search table"
                        class="w-full pl-9"
                        @update:model-value="(value) => emit('search', String(value ?? ''))"
                    />
                </div>
                <Select
                    v-for="filter in quick"
                    :key="filter.attribute"
                    class="w-40"
                    :aria-label="resource.quickFilterLabels?.[filter.attribute] ?? filter.label"
                    :model-value="state.filters[filter.attribute]?.enabled ? String(state.filters[filter.attribute].value) : '__all'"
                    :options="[
                        { value: '__all', label: resource.quickFilterLabels?.[filter.attribute] ?? `All ${filter.label.toLowerCase()}` },
                        ...(filter.options ?? []).map((option) => ({ value: String(option.value), label: option.label })),
                    ]"
                    @update:model-value="(value) => (value === '__all' ? emit('removeFilter', filter) : emit('filter', filter, 'equals', value))"
                />
                <slot />
            </div>
            <div class="ml-auto flex items-center gap-2">
                <DropdownMenu v-if="filters.length">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            :disabled="busy"
                        >
                            <Funnel class="size-4" />
                            Filters
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-for="filter in filters"
                            :key="filter.attribute"
                            :disabled="state.filters[filter.attribute]?.enabled"
                            @select="
                                emit(
                                    'filter',
                                    filter,
                                    filter.clauses?.[0] || 'equals',
                                    ['in', 'not_in', 'between', 'not_between'].includes(filter.clauses?.[0] || '') ? [] : '',
                                )
                            "
                        >
                            {{ filter.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <SavedViews
                    v-if="resource.views"
                    :resource="resource.views"
                    :name="resource.name"
                    :busy="busy"
                    @apply="emit('view', $event)"
                />
                <DropdownMenu v-if="exports.length">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            :disabled="busy"
                            aria-label="Export"
                            title="Export records"
                        >
                            <Download class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            v-for="item in exports"
                            :key="item.url"
                            @select="emit('export', item)"
                        >
                            {{ item.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu v-if="resource.hasToggleableColumns">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="default"
                            :disabled="busy"
                            aria-label="Columns"
                            title="Column settings"
                        >
                            <Eye class="size-4" />
                            Columns
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="column in resource.columns.filter((item) => item.toggleable)"
                            :key="column.attribute"
                            :model-value="state.columns[column.attribute] !== false"
                            @update:model-value="emit('toggleColumn', column.attribute)"
                            @select.prevent
                        >
                            {{ column.header }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <div
            v-if="active.length"
            class="flex flex-wrap items-center gap-2 px-4 pb-3"
        >
            <div
                v-for="filter in active"
                :key="filter.attribute"
                class="flex flex-wrap items-center gap-2 rounded-md border px-2 py-1"
            >
                <span class="text-xs font-medium">{{ filter.label }}</span>
                <TableFilter
                    :filter="filter"
                    :state="state.filters[filter.attribute]"
                    @remove="emit('removeFilter', filter)"
                    @change="(clause, value) => emit('filter', filter, clause, value)"
                />
                <Button
                    variant="ghost"
                    size="icon"
                    class="it-row-action-icon-button"
                    :aria-label="`Remove ${filter.label} filter`"
                    @click="emit('removeFilter', filter)"
                >
                    <X class="size-3.5" />
                </Button>
            </div>
            <Button
                variant="ghost"
                @click="emit('clear')"
            >
                Clear filters
            </Button>
        </div>
        <TabsRoot
            :model-value="selected"
            @update:model-value="
                (value) => {
                    const tab = tabs.find((tab) => tab.value === value);
                    if (tab) emit('tab', tab);
                }
            "
        >
            <TabsList
                aria-label="Table views"
                class="flex w-full overflow-x-auto border-t px-2"
            >
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.value"
                    :value="tab.value"
                    :disabled="busy"
                    class="text-muted-foreground data-[state=active]:text-foreground focus-visible:ring-ring flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-3 text-sm outline-none focus-visible:ring-2 data-[state=active]:border-current"
                    :class="
                        tab.tone === 'success'
                            ? 'text-emerald-700 data-[state=active]:text-emerald-700 dark:text-emerald-400 dark:data-[state=active]:text-emerald-400'
                            : tab.tone === 'warning'
                              ? 'text-amber-700 data-[state=active]:text-amber-700 dark:text-amber-400'
                              : undefined
                    "
                >
                    <Icon
                        v-if="tab.icon"
                        :name="tab.icon"
                        class="size-4"
                    />
                    <LayoutGrid
                        v-else-if="tab.label.toLowerCase() === 'all'"
                        class="size-4"
                    />
                    {{ tab.label }}
                    <span
                        v-if="tab.count != null"
                        class="border-border bg-background rounded-full border px-1.5 py-0 text-[10px] leading-4 tabular-nums"
                    >
                        {{ tab.count }}
                    </span>
                </TabsTrigger>
            </TabsList>
        </TabsRoot>
    </div>
</template>

<style>
.it-toolbar {
    --control-height: 2.25rem;
    --action-height: var(--control-height);
}
</style>
