<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Downloaders;

use Modules\Table\Exports\XlsxFileBuilder;
use Modules\Table\Models\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxDownloader implements Downloader
{
    public function __invoke(TableExport $export): StreamedResponse
    {
        $disk      = $export->getFileDisk();
        $directory = $export->getFileDirectory();
        $xlsxPath  = $directory.'/'.$export->file_name.'.xlsx';

        if ($disk->exists($xlsxPath)) {
            return $disk->download($xlsxPath, $export->file_name.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $tmp = app(XlsxFileBuilder::class)->writeToTemp($export, $export->getExporter());

        return response()->streamDownload(function () use ($tmp): void {
            try {
                echo (string) file_get_contents($tmp);
            } finally {
                if (file_exists($tmp)) {
                    unlink($tmp);
                }
            }
        }, $export->file_name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
