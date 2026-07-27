<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Options;

class TextOption extends OptionField
{
    /**
     * Get the field type identifier.
     */
    public function getType(): string
    {
        return 'text';
    }
}
