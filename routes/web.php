<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ComplaintWebController;
use App\Http\Controllers\WorkOrderWebController;
use App\Http\Controllers\DatasetWebController;
use App\Http\Controllers\DatasetFieldWebController;
use App\Http\Controllers\DatasetRecordWebController;
use App\Http\Controllers\UserWebController;
use App\Http\Controllers\RoleWebController;
use App\Http\Controllers\PermissionWebController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ComplaintImportWebController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\GisAnalysisController;
use App\Http\Controllers\GisImportExportController;
use App\Http\Controllers\OperationalGisController;

Route::get('/', function () { return view('welcome'); })->name('home');
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'webLogin'])->middleware('throttle:login');
Route::get('/forgot-password', [PasswordResetController::class, 'create'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware(['guest', 'throttle:login'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'createResetForm'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware(['guest', 'throttle:login'])->name('password.update');

Route::middleware(['auth', 'active', 'permission:complaints.view'])->get('/complaints', [ComplaintWebController::class, 'index'])->name('complaints.index');
Route::middleware(['auth', 'active', 'permission:complaints.import'])->group(function () { Route::get('/complaints/import', [ComplaintImportWebController::class, 'create'])->name('complaints.import'); Route::post('/complaints/import/preview', [ComplaintImportWebController::class, 'preview'])->name('complaints.import.preview'); Route::get('/complaints/import/mapping/{token}', [ComplaintImportWebController::class, 'mapping'])->name('complaints.import.mapping'); Route::post('/complaints/import/review', [ComplaintImportWebController::class, 'review'])->name('complaints.import.review'); Route::get('/complaints/import/review/{token}', [ComplaintImportWebController::class, 'showReview'])->name('complaints.import.review.get'); Route::post('/complaints/import', [ComplaintImportWebController::class, 'store'])->name('complaints.import.store'); });
Route::middleware(['auth', 'active', 'permission:complaints.create'])->group(function () { Route::get('/complaints/create', [ComplaintWebController::class, 'create'])->name('complaints.create'); Route::post('/complaints', [ComplaintWebController::class, 'store'])->name('complaints.store'); });
Route::middleware(['auth', 'active', 'permission:complaints.update|complaints.transition'])->group(function () { Route::get('/complaints/{complaint}/edit', [ComplaintWebController::class, 'edit'])->name('complaints.edit'); Route::put('/complaints/{complaint}', [ComplaintWebController::class, 'update'])->name('complaints.update'); });
Route::middleware(['auth', 'active', 'permission:complaints.convert_to_task'])->group(function () { Route::get('/complaints/{complaint}/convert-to-work-order', [ComplaintWebController::class, 'convertToWorkOrder'])->name('complaints.convert-to-work-order'); Route::post('/complaints/{complaint}/work-order', [ComplaintWebController::class, 'storeWorkOrder'])->name('complaints.work-order.store'); });
Route::middleware(['auth', 'active', 'permission:complaints.update'])->group(function () { Route::get('/complaints/{complaint}/add-to-work-order', [ComplaintWebController::class, 'addToExistingWorkOrder'])->name('complaints.add-to-work-order'); Route::post('/complaints/{complaint}/add-to-work-order', [ComplaintWebController::class, 'storeExistingWorkOrder'])->name('complaints.add-to-work-order.store'); });
Route::middleware(['auth', 'active', 'permission:complaints.delete'])->delete('/complaints/{complaint}', [ComplaintWebController::class, 'destroy'])->name('complaints.destroy');
Route::middleware(['auth', 'active', 'permission:complaints.view'])->get('/complaints/{complaint}', [ComplaintWebController::class, 'show'])->name('complaints.show');
Route::middleware(['auth', 'active', 'permission:complaints.update'])->post('/complaints/{complaint}/gis/features/{feature}', [OperationalGisController::class, 'linkComplaintWeb'])->name('complaints.gis.link');
Route::middleware(['auth', 'active', 'permission:complaints.update'])->delete('/complaints/{complaint}/gis/features/{feature}', [OperationalGisController::class, 'unlinkComplaintWeb'])->name('complaints.gis.unlink');

Route::middleware(['auth', 'active', 'permission:tasks.view'])->get('/work-orders', [WorkOrderWebController::class, 'index'])->name('work-orders.index');
Route::middleware(['auth', 'active', 'permission:tasks.create'])->group(function () { Route::get('/work-orders/create', [WorkOrderWebController::class, 'create'])->name('work-orders.create'); Route::post('/work-orders', [WorkOrderWebController::class, 'store'])->name('work-orders.store'); });
Route::middleware(['auth', 'active', 'permission:tasks.view'])->get('/work-orders/{workOrder}', [WorkOrderWebController::class, 'show'])->name('work-orders.show');
Route::middleware(['auth', 'active', 'permission:tasks.update'])->post('/work-orders/{workOrder}/gis/features/{feature}', [OperationalGisController::class, 'linkWorkOrderWeb'])->name('work-orders.gis.link');
Route::middleware(['auth', 'active', 'permission:tasks.update'])->delete('/work-orders/{workOrder}/gis/features/{feature}', [OperationalGisController::class, 'unlinkWorkOrderWeb'])->name('work-orders.gis.unlink');
Route::middleware(['auth', 'active', 'permission:tasks.update|tasks.assign|tasks.transition'])->put('/work-orders/{workOrder}', [WorkOrderWebController::class, 'update'])->name('work-orders.update');
Route::middleware(['auth', 'active', 'permission:tasks.update'])->post('/work-orders/{workOrder}/convert-to-complaint', [WorkOrderWebController::class, 'convertToComplaint'])->name('work-orders.convert-to-complaint');
Route::middleware(['auth', 'active', 'permission:tasks.delete'])->delete('/work-orders/{workOrder}', [WorkOrderWebController::class, 'destroy'])->name('work-orders.destroy');

Route::middleware(['auth', 'active', 'permission:gis.view|complaints.view|tasks.view|datasets.view'])->group(function () {
    Route::get('/gis', [DashboardController::class, 'index'])->name('gis.index');
    Route::get('/map', [GisController::class, 'index'])->name('map.index');
    Route::get('/map/data', [GisController::class, 'operationalData'])->name('map.data');
});

Route::middleware(['auth', 'active', 'permission:datasets.view'])->get('/datasets', [DatasetWebController::class, 'index'])->name('datasets.index');
Route::middleware(['auth', 'active', 'permission:datasets.create'])->group(function () { Route::get('/datasets/create', [DatasetWebController::class, 'create'])->name('datasets.create'); Route::post('/datasets', [DatasetWebController::class, 'store'])->name('datasets.store'); Route::get('/datasets/import', [GisImportExportController::class, 'create'])->name('datasets.import'); Route::post('/datasets/import/preview', [GisImportExportController::class, 'preview'])->name('datasets.import.preview'); Route::post('/datasets/import/confirm', [GisImportExportController::class, 'confirm'])->name('datasets.import.confirm'); });
Route::middleware(['auth', 'active', 'permission:datasets.view'])->group(function () { Route::get('/datasets/{dataset}/validation', [\App\Http\Controllers\GisValidationController::class, 'web'])->name('datasets.validation'); Route::get('/datasets/{dataset}/export/geojson', [GisImportExportController::class, 'exportGeoJson'])->name('datasets.export.geojson'); Route::get('/datasets/{dataset}/export/csv', [GisImportExportController::class, 'exportCsv'])->name('datasets.export.csv'); Route::get('/datasets/{dataset}/export/shapefile', [GisImportExportController::class, 'exportShapefile'])->name('datasets.export.shapefile'); Route::get('/datasets/{dataset}/fields/data', [DatasetFieldWebController::class, 'data'])->name('datasets.fields.data'); Route::get('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'index'])->name('datasets.fields.index'); Route::get('/datasets/{dataset}/records', [DatasetRecordWebController::class, 'index'])->name('datasets.records.index'); Route::get('/datasets/{dataset}/features', [\App\Http\Controllers\GisFeatureController::class, 'index'])->name('datasets.features.index'); Route::get('/datasets/{dataset}/features/query', [\App\Http\Controllers\GisFeatureController::class, 'query'])->name('datasets.features.query'); Route::get('/datasets/{dataset}/features/nearest', [\App\Http\Controllers\GisFeatureController::class, 'nearest'])->name('datasets.features.nearest'); Route::post('/datasets/{dataset}/features/buffer', [\App\Http\Controllers\GisFeatureController::class, 'buffer'])->name('datasets.features.buffer'); Route::post('/datasets/{dataset}/features/measure', [\App\Http\Controllers\GisFeatureController::class, 'measure'])->name('datasets.features.measure'); Route::post('/datasets/{dataset}/analysis', [GisAnalysisController::class, 'analyze'])->name('datasets.analysis'); Route::get('/datasets/{dataset}', [DatasetWebController::class, 'show'])->name('datasets.show'); });
Route::middleware(['auth', 'active', 'permission:users.view'])->get('/users', [UserWebController::class, 'index'])->name('users.index');
Route::middleware(['auth', 'active', 'permission:users.create'])->group(function () { Route::get('/users/create', [UserWebController::class, 'create'])->name('users.create'); Route::post('/users', [UserWebController::class, 'store'])->name('users.store'); });
Route::middleware(['auth', 'active', 'permission:users.view'])->get('/users/{user}', [UserWebController::class, 'show'])->name('users.show');
Route::middleware(['auth', 'active', 'permission:users.update'])->group(function () { Route::get('/users/{user}/edit', [UserWebController::class, 'edit'])->name('users.edit'); Route::put('/users/{user}', [UserWebController::class, 'update'])->name('users.update'); });
Route::middleware(['auth', 'active', 'permission:users.delete'])->delete('/users/{user}', [UserWebController::class, 'destroy'])->name('users.destroy');
Route::middleware(['auth', 'active', 'permission:roles.view'])->get('/roles', [RoleWebController::class, 'index'])->name('roles.index');
Route::middleware(['auth', 'active', 'permission:roles.update'])->group(function () { Route::get('/roles/{role}/edit', [RoleWebController::class, 'edit'])->name('roles.edit'); Route::put('/roles/{role}', [RoleWebController::class, 'update'])->name('roles.update'); });
Route::middleware(['auth', 'active', 'permission:permissions.view'])->get('/permissions', [PermissionWebController::class, 'index'])->name('permissions.index');
Route::middleware(['auth', 'active', 'permission:datasets.create'])->group(function () { Route::get('/datasets/{dataset}/fields/create', [DatasetFieldWebController::class, 'create'])->name('datasets.fields.create'); Route::post('/datasets/{dataset}/fields', [DatasetFieldWebController::class, 'store'])->name('datasets.fields.store'); Route::get('/datasets/{dataset}/records/create', [DatasetRecordWebController::class, 'create'])->name('datasets.records.create'); Route::post('/datasets/{dataset}/records', [DatasetRecordWebController::class, 'store'])->name('datasets.records.store'); Route::post('/datasets/{dataset}/features', [\App\Http\Controllers\GisFeatureController::class, 'store'])->name('datasets.features.store'); });
Route::middleware(['auth', 'active', 'permission:datasets.update'])->group(function () { Route::get('/datasets/{dataset}/edit', [DatasetWebController::class, 'edit'])->name('datasets.edit'); Route::put('/datasets/{dataset}/features/{feature}', [\App\Http\Controllers\GisFeatureController::class, 'update'])->name('datasets.features.update'); Route::put('/datasets/{dataset}', [DatasetWebController::class, 'update'])->name('datasets.update'); Route::get('/datasets/{dataset}/fields/{field}/edit', [DatasetFieldWebController::class, 'edit'])->name('datasets.fields.edit'); Route::put('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'update'])->name('datasets.fields.update'); Route::get('/datasets/{dataset}/records/{record}/edit', [DatasetRecordWebController::class, 'edit'])->name('datasets.records.edit'); Route::put('/datasets/{dataset}/records/{record}', [DatasetRecordWebController::class, 'update'])->name('datasets.records.update'); });
Route::middleware(['auth', 'active', 'permission:datasets.delete'])->group(function () { Route::delete('/datasets/{dataset}/features/{feature}', [\App\Http\Controllers\GisFeatureController::class, 'destroy'])->name('datasets.features.destroy'); Route::delete('/datasets/{dataset}/fields/{field}', [DatasetFieldWebController::class, 'destroy'])->name('datasets.fields.destroy'); Route::delete('/datasets/{dataset}/records/{record}', [DatasetRecordWebController::class, 'destroy'])->name('datasets.records.destroy'); });
Route::middleware(['auth', 'active'])->post('/logout', [LoginController::class, 'webLogout'])->name('logout');

Route::middleware(['auth', 'active', 'permission:reports.view|complaints.view|tasks.view'])->get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::middleware(['auth', 'active', 'permission:reports.export|complaints.export'])->get('/reports/complaints/export', [ReportController::class, 'exportComplaints'])->name('reports.complaints.export');
Route::middleware(['auth', 'active', 'permission:reports.export|complaints.export'])->get('/reports/complaints/{complaint}/pdf', [ReportController::class, 'complaintPdf'])->name('reports.complaints.pdf');
Route::middleware(['auth', 'active', 'permission:reports.export|tasks.export'])->get('/reports/work-orders/export', [ReportController::class, 'exportWorkOrders'])->name('reports.work-orders.export');
Route::middleware(['auth', 'active', 'permission:reports.export|tasks.export'])->get('/reports/work-orders/{workOrder}/pdf', [ReportController::class, 'workOrderPdf'])->name('reports.work-orders.pdf');
Route::middleware(['auth', 'active', 'permission:complaints.view|tasks.view'])->get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
