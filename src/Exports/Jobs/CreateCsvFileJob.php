<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Table\Exports\CsvFileMerger;
use Modules\Table\Models\TableExport;

class CreateCsvFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 0;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 300];

    public function __construct(public TableExport $export) {}

    public function handle(): void
    {
        app(CsvFileMerger::class)->merge($this->export);
    }
}
