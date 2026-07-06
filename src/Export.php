<?php

declare(strict_types=1);

namespace Modules\Table;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Excel;
use Modules\Table\Exports\ExportColumn;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Exports\Exporter as PipelineExporter;
use Modules\Table\Exports\Options\OptionField;
use Modules\Table\Exports\TableExportColumnResolver;
use Modules\Table\Exports\TableExporter;
use Modules\Table\Models\TableExport;
use Modules\Table\Traits\BelongsToTable;
use Modules\Table\Traits\GeneratesSignedTableUrls;
use Modules\Table\Traits\HandlesAuthorization;
use Modules\Table\Traits\HasDataAttributes;
use Modules\Table\Traits\HasMeta;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Export implements Arrayable
{
    use BelongsToTable;
    use GeneratesSignedTableUrls;
    use HandlesAuthorization;
    use HasDataAttributes;
    use HasMeta;

    /**
     * The index of the export.
     */
    protected int $index;

    /**
     * The default setting for limiting the export to the filtered rows.
     */
    protected static bool $defaultLimitToFilteredRows = false;

    /**
     * The default setting for limiting the export to the selected rows.
     */
    protected static bool $defaultLimitToSelectedRows = false;

    /**
     * The default queue name.
     */
    protected static ?string $defaultQueueName = null;

    /**
     * The default queue disk.
     */
    protected static ?string $defaultQueueDisk = null;

    /**
     * The pipeline exporter class.
     *
     * @var class-string<PipelineExporter>|null
     */
    protected ?string $exporterClass = null;

    /**
     * An explicit, translatable label for the exported resource (modal title/toasts).
     *
     * @var string|Closure|null
     */
    protected string|Closure|null $resourceLabel = null;

    /**
     * Whether the export should expose a column mapping UI.
     */
    protected bool $columnMapping = true;

    /**
     * The allowed export formats, or a Closure that returns them.
     *
     * @var array<int, ExportFormat>|Closure|null
     */
    protected array|Closure|null $formats = null;

    /**
     * The maximum number of rows to export.
     */
    protected ?int $maxRows = null;

    /**
     * The number of rows per export chunk (or a Closure that returns it).
     *
     * @var int|Closure
     */
    protected int|Closure $chunkSize = 100;

    /**
     * The file disk name for pipeline exports.
     */
    protected ?string $fileDiskName = null;

    /**
     * The pipeline file name (string or Closure receiving TableExport).
     *
     * @var string|Closure|null
     */
    protected string|Closure|null $pipelineFileName = null;

    /**
     * Options to pass through to the pipeline export run.
     *
     * @var array<string, mixed>
     */
    protected array $exportOptions = [];

    /**
     * Additional query modifier applied after the exporter's modifyQuery.
     */
    protected ?Closure $modifyQueryUsing = null;

    /**
     * Whether visible table columns should be enabled by default in the column mapping.
     */
    protected bool $enableVisibleTableColumnsByDefault = false;

    /**
     * Cached pipeline metadata (columns/formats/optionsForm) — reused across multiple toArray() calls.
     *
     * @var array<string, mixed>|null
     */
    private ?array $cachedPipelineMeta = null;

    /**
     * @param (Closure(Table, Export, Request, Builder): mixed)|null $using
     * @param Closure(PendingDispatch): mixed|null                   $withQueuedJob
     */
    public function __construct(
        public string $label,
        public string $filename,
        public string $type,
        public bool|Closure $authorize,
        public array $events = [],
        public ?array $dataAttributes = null,
        public ?array $meta = null,
        public ?bool $limitToFilteredRows = null,
        public ?bool $limitToSelectedRows = null,
        public ?Closure $using = null,
        public bool $asDownload = true,
        public bool $queue = false,
        public ?string $queueName = null,
        public ?string $queueDisk = null,
        public ?string $dialogTitle = '',
        public ?string $dialogMessage = '',
        public ?Closure $withQueuedJob = null,
        public ?Closure $redirect = null,
    ) {}

    /**
     * Get the label of the Export.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the filename of the Export.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get the type of the Export.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the events of the Export.
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Get the index of the export.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Set the index of the export.
     */
    public function setIndex(int $index): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Set the default setting for limiting the export to the filtered rows.
     */
    public static function defaultLimitToFilteredRows(bool $value = true): void
    {
        static::$defaultLimitToFilteredRows = $value;
    }

    /**
     * Limit the export to the filtered rows.
     */
    public function limitToFilteredRows(bool $value = true): self
    {
        $this->limitToFilteredRows = $value;

        return $this;
    }

    /**
     * Should the export be limited to the filtered rows.
     */
    public function shouldLimitToFilteredRows(): bool
    {
        return $this->limitToFilteredRows ?? static::$defaultLimitToFilteredRows;
    }

    /**
     * Set the default setting for limiting the export to the filtered rows.
     */
    public static function defaultLimitToSelectedRows(bool $value = true): void
    {
        static::$defaultLimitToSelectedRows = $value;
    }

    /**
     * Limit the export to the selected rows.
     */
    public function limitToSelectedRows(bool $value = true): self
    {
        $this->limitToSelectedRows = $value;

        return $this;
    }

    /**
     * Should the export be limited to the selected rows.
     */
    public function shouldLimitToSelectedRows(): bool
    {
        return $this->limitToSelectedRows ?? static::$defaultLimitToSelectedRows;
    }

    /**
     * Indicate that the export should be downloaded in the browser.
     */
    public function asDownload(bool $value = true): self
    {
        $this->asDownload = $value;

        return $this;
    }

    /**
     * Indicate that the export should not be downloaded but show a confirmation dialog.
     */
    public function redirectBackWithDialog(
        string $title = 'Export',
        string $message = 'Your export is being processed.'
    ): self {
        $this->dialogTitle   = $title;
        $this->dialogMessage = $message;

        return $this->asDownload(false);
    }

    /**
     * Set the default queue name.
     */
    public static function defaultQueueName(BackedEnum|string|null $value): void
    {
        static::$defaultQueueName = $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Get the queue name.
     */
    public function getQueueName(): ?string
    {
        return $this->queueName ?? static::$defaultQueueName;
    }

    /**
     * Get the default queue disk.
     */
    public static function defaultQueueDisk(BackedEnum|string|null $value): void
    {
        static::$defaultQueueDisk = $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Get the queue disk.
     */
    public function getQueueDisk(): ?string
    {
        return $this->queueDisk ?? static::$defaultQueueDisk;
    }

    /**
     * Indicate that the export should be queued.
     *
     * @param (callable(PendingDispatch): mixed)|(Closure(PendingDispatch): mixed)|null $withQueuedJob
     */
    public function queue(
        ?string $filename = null,
        BackedEnum|string|null $disk = null,
        BackedEnum|string|null $queue = null,
        string $title = 'Export',
        string $message = 'Your export is being processed.',
        callable|Closure|null $withQueuedJob = null,
    ): self {
        $this->filename = $filename ?? $this->filename;

        $this->queue         = true;
        $this->queueName     = $queue instanceof BackedEnum ? $queue->value : $queue;
        $this->queueDisk     = $disk instanceof BackedEnum ? $disk->value : $disk;
        $this->dialogTitle   = $title;
        $this->dialogMessage = $message;

        return $withQueuedJob ? $this->withQueuedJob($withQueuedJob) : $this;
    }

    /**
     * Interact with the queued job.
     */
    public function withQueuedJob(callable|Closure $withQueuedJob): self
    {
        $this->queue = true;

        $this->withQueuedJob = Helpers::asClosure($withQueuedJob);

        return $this;
    }

    /**
     * Dispatch the export job.
     */
    public function dispatchJob(): array
    {
        $pendingDispatch = dispatch($job = new ExportJob(
            $this->getTable(),
            $this->getIndex(),
        ));

        if (($callback = $this->withQueuedJob) instanceof Closure) {
            $callback($pendingDispatch);
        }

        if (! $this->hasUsingCallback() && ! empty($job->chained)) {
            $job->afterBuiltInExporter = $job->chained;
            $job->chained              = [];
        }

        return [$job, $pendingDispatch];
    }

    /**
     * Create a new Export instance.
     *
     * @param (callable(PendingDispatch): mixed)|(Closure(PendingDispatch): mixed)|null                                 $withQueuedJob
     * @param (callable(Table, Export, Request, Builder): mixed)|(Closure(Table, Export, Request, Builder): mixed)|null $using
     */
    public static function make(
        string $label = 'Excel Export',
        string $filename = 'export.xlsx',
        string $type = Excel::XLSX,
        bool|Closure $authorize = true,
        array $events = [],
        ?array $dataAttributes = null,
        ?array $meta = null,
        ?bool $limitToFilteredRows = null,
        ?bool $limitToSelectedRows = null,
        Closure|callable|null $using = null,
        bool $asDownload = true,
        string $dialogTitle = 'Exporting',
        string $dialogMessage = 'Your export is being processed.',
        callable|Closure|string|null $redirect = null,
        bool $queue = false,
        BackedEnum|string|null $queueName = null,
        BackedEnum|string|null $queueDisk = null,
        callable|Closure|null $withQueuedJob = null,
    ): static {
        $redirect = is_string($redirect)
            ? fn () => redirect()->to($redirect)
            : Helpers::asClosure($redirect);

        $using         = Helpers::asClosure($using);
        $withQueuedJob = Helpers::asClosure($withQueuedJob);

        // @phpstan-ignore-next-line
        return new static(
            label: $label,
            filename: $filename,
            type: $type,
            authorize: $authorize,
            events: $events,
            dataAttributes: $dataAttributes,
            meta: $meta,
            limitToFilteredRows: $limitToFilteredRows,
            limitToSelectedRows: $limitToSelectedRows,
            using: $using,
            asDownload: $asDownload,
            queue: $queue,
            queueName: $queueName instanceof BackedEnum ? $queueName->value : $queueName,
            queueDisk: $queueDisk instanceof BackedEnum ? $queueDisk->value : $queueDisk,
            dialogTitle: $dialogTitle,
            dialogMessage: $dialogMessage,
            withQueuedJob: $withQueuedJob,
            redirect: $redirect,
        );
    }

    /**
     * Set the closure that should be used to export the data.
     *
     * @param (callable(Table, Export, Request, Builder): mixed)|(Closure(Table, Export, Request, Builder): mixed) $using
     */
    public function using(Closure|callable $using): self
    {
        $this->using = Helpers::asClosure($using);

        return $this;
    }

    /**
     * Set a custom redirect path for custom and queued exports.
     */
    public function redirect(Closure|RedirectResponse|callable|string $redirect): self
    {
        if (is_string($redirect)) {
            $redirect = fn () => redirect()->to($redirect);
        } elseif ($redirect instanceof RedirectResponse) {
            $redirect = fn (): RedirectResponse => $redirect;
        }

        $this->redirect = Helpers::asClosure($redirect);

        return $this;
    }

    /**
     * Helper method to set a custom redirect route for custom and queued exports.
     */
    public function redirectToRoute(
        BackedEnum|string $route,
        mixed $parameters = [],
        int $status = 302,
        array $headers = []
    ): self {
        return $this->redirect(to_route($route, $parameters, $status, $headers));
    }

    /**
     * Determine if this export has a 'using' callback.
     */
    public function hasUsingCallback(): bool
    {
        return $this->using instanceof Closure;
    }

    /*
     * Execute the using callback for this export.
     */
    public function executeUsingCallback(): mixed
    {
        return ($this->using)(
            $this->table,
            $this,
            $this->table->getTableRequest()->getIlluminateRequest(),
            $this->makeExporter()->query()
        );
    }

    /**
     * Set the pipeline exporter class.
     *
     * When set, this takes precedence over queue()/using() for route and payload generation.
     *
     * @param  class-string<PipelineExporter>  $exporterClass
     */
    public function exporter(string $exporterClass): self
    {
        $this->exporterClass = $exporterClass;

        return $this;
    }

    /**
     * Use the built-in table-backed exporter: columns are resolved from the
     * Table's own columns and additionalExportColumns(), so no exporter class
     * is needed.
     */
    public function tableExporter(): self
    {
        $this->exporterClass = TableExporter::class;

        return $this;
    }

    /**
     * Determine whether this export resolves its columns from the Table.
     */
    public function isTableBacked(): bool
    {
        return $this->exporterClass === TableExporter::class;
    }

    /**
     * Get the pipeline exporter class, or null when no exporter has been attached.
     * Always guard calls with {@see hasExporter()} before use.
     *
     * @return class-string<PipelineExporter>|null
     */
    public function getExporterClass(): ?string
    {
        return $this->exporterClass;
    }

    /**
     * Determine whether a pipeline exporter has been attached.
     */
    public function hasExporter(): bool
    {
        return $this->exporterClass !== null;
    }

    /**
     * Resolve the ExportColumns for this export — from the Table when
     * table-backed, otherwise from the dedicated exporter class.
     *
     * @return array<int, ExportColumn>
     */
    public function getExportColumns(): array
    {
        return $this->isTableBacked()
            ? TableExportColumnResolver::resolve($this->getTable())
            : $this->getExporterClass()::getColumns();
    }

    /**
     * Set the human-friendly, pluralized label for the exported resource used in
     * modal titles and toasts. Pass a translated string (or Closure) to localize it.
     */
    public function resourceLabel(string|Closure $label): self
    {
        $this->resourceLabel = $label;

        return $this;
    }

    /**
     * Resolve the human-friendly, pluralized label for the exported resource.
     * Falls back to the model's class name when no explicit label is set.
     */
    public function getResourceLabel(): string
    {
        if ($this->resourceLabel !== null) {
            return (string) ($this->resourceLabel instanceof Closure ? ($this->resourceLabel)() : $this->resourceLabel);
        }

        return $this->isTableBacked()
            ? (string) str(class_basename($this->getTable()->resourceBuilder()->getModel()))->headline()->plural()
            : $this->getExporterClass()::getModelLabel();
    }

    /**
     * Set whether the export should expose a column mapping UI.
     */
    public function columnMapping(bool $columnMapping = true): self
    {
        $this->columnMapping = $columnMapping;

        return $this;
    }

    /**
     * Determine whether column mapping is enabled.
     */
    public function hasColumnMapping(): bool
    {
        return $this->columnMapping;
    }

    /**
     * Set the allowed export formats.
     *
     * @param  array<int, ExportFormat>|Closure  $formats
     */
    public function formats(array|Closure $formats): self
    {
        $this->formats = $formats;

        return $this;
    }

    /**
     * Get the allowed export formats, falling back to the exporter's defaults.
     *
     * @return array<int, ExportFormat>
     */
    public function getFormats(): array
    {
        $formats = $this->formats instanceof Closure ? ($this->formats)() : $this->formats;

        return $formats ?? ($this->hasExporter() ? $this->getExporterClass()::getFormats() : []);
    }

    /**
     * Set the maximum number of rows to export.
     */
    public function maxRows(int $maxRows): self
    {
        $this->maxRows = $maxRows;

        return $this;
    }

    /**
     * Get the maximum number of rows to export.
     */
    public function getMaxRows(): ?int
    {
        return $this->maxRows;
    }

    /**
     * Set the number of rows per export chunk.
     *
     * @param  int|Closure():int  $chunkSize
     */
    public function chunkSize(int|Closure $chunkSize): self
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * Get the number of rows per export chunk.
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize instanceof Closure
            ? ($this->chunkSize)()
            : $this->chunkSize;
    }

    /**
     * Set the file disk name for pipeline exports.
     */
    public function fileDisk(string $disk): self
    {
        $this->fileDiskName = $disk;

        return $this;
    }

    /**
     * Get the file disk name for pipeline exports.
     */
    public function getFileDiskName(): ?string
    {
        return $this->fileDiskName;
    }

    /**
     * Set the pipeline file name (string passthrough, or Closure receiving TableExport).
     *
     * @param  string|Closure(TableExport): string  $fileName
     */
    public function fileName(string|Closure $fileName): self
    {
        $this->pipelineFileName = $fileName;

        return $this;
    }

    /**
     * Resolve the pipeline file name for the given export record.
     */
    public function getPipelineFileName(TableExport $export): ?string
    {
        if ($this->pipelineFileName instanceof Closure) {
            return ($this->pipelineFileName)($export);
        }

        return $this->pipelineFileName;
    }

    /**
     * Set additional options to pass through to the pipeline export run.
     *
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $this->exportOptions = $options;

        return $this;
    }

    /**
     * Get the additional options for the pipeline export run.
     *
     * @return array<string, mixed>
     */
    public function getExportOptions(): array
    {
        return $this->exportOptions;
    }

    /**
     * Set a Closure that further modifies the exporter query.
     *
     * @param  callable|Closure  $closure
     */
    public function modifyQueryUsing(callable|Closure $closure): self
    {
        $this->modifyQueryUsing = Helpers::asClosure($closure);

        return $this;
    }

    /**
     * Indicate that visible table columns should be enabled by default in the column mapping.
     */
    public function enableVisibleTableColumnsByDefault(bool $value = true): self
    {
        $this->enableVisibleTableColumnsByDefault = $value;

        return $this;
    }

    /**
     * Determine whether visible table columns should be enabled by default.
     */
    public function shouldEnableVisibleTableColumnsByDefault(): bool
    {
        return $this->enableVisibleTableColumnsByDefault;
    }

    /**
     * Build the Eloquent query for the pipeline exporter.
     * Applies the exporter's static modifyQuery hook followed by the optional modifyQueryUsing closure.
     *
     * @param  array<string, mixed>  $options
     */
    public function buildExporterQuery(array $options = []): Builder
    {
        $query = $this->makeExporter()->query();

        $query = $this->getExporterClass()::modifyQuery($query);

        if ($this->modifyQueryUsing instanceof Closure) {
            $query = ($this->modifyQueryUsing)($query, $options) ?? $query;
        }

        return $query;
    }

    /**
     * Get the export URL.
     */
    public function getExportUrl(): string
    {
        $routeName = $this->hasExporter() || $this->queue || ! $this->asDownload
            ? 'inertia-tables.async-export'
            : 'inertia-tables.export';

        return $this->generateSignedTableUrl($this->table, $routeName, [
            'export' => $this->index,
            ...$this->shouldLimitToFilteredRows()
                ? $this->table->getTableRequest()->getQueryDataForExports()
                : [],
        ]);
    }

    /**
     * Get the array representation of the Export.
     */
    public function toArray(): array
    {
        if (! $this->hasExporter() && $this->queue && ! $this->using instanceof Closure && blank($this->getQueueDisk())) {
            throw new RuntimeException('The export is queued, but no (default) disk is set.');
        }

        $array = [
            'label'               => $this->getLabel(),
            'authorized'          => $this->isAuthorized(),
            'dataAttributes'      => $this->buildDataAttributes(),
            'meta'                => $this->meta,
            'limitToSelectedRows' => $this->shouldLimitToSelectedRows(),
            'asDownload'          => ! $this->hasExporter() && ! $this->queue && $this->asDownload,
            'url'                 => $this->getExportUrl(),
        ];

        if ($this->hasExporter()) {
            $array = array_merge($array, $this->cachedPipelineMeta ??= [
                'hasExporter'                        => true,
                'resourceLabel'                      => $this->getResourceLabel(),
                'hasColumnMapping'                   => $this->hasColumnMapping(),
                'enableVisibleTableColumnsByDefault' => $this->shouldEnableVisibleTableColumnsByDefault(),
                'columns'                            => collect($this->getExportColumns())
                    ->map(fn (ExportColumn $column): array => [
                        'name'             => $column->getName(),
                        'label'            => $column->getLabel(),
                        'enabledByDefault' => $column->isEnabledByDefault(),
                    ])->values()->all(),
                'formats'     => array_map(fn (ExportFormat $format): string => $format->value, $this->getFormats()),
                'optionsForm' => array_map(fn (OptionField $field): array => $field->toArray(), $this->getExporterClass()::getOptionsFormComponents()),
            ]);
        }

        return $array;
    }

    /**
     * Get the Exporter instance for the Export.
     */
    public function makeExporter(): Exporter
    {
        return new Exporter(
            $this->table,
            $this->getFilename(),
            $this->getType(),
            $this->getEvents(),
            $this->shouldLimitToFilteredRows(),
            $this->shouldLimitToSelectedRows()
        );
    }
}
