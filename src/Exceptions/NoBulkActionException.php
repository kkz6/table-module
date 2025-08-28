<?php

declare(strict_types=1);

namespace Modules\Table\Exceptions;

use RuntimeException;

class NoBulkActionException extends RuntimeException
{
    public static function new(): self
    {
        // @phpstan-ignore-next-line
        return new static('This Action does not support Bulk Actions.');
    }
}
