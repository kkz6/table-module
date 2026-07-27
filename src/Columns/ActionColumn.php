<?php

declare(strict_types=1);

namespace Modules\Table\Columns;

use Closure;
use Modules\Table\Enums\ColumnAlignment;

class ActionColumn extends Column
{
    /**
     * Whether the actions should be displayed as a dropdown.
     */
    protected ?bool $asDropdown = null;

    /**
     * Whether the actions should be displayed as a dropdown by default.
     */
    protected static bool $defaultAsDropdown = false;

    /**
     * {@inheritDoc}
     */
    public static function make(
        string $attribute,
        ?string $header = null,
        bool $sortable = false,
        bool $toggleable = false,
        bool $searchable = false,
        ColumnAlignment $alignment = ColumnAlignment::Center,
        Closure|callable|array|null $mapAs = null,
        Closure|bool|null $exportAs = null,
        Closure|string|null $exportFormat = null,
        Closure|array|null $exportStyle = null,
        bool $visible = true,
        Closure|callable|null $sortUsing = null,
        ?array $meta = null,
        Closure|callable|null $url = null,
        bool $wrap = false,
        ?int $truncate = null,
        array|string|null $headerClass = null,
        array|string|null $cellClass = null,
        Closure|callable|null $image = null,
        ?bool $stickable = null,
    ): static {
        return parent::make(
            attribute: $attribute,
            header: $header ?? __('table::table.actions'),
            sortable: $sortable,
            toggleable: $toggleable,
            searchable: $searchable,
            alignment: $alignment,
            mapAs: $mapAs,
            exportAs: $exportAs,
            exportFormat: $exportFormat,
            exportStyle: $exportStyle,
            visible: $visible,
            sortUsing: $sortUsing,
            meta: $meta,
            url: $url,
            wrap: $wrap,
            truncate: $truncate,
            headerClass: $headerClass,
            cellClass: $cellClass,
            image: $image,
            stickable: $stickable,
        );
    }

    /**
     * Create a new instance with the given label.
     */
    public static function new(string $label = '', ?bool $asDropdown = null): ActionColumn
    {
        return static::make(
            attribute: '_actions',
            header: $label,
            toggleable: false,
            alignment: ColumnAlignment::Right
        )->asDropdown($asDropdown ?? static::$defaultAsDropdown);
    }

    /**
     * Set whether the actions should be displayed as a dropdown by default.
     */
    public static function defaultAsDropdown(bool $value = true): void
    {
        static::$defaultAsDropdown = $value;
    }

    /**
     * Set whether the actions should be displayed as a dropdown.
     */
    public function asDropdown(bool $value = true): self
    {
        $this->asDropdown = $value;

        return $this;
    }

    /**
     * Always return the same constant attribute.
     */
    public function getAttribute(): string
    {
        return '_actions';
    }

    /**
     * Disallow changing the attribute.
     */
    public function attribute(string $attribute): self
    {
        return $this;
    }

    /**
     * Never export the column.
     */
    public function shouldBeExported(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'asDropdown' => $this->asDropdown ?? static::$defaultAsDropdown,
        ]);
    }
}
