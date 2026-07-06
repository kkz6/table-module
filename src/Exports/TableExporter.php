<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Modules\Table\Models\TableExport;
use Modules\Table\Table;
use RuntimeException;

/**
 * The single, generic pipeline exporter. It resolves its columns from the live
 * Table (its exportable columns plus additionalExportColumns()), so resources
 * define their entire export inline in the Table and need no exporter class.
 */
class TableExporter extends Exporter
{
    public function __construct(
        TableExport $export,
        array $columnMap,
        array $options,
        protected ?Table $table = null,
    ) {
        parent::__construct($export, $columnMap, $options);
    }

    /**
     * Unused for table-backed exports; column resolution flows through
     * {@see resolveColumns()} against the live Table instead.
     *
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, ExportColumn>
     */
    protected function resolveColumns(): array
    {
        if (! $this->table instanceof Table) {
            throw new RuntimeException('TableExporter requires a Table instance to resolve columns.');
        }

        return TableExportColumnResolver::resolve($this->table);
    }

    public function getFileName(TableExport $export): string
    {
        return __('table::table.export_file_name', [
            'export_id' => $export->getKey(),
            'model'     => $this->table instanceof Table
                ? (string) str(class_basename($this->table->resourceBuilder()->getModel()))->kebab()->plural()
                : 'export',
        ]);
    }
}
