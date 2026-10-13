<?php

declare(strict_types=1);

test('table service provider is registered', function () {
    expect(app()->getProvider(\Modules\Table\Providers\TableServiceProvider::class))->not->toBeNull();
});
