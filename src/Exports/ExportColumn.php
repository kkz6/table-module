<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Table\Helpers;
use ReflectionFunction;
use ReflectionNamedType;

class ExportColumn
{
    protected string|Closure|null $label = null;

    protected bool $isEnabledByDefault = true;

    protected ?Closure $stateUsing = null;

    protected mixed $defaultState = null;

    protected ?Closure $formatStateUsing = null;

    protected int|Closure|null $characterLimit = null;

    protected string|Closure $characterLimitEnd = '...';

    protected int|Closure|null $wordLimit = null;

    protected string|Closure $wordLimitEnd = '...';

    protected string|Closure|null $prefix = null;

    protected string|Closure|null $suffix = null;

    protected ?string $separator = null;

    protected bool|Closure $isDistinctList = false;

    protected bool|Closure $shouldListAsJson = false;

    protected ?Exporter $exporter = null;

    /** @var array<int, array{method: string, relationships: string|array<int, string>, column: ?string}> */
    protected array $relationshipAggregates = [];

    public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        // @phpstan-ignore new.static
        return new static($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label !== null
            ? (string) $this->evaluate($this->label)
            : Helpers::labelFromName($this->name);
    }

    public function enabledByDefault(bool $condition = true): static
    {
        $this->isEnabledByDefault = $condition;

        return $this;
    }

    public function isEnabledByDefault(): bool
    {
        return $this->isEnabledByDefault;
    }

    public function state(Closure $callback): static
    {
        $this->stateUsing = $callback;

        return $this;
    }

    public function default(mixed $state): static
    {
        $this->defaultState = $state;

        return $this;
    }

    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    public function limit(?int $length = 100, string $end = '...'): static
    {
        $this->characterLimit    = $length;
        $this->characterLimitEnd = $end;

        return $this;
    }

    public function words(?int $words = 100, string $end = '...'): static
    {
        $this->wordLimit    = $words;
        $this->wordLimitEnd = $end;

        return $this;
    }

