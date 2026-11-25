<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Registered under /install without the "web" middleware group (see bootstrap/app.php):
// before install there is no app key, session or database for that stack.
Route::get('/', [InstallController::class, 'show'])->name('install');
Route::post('database', [InstallController::class, 'testDatabase'])->name('install.database');
Route::post('/', [InstallController::class, 'store'])->name('install.store');
