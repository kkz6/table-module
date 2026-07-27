# Table Exports

The table module ships a Filament-style **streamed export** pipeline. A user picks
columns (and optionally reorders/relabels them) and a format in a modal, the export
is prepared while the modal shows an exporting state, and the browser downloads the
file as soon as it is ready.

The original queued pipeline remains available for legacy `Export::make(...)`
definitions that do not use a pipeline exporter.

There are two ways to define an export:

1. **Table-backed (recommended)** — the export reuses the table's own columns, so you
   define everything inline in the `Table` class and never write an exporter class.
2. **Custom exporter class** — for exports whose columns/behaviour differ from the table.

---

## 1. Quick start (table-backed)

Add an `export()` method to your `Table`. Singular table exports are table-backed
by default, including definitions that still pass legacy filename or Excel event
arguments:

```php
use Modules\Table\Export;
use Modules\Table\Exports\ExportFormat;

public function export(): Export
{
    return Export::make(label: __('finance::reimbursements.export_label'))
        ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
        ->limitToFilteredRows()
        ->limitToSelectedRows();
}
```

That's it. The export modal lists every table column that is exportable (see
[`exportable(false)`](#column-level-configuration)), in table order, and the exported file
reuses each column's label and value transform.

Tables that need more than one export can continue to override the legacy
`exports(): array` method.

Call `->tableExporter()` explicitly when constructing an export outside the table
normalization flow. Tables that need multiple exports can keep using
`exports(): array`; those legacy entries use the streamed exporter only when they
use the default configuration, while custom legacy entries retain their existing
behavior.

---

## 2. The `Export` builder

`Export::make()` accepts named arguments and exposes a fluent API. Only `label` is
required in practice; everything else has a sensible default.

### Core

| Method / argument | Type | What it does |
|---|---|---|
| `Export::make(label:)` | `string` | The label shown in the actions dropdown. |
| `->dynamicExport()` | `true` | Use the dynamic table export with column and format selection. |
| `->dynamicExport(false)` | — | Force this export to use the simple legacy exporter. |
| `->tableExporter()` | — | Resolve columns from the table (no exporter class needed). |
| `->exporter(Foo::class)` | `class-string` | Use a [custom exporter class](#6-custom-exporter-class) instead. |
| `->formats([...])` | `ExportFormat[]` \| `Closure` | Allowed formats. Defaults to the exporter's formats (CSV + XLSX). |
| `->fileName(...)` | `string` \| `Closure(TableExport): string` | The downloaded file name (without extension). |
| `->fileDisk('s3')` | `string` | Filesystem disk the export file is stored on. Defaults to `local`. |
| `->resourceLabel(...)` | `string` \| `Closure` | Pluralized resource name for the modal title and toasts. **Pass a translated string** to localize it; defaults to the model's (English) class name. |

```php
Export::make(label: __('finance::reimbursements.export_label'))
    ->tableExporter()
    ->resourceLabel(__('finance::reimbursements.export_resource_label'));   // localized "Reimbursements"
```

```php
Export::make(label: 'Export')
    ->tableExporter()
    ->formats([ExportFormat::Csv])                                   // CSV only
    ->fileName(fn (TableExport $export): string =>                  // dynamic name
        'reimbursements-'.$export->created_at->format('Y-m-d'))
    ->fileDisk('local');
```

### Scoping which rows are exported

| Method | Default | What it does |
|---|---|---|
| `->limitToFilteredRows()` | `false` | Apply the table's active filters/search to the export. |
| `->limitToSelectedRows()` | `false` | Export only the rows the user checked (falls back to all when none are selected). |
| `->modifyQueryUsing($closure)` | — | Further modify the export query: `fn (Builder $query, array $options) => $query->where(...)`. |
| `->maxRows(50_000)` | `null` | Reject the export (validation error) when it would exceed this many rows. |

```php
Export::make(label: 'Export')
    ->tableExporter()
    ->limitToFilteredRows()
    ->limitToSelectedRows()
    ->maxRows(100_000)
    ->modifyQueryUsing(fn (Builder $query) => $query->where('archived', false));
```

### The column-mapping modal

| Method | Default | What it does |
|---|---|---|
| `->columnMapping(false)` | `true` | Hide the column picker and export every default-enabled column as-is. |
| `->enableVisibleTableColumnsByDefault()` | `false` | Pre-check only the columns currently visible in the table. |

### Performance / streaming

| Method | Default | What it does |
|---|---|---|
| `->chunkSize(500)` | `100` | Rows read per keyset-pagination chunk. Also accepts a `Closure(): int`. |
| `->options(['foo' => 'bar'])` | `[]` | Fixed values passed through to `modifyQueryUsing` and column closures via the `$options` array. |

> **Memory note:** XLSX assembly loads the whole workbook into memory (~1 KB/cell). For
> very large tables prefer `->formats([ExportFormat::Csv])` or a low `->maxRows()`.

---

## 3. Column-level configuration

Table columns are exported using their own display transform by default, so you rarely
need to configure anything. When you do, these methods live on every `Column`:

| Method | What it does |
|---|---|
| `->exportable(false)` | Exclude the column from exports entirely (e.g. an `id` or an action column). |
| `->includeInExportByDefault(false)` | Keep the column available in the modal but unchecked by default. |
| `->exportAs($closure)` | Use a different value **for export** than for display. |
| `->mapAs($closure)` | Display transform — also used for export when `exportAs()` is not set. |

```php
public function columns(): array
{
    return [
        // Never exported.
        TextColumn::make('id')->exportable(false),

        // Available in the modal, but unchecked unless the user opts in.
        TextColumn::make('description')->includeInExportByDefault(false),

        // Different formatting for the sheet than for the on-screen table:
        // table shows "1,200.00 (JPY)"; the export writes "1,200.00 JPY".
        TextColumn::make('amount')
            ->mapAs(fn (float $amount, Reimbursement $r) => number_format($amount, 2).' ('.$r->currency.')')
            ->exportAs(fn (float $amount, Reimbursement $r) => number_format($amount, 2).' '.$r->currency),

        // Enum/badge columns: the export writes the label, not the badge payload.
        BadgeColumn::make('status')->mapAs(fn (Status $s) => $s->label()),
    ];
}
```

The export transform closure receives `(mixed $value, Model $record, Table $table)`.

---

## 4. Additional export columns

To include data that is **not** a table column (aggregates, computed fields), override
`additionalExportColumns()` on the `Table`. These appear in the modal **after** the
table columns, in the order returned.

```php
use Modules\Table\Exports\ExportColumn;

public function additionalExportColumns(): array
{
    return [
        ExportColumn::make('items_count')
            ->label(__('finance::reimbursements.table.items_count'))
            ->counts('items')            // adds withCount('items') to the query
            ->enabledByDefault(false),
    ];
}
```

### `ExportColumn` API

`ExportColumn::make($name)` resolves `$name` from the record via dot-notation
(`employee.name`, `items_count`, …). It supports:

| Method | What it does |
|---|---|
| `->label('Header')` | Column header. Defaults to a title-cased name. Accepts a `Closure`. |
| `->enabledByDefault(false)` | Uncheck this column by default in the modal. |
| `->state($closure)` | Custom value resolver: `fn (Model $record, ...) => ...`. |
| `->default($value)` | Value to use when the resolved state is blank. |
| `->formatStateUsing($closure)` | Transform the resolved value: `fn (mixed $state, Model $record) => ...`. |
| `->prefix('$')` / `->suffix(' kg')` | Wrap the formatted value. Accept `Closure`s. |
| `->limit(50)` / `->words(10)` | Truncate by characters / words. |
| `->separator(',')` | Split a string state into a list. |
| `->distinctList()` | De-duplicate a list state. |
| `->listAsJson()` | Render a list state as JSON instead of a comma-joined string. |

### Relationship aggregates

Each of these adds the matching `with*` aggregate to the query and exposes the result
under the column name (e.g. `items_count`, `items_exists`, `payments_sum_amount`):

```php
ExportColumn::make('items_count')->counts('items');
ExportColumn::make('has_receipt')->exists('receipt');
ExportColumn::make('payments_sum_amount')->sum('payments', 'amount');
ExportColumn::make('items_avg_price')->avg('items', 'price');
ExportColumn::make('items_min_price')->min('items', 'price');
ExportColumn::make('items_max_price')->max('items', 'price');
```

---

## 5. Formats

`ExportFormat` is a string enum with `Csv` and `Xlsx`. CSV files are UTF-8 with a BOM
(Excel-friendly); XLSX files are auto-sized, centered, and get sortable header filters.

```php
use Modules\Table\Exports\ExportFormat;

->formats([ExportFormat::Csv, ExportFormat::Xlsx])   // both (default)
->formats([ExportFormat::Csv])                        // CSV only
->formats(fn () => $user->isAdmin()                   // dynamic
    ? [ExportFormat::Csv, ExportFormat::Xlsx]
    : [ExportFormat::Csv]);
```

---

## 6. Custom exporter class

When an export needs columns or behaviour that don't map to the table, generate an
exporter and attach it with `->exporter(...)` instead of `->tableExporter()`:

```bash
php artisan make:table-exporter ReimbursementExporter
```

```php
class ReimbursementExporter extends Exporter
{
    protected static ?string $model = Reimbursement::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('title'),
            ExportColumn::make('amount')
                ->formatStateUsing(fn ($state, Reimbursement $r) => number_format($state, 2).' '.$r->currency),
        ];
    }
}
```

```php
Export::make(label: 'Export')->exporter(ReimbursementExporter::class);
```

The base `Exporter` exposes overridable hooks — the useful ones:

| Hook | Purpose |
|---|---|
| `getColumns()` | The export columns (required). |
| `getFormats()` | Allowed formats. |
| `getCsvDelimiter()` | CSV delimiter (default `,`). |
| `getModelLabel()` | Pluralized name used in toasts (“Reimbursements”). |
| `getFileName($export)` | File name (without extension). |
| `getFileDisk()` | Storage disk. |
| `getOptionsFormComponents()` | User-configurable [options form](#options-form). |
| `getXlsxHeaderStyle()` / `getXlsxCellStyle()` | XLSX styling arrays. |
| `configureXlsxSheet($sheet)` | Post-process the PhpSpreadsheet worksheet. |
| `modifyQuery($query)` | Modify the base query (static). |
| `getJobQueue()` / `getJobConnection()` / `getJobMiddleware()` / `getJobBackoff()` / `getJobRetryUntil()` | Queue/job tuning. |

### Options form

Return option fields from `getOptionsFormComponents()` to render inputs in the modal.
Their values arrive in the `$options` array (available in `modifyQuery`, `formatStateUsing`, etc.).

```php
use Modules\Table\Exports\Options\{CheckboxOption, SelectOption, TextOption};

public static function getOptionsFormComponents(): array
{
    return [
        TextOption::make('note')->label('Note')->default(''),
        SelectOption::make('currency')->label('Currency')->options(['JPY' => 'JPY', 'USD' => 'USD'])->default('JPY'),
        CheckboxOption::make('include_drafts')->label('Include drafts')->default(false),
    ];
}
```

---

## 7. How it works

- **Streamed pipeline:** `StreamingExportController` resolves the signed request,
  applies the selected columns and query options, then reads records with keyset
  pagination (`lazyById`). CSV is written directly to the response. XLSX is built in
  a temporary file and streamed when complete. Selecting both formats returns one ZIP
  containing both files.
- **Exporting state:** the modal remains open and locked while the request is active;
  successful responses trigger a browser download and close the modal.
- **Legacy compatibility:** entries returned from the legacy `exports(): array`
  method keep their existing direct, custom, or queued behavior unless they use the
  default configuration. Singular `export(): Export` entries are table-backed by
  default.
- **Auth scoping:** the signed request resolves the table and exporter using the
  current authenticated user, so authorization and auth-dependent columns are applied
  at export time.
- **Row order:** rows are read by primary key rather than the table's `defaultSort` or
  a custom `orderBy`. Sort in the spreadsheet (XLSX headers carry sort/filter dropdowns).

---

## 8. File retention

Export files accumulate on disk. A scheduled command prunes old ones (and their
records):

```bash
php artisan table:prune-exports          # delete exports older than 15 days (default)
php artisan table:prune-exports --days=30
```

It is registered in `bootstrap/app.php` to run daily. Adjust the window there.
