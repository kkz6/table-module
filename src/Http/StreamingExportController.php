<?php

declare(strict_types=1);

namespace Modules\Table\Http;

use Modules\Table\Exports\StreamingExport;
use Symfony\Component\HttpFoundation\Response;

class StreamingExportController
{
    public function __invoke(ExportRequest $request, StreamingExport $streamingExport): Response
    {
        return $streamingExport->stream($request->getExport(), $request);
    }
}
