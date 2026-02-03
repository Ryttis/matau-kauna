<?php

use App\Http\Controllers\Admin\InstitutionController as AdminInstitutionController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\RbacController;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(['permission:issues.view'])->group(function () {
        Route::get('/dashboard', [ProblemController::class, 'index'])->name('dashboard');
        Route::get('/map', [ProblemController::class, 'map'])->name('map');
        Route::get('/problems/assign', [ProblemController::class, 'assign'])->name('problems.assign');
        Route::post('/problems/assign', [ProblemController::class, 'storeAssign'])->name('problems.assign.store');
        Route::post('/problems/assign/email', [ProblemController::class, 'sendAssignEmail'])->name('problems.assign.email');
        Route::get('/problems/export', [ProblemController::class, 'export'])->name('problems.export');
        Route::get('/problems/{problem}/assign', [ProblemController::class, 'assignSingle'])->name('problems.assign.single');
        Route::get('/problems/{problem}/assign-institution', [ProblemController::class, 'assignInstitution'])->name('problems.assignInstitution');
        Route::post('/problems/{problem}/assign-institution', [ProblemController::class, 'storeAssignInstitution'])->name('problems.assignInstitution.store');
        Route::get('/problems/{problem}/review-progress', [ProblemController::class, 'reviewProgress'])->name('problems.reviewProgress');
        Route::get('/problems/{problem}/merge', [ProblemController::class, 'merge'])->name('problems.merge');
        Route::get('/problems/{problem}', [ProblemController::class, 'show'])->name('problems.show');
        Route::put('/problems/{problem}', [ProblemController::class, 'update'])->name('problems.update');
        Route::delete('/problems/{problem}', [ProblemController::class, 'destroy'])->name('problems.destroy');
        Route::patch('/problems/{problem}/status', [ProblemController::class, 'updateStatus'])->name('problems.updateStatus');
        Route::post('/problems/bulk-update-status', [ProblemController::class, 'bulkUpdateStatus'])->name('problems.bulkUpdateStatus');
    });

    Route::middleware(['permission:statistics.view'])->group(function () {
        Route::get('/statistics', [ProblemController::class, 'statistics'])->name('statistics');
        Route::get('/statistics/resolution-terms', [ProblemController::class, 'statistics'])->name('statistics.resolution-terms');
        Route::get('/statistics/categories', [ProblemController::class, 'statistics'])->name('statistics.categories');
        Route::get('/statistics/institutions', [ProblemController::class, 'statistics'])->name('statistics.institutions');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/settings/change-password', [SettingsController::class, 'changePassword'])->name('settings.change-password');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/employees', [SettingsController::class, 'storeEmployee'])->name('settings.employees');
    Route::patch('/settings/institution', [SettingsController::class, 'updateInstitution'])->name('settings.institution');
    Route::patch('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::post('/settings/password/email', [SettingsController::class, 'sendPasswordResetLink'])->name('settings.password.email');
    Route::delete('/settings', [SettingsController::class, 'destroy'])->name('settings.destroy');

    Route::post('/invitations', [AdminInvitationController::class, 'store']);

    Route::prefix('admin')
        ->middleware(['auth', 'verified', 'admin'])
        ->group(function () {
            Route::get('/', fn () => Inertia::render('Admin/Index'))->name('admin');

            Route::get('/rbac/users', [RbacController::class, 'users']);
            Route::post('/rbac/users', [RbacController::class, 'createUser']);
            Route::put('/rbac/users/{user}', [RbacController::class, 'updateUser']);
            Route::delete('/rbac/users/{user}', [RbacController::class, 'deleteUser']);
            Route::post('/rbac/users/{user}/roles', [RbacController::class, 'syncUserRoles']);

            Route::get('/rbac/roles', [RbacController::class, 'roles']);
            Route::post('/rbac/roles', [RbacController::class, 'createRole']);
            Route::delete('/rbac/roles/{role}', [RbacController::class, 'deleteRole']);
            Route::post('/rbac/roles/{role}/groups', [RbacController::class, 'syncRoleGroups']);

            Route::get('/rbac/groups', [RbacController::class, 'groups']);
            Route::post('/rbac/groups', [RbacController::class, 'createGroup']);
            Route::delete('/rbac/groups/{group}', [RbacController::class, 'deleteGroup']);
            Route::post('/rbac/groups/{group}/permissions', [RbacController::class, 'syncGroupPermissions']);

            Route::get('/rbac/permissions', [RbacController::class, 'permissions']);
            Route::post('/rbac/permissions', [RbacController::class, 'createPermission']);
            Route::delete('/rbac/permissions/{permission}', [RbacController::class, 'deletePermission']);

            Route::get('/invitations', [AdminInvitationController::class, 'index']);
            Route::post('/invitations/{invitation}/resend', [AdminInvitationController::class, 'resend']);

            Route::get('/institutions', [AdminInstitutionController::class, 'index']);
            Route::get('/institutions/list', [AdminInstitutionController::class, 'list']);
            Route::post('/institutions', [AdminInstitutionController::class, 'store']);
            Route::put('/institutions/{institution}', [AdminInstitutionController::class, 'update']);
        });
});

require __DIR__ . '/auth.php';
