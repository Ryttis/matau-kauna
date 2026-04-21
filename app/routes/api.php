<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\InfoApiController;
use App\Http\Controllers\Api\InternalProblemApiController;
use App\Http\Controllers\Api\ProblemApiController;
use App\Http\Controllers\Api\ReporterProblemApiController;
use App\Http\Controllers\ReporterProblemController;
use App\Support\Content\GuestPublishedContentCatalog;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/register', [AuthApiController::class, 'register']);

Route::get('/info/statistics', [InfoApiController::class, 'statistics']);
Route::get('/info/map', [InfoApiController::class, 'mapProblems']);
Route::get('/content/{type}', [InfoApiController::class, 'content'])
    ->where('type', GuestPublishedContentCatalog::publicJsonRoutePattern());

Route::get('/problems', [ProblemApiController::class, 'index']);
Route::get('/problems/{problem}', [ProblemApiController::class, 'show']);

Route::middleware(['auth:sanctum', 'verified', 'reporter.terms'])->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/me', [AuthApiController::class, 'me']);

    Route::get('/info/file-upload-settings', [InfoApiController::class, 'fileUploadSettings']);

    Route::post('/problems', [ReporterProblemController::class, 'reporterReportStore']);

    Route::post('/reporter/wizard-dismissed', [ReporterProblemController::class, 'reporterWizardDismissed']);
    Route::post('/reporter/report-terms-accepted', [ReporterProblemController::class, 'reporterTermsAccepted']);

    Route::get('/reporter/problems', [ReporterProblemApiController::class, 'index']);
    Route::get('/reporter/statistics', [ReporterProblemApiController::class, 'statistics']);
    Route::get('/reporter/report-meta', [ReporterProblemApiController::class, 'reportMeta']);
    Route::get('/reporter/problems/{problem}', [ReporterProblemApiController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('internal')->group(function () {
    Route::get('/problems', [InternalProblemApiController::class, 'index']);
    Route::post('/problems/assign', [InternalProblemApiController::class, 'assignBulk']);
    Route::post('/problems/bulk-update-status', [InternalProblemApiController::class, 'bulkUpdateStatus']);
    Route::post('/problems/bulk-destroy', [InternalProblemApiController::class, 'bulkDestroy']);
    Route::post('/problems/bulk-update-publish', [InternalProblemApiController::class, 'bulkUpdatePublish']);
    Route::put('/problems/{problem}', [InternalProblemApiController::class, 'update']);
    Route::delete('/problems/{problem}', [InternalProblemApiController::class, 'destroy']);
    Route::patch('/problems/{problem}/status', [InternalProblemApiController::class, 'updateStatus']);
    Route::patch('/problems/{problem}/publish', [InternalProblemApiController::class, 'updatePublish']);
    Route::post('/problems/{problem}/merge', [InternalProblemApiController::class, 'merge']);
    Route::patch('/problems/{problem}/unmerge', [InternalProblemApiController::class, 'unmerge']);
    Route::post('/problems/{problem}/assign-institution', [InternalProblemApiController::class, 'assignInstitution']);
    Route::post('/problems/{problem}/reassign-institution', [InternalProblemApiController::class, 'reassignInstitution']);
    Route::patch('/problems/{problem}/return', [InternalProblemApiController::class, 'returnToAdmin']);
});
