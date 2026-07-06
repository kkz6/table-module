<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Modules\Table\Export;
use Modules\Table\Exports\Jobs\CompleteExportJob;
use Modules\Table\Exports\Jobs\CreateCsvFileJob;
use Modules\Table\Exports\Jobs\CreateXlsxFileJob;
use Modules\Table\Exports\Jobs\PrepareExportJob;
use Modules\Table\Http\ExportRequest;
use Modules\Table\Models\TableExport;

class ExportDispatcher
{
    /**
     * Dispatch the pipeline export chain and return the created export with row count.
     *
     * @return array{export: TableExport, totalRows: int}
     */
    public function dispatch(Export $export, ExportRequest $request): array
    {
        $exporterClass = $export->getExporterClass();
        $options       = array_merge($export->getExportOptions(), $request->input('options', []) ?? []);
        $columnMap     = $this->resolveColumnMap($export, $request);
        $formats       = array_map(ExportFormat::from(...), $request->input('formats'));

        $query     = $export->buildExporterQuery($options);
        $totalRows = $query->toBase()->getCountForPagination();

        if (($limit = $query->getQuery()->limit) !== null) {
            $totalRows = min($totalRows, $limit);
        }

        if (($maxRows = $export->getMaxRows()) !== null && $maxRows < $totalRows) {
            throw ValidationException::withMessages([
                'maxRows' => __('table::table.export_max_rows_message', [
                    'max'   => number_format($maxRows),
                    'count' => number_format($totalRows),
                ]),
            ]);
        }

        $tableExport = new TableExport([
            'exporter'   => $exporterClass,
            'total_rows' => $totalRows,
        ]);
        $tableExport->user()->associate($request->user());

        $exporterInstance = $tableExport->getExporter($columnMap, $options, $export->getTable());

        $tableExport->file_disk = $export->getFileDiskName() ?? $exporterInstance->getFileDisk();
        $tableExport->save();
        $tableExport->deleteFileDirectory();
        $tableExport->file_name = $export->getPipelineFileName($tableExport) ?? $exporterInstance->getFileName($tableExport);
        $tableExport->save();
        $tableExport->unsetRelation('user');

        $this->dispatchChain($export, $tableExport, $exporterInstance, $columnMap, $options, $formats);

        return ['export' => $tableExport, 'totalRows' => $totalRows];
    }

    /**
     * Resolve the active column map from the request or exporter defaults.
     *
     * @return array<string, string>
     */
    protected function resolveColumnMap(Export $export, ExportRequest $request): array
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

    /**
     * Build and dispatch the job chain.
     *
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>   $options
     * @param  array<int, ExportFormat>  $formats
     */
    protected function dispatchChain(
        Export $export,
        TableExport $tableExport,
        Exporter $exporter,
        array $columnMap,
        array $options,
        array $formats,
    ): void {
        $hasCsv  = in_array(ExportFormat::Csv, $formats, true);
        $hasXlsx = in_array(ExportFormat::Xlsx, $formats, true);

        $queue      = $exporter->getJobQueue();
        $connection = $exporter->getJobConnection();

        $batch = Bus::batch([new PrepareExportJob(
            $tableExport,
            $export->getTable(),
            $export->getIndex(),
            $columnMap,
            $options,
            $export->getChunkSize(),
        )])->allowFailures();

        if (($batchName = $exporter->getJobBatchName()) !== null) {
            $batch->name($batchName);
        }

        if ($queue !== null) {
            $batch->onQueue($queue);
        }

        if ($connection !== null) {
            $batch->onConnection($connection);
        }

        $chain = Bus::chain(array_filter([
            $batch,
            $hasCsv  ? new CreateCsvFileJob($tableExport) : null,
            $hasXlsx ? new CreateXlsxFileJob($tableExport, $columnMap, $options) : null,
            new CompleteExportJob($tableExport, $formats, $export->getResourceLabel()),
        ]));

        if ($queue !== null) {
            $chain->onQueue($queue);
        }

        if ($connection !== null) {
            $chain->onConnection($connection);
        }

        $chain->dispatch();
    }
}