    public function prefix(string|Closure $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string|Closure $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function separator(?string $separator = ','): static
    {
        $this->separator = $separator;

        return $this;
    }

    public function distinctList(bool $condition = true): static
    {
        $this->isDistinctList = $condition;

        return $this;
    }

    public function listAsJson(bool $condition = true): static
    {
        $this->shouldListAsJson = $condition;

        return $this;
    }

    public function exporter(Exporter $exporter): static
    {
        $this->exporter = $exporter;

        return $this;
    }

    public function getExporter(): ?Exporter
    {
        return $this->exporter;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function counts(string|array $relationships): static
    {
        $this->relationshipAggregates[] = ['method' => 'withCount', 'relationships' => $relationships, 'column' => null];

        return $this;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function exists(string|array $relationships): static
    {
        $this->relationshipAggregates[] = ['method' => 'withExists', 'relationships' => $relationships, 'column' => null];

        return $this;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function avg(string|array $relationships, string $column): static
    {
        $this->relationshipAggregates[] = ['method' => 'withAvg', 'relationships' => $relationships, 'column' => $column];

        return $this;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function min(string|array $relationships, string $column): static
    {
        $this->relationshipAggregates[] = ['method' => 'withMin', 'relationships' => $relationships, 'column' => $column];

        return $this;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function max(string|array $relationships, string $column): static
    {
        $this->relationshipAggregates[] = ['method' => 'withMax', 'relationships' => $relationships, 'column' => $column];

        return $this;
    }

    /**
     * @param  string|array<int, string>  $relationships
     */
    public function sum(string|array $relationships, string $column): static
    {
        $this->relationshipAggregates[] = ['method' => 'withSum', 'relationships' => $relationships, 'column' => $column];

        return $this;
    }

    public function applyRelationshipAggregates(Builder $query): void
    {
        foreach ($this->relationshipAggregates as $aggregate) {
            $aggregate['column'] === null
                ? $query->{$aggregate['method']}($aggregate['relationships'])
                : $query->{$aggregate['method']}($aggregate['relationships'], $aggregate['column']);
        }
    }

    public function applyEagerLoading(Builder $query): void
    {
        if ($this->relationshipAggregates !== [] || ! str_contains($this->name, '.')) {
            return;
        }

        $query->with((string) str($this->name)->beforeLast('.'));
    }

    public function getRecord(): ?Model
    {
        return $this->exporter?->getRecord();
    }

    public function getState(): mixed
    {
        $state = $this->stateUsing !== null
            ? $this->evaluate($this->stateUsing)
            : $this->resolveStateFromRecord();

        if (is_string($state) && ($separator = $this->evaluate($this->separator)) !== null && $separator !== '') {
            $state = array_map(trim(...), explode($separator, $state));
        }

        if (blank($state) && $this->defaultState !== null) {
            $state = $this->evaluate($this->defaultState);
        }

        return $state instanceof Collection ? $state->all() : $state;
    }

    protected function resolveStateFromRecord(): mixed
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return null;
        }

        return $this->walkState($record, explode('.', $this->name));
    }

    /**
     * Walks dot-notation segments; fans out over collections (to-many relations).
     */
    protected function walkState(mixed $target, array $segments): mixed
    {
        if ($target === null || $segments === []) {
            return $target;
        }

        $segment = array_shift($segments);

        if ($target instanceof Collection || is_array($target)) {
            return collect($target)
                ->map(fn (mixed $item): mixed => $this->walkState($item, [$segment, ...$segments]))
                ->flatten()
                ->filter(fn (mixed $value): bool => filled($value))
                ->values();
        }

        return $this->walkState(data_get($target, $segment), $segments);
    }

    public function getFormattedState(): ?string
    {
        $state = $this->getState();

        if (is_array($state)) {
            $formatted = array_map(fn (mixed $value): mixed => $this->formatState($value), $state);

            if ($this->evaluate($this->isDistinctList)) {
                $formatted = array_unique($formatted);
            }

            return $this->evaluate($this->shouldListAsJson)
                ? json_encode(array_values($formatted)) ?: '[]'
                : implode(', ', array_map(strval(...), $formatted));
        }

        $formatted = $this->formatState($state);

        return $formatted === null ? null : (string) $formatted;
    }

    protected function formatState(mixed $state): mixed
    {
        if ($this->formatStateUsing !== null) {
            $state = $this->evaluate($this->formatStateUsing, ['state' => $state]);
        }

        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if (is_bool($state)) {
            $state = $state ? 'True' : 'False';
        }

        if (($limit = $this->evaluate($this->characterLimit)) !== null && is_string($state)) {
            $state = Str::limit($state, $limit, (string) $this->evaluate($this->characterLimitEnd));
        }

        if (($words = $this->evaluate($this->wordLimit)) !== null && is_string($state)) {
            $state = Str::words($state, $words, (string) $this->evaluate($this->wordLimitEnd));
        }

        if (filled($state)) {
            $state = $this->evaluate($this->prefix) . $state . $this->evaluate($this->suffix);
        }

        return $state;
    }

    protected function evaluate(mixed $value, array $named = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $injections = [
            'column'   => $this,
            'exporter' => $this->exporter,
            'record'   => $this->getRecord(),
            'options'  => $this->exporter?->getOptions() ?? [],
            ...$named,
        ];

        $arguments = [];

        foreach ((new ReflectionFunction($value))->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $injections)) {
                $arguments[] = $injections[$parameter->getName()];

                continue;
            }

            $type = $parameter->getType() instanceof ReflectionNamedType ? $parameter->getType()->getName() : null;

            $arguments[] = match (true) {
                $type !== null && is_a($type, Model::class, true)    => $this->getRecord(),
                $type !== null && is_a($type, Exporter::class, true) => $this->exporter,
                $parameter->isDefaultValueAvailable()                => $parameter->getDefaultValue(),
                default                                              => null,
            };
        }

        return $value(...$arguments);
    }
}
