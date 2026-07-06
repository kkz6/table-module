<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs;

use Carbon\CarbonInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Table\Exports\Exporter;
use Modules\Table\Models\TableExport;
use Modules\Table\Table;
use Throwable;

class ExportRowsJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $maxExceptions = 5;

    public bool $deleteWhenMissingModels = true;

    private ?Exporter $resolvedExporter = null;

    /**
     * @param  array<int, mixed>      $keys
     * @param  array<string, string>  $columnMap
     * @param  array<string, mixed>   $options
     */
    public function __construct(
        public TableExport $export,
        public Table $table,
        public int $index,
        public array $keys,
        public array $columnMap,
        public array $options,
        public int $page,
    ) {}

    public function handle(): void
    {
        // Scope auth to the export owner before resolving the exporter, so a
        // table-backed exporter sees the same (auth-dependent) columns the
        // owner saw when the export was dispatched.
        auth()->setUser($this->export->user);

        $exporter = $this->exporter();

        try {
            $exportAction = $this->table->getExportById($this->index)
                ?? throw new \RuntimeException(sprintf('Export index [%d] no longer exists on table [%s].', $this->index, $this->table::class));
            $query = $exportAction->buildExporterQuery($this->options);

            // Only eager-load/aggregate the columns actually selected for export;
            // unchecked columns are never written, so their relations shouldn't
            // be loaded (avoids wasted withCount/with work and unrelated failures).
            $cachedColumns = $exporter->getCachedColumns();

            // Fail this chunk once (loudly) if a selected column no longer resolves
            // — e.g. an auth-scoped table column the owner can no longer see — rather
            // than throwing per row inside the loop below and emitting one report()
            // per record while still producing a blank column.
            $missingColumns = array_diff(array_keys($this->columnMap), array_keys($cachedColumns));

            if ($missingColumns !== []) {
                $this->fail(new \RuntimeException(sprintf(
                    'Export columns [%s] could not be resolved for table [%s].',
                    implode(', ', $missingColumns),
                    $this->table::class,
                )));

                return;
            }

            foreach (array_keys($this->columnMap) as $name) {
                $column = $cachedColumns[$name] ?? null;
                $column?->applyRelationshipAggregates($query);
                $column?->applyEagerLoading($query);
            }

            $records = $query->whereKey($this->keys)->get();

            $csv        = fopen('php://temp', 'r+');
            $successful = 0;

            try {
                foreach ($records as $record) {
                    try {
                        fputcsv($csv, $exporter($record), $exporter::getCsvDelimiter());
                        $successful++;
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }

                rewind($csv);
                $this->export->getFileDisk()->put(
                    $this->export->getPartsDirectory().'/'.str_pad((string) $this->page, 16, '0', STR_PAD_LEFT).'.csv',
                    stream_get_contents($csv),
                );
            } finally {
                fclose($csv);
            }

            $processed = count($this->keys);

            DB::transaction(function () use ($processed, $successful): void {
                $export = TableExport::query()->lockForUpdate()->find($this->export->getKey());

                $export?->update([
                    'processed_rows'  => min($export->processed_rows + $processed, $export->total_rows),
                    'successful_rows' => min($export->successful_rows + $successful, $export->total_rows),
                ]);
            });
        } finally {
            auth()->forgetGuards();
        }
    }

    /**
     * Resolve (and memoize) the exporter, always bound to the live Table so a
     * table-backed exporter can resolve its columns.
     */
    private function exporter(): Exporter
    {
        return $this->resolvedExporter ??= $this->export->getExporter($this->columnMap, $this->options, $this->table);
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return $this->exporter()->getJobMiddleware();
    }

    public function retryUntil(): ?CarbonInterface
    {
        return $this->exporter()->getJobRetryUntil();
    }

    /**
     * @return int|array<int, int>|null
     */
    public function backoff(): int|array|null
    {
        return $this->exporter()->getJobBackoff();
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return $this->exporter()->getJobTags();
    }
}
