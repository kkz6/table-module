<?php

declare(strict_types=1);

namespace Modules\Table\Contracts;

use Closure;

interface ExportExecutionContext
{
    public function run(int|string $ownerId, Closure $callback): mixed;
}
