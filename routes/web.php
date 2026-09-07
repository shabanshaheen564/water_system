<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\DatasetsController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'webLogin']);
Route::post('/logout', [LoginController::class, 'webLogout'])->name('logout');

Route::middleware(['auth', 'active', 'permission:datasets.view'])->group(function () {
    Route::get('/gis', [GisController::class, 'index'])->name('gis.index');
    Route::get('/datasets', [DatasetsController::class, 'index'])->name('datasets.index');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'webLogout'])->name('logout');
});