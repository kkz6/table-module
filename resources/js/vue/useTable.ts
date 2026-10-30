import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { filterQuery } from '../filterQuery';
import { queryFor } from './state';
import type { FilterDefinition, TableColumn, TableResource, TableState, TableTab } from './types';

/** Vue port of the module's URL/state controller; the PHP Table remains authoritative. */
export function useTable<T extends object>(resource: Ref<TableResource<T>>) {
    const state = ref<TableState>(JSON.parse(JSON.stringify(resource.value.state)) as TableState);
    const isNavigating = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let cancel: (() => void) | undefined;
    let revision = 0;
    watch(
        () => resource.value.state,
        (value) => {
            state.value = JSON.parse(JSON.stringify(value)) as TableState;
        },
        { deep: true },
    );
    function visit(url: string, data?: Parameters<typeof router.get>[1]): void {
        cancel?.();
        const current = ++revision;
        isNavigating.value = true;
        router.get(url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onCancelToken: (token) => {
                cancel = () => token.cancel();
            },
            onFinish: () => {
                if (current === revision) isNavigating.value = false;
            },
        });
    }
    function navigate(): void {
        if (timer) clearTimeout(timer);
        visit(`${window.location.pathname}?${queryFor(resource.value, state.value, window.location.search)}`);
    }
    function setSearch(value: string): void {
        state.value.search = value;
        state.value.page = 1;
        state.value.cursor = null;
        if (timer) clearTimeout(timer);
        timer = setTimeout(navigate, resource.value.debounceTime ?? 300);
    }
    function setSort(value: string): void {
        state.value.sort = value;
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    function setPerPage(value: string): void {
        state.value.perPage = Number(value);
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    function setFilter(filter: FilterDefinition, clause: string, value: unknown): void {
        state.value.filters[filter.attribute] = { enabled: true, clause, value };
        state.value.page = 1;
        state.value.cursor = null;
        if (filterQuery(state.value.filters[filter.attribute]) !== null) navigate();
    }
    function removeFilter(filter: FilterDefinition): void {
        state.value.filters[filter.attribute] = { enabled: false, value: null };
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    function toggleColumn(column: TableColumn): void {
        if (!column.toggleable) return;
        const visible = Object.values(state.value.columns).filter(Boolean).length;
        if (visible === 1 && state.value.columns[column.attribute]) return;
        state.value.columns[column.attribute] = !state.value.columns[column.attribute];
        navigate();
    }
    function toggleSticky(column: TableColumn): void {
        state.value.sticky = state.value.sticky.includes(column.attribute)
            ? state.value.sticky.filter((key) => key !== column.attribute)
            : [...state.value.sticky, column.attribute];
        navigate();
    }
    function clearFilters(): void {
        state.value.search = '';
        state.value.filters = Object.fromEntries(Object.keys(state.value.filters).map((key) => [key, { enabled: false, value: null }]));
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    onBeforeUnmount(() => {
        if (timer) clearTimeout(timer);
        cancel?.();
    });
    function applyState(value: TableState): void {
        state.value = JSON.parse(JSON.stringify(value));
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    function setTab(tab: TableTab): void {
        for (const item of resource.value.tabs ?? [])
            for (const key of Object.keys(item.filters)) state.value.filters[key] = { enabled: false, value: null };
        for (const [key, preset] of Object.entries(tab.filters))
            state.value.filters[key] =
                preset === null
                    ? { enabled: false, value: null }
                    : {
                          enabled: true,
                          ...(typeof preset === 'object' && preset && 'value' in preset ? preset : { clause: 'equals', value: preset }),
                      };
        state.value.page = 1;
        state.value.cursor = null;
        navigate();
    }
    return {
        applyState,
        setTab,
        state,
        isNavigating,
        visibleColumns: computed(() =>
            resource.value.columns
                .filter((column) => state.value.columns[column.attribute] !== false)
                .sort((a, b) => Number(state.value.sticky.includes(b.attribute)) - Number(state.value.sticky.includes(a.attribute))),
        ),
        setSearch,
        setSort,
        setPerPage,
        setFilter,
        removeFilter,
        toggleColumn,
        toggleSticky,
        clearFilters,
        visit,
    };
}
