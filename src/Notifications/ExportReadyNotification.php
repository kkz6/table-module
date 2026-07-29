<?php

declare(strict_types=1);

namespace Modules\Table\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;

class ExportReadyNotification extends Notification
{
    use Queueable;

    /**
     * @param array<int, ExportFormat> $formats
     */
    public function __construct(public TableExport $export, public array $formats, public string $resourceLabel) {}

    /**
     * Persist to the database only. Realtime delivery is handled by the
     * Modules\Table\Events\ExportReady broadcast event (an explicit private
     * channel), mirroring the app's other realtime features.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array{type: string, exportId: int, title: string, body: string, severity: string, downloads: array<int, array{format: string, label: string, url: string}>}
     */
    public function payload(): array
    {
        /** @var class-string<\Modules\Table\Exports\Exporter> $exporterClass */
        $exporterClass = $this->export->exporter;

        // A file exists unless a non-empty export had every row fail. An empty
        // export (0 rows) still produces a valid header-only file to download.
        $hasFile = $this->export->total_rows === 0 || $this->export->successful_rows > 0;

        return [
            'type'      => 'export_ready',
            'exportId'  => $this->export->getKey(),
            'title'     => __('table::table.export_completed_toast_title', ['model' => $this->resourceLabel]),
            'body'      => $exporterClass::getCompletedToastBody($this->export),
            'severity'  => $this->export->getSeverity(),
            'downloads' => $hasFile
                ? array_map(fn (ExportFormat $format): array => [
                    'format' => $format->value,
                    'label'  => __('table::table.export_download_'.$format->value),
                    'url'    => $format->getDownloadUrl($this->export),
                ], $this->formats)
                : [],
        ];
    }
}
