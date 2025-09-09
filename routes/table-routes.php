<?php

use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use Modules\Table\Http\ActionController;
use Modules\Table\Http\ExportController;
use Modules\Table\Http\ViewController;

Route::name('inertia-tables.')->middleware(['auth', 'web'])->prefix('/_inertia-tables/{table}/{name}')->group(function () {
    Route::post('/action/{action}/{state?}', ActionController::class)
        ->middleware(ValidateSignature::absolute())
        ->name('action');

    Route::get('/export/{export}/{state?}', ExportController::class)
        ->middleware(ValidateSignature::absolute('keys'))
        ->name('export');

    Route::post('/async-export/{export}/{state?}', ExportController::class)
        ->middleware(ValidateSignature::absolute('keys'))
        ->name('async-export');

    Route::post('/view/{state?}', [ViewController::class, 'store'])
        ->middleware(ValidateSignature::absolute())
        ->name('view.store');

    Route::delete('/view/{key}/{state?}', [ViewController::class, 'destroy'])
        ->middleware(ValidateSignature::absolute())
        ->name('view.destroy');
});
