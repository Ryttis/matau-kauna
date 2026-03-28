<?php

namespace App\Http\Controllers;

use App\Enums\AuditLogResult;
use App\Http\Controllers\Concerns\AuthorizesAutomation;
use App\Http\Requests\Automation\PublishRuleRequest;
use App\Models\ProblemAutoPublishRule;
use App\Services\AuditLogService;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;

class AutomationPublishRuleController extends Controller
{
    use AuthorizesAutomation;

    public function __construct(
        private AuditLogService $auditLogService,
        private AutomationService $automationService,
    ) {}

    public function store(PublishRuleRequest $request): RedirectResponse
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

    public function update(PublishRuleRequest $request, ProblemAutoPublishRule $publish_rule): RedirectResponse
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

    public function destroy(ProblemAutoPublishRule $publish_rule): RedirectResponse
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
}
