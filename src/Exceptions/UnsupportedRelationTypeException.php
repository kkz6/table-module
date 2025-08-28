<?php

declare(strict_types=1);

namespace Modules\Table\Exceptions;

use RuntimeException;

class UnsupportedRelationTypeException extends RuntimeException
{
    public static function new(): static
    {
        // @phpstan-ignore-next-line
        return new static(
            'Relationship type is not supported when related through another database connection.'
        );
    }
}
