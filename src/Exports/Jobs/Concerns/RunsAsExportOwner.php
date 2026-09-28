<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs\Concerns;

use Closure;
use Modules\Table\Contracts\ExportExecutionContext;
use Modules\Table\Models\TableExport;

trait RunsAsExportOwner
{
    private function runAsExportOwner(TableExport $export, Closure $callback): mixed
    {
        return app(ExportExecutionContext::class)->run($export->user_id, $callback);
    }
}
