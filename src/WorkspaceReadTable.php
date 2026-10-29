<?php

declare(strict_types=1);

namespace Modules\Table;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Table\Columns\ActionColumn;
use Modules\Table\Enums\TableComponent;

/** Reuses a module's existing column/filter definitions with a workspace-scoped query. */
final class WorkspaceReadTable extends Table
{
    protected ?TableComponent $autofocus=TableComponent::None;

    public function __construct(private readonly Table $source, private readonly Builder $query, private readonly string $path)
    {
        $this->search        =$source->search();
        $this->defaultSort   =$source->getDefaultSort();
        $this->perPageOptions=$source->getPerPageOptions();
    }

    public function resource(): Builder|string
    {
        return clone $this->query;
    }

    public function columns(): array
    {
        $columns=[];
        foreach ($this->source->columns() as $column) {
            if ($column instanceof ActionColumn) {
                continue;
            }
            $copy=clone $column;
            if ($columns === [] || $copy->hasUrl()) {
                $copy->url(fn (Model $record): string =>$this->path.'/'.$record->getKey());
            }
            $columns[]=$copy;
        }

        return $columns;
    }

    public function filters(): array
    {
        return $this->source->filters();
    }

    public function transformModel(Model $model, array $data): array
    {
        return ['id'=>$model->getKey(), ...$data, ...array_intersect_key($this->source->transformModel($model, $data), $data)];
    }
}
