<?php

declare(strict_types=1);

namespace Modules\Table\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Table\Database\Factories\TableExportFactory;

/**
 * @property int                             $id
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string                          $file_disk
 * @property string|null                     $file_name
 * @property string                          $exporter
 * @property int                             $processed_rows
 * @property int                             $total_rows
 * @property int                             $successful_rows
 * @property int                             $user_id
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
class TableExport extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'completed_at'    => 'datetime',
            'processed_rows'  => 'integer',
            'total_rows'      => 'integer',
            'successful_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function getExporter(array $columnMap = [], array $options = [], ?\Modules\Table\Table $table = null): \Modules\Table\Exports\Exporter
    {
        return app($this->exporter, ['export' => $this, 'columnMap' => $columnMap, 'options' => $options, 'table' => $table]);
    }

    public function getFailedRowsCount(): int
    {
        return $this->total_rows - $this->successful_rows;
    }

    public function getSeverity(): string
    {
        $failedRows = $this->getFailedRowsCount();

        return match (true) {
            $failedRows === 0                 => 'success',
            $failedRows < $this->total_rows   => 'warning',
            default                           => 'error',
        };
    }

    public function getFileDisk(): Filesystem
    {
        return Storage::disk($this->file_disk);
    }

    public function getFileDirectory(): string
    {
        return 'table-exports/'.$this->getKey();
    }

    public function getPartsDirectory(): string
    {
        return $this->getFileDirectory().'/parts';
    }

    public function deleteFileDirectory(): void
    {
        $this->getFileDisk()->deleteDirectory($this->getFileDirectory());
    }

    protected static function newFactory(): TableExportFactory
    {
        return TableExportFactory::new();
    }
}
