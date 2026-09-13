<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\DatasetWebController;
use App\Http\Controllers\DatasetFieldWebController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'webLogin']);

Route::middleware(['auth', 'active', 'permission:datasets.view'])->group(function () {
    Route::get('/gis', [GisController::class, 'index'])->name('gis.index');
    Route::get('/datasets', [DatasetWebController::class, 'index'])->name('datasets.index');
    Route::get('/datasets/{dataset}', [DatasetWebController::class, 'show'])->name('datasets.show');
    Route::get('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'index'])->name('datasets.fields.index');
});

Route::middleware(['auth', 'active', 'permission:datasets.create'])->group(function () {
    Route::get('/datasets/create', [DatasetWebController::class, 'create'])->name('datasets.create');
    Route::post('/datasets', [DatasetWebController::class, 'store'])->name('datasets.store');
    Route::get('/datasets/{dataset}/fields/create', [DatasetFieldWebController::class, 'create'])->name('datasets.fields.create');
    Route::post('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'store'])->name('datasets.fields.store');
});

Route::middleware(['auth', 'active', 'permission:datasets.update'])->group(function () {
    Route::get('/datasets/{dataset}/edit', [DatasetWebController::class, 'edit'])->name('datasets.edit');
    Route::put('/datasets/{dataset}', [DatasetWebController::class, 'update'])->name('datasets.update');
    Route::get('/datasets/{dataset}/fields/{field}/edit', [DatasetFieldWebController::class, 'edit'])->name('datasets.fields.edit');
    Route::put('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'update'])->name('datasets.fields.update');
});

Route::middleware(['auth', 'active', 'permission:datasets.delete'])->group(function () {
    Route::delete('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'destroy'])->name('datasets.fields.destroy');
});

Route::middleware(['auth', 'active'])->post('/logout', [LoginController::class, 'webLogout'])->name('logout');
