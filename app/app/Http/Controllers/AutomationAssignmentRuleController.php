<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\AuthorizesAutomation;
use App\Http\Requests\Automation\AssignmentRuleRequest;
use App\Models\ProblemAutoAssignmentRule;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;

class AutomationAssignmentRuleController extends Controller
{
    use AuthorizesAutomation;

    public function __construct(
        private AuditLogService $auditLogService,
        private AutomationService $automationService,
    ) {}

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
}
