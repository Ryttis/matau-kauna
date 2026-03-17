<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Requests\Automation\AssignmentRuleRequest;
use App\Http\Requests\Automation\InactiveReporterSettingRequest;
use App\Http\Requests\Automation\PublishRuleRequest;
use App\Http\Requests\Automation\ResolutionTermRuleRequest;
use App\Models\InactiveReporterDeletionSetting;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
use App\Models\ProblemResolutionTermRule;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService,
        private AutomationService $automationService
    ) {}

    private function authorizeAutomation(): void
    {
        $user = request()->user();

        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }
    }

    public function index(): Response
    {
        $this->authorizeAutomation();
        $data = $this->automationService->getIndexData();

        return Inertia::render('Automation/Index', $data);
    }

    public function store(AssignmentRuleRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizeAssignmentRuleData($request->validated());
            $rule = ProblemAutoAssignmentRule::create($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.create', 'ProblemAutoAssignmentRule', $rule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Taisyklė pridėta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.create', 'ProblemAutoAssignmentRule', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function update(AssignmentRuleRequest $request, ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizeAssignmentRuleData($request->validated());
            $rule->update($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.update', 'ProblemAutoAssignmentRule', $rule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Taisyklė atnaujinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.update', 'ProblemAutoAssignmentRule', $rule->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function destroy(ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $rule->id;
        try {
            $rule->delete();
            $this->auditLogService->logUserAction(request()->user(), 'automation.assignment_rule.destroy', 'ProblemAutoAssignmentRule', $id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Taisyklė pašalinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction(request()->user(), 'automation.assignment_rule.destroy', 'ProblemAutoAssignmentRule', $id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function storePublishRule(PublishRuleRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizePublishRuleData($request->validated());
            $publishRule = ProblemAutoPublishRule::create($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.create', 'ProblemAutoPublishRule', $publishRule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pridėta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.create', 'ProblemAutoPublishRule', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function updatePublishRule(PublishRuleRequest $request, ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizePublishRuleData($request->validated());
            $publish_rule->update($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.update', 'ProblemAutoPublishRule', $publish_rule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė atnaujinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.update', 'ProblemAutoPublishRule', $publish_rule->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function destroyPublishRule(ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $publish_rule->id;
        try {
            $publish_rule->delete();
            $this->auditLogService->logUserAction(request()->user(), 'automation.publish_rule.destroy', 'ProblemAutoPublishRule', $id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pašalinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction(request()->user(), 'automation.publish_rule.destroy', 'ProblemAutoPublishRule', $id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function storeResolutionTermRule(ResolutionTermRuleRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizeResolutionTermRuleData($request->validated());
            $termRule = ProblemResolutionTermRule::create($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.create', 'ProblemResolutionTermRule', $termRule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pridėta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.create', 'ProblemResolutionTermRule', null, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function updateResolutionTermRule(ResolutionTermRuleRequest $request, ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        try {
            $data = $this->automationService->normalizeResolutionTermRuleData($request->validated());
            $resolution_term_rule->update($data);
            $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.update', 'ProblemResolutionTermRule', $resolution_term_rule->id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė atnaujinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.update', 'ProblemResolutionTermRule', $resolution_term_rule->id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
    }

    public function destroyResolutionTermRule(ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $resolution_term_rule->id;
        try {
            $resolution_term_rule->delete();
            $this->auditLogService->logUserAction(request()->user(), 'automation.resolution_term_rule.destroy', 'ProblemResolutionTermRule', $id, AuditLogResult::Success);
            return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pašalinta.');
        } catch (\Throwable $e) {
            $this->auditLogService->logUserAction(request()->user(), 'automation.resolution_term_rule.destroy', 'ProblemResolutionTermRule', $id, AuditLogResult::Error, $e->getMessage());
            throw $e;
        }
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
}
