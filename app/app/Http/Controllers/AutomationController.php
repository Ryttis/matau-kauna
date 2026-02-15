<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\Problem;
use App\Models\ProblemAutoAssignmentRule;
use App\Models\ProblemAutoPublishRule;
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

        $categories = Problem::distinct()->pluck('category')->filter()->sort()->values()->all();
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

        return Inertia::render('Automation/Index', [
            'rules' => $rules,
            'categories' => $categories,
            'institutions' => $institutions,
            'publishRules' => $publishRules,
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
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (isset($data['category']) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        if (isset($data['status']) && $data['status'] !== null) {
            $data['status'] = trim($data['status']) ?: null;
        }
        ProblemAutoPublishRule::create($data);
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pridėta.');
    }

    public function updatePublishRule(Request $request, ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (isset($data['category']) && $data['category'] !== null) {
            $data['category'] = trim($data['category']) ?: null;
        }
        if (isset($data['status']) && $data['status'] !== null) {
            $data['status'] = trim($data['status']) ?: null;
        }
        $publish_rule->update($data);
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė atnaujinta.');
    }

    public function destroyPublishRule(ProblemAutoPublishRule $publish_rule): RedirectResponse
    {
        $this->authorizeAutomation();
        $publish_rule->delete();
        return redirect()->route('automation.index')->with('success', 'Publikavimo taisyklė pašalinta.');
    }
}
