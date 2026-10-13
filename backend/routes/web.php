<?php

use Illuminate\Support\Facades\Route;

Route::get('/docs', function () {
    return view('swagger');
})->name('swagger.ui');

Route::get('/api/documentation', function () {
    return view('swagger');
});

Route::get('/openapi.json', function () {
    return response()->file(public_path('openapi.json'), [
        'Content-Type' => 'application/json',
    ]);
})->name('swagger.json');

// Catch-all route to serve the Vue 3 SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|docs|openapi\.json|storage|build).*$');

Route::get('/', function () {
    return view('app');
});
