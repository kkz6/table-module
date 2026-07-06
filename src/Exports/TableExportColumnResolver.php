<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Illuminate\Database\Eloquent\Model;
use Modules\Table\Columns\Column;
use Modules\Table\Table;

class TableExportColumnResolver
{
    /**
     * Resolve the ExportColumns for a table: its exportable columns first
     * (reusing each column's own display/export transform), then the table's
     * additional export-only columns. This is the order shown in the modal.
     *
     * @return array<int, ExportColumn>
     */
    public static function resolve(Table $table): array
    {
        $columns = array_map(
            fn (Column $column): ExportColumn => self::toExportColumn($column, $table),
            array_filter(
                $table->buildColumns(),
                fn (Column $column): bool => $column->shouldBeExported(),
            ),
        );

        return [...$columns, ...$table->additionalExportColumns()];
    }

    /**
     * Adapt a table Column into an ExportColumn, delegating value resolution and
     * formatting back to the column so mapAs()/exportAs()/date and enum handling
     * behave identically to the table.
     */
    private static function toExportColumn(Column $column, Table $table): ExportColumn
    {
        return ExportColumn::make($column->getAttribute())
            ->label($column->getHeader())
            ->enabledByDefault($column->isExportEnabledByDefault())
            ->state(fn (Model $record): mixed => $column->getDataFromItem($record))
            ->formatStateUsing(fn (mixed $state, Model $record): mixed => $column->mapForExport($state, $table, $record));
    }
}
