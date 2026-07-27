<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Options;

use Modules\Table\Helpers;

abstract class OptionField
{
    protected ?string $label = null;

    protected mixed $default = null;

    public function __construct(protected string $name) {}

    /**
     * Create a new OptionField instance.
     */
    public static function make(string $name): static
    {
        // @phpstan-ignore new.static
        return new static($name);
    }

    /**
     * Set the label for the field.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the default value for the field.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Get the field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the field label, derived from the name when not set explicitly.
     */
    public function getLabel(): string
    {
        return $this->label !== null
            ? $this->label
            : Helpers::labelFromName($this->name);
    }

    /**
     * Get the default value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Get the field type identifier.
     */
    abstract public function getType(): string;

    /**
     * Serialize the field to an array for the frontend.
     *
     * @return array{type: string, name: string, label: string, default: mixed}
     */
    public function toArray(): array
    {
        return [
            'type'    => $this->getType(),
            'name'    => $this->name,
            'label'   => $this->getLabel(),
            'default' => $this->default,
        ];
    }
}
