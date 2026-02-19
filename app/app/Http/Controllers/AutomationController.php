<?php

namespace App\Http\Controllers;

use App\Http\Requests\Automation\AssignmentRuleRequest;
use App\Http\Requests\Automation\PublishRuleRequest;
use App\Http\Requests\Automation\ResolutionTermRuleRequest;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
use App\Models\ProblemResolutionTermRule;
use App\Models\UserRole;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
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
        $data = $this->automationService->normalizeAssignmentRuleData($request->validated());
        $rule = ProblemAutoAssignmentRule::create($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.create', 'ProblemAutoAssignmentRule', $rule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Taisyklė pridėta.');
    }

    public function update(AssignmentRuleRequest $request, ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->automationService->normalizeAssignmentRuleData($request->validated());
        $rule->update($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.assignment_rule.update', 'ProblemAutoAssignmentRule', $rule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Taisyklė atnaujinta.');
    }

    public function destroy(ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $rule->id;
        $rule->delete();
        $this->auditLogService->logUserAction(request()->user(), 'automation.assignment_rule.destroy', 'ProblemAutoAssignmentRule', $id, 'success');

        return redirect()->route('automation.index')->with('success', 'Taisyklė pašalinta.');
    }

    public function storePublishRule(PublishRuleRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->automationService->normalizePublishRuleData($request->validated());
        $publishRule = ProblemAutoPublishRule::create($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.create', 'ProblemAutoPublishRule', $publishRule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pridėta.');
    }

    public function updatePublishRule(PublishRuleRequest $request, ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->automationService->normalizePublishRuleData($request->validated());
        $publish_rule->update($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.publish_rule.update', 'ProblemAutoPublishRule', $publish_rule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė atnaujinta.');
    }

    public function destroyPublishRule(ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $publish_rule->id;
        $publish_rule->delete();
        $this->auditLogService->logUserAction(request()->user(), 'automation.publish_rule.destroy', 'ProblemAutoPublishRule', $id, 'success');

        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pašalinta.');
    }

    public function storeResolutionTermRule(ResolutionTermRuleRequest $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->automationService->normalizeResolutionTermRuleData($request->validated());
        $termRule = ProblemResolutionTermRule::create($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.create', 'ProblemResolutionTermRule', $termRule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pridėta.');
    }

    public function updateResolutionTermRule(ResolutionTermRuleRequest $request, ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->automationService->normalizeResolutionTermRuleData($request->validated());
        $resolution_term_rule->update($data);
        $this->auditLogService->logUserAction($request->user(), 'automation.resolution_term_rule.update', 'ProblemResolutionTermRule', $resolution_term_rule->id, 'success');

        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė atnaujinta.');
    }

    public function destroyResolutionTermRule(ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $id = $resolution_term_rule->id;
        $resolution_term_rule->delete();
        $this->auditLogService->logUserAction(request()->user(), 'automation.resolution_term_rule.destroy', 'ProblemResolutionTermRule', $id, 'success');

        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pašalinta.');
    }
}
