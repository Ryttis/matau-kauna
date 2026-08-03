<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\AuthorizesAutomation;
use App\Http\Requests\Automation\InactiveReporterSettingRequest;
use App\Http\Requests\Automation\ResolvedProblemPrivacySettingRequest;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\ResolvedProblemPrivacySetting;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    use AuthorizesAutomation;

    public function __construct(
        private AuditLogService $auditLogService,
        private AutomationService $automationService,
    ) {}

    public function index(): Response
    {
        $this->authorizeAutomation();
        $data = $this->automationService->getIndexData();

        return Inertia::render('Automation/Index', $data);
    }

    public function updateInactiveReporterSetting(InactiveReporterSettingRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $validated = $request->validated();
        $setting = InactiveReporterDeletionSetting::firstOrCreateDefault();
        $setting->update([
            'inactivity_days' => (int) $validated['inactivity_days'],
            'is_active' => (bool) ($validated['is_active'] ?? $setting->is_active),
        ]);
        $this->auditLogService->logUserAction($request->user(), 'automation.inactive_reporter_setting.update', 'InactiveReporterDeletionSetting', $setting->id, AuditLogResult::Success);

        return redirect()->route('automation.index')->with('success', 'Nebenaudojamų paskyrų nustatymai atnaujinti.');
    }

    public function updateResolvedProblemPrivacySetting(ResolvedProblemPrivacySettingRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $validated = $request->validated();
        $setting = ResolvedProblemPrivacySetting::firstOrCreateDefault();
        $setting->update([
            'days_after_resolution' => (int) $validated['days_after_resolution'],
            'is_active' => (bool) ($validated['is_active'] ?? $setting->is_active),
        ]);
        $this->auditLogService->logUserAction($request->user(), 'automation.resolved_problem_privacy_setting.update', 'ResolvedProblemPrivacySetting', $setting->id, AuditLogResult::Success);

        return redirect()->route('automation.index')->with('success', 'Išnagrinėtų problemų paslėpimo nustatymai atnaujinti.');
    }
}
