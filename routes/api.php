<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Auth\PermissionController;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/user', [CurrentUserController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ChangePasswordController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'permission:users.view'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:users.create'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/register', [RegisterController::class, 'register']);
});

Route::middleware(['auth:sanctum', 'permission:users.update'])->group(function () {
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::put('/users/{user}/roles', [UserController::class, 'syncRoles']);
    Route::put('/users/{user}/status', [UserController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'permission:roles.view'])->group(function () {
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:roles.create'])->group(function () {
    Route::post('/roles', [RoleController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:roles.update'])->group(function () {
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
});

Route::middleware(['auth:sanctum', 'permission:permissions.view'])->group(function () {
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
});
