import { Input } from '@shared/components/ui/input';
import { ScrollArea, ScrollBar } from '@shared/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@shared/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@shared/components/ui/tabs';
import { cn } from '@shared/lib/utils';
import { Search } from 'lucide-react';
import type { ComponentType, ReactNode, SVGProps } from 'react';
import ActionsDropdown from './ActionsDropdown';
import AddFilterDropdown from './AddFilterDropdown';
import DynamicIcon from './DynamicIcon';
import Filter from './Filter';
import { resolveIcon } from './iconResolver';
import ToggleColumnDropdown from './ToggleColumnDropdown';
import type { ActionsDropdownProps, FilterDefinitionExtended, TableResource, TableTab, UseActionsReturn, UseTableReturn } from './types';

export function activeTableTab(tabs: TableTab[], state: UseTableReturn['state']): string {
    return (
        tabs.find((tab) =>
            Object.entries(tab.filters).every(([attribute, preset]) => {
                const current = state.filters[attribute];
                if (preset === null) return !current?.enabled;
                const expected =
                    typeof preset === 'object' && preset !== null && 'value' in preset
                        ? (preset as { value: unknown; clause?: string })
                        : { value: preset, clause: 'equals' };
                return (
                    !!current?.enabled &&
                    current.clause === (expected.clause ?? 'equals') &&
                    (Array.isArray(current.value) || Array.isArray(expected.value)
                        ? JSON.stringify(current.value) === JSON.stringify(expected.value)
                        : String(current.value) === String(expected.value))
                );
            }),
        )?.value ?? ''
    );
}

const activeColors: Record<string, string> = {
    neutral: 'data-[state=active]:border-primary data-[state=active]:text-foreground',
    success: 'data-[state=active]:border-emerald-600 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-300',
    info: 'data-[state=active]:border-blue-600 data-[state=active]:text-blue-700 dark:data-[state=active]:text-blue-300',
    warning: 'data-[state=active]:border-amber-600 data-[state=active]:text-amber-800 dark:data-[state=active]:text-amber-300',
    destructive: 'data-[state=active]:border-red-600 data-[state=active]:text-red-700 dark:data-[state=active]:text-red-300',
};

type Context = { table: UseTableReturn; actions: UseActionsReturn };
type Props = {
    resource: TableResource;
    table: UseTableReturn;
    actions: UseActionsReturn;
    topbar?: ((context: Context) => ReactNode) | null;
    filters?: ((context: Context) => ReactNode) | null;
    iconResolver?: ((icon: string) => ComponentType<SVGProps<SVGSVGElement>>) | null;
    onActionSuccess?: ActionsDropdownProps['onSuccess'];
    onActionError?: ActionsDropdownProps['onError'];
    onCustomAction?: ActionsDropdownProps['onHandle'];
};

