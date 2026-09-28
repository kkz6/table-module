<?php

declare(strict_types=1);

namespace Modules\Table\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Modules\Table\Contracts\ExportExecutionContext;
use Modules\Table\Exports\Support\AuthenticatedExportExecutionContext;
use Modules\Table\Table;

class TableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(ExportExecutionContext::class, AuthenticatedExportExecutionContext::class);
    }

    /**
     * Register the Inertia Table package.
     */
    public function boot(): void
    {
        $this->app->afterResolving(Table::class, static function (Table $table, Application $app): void {
            $table->setRequest($app['request']);
        });
    }
}
