<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register/invite/{token}', [InvitationRegistrationController::class, 'show'])
        ->name('register.invite');

    Route::post('register/invite/{token}', [InvitationRegistrationController::class, 'store'])
        ->name('register.invite.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
});

Route::middleware('auth')->group(function () {
    Route::get('role-select', [\App\Http\Controllers\Auth\RoleSelectController::class, 'show'])
        ->name('role.select');
    Route::post('role-select', [\App\Http\Controllers\Auth\RoleSelectController::class, 'store'])
        ->name('role.select.store');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
