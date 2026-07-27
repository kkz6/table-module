<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Generator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Table\Export;
use Modules\Table\Http\ExportRequest;
use Modules\Table\Models\TableExport;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class StreamingExport
{
    public function __construct(
        private readonly ExportConfiguration $configuration,
        private readonly XlsxFileBuilder $xlsxFileBuilder,
    ) {}

    public function stream(Export $export, ExportRequest $request): Response
    {
        $configuration = $this->configuration->resolve($export, $request);
        $formats = $configuration['formats'];
        $tableExport = $this->makeExportState($export, $request, $configuration['totalRows']);
        $exporter = $tableExport->getExporter($configuration['columnMap'], $configuration['options'], $export->getTable());

        $this->applyColumnLoading($configuration['query'], $exporter, $configuration['columnMap']);

        $fileName = $this->resolveFileName($export, $exporter, $tableExport);

        if (count($formats) === 1) {
            return $formats[0] === ExportFormat::Csv
                ? $this->streamCsv($fileName, $configuration, $exporter)
                : $this->streamXlsx($fileName, $configuration, $exporter);
        }

        return $this->streamArchive($fileName, $formats, $configuration, $exporter);
    }

    /**
     * @param  array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }  $configuration
     */
    private function streamCsv(string $fileName, array $configuration, Exporter $exporter): StreamedResponse
    {
        return response()->streamDownload(function () use ($configuration, $exporter): void {
            $output = fopen('php://output', 'wb');

            if (! is_resource($output)) {
                throw new RuntimeException('Unable to open the export output stream.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($configuration['columnMap']), $exporter::getCsvDelimiter());

                foreach ($this->rows($configuration['query'], $exporter, $configuration['totalRows'], $configuration['chunkSize']) as $row) {
                    fputcsv($output, $row, $exporter::getCsvDelimiter());
                    flush();
                }
            } finally {
                fclose($output);
            }
        }, $fileName.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }  $configuration
     */
    private function streamXlsx(string $fileName, array $configuration, Exporter $exporter): StreamedResponse
    {
        $temporaryFile = $this->writeXlsx($configuration, $exporter);

        return response()->streamDownload(function () use ($temporaryFile): void {
            try {
                readfile($temporaryFile);
            } finally {
                $this->deleteTemporaryFile($temporaryFile);
            }
        }, $fileName.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, ExportFormat>  $formats
     * @param  array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }  $configuration
     */
    private function streamArchive(string $fileName, array $formats, array $configuration, Exporter $exporter): StreamedResponse
    {
        $temporaryFiles = [];

        try {
            foreach ($formats as $format) {
                $temporaryFiles[$format->value] = $format === ExportFormat::Csv
                    ? $this->writeCsv($configuration, $exporter)
                    : $this->writeXlsx($configuration, $exporter);
            }
        } catch (Throwable $exception) {
            $this->deleteTemporaryFiles($temporaryFiles);

            throw $exception;
        }

        $archivePath = (string) tempnam(sys_get_temp_dir(), 'table-export-');
        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            $this->deleteTemporaryFile($archivePath);
            $this->deleteTemporaryFiles($temporaryFiles);

            throw new RuntimeException('Unable to create the export archive.');
        }

        foreach ($temporaryFiles as $format => $temporaryFile) {
            $archive->addFile($temporaryFile, $fileName.'.'.$format);
        }

        $archive->close();

        return response()->streamDownload(function () use ($archivePath, $temporaryFiles): void {
            try {
                readfile($archivePath);
            } finally {
                $this->deleteTemporaryFile($archivePath);
                $this->deleteTemporaryFiles($temporaryFiles);
            }
        }, $fileName.'.zip', ['Content-Type' => 'application/zip']);
    }

    /**
     * @param  array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }  $configuration
     */
    private function writeCsv(array $configuration, Exporter $exporter): string
    {
        $temporaryFile = (string) tempnam(sys_get_temp_dir(), 'table-export-');
        $output = fopen($temporaryFile, 'wb');

        if (! is_resource($output)) {
            throw new RuntimeException('Unable to create the CSV export.');
        }

        try {
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($configuration['columnMap']), $exporter::getCsvDelimiter());

            foreach ($this->rows($configuration['query'], $exporter, $configuration['totalRows'], $configuration['chunkSize']) as $row) {
                fputcsv($output, $row, $exporter::getCsvDelimiter());
            }
        } catch (Throwable $exception) {
            fclose($output);
            $this->deleteTemporaryFile($temporaryFile);

            throw $exception;
        }

        fclose($output);

        return $temporaryFile;
    }

    /**
     * @param  array{
     *     columnMap: array<string, string>,
     *     formats: array<int, ExportFormat>,
     *     options: array<string, mixed>,
     *     query: Builder,
     *     totalRows: int,
     *     chunkSize: int,
     * }  $configuration
     */
    private function writeXlsx(array $configuration, Exporter $exporter): string
    {
        $temporaryFile = (string) tempnam(sys_get_temp_dir(), 'table-export-');
        $rows = (function () use ($configuration, $exporter): Generator {
            yield array_values($configuration['columnMap']);

            yield from $this->rows($configuration['query'], $exporter, $configuration['totalRows'], $configuration['chunkSize']);
        })();

        try {
            $this->xlsxFileBuilder->writeRows($rows, $exporter, $temporaryFile);
        } catch (Throwable $exception) {
            $this->deleteTemporaryFile($temporaryFile);

            throw $exception;
        }

        return $temporaryFile;
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    private function rows(Builder $query, Exporter $exporter, int $rowLimit, int $chunkSize = 100): Generator
    {
        $model = $query->getModel();
        $keyName = $model->getKeyName();
        $qualifiedKey = $model->getQualifiedKeyName();
        $remaining = $rowLimit;

        foreach ($query->clone()->reorder()->lazyById($chunkSize, $qualifiedKey, $keyName) as $record) {
            if ($remaining <= 0) {
                break;
            }

            try {
                yield $exporter($record);
            } catch (Throwable $exception) {
                report($exception);
            }

            $remaining--;
        }
    }

    private function makeExportState(Export $export, ExportRequest $request, int $totalRows): TableExport
    {
        $tableExport = new TableExport([
            'exporter' => $export->getExporterClass(),
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'file_disk' => 'local',
        ]);

        $tableExport->forceFill([
            'id' => random_int(1, PHP_INT_MAX),
            'created_at' => now(),
            'updated_at' => now(),
            'user_id' => $request->user()->getKey(),
        ]);

        return $tableExport;
    }

    private function resolveFileName(Export $export, Exporter $exporter, TableExport $tableExport): string
    {
        $fileName = $export->getPipelineFileName($tableExport) ?? $exporter->getFileName($tableExport);
        $fileName = pathinfo($fileName, PATHINFO_FILENAME);

        $fileName = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName), '-');

        return $fileName !== '' ? $fileName : 'export';
    }

    private function applyColumnLoading(Builder $query, Exporter $exporter, array $columnMap): void
    {
        $columns = $exporter->getCachedColumns();

        foreach (array_keys($columnMap) as $name) {
            $column = $columns[$name] ?? throw new RuntimeException("Export column [{$name}] could not be resolved.");
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }
    }

    /**
     * @param  array<string, string>  $temporaryFiles
     */
    private function deleteTemporaryFiles(array $temporaryFiles): void
    {
        foreach ($temporaryFiles as $temporaryFile) {
            $this->deleteTemporaryFile($temporaryFile);
        }
    }

    private function deleteTemporaryFile(string $temporaryFile): void
    {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}
