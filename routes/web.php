<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ComplaintWebController;
use App\Http\Controllers\DatasetWebController;
use App\Http\Controllers\DatasetFieldWebController;
use App\Http\Controllers\DatasetRecordWebController;
use App\Http\Controllers\UserWebController;
use App\Http\Controllers\RoleWebController;
use App\Http\Controllers\PermissionWebController;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () { return view('welcome'); })->name('home');
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'webLogin']);

Route::middleware(['auth', 'active', 'permission:complaints.view'])->group(function () {
    Route::get('/complaints', [ComplaintWebController::class, 'index'])->name('complaints.index');
});

Route::middleware(['auth', 'active', 'permission:complaints.create'])->group(function () {
    Route::get('/complaints/create', [ComplaintWebController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintWebController::class, 'store'])->name('complaints.store');
});

Route::middleware(['auth', 'active', 'permission:complaints.update'])->group(function () {
    Route::get('/complaints/{complaint}/edit', [ComplaintWebController::class, 'edit'])->name('complaints.edit');
    Route::put('/complaints/{complaint}', [ComplaintWebController::class, 'update'])->name('complaints.update');
});

Route::middleware(['auth', 'active', 'permission:complaints.delete'])->delete('/complaints/{complaint}', [ComplaintWebController::class, 'destroy'])->name('complaints.destroy');

Route::middleware(['auth', 'active', 'permission:complaints.view'])->get('/complaints/{complaint}', [ComplaintWebController::class, 'show'])->name('complaints.show');

Route::middleware(['auth', 'active', 'permission:datasets.view'])->group(function () {
    Route::get('/gis', [DashboardController::class, 'index'])->name('gis.index');
    Route::get('/map', [GisController::class, 'index'])->name('map.index');
    Route::get('/datasets', [DatasetWebController::class, 'index'])->name('datasets.index');
});

Route::middleware(['auth', 'active', 'permission:datasets.create'])->group(function () {
    Route::get('/datasets/create', [DatasetWebController::class, 'create'])->name('datasets.create');
    Route::post('/datasets', [DatasetWebController::class, 'store'])->name('datasets.store');
});

Route::middleware(['auth', 'active', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'index'])->name('datasets.fields.index');
    Route::get('/datasets/{dataset}/records', [DatasetRecordWebController::class, 'index'])->name('datasets.records.index');
    Route::get('/datasets/{dataset}', [DatasetWebController::class, 'show'])->name('datasets.show');
});

Route::middleware(['auth', 'active', 'permission:users.view'])->group(function () {
    Route::get('/users', [UserWebController::class, 'index'])->name('users.index');
});

Route::middleware(['auth', 'active', 'permission:users.create'])->group(function () {
    Route::get('/users/create', [UserWebController::class, 'create'])->name('users.create');
    Route::post('/users', [UserWebController::class, 'store'])->name('users.store');
});

Route::middleware(['auth', 'active', 'permission:users.view'])->group(function () {
    Route::get('/users/{user}', [UserWebController::class, 'show'])->name('users.show');
});

Route::middleware(['auth', 'active', 'permission:users.update'])->group(function () {
    Route::get('/users/{user}/edit', [UserWebController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserWebController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', 'active', 'permission:users.delete'])->delete('/users/{user}', [UserWebController::class, 'destroy'])->name('users.destroy');

Route::middleware(['auth', 'active', 'permission:roles.view'])->group(function () {
    Route::get('/roles', [RoleWebController::class, 'index'])->name('roles.index');
});

Route::middleware(['auth', 'active', 'permission:roles.update'])->group(function () {
    Route::get('/roles/{role}/edit', [RoleWebController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleWebController::class, 'update'])->name('roles.update');
});

Route::middleware(['auth', 'active', 'permission:permissions.view'])->group(function () {
    Route::get('/permissions', [PermissionWebController::class, 'index'])->name('permissions.index');
});

Route::middleware(['auth', 'active', 'permission:datasets.create'])->group(function () {
    Route::get('/datasets/{dataset}/fields/create', [DatasetFieldWebController::class, 'create'])->name('datasets.fields.create');
    Route::post('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'store'])->name('datasets.fields.store');
    Route::get('/datasets/{dataset}/records/create', [DatasetRecordWebController::class, 'create'])->name('datasets.records.create');
    Route::post('/datasets/{dataset}/records', [DatasetRecordWebController::class, 'store'])->name('datasets.records.store');
});

Route::middleware(['auth', 'active', 'permission:datasets.update'])->group(function () {
    Route::get('/datasets/{dataset}/edit', [DatasetWebController::class, 'edit'])->name('datasets.edit');
    Route::put('/datasets/{dataset}', [DatasetWebController::class, 'update'])->name('datasets.update');
    Route::get('/datasets/{dataset}/fields/{field}/edit', [DatasetFieldWebController::class, 'edit'])->name('datasets.fields.edit');
    Route::put('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'update'])->name('datasets.fields.update');
    Route::get('/datasets/{dataset}/records/{record}/edit', [DatasetRecordWebController::class, 'edit'])->name('datasets.records.edit');
    Route::put('/datasets/{dataset}/records/{record}', [DatasetRecordWebController::class, 'update'])->name('datasets.records.update');
});

Route::middleware(['auth', 'active', 'permission:datasets.delete'])->group(function () {
    Route::delete('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'destroy'])->name('datasets.fields.destroy');
    Route::delete('/datasets/{dataset}/records/{record}', [DatasetRecordWebController::class, 'destroy'])->name('datasets.records.destroy');
});

Route::middleware(['auth', 'active'])->post('/logout', [LoginController::class, 'webLogout'])->name('logout');
