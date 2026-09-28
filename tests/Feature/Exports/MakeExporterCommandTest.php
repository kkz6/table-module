<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('scaffolds an exporter class', function () {
    $path = app_path('Exports/PlanetExporter.php');
    File::delete($path);

    try {
        $this->artisan('make:table-exporter', ['name' => 'PlanetExporter', '--model' => 'App\\Models\\Planet'])
            ->assertSuccessful();

        expect(File::get($path))
            ->toContain('class PlanetExporter extends Exporter')
            ->toContain('protected static ?string $model = \\App\\Models\\Planet::class;')
            ->toContain('public static function getColumns(): array');
    } finally {
        File::delete($path);

        $dir = app_path('Exports');
        if (is_dir($dir) && count(File::files($dir)) === 0) {
            File::deleteDirectory($dir);
        }
    }
});
