<?php

declare(strict_types=1);

namespace Modules\Table\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Table\Models\TableExport;
use Modules\User\Models\User;

/**
 * @extends Factory<TableExport>
 */
class TableExportFactory extends Factory
{
    protected $model = TableExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_disk'       => 'local',
            'file_name'       => 'export-test',
            'exporter'        => 'Modules\\Table\\Tests\\Support\\TestUserExporter',
            'total_rows'      => 0,
            'successful_rows' => 0,
            'processed_rows'  => 0,
            'user_id'         => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
