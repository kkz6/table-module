<?php

declare(strict_types=1);

namespace Modules\Table\Exports;

use Modules\Table\Models\TableExport;

final class CsvFileMerger
{
    /**
     * Merge headers + sorted parts into one file with a UTF-8 BOM.
     * Streams part content to avoid loading everything into memory.
     *
     * @return string Relative path written: {fileDirectory}/{file_name}.csv
     */
    public function merge(TableExport $export): string
    {
        $disk           = $export->getFileDisk();
        $partsDirectory = $export->getPartsDirectory();
        $destination    = $export->getFileDirectory().'/'.$export->file_name.'.csv';

        $stream = fopen('php://temp', 'w+b');

        fwrite($stream, "\xEF\xBB\xBF");
        fwrite($stream, (string) $disk->get($partsDirectory.'/headers.csv'));

        $parts = collect($disk->files($partsDirectory))
            ->filter(fn (string $file): bool => str_ends_with($file, '.csv') && ! str_ends_with($file, 'headers.csv'))
            ->sort();

        foreach ($parts as $part) {
            fwrite($stream, (string) $disk->get($part));
        }

        rewind($stream);
        $disk->writeStream($destination, $stream);
        fclose($stream);

        return $destination;
    }
}
