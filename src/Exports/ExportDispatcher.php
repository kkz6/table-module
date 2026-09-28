<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Illuminate\Support\Facades\Bus;
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
        $configuration = app(ExportConfiguration::class)->resolve($export, $request);
        $exporterClass = $export->getExporterClass();
        $columnMap     = $configuration['columnMap'];
        $formats       = $configuration['formats'];
        $options       = $configuration['options'];
        $totalRows     = $configuration['totalRows'];

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
     * Build and dispatch the job chain.
     *
     * @param array<string, string>    $columnMap
     * @param array<string, mixed>     $options
     * @param array<int, ExportFormat> $formats
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
            $hasCsv ? new CreateCsvFileJob($tableExport) : null,
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
