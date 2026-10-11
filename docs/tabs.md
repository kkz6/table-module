# Table toolbar and preset tabs

`TableToolbar.tsx` is the shared search/filter/tab panel for both table renderers. It uses the existing shadcn components. The default panel has an icon search field, optional quick set-filter selectors, filter and column controls, visible active-filter chips, and a horizontal tab row. Existing custom `topbar` and `filters` render callbacks remain supported.

## Configure a table

Define filters normally, then override these optional methods on a `Modules\Table\Table` subclass:

```php
use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Tab;

public function quickFilters(): array
{
    return ['currency'];
}

public function quickFilterLabels(): array
{
    return ['currency' => 'All currencies'];
}

public function tabs(): array
{
    return [
        Tab::make('all', 'All')
            ->filters(['status' => null])
            ->icon('layout-grid')
            ->countUsing(fn (Builder $query) => $query->count()),
        Tab::make('draft', 'Drafts')
            ->filters(['status' => 'draft'])
            ->icon('file-pen-line')
            ->countUsing(fn (Builder $query) => $query->where('status', 'draft')->count()),
        Tab::make('issued', 'Issued')
            ->filters(['status' => 'issued'])
            ->icon('circle-check')
            ->tone('info')
            ->countUsing(fn (Builder $query) => $query->where('status', 'issued')->count()),
    ];
}
```

Preset attributes must refer to existing table filters. Values are simple filter values or `{ value, clause }` arrays for custom clauses. A null preset disables that filter, including a filter with an enabled default. Use unique string tab values. Supported selection tones are `neutral`, `success`, `info`, `warning`, and `destructive`. Icons use the table's existing icon resolver.

Counts are optional. Each count callback receives a clone of the table's base query, preserving authorization/data scopes, current search, and filters outside the preset's managed attributes. Apply the preset's own conditions in the callback; do not replace the provided query with an unrestricted model query. Counts are independent of pagination. All tabs share the union of managed preset filter attributes; those attributes are excluded from the facet count query. Quick selectors support single-value set filters only.

## Client behavior

The response adds `tabs`, `quickFilters`, and `quickFilterLabels` to the table resource. `useTable().setTab(tab)` applies all preset filters together, resets page/cursor, and preserves search, sort, columns, and unrelated filters. Filter state determines the selected tab; there is no competing tab query parameter. Changing a filter can select a matching tab or leave no preset selected for a custom combination.

Active chips remain visible even when a tab or quick selector represents the same filter. Chips can edit/remove filters independently; their state and the selected tab stay synchronized. This is intentional: selecting a filter must never make its visible chip disappear.

No tabs are rendered when none are configured. For a genuinely empty authorized base collection, the shared empty state hides the toolbar and tabs. A search or filtered result with no matches keeps recovery controls available. Archived records remain discoverable where the base collection's archive policy supports them.

## Reference implementation and verification

- `src/Tab.php`: preset/count definition.
- `src/Table.php`: `tabs()`, `quickFilters()`, `quickFilterLabels()`, facet serialization.
- `src/QueryBuilder.php`: cloned resource queries and filter exclusions for facet counts.
- `resources/js/TableToolbar.tsx`: common panel and visible filter chips.
- `resources/js/useTable.ts`: atomic preset application.
- `tests/Feature/TableTabsTest.php`: scoped counts, search/other filters, default-filter reset, empty collection, optional configuration.
- Billing `Customers` and `Invoices` tables use this API for status tabs and quick type/currency selectors.

This repository contains the table as a Git submodule. Commit changes inside that module and then update the parent pointer when packaging the work.
