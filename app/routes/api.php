<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\InfoApiController;
use App\Http\Controllers\Api\ProblemApiController;
use App\Http\Controllers\ProblemController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/register', [AuthApiController::class, 'register']);

Route::get('/info/statistics', [InfoApiController::class, 'statistics']);
Route::get('/problems', [ProblemApiController::class, 'index']);
Route::get('/problems/{problem}', [ProblemApiController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::get('/info/file-upload-settings', [InfoApiController::class, 'fileUploadSettings']);
    Route::post('/problems', [ProblemController::class, 'reporterReportStore']);
});

