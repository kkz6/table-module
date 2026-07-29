<?php

declare(strict_types=1);

namespace Modules\Table\Exports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Table\Events\ExportReady;
use Modules\Table\Exports\ExportFormat;
use Modules\Table\Models\TableExport;
use Modules\Table\Notifications\ExportReadyNotification;

class CompleteExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param array<int, ExportFormat> $formats
     */
    public function __construct(
        public TableExport $export,
        public array $formats,
        public string $resourceLabel,
    ) {}

    public function handle(): void
    {
        $this->export->touch('completed_at');

        $user = $this->export->user;

        if ($user === null) {
            return;
        }

        // Persist for the bell, then broadcast the same id so the realtime item and the stored row are one record.
        $notification     = new ExportReadyNotification($this->export, $this->formats, $this->resourceLabel);
        $notification->id = (string) Str::uuid();

        Notification::send($user, $notification);

        broadcast(new ExportReady((int) $user->getKey(), [
            'id'        => $notification->id,
            'readAt'    => null,
            'createdAt' => now()->toIso8601String(),
            ...$notification->payload(),
        ]));
    }
}
