<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\InfoApiController;
use App\Http\Controllers\Api\InternalProblemApiController;
use App\Http\Controllers\Api\InternalTwoFactorApiController;
use App\Http\Controllers\Api\ProblemApiController;
use App\Http\Controllers\Api\ReporterProblemApiController;
use App\Http\Controllers\Api\ViispApiController;
use App\Http\Controllers\ProblemAttachmentController;
use App\Http\Controllers\ProblemReviewController;
use App\Http\Controllers\ProblemWatchController;
use App\Http\Controllers\ReporterProblemController;
use App\Support\Content\GuestPublishedContentCatalog;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/two-factor/challenge', [AuthApiController::class, 'verifyTwoFactorLogin']);
Route::post('/register', [AuthApiController::class, 'register']);

Route::post('/viisp/init', [ViispApiController::class, 'init']);
Route::get('/viisp/callback', [ViispApiController::class, 'callback']);
Route::post('/viisp/exchange', [ViispApiController::class, 'exchange']);

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
    Route::post('/problems/{problem}/watch', [ProblemWatchController::class, 'toggle']);
    Route::post('/problems/{problem}/attachments', [ProblemAttachmentController::class, 'storeAttachments']);
    Route::delete('/problems/{problem}/attachments/{attachment}', [ProblemAttachmentController::class, 'destroyAttachment']);
    Route::get('/problems/{problem}/review-progress', [ProblemReviewController::class, 'reviewProgress']);
    Route::post('/reporter/wizard-dismissed', [ReporterProblemController::class, 'reporterWizardDismissed']);
    Route::post('/reporter/report-terms-accepted', [ReporterProblemController::class, 'reporterTermsAccepted']);

    Route::get('/reporter/problems', [ReporterProblemApiController::class, 'index']);
    Route::get('/reporter/statistics', [ReporterProblemApiController::class, 'statistics']);
    Route::get('/reporter/report-meta', [ReporterProblemApiController::class, 'reportMeta']);
    Route::get('/reporter/problems/{problem}', [ReporterProblemApiController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'verified', \App\Http\Middleware\ApplyActiveRoleContext::class])->prefix('internal')->group(function () {
    Route::middleware('admin')->prefix('two-factor')->group(function () {
        Route::get('/users', [InternalTwoFactorApiController::class, 'index']);
        Route::post('/users/{user}', [InternalTwoFactorApiController::class, 'updateUser']);
        Route::post('/global', [InternalTwoFactorApiController::class, 'updateGlobal']);
        Route::post('/disable-all-staff', [InternalTwoFactorApiController::class, 'disableAllStaff']);
    });

    Route::get('/institutions', [InternalProblemApiController::class, 'institutionsForAssign']);

    Route::get('/problems', [InternalProblemApiController::class, 'index']);
    Route::post('/problems/assign', [InternalProblemApiController::class, 'assignBulk']);
    Route::post('/problems/bulk-update-status', [InternalProblemApiController::class, 'bulkUpdateStatus']);
    Route::post('/problems/bulk-destroy', [InternalProblemApiController::class, 'bulkDestroy']);
    Route::post('/problems/bulk-update-publish', [InternalProblemApiController::class, 'bulkUpdatePublish']);

    Route::match(['put', 'post'], '/problems/{problem}', [InternalProblemApiController::class, 'update']);
    Route::delete('/problems/{problem}', [InternalProblemApiController::class, 'destroy']);
    Route::patch('/problems/{problem}/status', [InternalProblemApiController::class, 'updateStatus']);
    Route::patch('/problems/{problem}/publish', [InternalProblemApiController::class, 'updatePublish']);
    Route::post('/problems/{problem}/merge', [InternalProblemApiController::class, 'merge']);
    Route::patch('/problems/{problem}/unmerge', [InternalProblemApiController::class, 'unmerge']);
    Route::get('/problems/{problem}/assign-institution-options', [InternalProblemApiController::class, 'assignInstitutionOptions']);
    Route::post('/problems/{problem}/assign-institution', [InternalProblemApiController::class, 'assignInstitution']);
    Route::post('/problems/{problem}/reassign-institution', [InternalProblemApiController::class, 'reassignInstitution']);
    Route::patch('/problems/{problem}/return', [InternalProblemApiController::class, 'returnToAdmin']);

    Route::get('/problems/{problem}/review-progress', [ProblemReviewController::class, 'reviewProgress']);
    Route::post('/problems/{problem}/review-progress', [ProblemReviewController::class, 'storeReviewProgress']);
    Route::match(['put', 'post'], '/problems/{problem}/review-progress/{entry}', [ProblemReviewController::class, 'updateReviewProgress']);
    Route::delete('/problems/{problem}/review-progress/attachments/{attachment}', [ProblemReviewController::class, 'destroyReviewAttachment']);
});
