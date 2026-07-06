<?php

declare(strict_types=1);

namespace Modules\Table\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExportController
{
    public function __invoke(Request $request, TableExport $tableExport): StreamedResponse
    {
        $user = $request->user();

        if (Gate::getPolicyFor($tableExport) !== null) {
            Gate::forUser($user)->authorize('view', $tableExport);
        } else {
            abort_unless($tableExport->user()->is($user), 403);
        }

        abort_unless($tableExport->completed_at !== null, 404);

        $format = ExportFormat::tryFrom((string) $request->query('format')) ?? abort(404);

        return $format->getDownloader()($tableExport);
    }
}
