<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Table\Models\TableExport;
use Modules\Table\Table;

class PrepareExportJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $maxExceptions = 0;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param array<string, string> $columnMap
     * @param array<string, mixed>  $options
     * @param int                   $chunkSize Rows fetched per ExportRowsJob. Prefer larger values (500–1000)
     *                                         for very large tables to reduce batch-insert overhead.
     */
    public function __construct(
        public TableExport $export,
        public Table $table,
        public int $index,
        public array $columnMap,
        public array $options,
        public int $chunkSize,
    ) {}

    public function handle(): void
    {
        auth()->setUser($this->export->user);

        try {
            $exportAction = $this->table->getExportById($this->index)
                ?? throw new \RuntimeException(sprintf('Export index [%d] no longer exists on table [%s].', $this->index, $this->table::class));
            $exporter = $this->export->getExporter($this->columnMap, $this->options, $this->table);
            $disk     = $this->export->getFileDisk();

            $headers = fopen('php://temp', 'r+');
            fputcsv($headers, array_values($this->columnMap), $exporter::getCsvDelimiter());
            rewind($headers);
            $disk->put($this->export->getPartsDirectory().'/headers.csv', stream_get_contents($headers));
            fclose($headers);

            $query   = $exportAction->buildExporterQuery($this->options);
            $model   = $query->getModel();
            $keyName = $model->getKeyName();

            /** @var array<int, ExportRowsJob> $jobs */
            $jobs      = [];
            $page      = 1;
            $remaining = $this->export->total_rows;

            $query->clone()
                ->reorder()
                ->select([$model->getQualifiedKeyName()])
                ->chunkById(
                    $this->chunkSize,
                    function ($records) use (&$jobs, &$page, &$remaining, $keyName): bool {
                        $keys = $records->pluck($keyName)->take($remaining)->all();

                        if ($keys !== []) {
                            $jobs[] = new ExportRowsJob(
                                $this->export,
                                $this->table,
                                $this->index,
                                $keys,
                                $this->columnMap,
                                $this->options,
                                $page++,
                            );
                            $remaining -= count($keys);
                        }

                        if (count($jobs) >= 100) {
                            $this->batch()?->add($jobs);
                            $jobs = [];
                        }

                        return $remaining > 0;
                    },
                    // Qualify the key for chunkById's internal WHERE/ORDER so exporter
                    // queries that join other tables don't hit an ambiguous "id".
                    $model->getQualifiedKeyName(),
                    $keyName,
                );

            if ($jobs !== []) {
                $this->batch()?->add($jobs);
            }
        } finally {
            auth()->forgetGuards();
        }
    }
}
