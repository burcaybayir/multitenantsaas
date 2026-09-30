<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Central domain only (registered per central domain in bootstrap/app.php).
 */
Route::get('/', fn () => view('welcome'))->name('central.home');
