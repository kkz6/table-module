<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Downloaders;

use Modules\Table\Models\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Downloader
{
    public function __invoke(TableExport $export): StreamedResponse;
}
