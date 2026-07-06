<?php

declare(strict_types=1);

use Modules\Table\Exports\ExportFormat;

it('exposes labels for each format', function () {
    expect(ExportFormat::Csv->getLabel())->toBe('CSV')
        ->and(ExportFormat::Xlsx->getLabel())->toBe('Excel (XLSX)');
});

it('has ja translations for every new export key', function () {
    $en = require base_path('modules/table/resources/lang/en/table.php');
    $ja = require base_path('modules/table/resources/lang/ja/table.php');

    $keys = array_filter(array_keys($en), fn (string $key): bool => str_starts_with($key, 'export_'));

    expect($keys)->not->toBeEmpty();
    foreach ($keys as $key) {
        expect($ja)->toHaveKey($key);
    }
});
