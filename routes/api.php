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
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DatasetFieldController;
use App\Http\Controllers\DatasetRecordController;
use App\Http\Controllers\DatasetRelationshipController;
use App\Http\Controllers\DatasetImportController;

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

// Complaints
Route::middleware(['auth:sanctum', 'permission:complaints.view'])->group(function () {
    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:complaints.create'])->group(function () {
    Route::post('/complaints', [ComplaintController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:complaints.update'])->group(function () {
    Route::put('/complaints/{complaint}', [ComplaintController::class, 'update']);
});

// Work Orders (using tasks permissions)
Route::middleware(['auth:sanctum', 'permission:tasks.view'])->group(function () {
    Route::get('/work-orders', [WorkOrderController::class, 'index']);
    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:tasks.create'])->group(function () {
    Route::post('/work-orders', [WorkOrderController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:tasks.update'])->group(function () {
    Route::put('/work-orders/{workOrder}', [WorkOrderController::class, 'update']);
});

// Datasets
Route::middleware(['auth:sanctum', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets', [DatasetController::class, 'index']);
    Route::get('/datasets/{dataset}', [DatasetController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.create'])->group(function () {
    Route::post('/datasets', [DatasetController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.update'])->group(function () {
    Route::put('/datasets/{dataset}', [DatasetController::class, 'update']);
});

// Dataset Fields
Route::middleware(['auth:sanctum', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets/{dataset}/fields', [DatasetFieldController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.create'])->group(function () {
    Route::post('/datasets/{dataset}/fields', [DatasetFieldController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.update'])->group(function () {
    Route::put('/dataset-fields/{field}', [DatasetFieldController::class, 'update']);
});

// Dataset Records
Route::middleware(['auth:sanctum', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets/{dataset}/records', [DatasetRecordController::class, 'index']);
    Route::get('/datasets/{dataset}/records/{record}', [DatasetRecordController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.create'])->group(function () {
    Route::post('/datasets/{dataset}/records', [DatasetRecordController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.update'])->group(function () {
    Route::put('/datasets/{dataset}/records/{record}', [DatasetRecordController::class, 'update']);
});

// Dataset Relationships
Route::middleware(['auth:sanctum', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets/{dataset}/relationships', [DatasetRelationshipController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.create'])->group(function () {
    Route::post('/datasets/{dataset}/relationships', [DatasetRelationshipController::class, 'store']);
});

// Dataset Imports
Route::middleware(['auth:sanctum', 'permission:datasets.view'])->group(function () {
    Route::get('/datasets/{dataset}/imports', [DatasetImportController::class, 'index']);
    Route::get('/datasets/{dataset}/imports/{import}', [DatasetImportController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:datasets.create'])->group(function () {
    Route::post('/datasets/{dataset}/imports', [DatasetImportController::class, 'store']);
});