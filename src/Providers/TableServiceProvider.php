<?php

declare(strict_types=1);

namespace Modules\Table\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Modules\Table\Table;

class TableServiceProvider extends ServiceProvider
{
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