export default function TableToolbar({
    resource,
    table,
    actions,
    topbar,
    filters,
    iconResolver,
    onActionSuccess,
    onActionError,
    onCustomAction,
}: Props) {
    if (resource.hasRecords === false && !table.state.search) return null;
    const tabs = resource.tabs ?? [];
    const selected = activeTableTab(tabs, table.state);
    const quick = resource.filters.filter(
        (filter) => (resource.quickFilters ?? []).includes(filter.attribute) && filter.type === 'set' && !filter.multiple,
    );
    const shownFilters = resource.filters.filter((filter) => table.state.filters[filter.attribute]?.enabled);
    if (!resource.hasSearch && !resource.hasFilters && !resource.hasExports && !resource.hasToggleableColumns && !tabs.length && !topbar && !filters)
        return null;
    const resolver = (name: string) => iconResolver?.(name) ?? resolveIcon(name) ?? (() => null);
    return (
        <div className="it-toolbar bg-card overflow-hidden rounded-lg border">
            <div className="p-3 sm:p-4">
                {topbar ? (
                    topbar({ table, actions })
                ) : (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                            {resource.hasSearch && (
                                <div className="relative w-full sm:w-72">
                                    <Search
                                        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={table.state.search ?? ''}
                                        onChange={(event) => table.setSearch(event.target.value)}
                                        placeholder="Search..."
                                        aria-label="Search table"
                                        autoFocus={resource.autofocus === 'search'}
                                        className="w-full pl-9"
                                    />
                                </div>
                            )}
                            {quick.map((filter) => {
                                const state = table.state.filters[filter.attribute];
                                const current = !state?.enabled
                                    ? '__table_all__'
                                    : state.clause === 'equals' && !Array.isArray(state.value)
                                      ? String(state.value)
                                      : '__table_custom__';
                                return (
                                    <Select
                                        key={filter.attribute}
                                        value={current}
                                        onValueChange={(value) =>
                                            value === '__table_all__' ? table.removeFilter(filter) : table.setFilter(filter, 'equals', value)
                                        }
                                        disabled={table.isNavigating}
                                    >
                                        <SelectTrigger className="w-full sm:w-auto sm:min-w-36" aria-label={`Filter ${filter.label}`}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__table_all__">
                                                {resource.quickFilterLabels?.[filter.attribute] ?? `All ${filter.label.toLowerCase()}`}
                                            </SelectItem>
                                            {current === '__table_custom__' && (
                                                <SelectItem value="__table_custom__" disabled>
                                                    Custom selection
                                                </SelectItem>
                                            )}
                                            {filter.options?.map((option) => (
                                                <SelectItem key={String(option.value)} value={String(option.value)}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                );
                            })}
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {resource.hasExports && actions.selectedItems.length === 0 && (
                                <ActionsDropdown
                                    actions={[]}
                                    exports={resource.exports ?? []}
                                    selectedItems={actions.selectedItems}
                                    performAction={actions.performAction}
                                    performAsyncExport={actions.performAsyncExport}
                                    iconResolver={resolver}
                                    onSuccess={onActionSuccess}
                                    onError={onActionError}
                                    onHandle={onCustomAction}
                                />
                            )}
                            {resource.hasFilters && (
                                <AddFilterDropdown state={table.state.filters} filters={resource.filters} onAdd={table.addFilter} />
                            )}
                            {resource.hasToggleableColumns && (
                                <ToggleColumnDropdown state={table.state.columns} columns={resource.columns} onToggle={table.toggleColumn} />
                            )}
                        </div>
                    </div>
                )}
                {filters ? (
                    <div className="mt-3">{filters({ table, actions })}</div>
                ) : (
                    shownFilters.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {shownFilters.map((filter) => (
                                <Filter
                                    key={filter.attribute}
                                    filter={{
                                        ...filter,
                                        type: (filter.type ?? 'set') as FilterDefinitionExtended['type'],
                                        clauses: filter.clauses ?? [],
                                    }}
                                    value={{
                                        ...table.state.filters[filter.attribute],
                                        clause: table.state.filters[filter.attribute].clause ?? 'equals',
                                    }}
                                    onChange={(next) => table.setFilter(filter, next.clause, next.value)}
                                    onRemove={() => table.removeFilter(filter)}
                                />
                            ))}
                        </div>
                    )
                )}
            </div>
            {tabs.length > 0 && (
                <Tabs
                    value={selected}
                    onValueChange={(value) => {
                        const tab = tabs.find((tab) => tab.value === value);
                        if (tab) table.setTab(tab);
                    }}
                >
                    <ScrollArea hideVerticalScrollbar className="border-t">
                        <TabsList aria-label="Table views" className="h-auto w-max justify-start rounded-none bg-transparent p-0 px-2">
                            {tabs.map((tab) => (
                                <TabsTrigger
                                    key={tab.value}
                                    value={tab.value}
                                    disabled={table.isNavigating}
                                    className={cn(
                                        'gap-2 rounded-none border-b-2 border-transparent px-3 py-3 data-[state=active]:bg-transparent data-[state=active]:shadow-none',
                                        activeColors[tab.tone ?? 'neutral'] ?? activeColors.neutral,
                                    )}
                                >
                                    {tab.icon && <DynamicIcon icon={tab.icon} resolver={resolver} context={tab} className="size-4" />}
                                    {tab.label}
                                    {tab.count !== null && tab.count !== undefined && (
                                        <span className="rounded-full border px-1.5 py-0.5 text-[10px] leading-none tabular-nums">{tab.count}</span>
                                    )}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                </Tabs>
            )}
        </div>
    );
}
