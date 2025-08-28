<?php

declare(strict_types=1);

namespace Modules\Table\Exceptions;

use RuntimeException;

class InvalidTableClassException extends RuntimeException
{
    public static function new(string $class): self
    {
        // @phpstan-ignore-next-line
        return new static(sprintf('Table class [%s] is invalid.', $class));
    }
}
