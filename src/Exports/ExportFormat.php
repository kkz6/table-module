<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Illuminate\Support\Facades\URL;
use Modules\Table\Exports\Downloaders\CsvDownloader;
use Modules\Table\Exports\Downloaders\Downloader;
use Modules\Table\Exports\Downloaders\XlsxDownloader;
use Modules\Table\Models\TableExport;

enum ExportFormat: string
{
    case Csv  = 'csv';
    case Xlsx = 'xlsx';

    public function getLabel(): string
    {
        return __('table::table.export_format_'.$this->value);
    }

    public function getDownloadUrl(TableExport $export): string
    {
        return URL::signedRoute('inertia-tables.exports.download', [
            'tableExport' => $export,
            'format'      => $this->value,
        ]);
    }

    public function getDownloader(): Downloader
    {
        return match ($this) {
            self::Csv  => app(CsvDownloader::class),
            self::Xlsx => app(XlsxDownloader::class),
        };
    }
}
