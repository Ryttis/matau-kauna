<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
        ->name('register.store');

    Route::get('register/invite/{token}', [InvitationRegistrationController::class, 'show'])
        ->name('register.invite');

    Route::post('register/invite/{token}', [InvitationRegistrationController::class, 'store'])
        ->name('register.invite.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

});

Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])
    ->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'store'])
    ->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', fn () => Inertia::render('Auth/VerifyEmail', [
        'status' => session('status'),
    ]))->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard', absolute: false).'?verified=1';
    })->middleware(['signed'])->name('verification.verify');

    Route::post('email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('Patvirtinimo nuoroda išsiųsta. Patikrinkite el. paštą.'));
    })->middleware('throttle:6,1')->name('verification.send');

    Route::get('role-select', [\App\Http\Controllers\Auth\RoleSelectController::class, 'show'])
        ->name('role.select');
    Route::post('role-select', [\App\Http\Controllers\Auth\RoleSelectController::class, 'store'])
        ->name('role.select.store');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
