<?php

use App\Http\Controllers\Admin\ArchiveController as AdminArchiveController;
use App\Http\Controllers\Admin\ClassifierController as AdminClassifierController;
use App\Http\Controllers\Admin\InstitutionController as AdminInstitutionController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\RbacPermissionController;
use App\Http\Controllers\Admin\RbacRoleController;
use App\Http\Controllers\Admin\RbacUserController;
use App\Http\Controllers\AutomationAssignmentRuleController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationPublishRuleController;
use App\Http\Controllers\AutomationResolutionTermRuleController;
use App\Http\Controllers\GuestProblemController;
use App\Http\Controllers\InstitutionAdminController;
use App\Http\Controllers\ProblemAdminAssignController;
use App\Http\Controllers\ProblemAttachmentController;
use App\Http\Controllers\ProblemController;
use App\Http\Controllers\ProblemInstitutionAssignmentController;
use App\Http\Controllers\ProblemMapController;
use App\Http\Controllers\ProblemMergeController;
use App\Http\Controllers\ProblemReviewController;
use App\Http\Controllers\ProblemStatisticsController;
use App\Http\Controllers\ProblemWatchController;
use App\Http\Controllers\ReporterProblemController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsTeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [GuestProblemController::class, 'landing'])->name('landing');

Route::get('/faq', [\App\Http\Controllers\GuestContentController::class, 'faq'])->name('faq');
Route::get('/privacy', [\App\Http\Controllers\GuestContentController::class, 'privacy'])->name('privacy');
Route::get('/slapukai', [\App\Http\Controllers\GuestContentController::class, 'cookies'])->name('cookies');
Route::get('/contacts', [\App\Http\Controllers\GuestContentController::class, 'contacts'])->name('contacts');
Route::get('/help', [\App\Http\Controllers\GuestContentController::class, 'help'])->name('help');

Route::get('/statistika', fn () => Inertia::render('Guest/PublicStatistics'))->name('public.statistics');
Route::get('/statistika/duomenys', [\App\Http\Controllers\PublicStatisticsController::class, 'data'])->name('public.statistics.data');

Route::get('/reports', [GuestProblemController::class, 'publicMap'])->name('reports');
Route::get('/reports/{problem}', [GuestProblemController::class, 'publicShow'])->name('reports.show');

