<?php

use App\Http\Controllers\ProblemController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\RbacController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ProblemController::class, 'index'])->name('dashboard');
    Route::patch('/problems/{problem}/status', [ProblemController::class, 'updateStatus'])->name('problems.updateStatus');
    Route::get('/problems/export', [ProblemController::class, 'export'])->name('problems.export');

    Route::prefix('admin')
        ->middleware(['auth', 'verified', 'admin'])
        ->group(function () {
            Route::get('/', fn () => Inertia::render('Admin/Index'))->name('admin');

            // USERS
            Route::get('/rbac/users', [RbacController::class, 'users']);
            Route::post('/rbac/users', [RbacController::class, 'createUser']);
            Route::put('/rbac/users/{user}', [RbacController::class, 'updateUser']);
            Route::delete('/rbac/users/{user}', [RbacController::class, 'deleteUser']);
            Route::post('/rbac/users/{user}/roles', [RbacController::class, 'syncUserRoles']);

            // ROLES
            Route::get('/rbac/roles', [RbacController::class, 'roles']);
            Route::post('/rbac/roles', [RbacController::class, 'createRole']);
            Route::delete('/rbac/roles/{role}', [RbacController::class, 'deleteRole']);
            Route::post('/rbac/roles/{role}/groups', [RbacController::class, 'syncRoleGroups']);

            // GROUPS
            Route::get('/rbac/groups', [RbacController::class, 'groups']);
            Route::post('/rbac/groups', [RbacController::class, 'createGroup']);
            Route::delete('/rbac/groups/{group}', [RbacController::class, 'deleteGroup']);
            Route::post('/rbac/groups/{group}/permissions', [RbacController::class, 'syncGroupPermissions']);

            // PERMISSIONS
            Route::get('/rbac/permissions', [RbacController::class, 'permissions']);
            Route::post('/rbac/permissions', [RbacController::class, 'createPermission']);
            Route::delete('/rbac/permissions/{permission}', [RbacController::class, 'deletePermission']);
        });
});

require __DIR__ . '/auth.php';
