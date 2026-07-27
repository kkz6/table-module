<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Table\Models\TableExport;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

abstract class Exporter
{
    /** @var class-string<Model>|null */
    protected static ?string $model = null;

    protected ?Model $record = null;

    /** @var array<string, ExportColumn>|null */
    protected ?array $cachedColumns = null;

    public function __construct(
        protected TableExport $export,
        protected array $columnMap,
        protected array $options,
    ) {}

    /**
     * Return all available ExportColumns for this exporter.
     *
     * @return array<int, ExportColumn>
     */
    abstract public static function getColumns(): array;

    /**
     * Return the fully-qualified model class for this exporter.
     * Falls back to deriving the class name from the exporter class name.
     *
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        return static::$model ?? (string) str(class_basename(static::class))
            ->beforeLast('Exporter')
            ->prepend('App\\Models\\');
    }

    /**
     * Modify the base query before chunking records.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Return the form components for the options form shown before exporting.
     *
     * @return array<int, mixed>
     */
    public static function getOptionsFormComponents(): array
    {
        return [];
    }

    /**
     * Return the supported export formats.
     *
     * ⚠️ XLSX assembly loads the entire workbook into memory (~1 KB/cell).
     * Override this method to return only {@see ExportFormat::Csv} for exporters
     * that target very large tables, or configure {@see Export::maxRows()}.
     *
     * @return array<int, ExportFormat>
     */
    public static function getFormats(): array
    {
        return [ExportFormat::Csv, ExportFormat::Xlsx];
    }

    /**
     * Return the CSV column delimiter.
     */
    public static function getCsvDelimiter(): string
    {
        return ',';
    }

    /**
     * Return a human-friendly, pluralized label for the exported model
     * (e.g. "Reimbursements", "Debit Notes"). Override for a custom name.
     */
    public static function getModelLabel(): string
    {
        return (string) str(class_basename(static::getModel()))->headline()->plural();
    }

    /**
     * Return the body of the completed-export toast notification, including
     * an optional failed-rows suffix when some rows could not be exported.
     */
    public static function getCompletedToastBody(TableExport $export): string
    {
        $body = trans_choice('table::table.export_completed_toast_body', $export->successful_rows, [
            'count' => number_format($export->successful_rows),
        ]);

        if (($failed = $export->getFailedRowsCount()) > 0) {
            $body .= ' '.trans_choice('table::table.export_failed_rows_toast_body', $failed, [
                'count' => number_format($failed),
            ]);
        }

        return $body;
    }

    /**
     * Return the filesystem disk used to store the export file.
     */
    public function getFileDisk(): string
    {
        return 'local';
    }

    /**
     * Return the file name for the export (without extension).
     */
    public function getFileName(TableExport $export): string
    {
        return __('table::table.export_file_name', [
            'export_id' => $export->getKey(),
            'model'     => (string) str(class_basename(static::getModel()))->kebab()->plural(),
        ]);
    }

    /**
     * Return the currently-processing Model record.
     */
    public function getRecord(): ?Model
    {
        return $this->record;
    }

    /**
     * Return the resolved options for this export run.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Return the underlying TableExport model.
     */
    public function getExport(): TableExport
    {
        return $this->export;
    }

    /**
     * Return the queue name for the export job, or null to use the default.
     */
    public function getJobQueue(): ?string
    {
        return null;
    }

    /**
     * Return the queue connection for the export job, or null to use the default.
     */
    public function getJobConnection(): ?string
    {
        return null;
    }

    /**
     * Return the batch name for the export job, or null if not batched.
     */
    public function getJobBatchName(): ?string
    {
        return null;
    }

    /**
     * Return the middleware stack for the export job. Empty by default: chunk
     * jobs write distinct part files and update counters transactionally, so no
     * cross-job locking is required (a shared WithoutOverlapping lock would make
     * sibling chunk jobs of the same export busy-loop re-queueing).
     *
     * @return array<int, mixed>
     */
    public function getJobMiddleware(): array
    {
        return [];
    }

    /**
     * Return the timestamp after which the export job should not be retried.
     */
    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addDay();
    }

    /**
     * Return the backoff seconds between job retry attempts.
     *
     * @return int|array<int, int>|null
     */
    public function getJobBackoff(): int|array|null
    {
        return [60, 120, 300, 600];
    }

    /**
     * Return the tags for the export job.
     *
     * @return array<int, string>
     */
    public function getJobTags(): array
    {
        return ['table-export'.$this->export->getKey()];
    }

    /**
     * Return the XLSX header row style, or null to use no styling.
     *
     * @return array<string, mixed>|null
     */
    public function getXlsxHeaderStyle(): ?array
    {
        return ['font' => ['bold' => true]];
    }

    /**
     * Return the XLSX data cell style, or null to use no styling.
     *
     * @return array<string, mixed>|null
     */
    public function getXlsxCellStyle(): ?array
    {
        return null;
    }

    /**
     * Configure the XLSX worksheet after it is created.
     */
    public function configureXlsxSheet(Worksheet $sheet): void {}

    /**
     * Resolve the ExportColumns for this exporter instance. Defaults to the
     * static definition; table-backed exporters override this to resolve from
     * the live Table so no per-resource exporter class is required.
     *
     * @return array<int, ExportColumn>
     */
    protected function resolveColumns(): array
    {
        return static::getColumns();
    }

    /**
     * Return columns keyed by name, injecting this exporter instance into each.
     * The result is memoized for the lifetime of this exporter instance.
     *
     * @return array<string, ExportColumn>
     */
    public function getCachedColumns(): array
    {
        return $this->cachedColumns ??= collect($this->resolveColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->exporter($this)])
            ->all();
    }

    /**
     * Set the current record and return the formatted values for the active column map, in order.
     *
     * @return array<int, string|null>
     */
    public function __invoke(Model $record): array
    {
        $this->record = $record;

        $columns = $this->getCachedColumns();

        // Fail loudly rather than writing a blank cell that still counts as a
        // successful row: a selected column that no longer resolves (e.g. an
        // auth-scoped table column the owner can no longer see) is a real error.
        return array_map(
            fn (string $name): ?string => ($columns[$name]
                ?? throw new RuntimeException("Export column [{$name}] could not be resolved for the current export."))
                ->getFormattedState(),
            array_keys($this->columnMap),
        );
    }
}
