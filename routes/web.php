<?php

use Illuminate\Support\Facades\Route;

// Serves the Vue SPA shell for every frontend route. Excludes /api and /api/*
// so an unknown API endpoint falls through to Laravel's normal 404 handling
// instead of receiving the SPA shell.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api(?:/|$)).*$')->name('spa');
