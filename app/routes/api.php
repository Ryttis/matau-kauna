<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\InfoApiController;
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