Route::middleware(['auth', 'verified', 'reporter.terms'])->group(function () {
    Route::get('/reporter/terms', [\App\Http\Controllers\Auth\ReporterTermsController::class, 'show'])
        ->name('reporter.terms');
    Route::post('/reporter/terms', [\App\Http\Controllers\Auth\ReporterTermsController::class, 'store'])
        ->name('reporter.terms.store');

    Route::get('/dashboard', [ProblemController::class, 'index'])->name('dashboard');
    Route::get('/reporter/sarasas', [ReporterProblemController::class, 'reporterSarasas'])->name('reporter.sarasas');
    Route::get('/reporter/statistics', [ReporterProblemController::class, 'reporterStatistics'])->name('reporter.statistics');
    Route::get('/reporter/report', [ReporterProblemController::class, 'reporterReport'])->name('reporter.report');
    Route::post('/reporter/report', [ReporterProblemController::class, 'reporterReportStore'])->name('reporter.report.store');
    Route::post('/reporter/wizard-dismissed', [ReporterProblemController::class, 'reporterWizardDismissed'])->name('reporter.wizardDismissed');
    Route::post('/reporter/terms-accepted', [ReporterProblemController::class, 'reporterTermsAccepted'])->name('reporter.termsAccepted');
    Route::get('/file-upload-settings', \App\Http\Controllers\FileUploadSettingsController::class)->name('file-upload-settings');
    Route::get('/problems/{problem}/review-progress', [ProblemReviewController::class, 'reviewProgress'])->name('problems.reviewProgress');
    Route::post('/problems/{problem}/watch', [ProblemWatchController::class, 'toggle'])->name('problems.watch.toggle');
    Route::delete('/problems/{problem}/attachments/{attachment}', [ProblemAttachmentController::class, 'destroyAttachment'])->name('problems.attachments.destroy');
    Route::post('/problems/{problem}/attachments', [ProblemAttachmentController::class, 'storeAttachments'])->name('problems.attachments.store');

    Route::middleware(['permission:issues.view'])->group(function () {
        Route::middleware(['automation'])->group(function () {
            Route::get('/automation', [AutomationController::class, 'index'])->name('automation.index');
            Route::post('/automation', [AutomationAssignmentRuleController::class, 'store'])->name('automation.store');
            Route::put('/automation/inactive-reporter-setting', [AutomationController::class, 'updateInactiveReporterSetting'])->name('automation.inactiveReporterSetting.update');
            Route::put('/automation/{rule}', [AutomationAssignmentRuleController::class, 'update'])->name('automation.update');
            Route::delete('/automation/{rule}', [AutomationAssignmentRuleController::class, 'destroy'])->name('automation.destroy');
            Route::post('/automation/publish-rules', [AutomationPublishRuleController::class, 'store'])->name('automation.publishRules.store');
            Route::put('/automation/publish-rules/{publish_rule}', [AutomationPublishRuleController::class, 'update'])->name('automation.publishRules.update');
            Route::delete('/automation/publish-rules/{publish_rule}', [AutomationPublishRuleController::class, 'destroy'])->name('automation.publishRules.destroy');
            Route::post('/automation/resolution-term-rules', [AutomationResolutionTermRuleController::class, 'store'])->name('automation.resolutionTermRules.store');
            Route::put('/automation/resolution-term-rules/{resolution_term_rule}', [AutomationResolutionTermRuleController::class, 'update'])->name('automation.resolutionTermRules.update');
            Route::delete('/automation/resolution-term-rules/{resolution_term_rule}', [AutomationResolutionTermRuleController::class, 'destroy'])->name('automation.resolutionTermRules.destroy');
        });
        Route::get('/map', [ProblemMapController::class, 'map'])->name('map');
        Route::get('/map/address-search', [ProblemMapController::class, 'addressSearch'])->name('map.addressSearch');
        Route::get('/problems/assign', [ProblemAdminAssignController::class, 'assign'])->name('problems.assign');
        Route::post('/problems/assign', [ProblemAdminAssignController::class, 'storeAssign'])->name('problems.assign.store');
        Route::post('/problems/assign/email', [ProblemAdminAssignController::class, 'sendAssignEmail'])->name('problems.assign.email');
        Route::get('/problems/export', [ProblemController::class, 'export'])->name('problems.export');
        Route::get('/problems/{problem}/assign', [ProblemAdminAssignController::class, 'assignSingle'])->name('problems.assign.single');
        Route::get('/problems/{problem}/assign-institution', [ProblemInstitutionAssignmentController::class, 'assignInstitution'])->name('problems.assignInstitution');
        Route::post('/problems/{problem}/assign-institution', [ProblemInstitutionAssignmentController::class, 'storeAssignInstitution'])->name('problems.assignInstitution.store');
        Route::post('/problems/{problem}/send-assign-email', [ProblemInstitutionAssignmentController::class, 'sendAssignInstitutionEmail'])->name('problems.assignInstitution.sendEmail');
        Route::patch('/problems/{problem}/return', [ProblemInstitutionAssignmentController::class, 'returnToAdmin'])->name('problems.return');
        Route::post('/problems/{problem}/reassign-institution', [ProblemInstitutionAssignmentController::class, 'reassignInstitution'])->name('problems.reassignInstitution');
        Route::get('/problems/{problem}/merge', [ProblemMergeController::class, 'merge'])->name('problems.merge');
        Route::post('/problems/{problem}/merge', [ProblemMergeController::class, 'storeMerge'])->name('problems.merge.store');
        Route::patch('/problems/{problem}/unmerge', [ProblemMergeController::class, 'unmerge'])->name('problems.unmerge');
        Route::get('/problems/{problem}', [ProblemController::class, 'show'])->name('problems.show');
        Route::put('/problems/{problem}', [ProblemController::class, 'update'])->name('problems.update');
        Route::delete('/problems/{problem}', [ProblemController::class, 'destroy'])->name('problems.destroy');
        Route::patch('/problems/{problem}/status', [ProblemController::class, 'updateStatus'])->name('problems.updateStatus');
        Route::post('/problems/{problem}/review-progress', [ProblemReviewController::class, 'storeReviewProgress'])->name('problems.reviewProgress.store');
        Route::put('/problems/{problem}/review-progress/{entry}', [ProblemReviewController::class, 'updateReviewProgress'])->name('problems.reviewProgress.update');
        Route::delete('/problems/{problem}/review-progress/attachments/{attachment}', [ProblemReviewController::class, 'destroyReviewAttachment'])->name('problems.reviewProgress.attachments.destroy');
        Route::post('/problems/bulk-update-status', [ProblemController::class, 'bulkUpdateStatus'])->name('problems.bulkUpdateStatus');
        Route::post('/problems/bulk-destroy', [ProblemController::class, 'bulkDestroy'])->name('problems.bulkDestroy');
        Route::patch('/problems/{problem}/publish', [ProblemController::class, 'updatePublish'])->name('problems.updatePublish');
        Route::post('/problems/bulk-update-publish', [ProblemController::class, 'bulkUpdatePublish'])->name('problems.bulkUpdatePublish');
    });

    Route::middleware(['permission:statistics.view'])->group(function () {
        Route::get('/statistics', [ProblemStatisticsController::class, 'statistics'])->name('statistics');
        Route::get('/statistics/export', [ProblemStatisticsController::class, 'statisticsExport'])->name('statistics.export');
        Route::get('/statistics/resolution-terms', [ProblemStatisticsController::class, 'statistics'])->name('statistics.resolution-terms');
    });

    Route::get('/profile', [SettingsController::class, 'profile'])->name('profile');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/settings/change-password', [SettingsController::class, 'changePassword'])->name('settings.change-password');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/employees', [SettingsController::class, 'storeEmployee'])->name('settings.employees');
    Route::get('/settings/teams', [SettingsTeamController::class, 'listTeams'])->name('settings.teams.list');
    Route::post('/settings/teams', [SettingsTeamController::class, 'storeTeam'])->name('settings.teams');
    Route::put('/settings/teams/{team}', [SettingsTeamController::class, 'updateTeam'])->name('settings.teams.update');
    Route::delete('/settings/teams/{team}', [SettingsTeamController::class, 'destroyTeam'])->name('settings.teams.destroy');
    Route::post('/settings/teams/{team}/members', [SettingsTeamController::class, 'syncTeamMembers'])->name('settings.teams.members');
    Route::patch('/settings/institution', [SettingsController::class, 'updateInstitution'])->name('settings.institution');
    Route::patch('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::post('/settings/password/email', [SettingsController::class, 'sendPasswordResetLink'])->name('settings.password.email');
    Route::delete('/settings', [SettingsController::class, 'destroy'])->name('settings.destroy');

    Route::get('/institution-admin', [InstitutionAdminController::class, 'index'])->name('institution-admin');

    Route::post('/invitations', [AdminInvitationController::class, 'store']);

    Route::prefix('admin')
        ->middleware(['auth', 'verified', 'admin'])
        ->group(function () {
            Route::get('/', fn () => Inertia::render('Admin/Index'))->name('admin');

            Route::get('/rbac/users', [RbacUserController::class, 'users']);
            Route::post('/rbac/users', [RbacUserController::class, 'createUser']);
            Route::put('/rbac/users/{user}', [RbacUserController::class, 'updateUser']);
            Route::delete('/rbac/users/{user}', [RbacUserController::class, 'deleteUser']);
            Route::post('/rbac/users/{user}/roles', [RbacUserController::class, 'syncUserRoles']);

            Route::get('/rbac/roles', [RbacRoleController::class, 'roles']);
            Route::post('/rbac/roles', [RbacRoleController::class, 'createRole']);
            Route::delete('/rbac/roles/{role}', [RbacRoleController::class, 'deleteRole']);
            Route::post('/rbac/roles/{role}/permissions', [RbacRoleController::class, 'syncRolePermissions']);

            Route::get('/rbac/permissions', [RbacPermissionController::class, 'permissions']);
            Route::post('/rbac/permissions', [RbacPermissionController::class, 'createPermission']);
            Route::delete('/rbac/permissions/{permission}', [RbacPermissionController::class, 'deletePermission']);

            Route::get('/invitations', [AdminInvitationController::class, 'index']);
            Route::post('/invitations/{invitation}/resend', [AdminInvitationController::class, 'resend']);

            Route::get('/password-policy', [\App\Http\Controllers\Admin\PasswordPolicyController::class, 'show']);
            Route::put('/password-policy', [\App\Http\Controllers\Admin\PasswordPolicyController::class, 'update']);
            Route::get('/login-history', [\App\Http\Controllers\Admin\LoginHistoryController::class, 'index']);

            Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditController::class, 'index']);
            Route::get('/audit-logs/export', [\App\Http\Controllers\Admin\AuditController::class, 'export']);
            Route::get('/audit-settings', [\App\Http\Controllers\Admin\AuditController::class, 'settings']);
            Route::put('/audit-settings', [\App\Http\Controllers\Admin\AuditController::class, 'updateSettings']);

            Route::get('/institutions', [AdminInstitutionController::class, 'index']);
            Route::get('/institutions/list', [AdminInstitutionController::class, 'list']);
            Route::post('/institutions', [AdminInstitutionController::class, 'store']);
            Route::put('/institutions/{institution}', [AdminInstitutionController::class, 'update']);
            Route::delete('/institutions/{institution}', [AdminInstitutionController::class, 'destroy']);

            Route::get('/classifiers', [AdminClassifierController::class, 'index']);
            Route::get('/classifiers/types/{type}/natures', [AdminClassifierController::class, 'naturesForType']);
            Route::post('/classifiers/values', [AdminClassifierController::class, 'storeValue']);
            Route::put('/classifiers/values/{value}', [AdminClassifierController::class, 'updateValue']);
            Route::delete('/classifiers/values/{value}', [AdminClassifierController::class, 'destroyValue']);

            Route::get('/archive', [AdminArchiveController::class, 'index']);
            Route::get('/email-notifications', [\App\Http\Controllers\Admin\EmailNotificationController::class, 'index']);
            Route::post('/email-notifications/preview-body', [\App\Http\Controllers\Admin\EmailNotificationController::class, 'previewBody']);
            Route::put('/email-notifications/{emailNotification}', [\App\Http\Controllers\Admin\EmailNotificationController::class, 'update']);

            Route::get('/content-versions', [\App\Http\Controllers\Admin\ContentVersionController::class, 'index']);
            Route::post('/content-versions', [\App\Http\Controllers\Admin\ContentVersionController::class, 'store']);
            Route::put('/content-versions/{contentVersion}', [\App\Http\Controllers\Admin\ContentVersionController::class, 'update']);
            Route::delete('/content-versions/{contentVersion}', [\App\Http\Controllers\Admin\ContentVersionController::class, 'destroy']);
            Route::post('/content-versions/{contentVersion}/publish', [\App\Http\Controllers\Admin\ContentVersionController::class, 'publish']);
            Route::post('/content-versions/{contentVersion}/unpublish', [\App\Http\Controllers\Admin\ContentVersionController::class, 'unpublish']);

            Route::get('/public-statistics-config', [\App\Http\Controllers\Admin\PublicStatisticsConfigController::class, 'show']);
            Route::put('/public-statistics-config', [\App\Http\Controllers\Admin\PublicStatisticsConfigController::class, 'update']);

            Route::get('/file-upload-settings', [\App\Http\Controllers\Admin\FileUploadSettingsController::class, 'show'])
                ->name('admin.file-upload-settings.show');
            Route::put('/file-upload-settings', [\App\Http\Controllers\Admin\FileUploadSettingsController::class, 'update'])
                ->name('admin.file-upload-settings.update');
            Route::put('/archive/retention-rule', [AdminArchiveController::class, 'updateRetentionRule']);
            Route::post('/archive/users/{user}/restore', [AdminArchiveController::class, 'restoreUser']);
            Route::delete('/archive/users/{user}', [AdminArchiveController::class, 'destroyUser']);
            Route::post('/archive/institutions/{institution}/restore', [AdminArchiveController::class, 'restoreInstitution']);
            Route::post('/archive/problems/{problem}/restore', [AdminArchiveController::class, 'restoreProblem']);
            Route::post('/archive/classifier-values/{classifier_value}/restore', [AdminArchiveController::class, 'restoreClassifierValue']);
        });
});

require __DIR__.'/auth.php';
