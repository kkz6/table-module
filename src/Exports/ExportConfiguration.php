<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Table\Export;
use Modules\Table\Http\ExportRequest;

class ExportConfiguration
{
    /**
     * Resolve all request-dependent values needed by a table export.
     *
     * @return array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }
     */
    public function resolve(Export $export, ExportRequest $request): array
    {
        $options = array_merge($export->getExportOptions(), $request->input('options', []) ?? []);
        $columnMap = $this->resolveColumnMap($export, $request);
        $formats = collect(array_map(ExportFormat::from(...), $request->input('formats')))
            ->unique()
            ->values()
            ->all();
        $query = $export->buildExporterQuery($options);

        $totalRows = $query->toBase()->getCountForPagination();

        if (($limit = $query->getQuery()->limit) !== null) {
            $totalRows = min($totalRows, $limit);
        }

        if (($maxRows = $export->getMaxRows()) !== null && $maxRows < $totalRows) {
            throw ValidationException::withMessages([
                'maxRows' => __('table::table.export_max_rows_message', [
                    'max' => number_format($maxRows),
                    'count' => number_format($totalRows),
                ]),
            ]);
        }

        return [
            'columnMap' => $columnMap,
            'formats' => $formats,
            'options' => $options,
            'query' => $query,
            'totalRows' => $totalRows,
            'chunkSize' => $export->getChunkSize(),
        ];
    }

    /**
     * Resolve the active column map from the request or exporter defaults.
     *
     * @return array<string, string>
     */
    private function resolveColumnMap(Export $export, ExportRequest $request): array
    {
        $columns = collect($export->getExportColumns())
            ->keyBy(fn (ExportColumn $column): string => $column->getName());

        $columnMap = $export->hasColumnMapping()
            ? collect($request->input('columnMap', []))
                ->only($columns->keys())
                ->filter(fn (array $column): bool => (bool) ($column['isEnabled'] ?? false))
                ->map(fn (array $column, string $name): string => filled($column['label'] ?? null)
                    ? (string) $column['label']
                    : $columns[$name]->getLabel())
            : $columns
                ->filter(fn (ExportColumn $column): bool => $column->isEnabledByDefault())
                ->map(fn (ExportColumn $column): string => $column->getLabel());

        if ($columnMap->isEmpty()) {
            throw ValidationException::withMessages([
                'columnMap' => __('table::table.export_no_columns_message'),
            ]);
        }

        return $columnMap->all();
    }
}
