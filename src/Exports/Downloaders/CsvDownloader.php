<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Downloaders;

use Modules\Table\Models\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownloader implements Downloader
{
    public function __invoke(TableExport $export): StreamedResponse
    {
        $disk          = $export->getFileDisk();
        $fileDirectory = $export->getFileDirectory();
        $csvPath       = $fileDirectory.'/'.$export->file_name.'.csv';

        if ($disk->exists($csvPath)) {
            return $disk->download($csvPath, $export->file_name.'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $partsDirectory = $export->getPartsDirectory();

        abort_unless($disk->exists($partsDirectory.'/headers.csv'), 404);

        return response()->streamDownload(function () use ($disk, $partsDirectory): void {
            echo "\xEF\xBB\xBF";
            echo $disk->get($partsDirectory.'/headers.csv');

            collect($disk->files($partsDirectory))
                ->filter(fn (string $file): bool => str_ends_with($file, '.csv') && ! str_ends_with($file, 'headers.csv'))
                ->sort()
                ->each(function (string $file) use ($disk): void {
                    echo $disk->get($file);
                    flush();
                });
        }, $export->file_name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
