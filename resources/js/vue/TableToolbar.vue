<script setup lang="ts" generic="T extends object">
import { Button, DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, Input } from '@workspace/ui';
import { Columns3, Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { computed } from 'vue';
import TableFilter from './TableFilter.vue';
import type { FilterDefinition, TableResource, TableState } from './types';
const props = defineProps<{ resource: TableResource<T>; state: TableState; busy?: boolean }>();
const emit = defineEmits<{
    search: [value: string];
    toggleColumn: [attribute: string];
    filter: [filter: FilterDefinition, clause: string, value: unknown];
    removeFilter: [filter: FilterDefinition];
    clear: [];
}>();
const active = computed(() => props.resource.filters.filter((filter) => props.state.filters[filter.attribute]?.enabled));
</script>
<template>
    <div
        v-if="resource.hasSearch || resource.hasFilters || resource.hasToggleableColumns || $slots.default"
        class="it-toolbar bg-card overflow-hidden rounded-lg border"
    >
        <div class="p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
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
                    <slot />
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <DropdownMenu v-if="resource.filters.length">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                            >
                                <SlidersHorizontal class="size-4" />
                                Filters
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-for="filter in resource.filters"
                                :key="filter.attribute"
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
                    <DropdownMenu v-if="resource.hasToggleableColumns">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                            >
                                <Columns3 class="size-4" />
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
                class="mt-3 flex flex-wrap items-center gap-2"
            >
                <div
                    v-for="filter in active"
                    :key="filter.attribute"
                    class="flex items-center gap-2 rounded-md border px-2 py-1"
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
                        class="size-6"
                        :aria-label="`Remove ${filter.label} filter`"
                        @click="emit('removeFilter', filter)"
                    >
                        <X class="size-3.5" />
                    </Button>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="emit('clear')"
                >
                    Clear filters
                </Button>
            </div>
        </div>
    </div>
</template>
