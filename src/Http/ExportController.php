<?php

declare(strict_types=1);

namespace Modules\Table\Http;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Modules\Table\Export;
use Modules\Table\Exports\ExportDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use TypeError;

class ExportController
{
    /**
     * Handle the export request.
     */
    public function __invoke(ExportRequest $request): mixed
    {
        return $this->toResponse($request->getExport(), $request);
    }

    /**
     * Get the export response.
     */
    public function toResponse(Export $export, ExportRequest $request): mixed
    {
        return match (true) {
            $export->hasExporter()            => $this->toPipelineResponse($export, $request),
            $export->queue                    => $this->toQueuedResponse($export),
            $export->using instanceof Closure => $this->toCustomUsingResponse($export),
            default                           => $export->makeExporter(),
        };
    }

    /**
     * Dispatch the pipeline export chain and respond with a started toast.
     */
    private function toPipelineResponse(Export $export, ExportRequest $request): JsonResponse
    {
        $result = app(ExportDispatcher::class)->dispatch($export, $request);

        return response()->json([
            'started'    => true,
            'totalRows'  => $result['totalRows'],
            'toastTitle' => __('table::table.export_started_toast_title', [
                'model' => $export->getResourceLabel(),
            ]),
            'toastBody'  => trans_choice('table::table.export_started_toast_body', $result['totalRows'], [
                'count' => number_format($result['totalRows']),
            ]),
        ]);
    }

    /**
     * Queue the export and respond with a dialog response.
     */
    private function toQueuedResponse(Export $export): JsonResponse
    {
        $export->dispatchJob();

        $response = $export->redirect instanceof Closure
            ? App::call($export->redirect)
            : null;

        if ($response && ! $response instanceof RedirectResponse) {
            throw new TypeError("The 'redirect' property must return an instance of RedirectResponse.");
        }

        return $this->toDialogResponse($export, $response);
    }

    /**
     * Execute the export using the given callback and respond with a dialog response
     * if the callback does not return a Response instance.
     */
    private function toCustomUsingResponse(Export $export): mixed
    {
        $response = $export->executeUsingCallback();

        if ($response instanceof Response || $export->asDownload) {
            return $response;
        }

        return $this->toDialogResponse($export);
    }

    /**
     * Respond with a dialog response and optional redirect.
     */
    private function toDialogResponse(Export $export, ?RedirectResponse $redirect = null): JsonResponse
    {
        return response()->json([
            'dialogTitle'   => blank($export->dialogTitle) ? null : $export->dialogTitle,
            'dialogMessage' => blank($export->dialogMessage) ? null : $export->dialogMessage,
            'targetUrl'     => $redirect?->getTargetUrl(),
        ]);
    }
}
