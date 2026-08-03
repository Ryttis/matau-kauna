<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Institution;
use App\Models\Problem;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemAutoPublishService;
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
        private ProblemAutoPublishService $autoPublishService,
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
            fn (string $status) => ! in_array($status, [
                ProblemStatus::RESOLVED->value,
                ProblemStatus::RETURNED_FOR_REVIEW->value,
            ], true)
        )));

        $validated = $request->validate([
            'problem_ids' => ['required', 'array', 'min:1'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', 'string', 'in:'.$statusesAllowedOnAssign],
        ]);

        $problemsToUpdate = Problem::whereIn('id', $validated['problem_ids'])->get();
        if ($problemsToUpdate->contains(fn (Problem $problem) => $problem->status === ProblemStatus::RESOLVED->value)) {
            return redirect()->back()->with('error', 'Išspręstų problemų negalima priskirti.');
        }

        $newInstitutionId = $validated['institution_id'] ?? null;
        $newStatus = $validated['status'] ?? null;
        $rootIdsMap = ! empty($newStatus)
            ? Problem::rootIdsMapFromProblemIds($validated['problem_ids'])
            : [];
        $oldByMain = $rootIdsMap !== []
            ? Problem::statusesByRootId($rootIdsMap)
            : [];

        $newInstitutionName = $newInstitutionId !== null
            ? Institution::query()->whereKey($newInstitutionId)->value('name')
            : null;
        $loggedInstitutionRootIds = [];

        foreach ($problemsToUpdate as $problem) {
            $attrs = ['institution_id' => $newInstitutionId];
            if ($newInstitutionId === null) {
                $attrs['assigned_employee_id'] = null;
                $attrs['assigned_institution_team_id'] = null;
            } elseif ((int) ($problem->institution_id ?? 0) !== (int) $newInstitutionId) {
                $attrs['assigned_employee_id'] = null;
                $attrs['assigned_institution_team_id'] = null;
            }
            if (! empty($newStatus)) {
                $attrs['status'] = $newStatus;
                $attrs['status_updated_at'] = now();
            }

            $rootId = (int) ($problem->merged_into_problem_id ?? $problem->id);
            if (! isset($loggedInstitutionRootIds[$rootId])
                && (int) ($problem->institution_id ?? 0) !== (int) ($newInstitutionId ?? 0)
            ) {
                $loggedInstitutionRootIds[$rootId] = true;
                $this->reviewHistoryService->logInternalEventEntry(
                    $problem,
                    $user,
                    ProblemReviewHistoryService::institutionChangeNote(
                        Institution::query()->whereKey($problem->institution_id)->value('name'),
                        $newInstitutionName,
                    ),
                );
            }

            $problem->update($attrs);
        }

        if (! empty($newStatus)) {
            $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, $newStatus);

            foreach ($oldByMain as $rootId => $oldStatus) {
                if ($oldStatus === null || $oldStatus === $newStatus) {
                    continue;
                }
                $rootProblem = Problem::find($rootId);
                if ($rootProblem) {
                    $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, $newStatus);
                }
            }
        }

        if (! empty($newInstitutionId)) {
            foreach (Problem::query()->whereIn('id', $validated['problem_ids'])->get() as $p) {
                $this->resolutionTermService->applyRules($p);
            }
            $this->assignmentNotifier->notifyInstitutionOfAssignment($validated['problem_ids'], (int) $newInstitutionId);
        }

        if (! empty($newStatus)) {
            foreach (Problem::query()->whereIn('id', $validated['problem_ids'])->get() as $p) {
                $this->autoPublishService->applyRules($p);
            }
        }

        $message = empty($newInstitutionId)
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

        $request->merge([
            'email' => trim((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'problem_ids' => ['required', 'array', 'min:1'],
            'problem_ids.*' => ['required', 'integer', 'exists:problems,id'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        $institution = Institution::query()->findOrFail($validated['institution_id']);
        $expandedIds = Problem::expandIdsToMergeGroups(array_map('intval', $validated['problem_ids']));

        $problems = Problem::whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($problems->contains(fn (Problem $problem) => $problem->status === ProblemStatus::RESOLVED->value)) {
            return redirect()->back()->with('error', 'Išspręstų problemų negalima priskirti.');
        }

        $loggedRootIds = [];
        foreach ($problems as $problem) {
            $rootId = (int) ($problem->merged_into_problem_id ?? $problem->id);
            if (isset($loggedRootIds[$rootId])) {
                continue;
            }
            if ((int) ($problem->institution_id ?? 0) === (int) $institution->id) {
                continue;
            }
            $loggedRootIds[$rootId] = true;
            $this->reviewHistoryService->logInternalEventEntry(
                $problem,
                $user,
                ProblemReviewHistoryService::institutionChangeNote(
                    Institution::query()->whereKey($problem->institution_id)->value('name'),
                    $institution->name,
                ),
            );
        }

        Problem::whereIn('id', $expandedIds)->update([
            'institution_id' => $institution->id,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        $problems = Problem::query()
            ->whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($problems as $p) {
            $this->resolutionTermService->applyRules($p);
        }

        $problems = Problem::query()
            ->whereIn('id', $expandedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $mail = $this->assignmentNotifier->buildManualAssignToInstitutionEmail($institution, $problems);

        if ($mail !== null) {
            TransactionalMail::send(
                $validated['email'],
                $mail['subject'],
                $mail['body'],
                ! empty($mail['action_url']) ? $mail['action_url'] : null,
                $mail['action_label'] ?? null,
            );
        }

        $rootIdsMap = Problem::rootIdsMapFromProblemIds($expandedIds);
        $oldByMain = Problem::statusesByRootId($rootIdsMap);
        Problem::whereIn('id', $expandedIds)->update([
            'status' => ProblemStatus::RESOLVED->value,
            'status_updated_at' => now(),
            'deadline_status' => null,
            'deadline_reminder_sent_at' => null,
        ]);
        $this->reporterStatusNotifier->notifyRootsAfterStatusChange($oldByMain, ProblemStatus::RESOLVED->value);
        foreach (array_keys($rootIdsMap) as $rootId) {
            $rootProblem = Problem::find($rootId);
            if ($rootProblem) {
                $this->reviewHistoryService->logStatusChangeEntry($rootProblem, $user, ProblemStatus::RESOLVED->value);
            }
        }

        $message = $mail !== null
            ? 'Informacija išsiųsta el. paštu ir problema priskirta įstaigai.'
            : 'Problema priskirta įstaigai.';

        return redirect()->back()->with('success', $message);
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
