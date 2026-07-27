<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Table\Exports\XlsxFileBuilder;
use Modules\Table\Models\TableExport;

class CreateXlsxFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 0;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 300];

    /**
     * @param array<string, string> $columnMap
     * @param array<string, mixed>  $options
     */
    public function __construct(
        public TableExport $export,
        public array $columnMap,
        public array $options,
    ) {}

    public function handle(): void
    {
        $tmp = app(XlsxFileBuilder::class)->writeToTemp(
            $this->export,
            $this->export->getExporter($this->columnMap, $this->options),
        );

        try {
            $this->export->getFileDisk()->putFileAs(
                $this->export->getFileDirectory(),
                new File($tmp),
                $this->export->file_name.'.xlsx',
            );
        } finally {
            @unlink($tmp);
        }
    }
}
