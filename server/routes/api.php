<?php

use App\Http\Controllers\Api\DocumentDownloadController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgramCycleController;
use App\Http\Controllers\Api\Staff\ApplicationController as StaffApplicationController;
use App\Http\Controllers\Api\Staff\CatalogController;
use App\Http\Controllers\Api\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\Staff\DeploymentController;
use App\Http\Controllers\Api\Staff\DocumentController as StaffDocumentController;
use App\Http\Controllers\Api\Student\ApplicationController as StudentApplicationController;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Api\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Api\Student\ProfileController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (guest) endpoints
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

/*
|--------------------------------------------------------------------------
| Authenticated endpoints
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    | Shared browsing
    |------------------------------------------------------------------------
    */
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::get('/program-cycles', [ProgramCycleController::class, 'index']);
    Route::get('/program-cycles/{cycle}', [ProgramCycleController::class, 'show']);
    Route::get('/program-cycles/{cycle}/requirements', [ProgramCycleController::class, 'requirements']);
    Route::get('/documents/{document}/download', [DocumentDownloadController::class, 'download']);

    /*
    |--------------------------------------------------------------------------
    | Student endpoints
    |--------------------------------------------------------------------------
    */
    Route::prefix('student')->middleware('role:student')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index']);

        Route::get('/applications', [StudentApplicationController::class, 'index']);
        Route::post('/applications', [StudentApplicationController::class, 'store']);
        Route::get('/applications/{application}', [StudentApplicationController::class, 'show']);
        Route::post('/applications/{application}/submit', [StudentApplicationController::class, 'submit']);
        Route::post('/applications/{application}/withdraw', [StudentApplicationController::class, 'withdraw']);
        Route::delete('/applications/{application}', [StudentApplicationController::class, 'destroy']);

        Route::get('/applications/{application}/documents', [StudentDocumentController::class, 'index']);
        Route::post('/applications/{application}/documents', [StudentDocumentController::class, 'store']);
        Route::delete('/applications/{application}/documents/{document}', [StudentDocumentController::class, 'destroy']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Staff endpoints
    |--------------------------------------------------------------------------
    */
    Route::prefix('staff')->middleware('role:staff')->group(function () {
        Route::get('/dashboard', [StaffDashboardController::class, 'index']);

        Route::get('/applications', [StaffApplicationController::class, 'index']);
        Route::get('/applications/{application}', [StaffApplicationController::class, 'show']);
        Route::post('/applications/{application}/review', [StaffApplicationController::class, 'review']);

        Route::get('/applications/{application}/documents', [StaffDocumentController::class, 'index']);
        Route::put('/applications/{application}/documents/{document}/verify', [StaffDocumentController::class, 'verify']);

        Route::get('/deployments', [DeploymentController::class, 'index']);
        Route::post('/deployments', [DeploymentController::class, 'store']);
        Route::patch('/deployments/{assignment}', [DeploymentController::class, 'update']);

        Route::get('/catalog/programs', [CatalogController::class, 'programs']);
        Route::post('/catalog/programs', [CatalogController::class, 'storeProgram']);
        Route::get('/catalog/programs/{program}', [CatalogController::class, 'showProgram']);
        Route::put('/catalog/programs/{program}', [CatalogController::class, 'updateProgram']);
        Route::delete('/catalog/programs/{program}', [CatalogController::class, 'destroyProgram']);

        Route::get('/catalog/cycles', [CatalogController::class, 'cycles']);
        Route::get('/catalog/cycles/{cycle}', [CatalogController::class, 'showCycle']);
        Route::post('/catalog/cycles', [CatalogController::class, 'storeCycle']);
        Route::put('/catalog/cycles/{cycle}', [CatalogController::class, 'updateCycle']);
        Route::delete('/catalog/cycles/{cycle}', [CatalogController::class, 'destroyCycle']);
        Route::post('/catalog/cycles/{cycle}/publish', [CatalogController::class, 'publishCycle']);
        Route::post('/catalog/cycles/{cycle}/unpublish', [CatalogController::class, 'unpublishCycle']);

        Route::get('/catalog/cycles/{cycle}/requirements', [CatalogController::class, 'cycleRequirements']);
        Route::post('/catalog/cycles/{cycle}/requirements', [CatalogController::class, 'storeCycleRequirement']);
        Route::put('/catalog/cycles/{cycle}/requirements/{requirement}', [CatalogController::class, 'updateCycleRequirement']);
        Route::delete('/catalog/cycles/{cycle}/requirements/{requirement}', [CatalogController::class, 'destroyCycleRequirement']);

        Route::get('/catalog/requirements', [CatalogController::class, 'requirements']);
        Route::post('/catalog/requirements', [CatalogController::class, 'storeRequirement']);
        Route::get('/catalog/requirements/{requirement}', [CatalogController::class, 'showRequirement']);
        Route::put('/catalog/requirements/{requirement}', [CatalogController::class, 'updateRequirement']);
        Route::delete('/catalog/requirements/{requirement}', [CatalogController::class, 'destroyRequirement']);

        Route::get('/catalog/host-agencies', [CatalogController::class, 'hostAgencies']);
        Route::post('/catalog/host-agencies', [CatalogController::class, 'storeHostAgency']);
        Route::put('/catalog/host-agencies/{agency}', [CatalogController::class, 'updateHostAgency']);

        Route::get('/catalog/deployment-sites', [CatalogController::class, 'deploymentSites']);
        Route::post('/catalog/deployment-sites', [CatalogController::class, 'storeDeploymentSite']);
        Route::put('/catalog/deployment-sites/{site}', [CatalogController::class, 'updateDeploymentSite']);
    });
});
