<?php

declare(strict_types=1);

namespace Modules\Table\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeExporterCommand extends GeneratorCommand
{
    /** @var string */
    protected $name = 'make:table-exporter';

    /** @var string */
    protected $description = 'Create a new table exporter class';

    /** @var string */
    protected $type = 'Exporter';

    protected function getStub(): string
    {
        return __DIR__.'/../stubs/exporter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Exports';
    }

    /**
     * Build the class with the given name, replacing the model placeholder.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        if ($this->option('model')) {
            $modelDefinition = '\\'.$this->qualifyModel($this->option('model')).'::class';
        } else {
            $modelDefinition = 'null';
        }

        return str_replace('{{ modelDefinition }}', $modelDefinition, $stub);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE,     'Create the exporter even if it already exists'],
            ['model', 'm', InputOption::VALUE_REQUIRED,  'The model class the exporter targets'],
        ];
    }
}
