<?php

declare(strict_types=1);

namespace Modules\Table;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/** A developer-defined filter preset with a count scoped to the table query. */
class Tab
{
    public function __construct(public string $value, public string $label, public array $filters = [], public ?string $icon = null, public string $tone = 'neutral', protected ?Closure $countUsing = null) {}

    public static function make(string $value, string $label): self
    {
        return new self($value, $label);
    }

    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function tone(string $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    public function countUsing(Closure $resolver): self
    {
        $this->countUsing = $resolver;

        return $this;
    }

    public function toArray(Builder $query): array
    {
        return ['value'=>$this->value, 'label'=>$this->label, 'filters'=>$this->filters, 'icon'=>$this->icon, 'tone'=>$this->tone, 'count'=>$this->countUsing ? max(0, (int) ($this->countUsing)(clone $query)) : null];
    }
}
