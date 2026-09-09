<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/docs');
});

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
