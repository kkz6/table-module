<?php

declare(strict_types=1);

namespace Modules\Table\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\Table\Models\TableExport;

class PruneExportsCommand extends Command
{
    /** @var string */
    protected $signature = 'table:prune-exports {--days=15 : Delete exports created more than this many days ago}';

    /** @var string */
    protected $description = 'Delete export records and their stored files older than the retention window';

    public function handle(): int
    {
        $days   = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $count  = 0;

        TableExport::query()
            ->where('created_at', '<=', $cutoff)
            ->chunkById(200, function (Collection $exports) use (&$count): void {
                /** @var TableExport $export */
                foreach ($exports as $export) {
                    $export->deleteFileDirectory();
                    $export->delete();
                    $count++;
                }
            });

        $this->info("Pruned {$count} export(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
