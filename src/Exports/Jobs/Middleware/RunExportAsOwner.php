<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs\Middleware;

use Closure;
use Modules\Table\Contracts\ExportExecutionContext;

final class RunExportAsOwner
{
    public function __construct(private readonly int|string $ownerId) {}

    public function handle(object $job, Closure $next): mixed
    {
        return app(ExportExecutionContext::class)->run(
            $this->ownerId,
            fn (): mixed => $next($job),
        );
    }
}
