import { router } from '@inertiajs/vue3';
import qs from 'qs';
import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { filterQuery } from '../filterQuery';
import type { FilterDefinition, TableColumn, TableResource, TableState } from './types';

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
        const current = state.value;
        const filters = Object.fromEntries(
            Object.entries(current.filters)
                .filter(([, filter]) => filter.enabled)
                .map(([key, filter]) => [key, filterQuery(filter)])
                .filter(([, value]) => value !== null),
        );
        const enabled = resource.value.columns.filter((column) => current.columns[column.attribute]).map((column) => column.attribute);
        const defaults = resource.value.columns.filter((column) => column.visibleByDefault !== false).map((column) => column.attribute);
        const query: Record<string, unknown> = {
            search: current.search || null,
            sort: current.sort || null,
            perPage: current.perPage,
            page: current.page || 1,
            filters,
            columns: enabled.join(',') === defaults.join(',') ? [] : enabled,
            sticky: current.sticky,
        };
        const existing = qs.parse(window.location.search, { ignoreQueryPrefix: true });
        const params = resource.value.name === 'default' ? { ...existing, ...query } : { ...existing, [resource.value.name]: query };
        visit(`${window.location.pathname}?${qs.stringify(params, { skipNulls: true })}`);
    }
    function setSearch(value: string): void {
        state.value.search = value;
        state.value.page = 1;
        if (timer) clearTimeout(timer);
        timer = setTimeout(navigate, resource.value.debounceTime ?? 300);
    }
    function setSort(value: string): void {
        state.value.sort = value;
        state.value.page = 1;
        navigate();
    }
    function setPerPage(value: string): void {
        state.value.perPage = Number(value);
        state.value.page = 1;
        navigate();
    }
    function setFilter(filter: FilterDefinition, clause: string, value: unknown): void {
        state.value.filters[filter.attribute] = { enabled: true, clause, value };
        state.value.page = 1;
        if (filterQuery(state.value.filters[filter.attribute]) !== null) navigate();
    }
    function removeFilter(filter: FilterDefinition): void {
        state.value.filters[filter.attribute] = { enabled: false, value: null };
        state.value.page = 1;
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
        navigate();
    }
    onBeforeUnmount(() => {
        if (timer) clearTimeout(timer);
        cancel?.();
    });
    return {
        state,
        isNavigating,
        visibleColumns: computed(() => resource.value.columns.filter((column) => state.value.columns[column.attribute] !== false)),
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
