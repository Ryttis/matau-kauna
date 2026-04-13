<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\Problem;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemReporterStatusNotifier;
use App\Services\ProblemResolutionTermService;
use App\Services\ProblemReviewHistoryService;
use App\Services\TransactionalMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProblemAdminAssignController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
        private ProblemAssignmentNotifier $assignmentNotifier,
        private ProblemResolutionTermService $resolutionTermService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private ProblemReviewHistoryService $reviewHistoryService,
    ) {}

    public function assign(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $institutions = $this->httpSupport->institutionsForAssignInertia();

        $problemIds = array_filter((array) $request->get('problem_ids', []));
        $problems = $problemIds ? Problem::whereIn('id', $problemIds)->get() : collect();
        if ($problems->contains(fn (Problem $problem) => $problem->status === ProblemStatus::RESOLVED->value)) {
            return redirect()->route('dashboard')->with('error', 'Išspręstų problemų negalima priskirti.');
        }
        $problem = $problems->count() === 1 ? $problems->first() : null;

        return Inertia::render('Problems/Assign', [
            'problem' => $problem,
            'problems' => $problems,
            'institutions' => $institutions,
        ]);
    }

    public function storeAssign(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $statusesAllowedOnAssign = implode(',', array_values(array_filter(
            ProblemStatus::values(),
            fn (string $status) => $status !== ProblemStatus::RESOLVED->value
        )));

        $validated = $request->validate([
            'problem_ids' => ['required', 'array'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', 'string', 'in:'.$statusesAllowedOnAssign],
        ]);

        $problemsToUpdate = Problem::whereIn('id', $validated['problem_ids'])->get();
        if ($problemsToUpdate->contains(fn (Problem $problem) => $problem->status === ProblemStatus::RESOLVED->value)) {
            return redirect()->back()->with('error', 'Išspręstų problemų negalima priskirti.');
        }

        $update = [
            'institution_id' => $validated['institution_id'] ?? null,
        ];

        if (empty($validated['institution_id'])) {
            $update['assigned_employee_id'] = null;
            $update['assigned_institution_team_id'] = null;
        }

        if (! empty($validated['status'])) {
            $update['status'] = $validated['status'];
            $update['status_updated_at'] = now();

            $rootIdsMap = Problem::rootIdsMapFromProblemIds($validated['problem_ids']);
            $oldByMain = Problem::statusesByRootId($rootIdsMap);

            Problem::whereIn('id', $validated['problem_ids'])->update($update);

            $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $validated['status']);

            foreach (array_keys($rootIdsMap) as $rootId) {
                $rootProblem = Problem::find($rootId);
                if ($rootProblem) {
                    $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, $validated['status']);
                }
            }
        } else {
            Problem::whereIn('id', $validated['problem_ids'])->update($update);
        }

        if (! empty($validated['institution_id'])) {
            foreach (Problem::whereIn('id', $validated['problem_ids'])->get() as $p) {
                $this->resolutionTermService->applyRules($p);
            }
            $this->assignmentNotifier->notifyInstitutionOfAssignment($validated['problem_ids'], $validated['institution_id']);
        }

        $message = empty($validated['institution_id'])
            ? 'Problemos atšauktos nuo įstaigos.'
            : 'Problemos priskirtos įstaigai.';

        return redirect()->route('dashboard')->with('success', $message);
    }

    public function sendAssignEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'problem_ids' => ['required', 'array'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        $institution = Institution::query()->findOrFail($validated['institution_id']);
        $allowedEmails = array_values(array_unique(array_filter([
            $institution->contact_email,
            $institution->integration_email,
        ])));

        if (! in_array($validated['email'], $allowedEmails, true)) {
            return redirect()->back()->with('error', 'Pasirinktas el. paštas nepriklauso pasirinktai įstaigai.');
        }

        $problems = Problem::whereIn('id', $validated['problem_ids'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'internal_number', 'description', 'status', 'created_at', 'completion_deadline']);

        if ($problems->contains(fn (Problem $problem) => $problem->status === ProblemStatus::RESOLVED->value)) {
            return redirect()->back()->with('error', 'Išspręstų problemų negalima priskirti.');
        }

        Problem::whereIn('id', $validated['problem_ids'])->update([
            'institution_id' => $institution->id,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        foreach ($problems as $p) {
            $this->resolutionTermService->applyRules($p);
        }

        $mail = $this->assignmentNotifier->buildManualAssignToInstitutionEmail($institution, $problems);

        TransactionalMail::send(
            $validated['email'],
            $mail['subject'],
            $mail['body'],
            ! empty($mail['action_url']) ? $mail['action_url'] : null,
            $mail['action_label'] ?? null,
        );

        return redirect()->back()->with('success', 'Informacija išsiųsta el. paštu ir problema priskirta įstaigai.');
    }

    public function assignSingle(Problem $problem): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user->isProblemAdminOrSystemAdmin()) {
            abort(403);
        }

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->route('problems.show', $problem)->with('error', 'Išspręstos problemos priskirti negalima.');
        }

        $institutions = $this->httpSupport->institutionsForAssignInertia();

        return Inertia::render('Problems/Assign', [
            'problem' => $problem,
            'problems' => [$problem],
            'institutions' => $institutions,
        ]);
    }
}
