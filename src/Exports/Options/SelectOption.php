<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Options;

class SelectOption extends OptionField
{
    /** @var array<string, string> */
    protected array $selectOptions = [];

    /**
     * Set the available options (value => label).
     *
     * @param array<string, string> $options
     */
    public function options(array $options): static
    {
        $this->selectOptions = $options;

        return $this;
    }

    /**
     * Get the field type identifier.
     */
    public function getType(): string
    {
        return 'select';
    }

    /**
     * Serialize the field to an array for the frontend.
     *
     * @return array{type: string, name: string, label: string, default: mixed, options: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'options' => $this->selectOptions,
        ];
    }
}
