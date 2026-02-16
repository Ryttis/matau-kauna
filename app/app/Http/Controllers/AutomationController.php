<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
use App\Models\ProblemResolutionTermRule;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    private function authorizeAutomation(): void
    {
        $user = request()->user();
        if (! $user->hasRole(UserRole::ROLE_PROBLEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            abort(403);
        }
    }

    private function ruleValidationRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:500'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'is_active' => ['boolean'],
        ];
    }

    private function publishRuleValidationRules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    private function normalizeRuleData(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (isset($data['category']) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        if (isset($data['keyword']) && $data['keyword'] !== null) {
            $data['keyword'] = trim($data['keyword']) ?: null;
        }
        return $data;
    }

    private function normalizePublishRuleData(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (array_key_exists('category', $data) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $data['status'] = trim($data['status']) ?: null;
        }
        return $data;
    }

    public function index(): Response
    {
        $this->authorizeAutomation();

        $rules = ProblemAutoAssignmentRule::query()
            ->with('institution:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemAutoAssignmentRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'category' => $r->category,
                'keyword' => $r->keyword,
                'institution_id' => $r->institution_id,
                'institution_name' => $r->institution?->name,
                'is_active' => $r->is_active,
            ]);

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();
        $institutions = Institution::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $publishRules = ProblemAutoPublishRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemAutoPublishRule $r) => [
                'id' => $r->id,
                'category' => $r->category,
                'status' => $r->status,
                'is_active' => $r->is_active,
            ]);

        $resolutionTermRules = ProblemResolutionTermRule::query()
            ->with(['institution:id,name', 'problemType:id,name', 'problemNature:id,name'])
            ->orderBy('id')
            ->get()
            ->map(fn (ProblemResolutionTermRule $r) => [
                'id' => $r->id,
                'days' => $r->days,
                'institution_id' => $r->institution_id,
                'institution_name' => $r->institution?->name,
                'category' => $r->category,
                'problem_type_id' => $r->problem_type_id,
                'problem_type_name' => $r->problemType?->name,
                'problem_nature_id' => $r->problem_nature_id,
                'problem_nature_name' => $r->problemNature?->name,
                'is_active' => $r->is_active,
            ]);

        $classifierTypesWithNatures = ClassifierValue::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['activeChildren:id,parent_id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'natures' => $t->activeChildren->map(fn ($n) => ['id' => $n->id, 'name' => $n->name])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Automation/Index', [
            'rules' => $rules,
            'categories' => $categories,
            'institutions' => $institutions,
            'publishRules' => $publishRules,
            'resolutionTermRules' => $resolutionTermRules,
            'classifierTypesWithNatures' => $classifierTypesWithNatures,
            'statusOptions' => ProblemStatus::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizeRuleData($request->validate($this->ruleValidationRules()));
        ProblemAutoAssignmentRule::create($data);
        return redirect()->route('automation.index')->with('success', 'Taisyklė pridėta.');
    }

    public function update(Request $request, ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizeRuleData($request->validate($this->ruleValidationRules()));
        $rule->update($data);
        return redirect()->route('automation.index')->with('success', 'Taisyklė atnaujinta.');
    }

    public function destroy(ProblemAutoAssignmentRule $rule): RedirectResponse
    {
        $this->authorizeAutomation();

        $rule->delete();

        return redirect()->route('automation.index')->with('success', 'Taisyklė pašalinta.');
    }

    public function storePublishRule(Request $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizePublishRuleData($request->validate($this->publishRuleValidationRules()));
        ProblemAutoPublishRule::create($data);
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pridėta.');
    }

    public function updatePublishRule(Request $request, ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizePublishRuleData($request->validate($this->publishRuleValidationRules()));
        $publish_rule->update($data);
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė atnaujinta.');
    }

    public function destroyPublishRule(ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $publish_rule->delete();
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pašalinta.');
    }

    private function resolutionTermRuleValidationRules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'problem_type_id' => ['nullable', 'integer', 'exists:classifier_values,id'],
            'problem_nature_id' => ['nullable', 'integer', 'exists:classifier_values,id'],
            'is_active' => ['boolean'],
        ];
    }

    private function normalizeResolutionTermRuleData(array $data): array
    {
        $data['days'] = (int) $data['days'];
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['institution_id'] = ! empty($data['institution_id']) ? (int) $data['institution_id'] : null;
        $data['problem_type_id'] = ! empty($data['problem_type_id']) ? (int) $data['problem_type_id'] : null;
        $data['problem_nature_id'] = ! empty($data['problem_nature_id']) ? (int) $data['problem_nature_id'] : null;
        if (array_key_exists('category', $data) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        return $data;
    }

    public function storeResolutionTermRule(Request $request): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizeResolutionTermRuleData($request->validate($this->resolutionTermRuleValidationRules()));
        ProblemResolutionTermRule::create($data);
        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pridėta.');
    }

    public function updateResolutionTermRule(Request $request, ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $this->normalizeResolutionTermRuleData($request->validate($this->resolutionTermRuleValidationRules()));
        $resolution_term_rule->update($data);
        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė atnaujinta.');
    }

    public function destroyResolutionTermRule(ProblemResolutionTermRule $resolution_term_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $resolution_term_rule->delete();
        return redirect()->route('automation.index')->with('success', 'Sprendimo termino taisyklė pašalinta.');
    }
}
