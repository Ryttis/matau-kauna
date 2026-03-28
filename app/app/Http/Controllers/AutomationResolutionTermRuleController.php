<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\AuthorizesAutomation;
use App\Http\Requests\Automation\ResolutionTermRuleRequest;
use App\Models\ProblemResolutionTermRule;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;

class AutomationResolutionTermRuleController extends Controller
{
    use AuthorizesAutomation;

    public function __construct(
        private AuditLogService $auditLogService,
        private AutomationService $automationService,
    ) {}

    public function store(ResolutionTermRuleRequest $request): RedirectResponse
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

    public function update(ResolutionTermRuleRequest $request, ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
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

    public function destroy(ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
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
}
