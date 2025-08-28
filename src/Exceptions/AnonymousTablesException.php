<?php

declare(strict_types=1);

namespace Modules\Table\Exceptions;

use RuntimeException;

class AnonymousTablesException extends RuntimeException
{
    public static function new(): self
    {
        // @phpstan-ignore-next-line
        return new static('Anonymous Tables do not support Actions or Exporting.');
    }
}
