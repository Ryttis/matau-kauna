<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ArcGisProblemTransferService;
use App\Services\DvsProblemTransferService;
use App\Services\ProblemAssignmentNotifier;
use App\Services\ProblemInstitutionAssignmentPickerService;
use App\Services\ProblemReporterStatusNotifier;
use App\Services\ProblemReviewHistoryService;
use App\Services\ProblemScopeService;
use App\Services\TransactionalMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProblemInstitutionAssignmentController extends Controller
{
    public function __construct(
        private ProblemScopeService $scopeService,
        private ProblemAssignmentNotifier $assignmentNotifier,
        private ProblemInstitutionAssignmentPickerService $assignmentPickerService,
        private ArcGisProblemTransferService $arcGisProblemTransferService,
        private DvsProblemTransferService $dvsProblemTransferService,
        private ProblemReporterStatusNotifier $reporterStatusNotifier,
        private ProblemReviewHistoryService $reviewHistoryService,
    ) {}

    public function assignInstitution(Problem $problem): Response|RedirectResponse
    {
        $user = request()->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->route('problems.show', $problem)->with('error', 'Išnagrinėtos problemos priskirti negalima.');
        }

        if ($user->institution_id) {
            $institutionUsers = User::where('institution_id', $user->institution_id)
                ->whereHas('roles.permissions', fn ($query) => $query->where('permission', 'issues.assign'))
                ->get(['id', 'name', 'surname', 'email']);

            foreach ($institutionUsers as $institutionUser) {
                Employee::updateOrCreate(
                    ['email' => $institutionUser->email],
                    [
                        'institution_id' => $user->institution_id,
                        'name' => $institutionUser->name ?? '',
                        'surname' => $institutionUser->surname ?? '',
                    ]
                );
            }
        }

        $institutionsQuery = Institution::query()->notArchived()->orderBy('name');
        if ($this->scopeService->isInstitutionScoped($user)) {
            $institutionsQuery->where('id', '!=', $user->institution_id);
        }

        $problem->loadMissing([
            'institution.gisConfig',
            'institution.dvsConfig',
            'gisTransfer.attachmentTransfers.attachment',
            'dvsTransfer',
        ]);

        $picker = $this->assignmentPickerService->pickerDataFor($user);

        return Inertia::render('Problems/AssignInstitution', [
            'problem' => $problem,
            'employees' => $picker['employees'],
            'teams' => $picker['teams'],
            'institutions' => $picker['institutions'],
            'gisAvailable' => (bool) $problem->institution?->gisConfig?->enabled,
            'gisTransfer' => $problem->gisTransfer?->loadMissing('attachmentTransfers.attachment'),
            'dvsAvailable' => (bool) $problem->institution?->dvsConfig?->enabled,
            'dvsTransfer' => $problem->dvsTransfer,
            'dvsComplaintApi' => $problem->institution?->dvsConfig?->complaint_api,
        ]);
    }

    public function storeAssignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išnagrinėtos problemos priskirti negalima.');
        }

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'institution_team_id' => ['nullable', 'integer', 'exists:institution_teams,id'],
            'completion_deadline' => ['nullable', 'date'],
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        $teamId = $validated['institution_team_id'] ?? null;

        if (($employeeId === null) === ($teamId === null)) {
            return redirect()->back()->with('error', 'Pasirinkite darbuotoją arba komandą.');
        }

        $deadline = array_key_exists('completion_deadline', $validated)
            ? ($validated['completion_deadline'] ?: null)
            : null;

        if ($employeeId !== null) {
            $assignableEmails = User::queryAssignableInstitutionEmployeeUsers(
                (int) $user->institution_id,
                $user->assignableInstitutionExcludedEmail(),
            )->pluck('email');
            if ($assignableEmails->isEmpty()) {
                abort(403);
            }
            $employee = Employee::where('id', $employeeId)
                ->where('institution_id', $user->institution_id)
                ->whereIn('email', $assignableEmails)
                ->first();

            if (! $employee) {
                abort(403);
            }

            $problem->update([
                'assigned_employee_id' => $employee->id,
                'assigned_institution_team_id' => null,
            ]);
            $this->applyAssignmentToMergeGroup($problem, $deadline);

            $this->reviewHistoryService->logInternalEventEntry(
                $problem,
                $user,
                ProblemReviewHistoryService::employeeAssignmentNote(
                    trim((string) $employee->name.' '.(string) $employee->surname)
                ),
                ProblemStatus::ASSIGNED->value,
            );

            $this->assignmentNotifier->notifyEmployeeOfAssignment($problem, $employee);

            return redirect()->back()->with('success', 'Problema priskirta darbuotojui.');
        }

        $team = InstitutionTeam::query()
            ->where('id', $teamId)
            ->where('institution_id', $user->institution_id)
            ->with('employees')
            ->first();

        if (! $team) {
            abort(403);
        }
        $assignableEmails = User::queryAssignableInstitutionEmployeeUsers(
            (int) $user->institution_id,
            $user->assignableInstitutionExcludedEmail(),
        )->pluck('email');
        $teamAssignees = $assignableEmails->isEmpty()
            ? collect()
            : $team->employees->whereIn('email', $assignableEmails);
        if ($teamAssignees->isEmpty()) {
            return redirect()->back()->with('error', 'Komanda neturi priskirtinų darbuotojų narių.');
        }

        $problem->update([
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => $team->id,
        ]);
        $this->applyAssignmentToMergeGroup($problem, $deadline);

        $this->reviewHistoryService->logInternalEventEntry(
            $problem,
            $user,
            ProblemReviewHistoryService::teamAssignmentNote((string) $team->name),
            ProblemStatus::ASSIGNED->value,
        );

        foreach ($teamAssignees as $employee) {
            if ($employee->email !== $user->email) {
                $this->assignmentNotifier->notifyEmployeeOfAssignment($problem->fresh(), $employee);
            }
        }

        return redirect()->back()->with('success', 'Problema priskirta komandai.');
    }

    public function sendAssignInstitutionEmail(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAssignInstitutionProblems()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išnagrinėtos problemos priskirti negalima.');
        }

        if ($user->institution_id === null) {
            return redirect()->back()->with('error', 'Negalima siųsti el. laiško be įstaigos konteksto.');
        }

        $request->merge([
            'email' => trim((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $mail = $this->assignmentNotifier->buildInstitutionProblemShareEmail($problem);
        if ($mail !== null) {
            TransactionalMail::send(
                $validated['email'],
                $mail['subject'],
                $mail['body'],
                $mail['action_url'] ?? null,
                $mail['action_label'] ?? null,
            );
        }

        $message = $mail !== null
            ? 'Informacija išsiųsta el. paštu.'
            : 'El. laiškas išjungtas, todėl informacija nebuvo išsiųsta.';

        return redirect()->back()->with('success', $message);
    }

    public function sendToGis(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išnagrinėtos problemos perduoti negalima.');
        }

        try {
            $problemForTransfer = $problem->replicate();
            $problemForTransfer->exists = true;
            $problemForTransfer->setAttribute($problem->getKeyName(), $problem->getKey());
            $problemForTransfer->forceFill(['status' => ProblemStatus::IN_REVIEW->value]);

            $result = $this->arcGisProblemTransferService->transferProblem($problemForTransfer, [
                'trigger' => 'manual',
                'actor' => $user,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->markProblemInReviewAfterTransfer($problem, $user);

        $message = $result['attachments_failed'] > 0
            ? 'Problema perduota į GIS, bet dalies priedų išsiųsti nepavyko.'
            : 'Problema sėkmingai perduota į GIS.';

        return redirect()->back()->with('success', $message);
    }

    public function sendToDvs(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išnagrinėtos problemos perduoti negalima.');
        }

        try {
            $this->dvsProblemTransferService->transferProblem($problem, [
                'trigger' => 'manual',
                'actor' => $user,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->markProblemInReviewAfterTransfer($problem, $user);

        return redirect()->back()->with('success', 'Problema sėkmingai perduota į DVS.');
    }

    public function pullFromDvs(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $problem->loadMissing('dvsTransfer');
        if (! $problem->dvsTransfer || ! filled($problem->dvsTransfer->external_object_id)) {
            return redirect()->back()->with('error', 'Problema dar nebuvo perduota į DVS.');
        }

        try {
            $result = $this->dvsProblemTransferService->syncFromDvs($problem->dvsTransfer, [
                'trigger' => 'manual',
                'actor' => $user,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($result['status_changed']) {
            if ((string) ($result['new_status'] ?? '') === ProblemStatus::RESOLVED->value) {
                return redirect()
                    ->route('problems.show', $problem)
                    ->with('success', 'Atnaujinta iš DVS: būsena pakeista į „Išnagrinėta“, problema uždaryta.');
            }

            return redirect()->back()->with('success', 'Atnaujinta iš DVS: būsena pakeista į „Išnagrinėta“.');
        }

        if ($result['answer_found']) {
            return redirect()->back()->with('success', 'DVS atsakymas jau užfiksuotas.');
        }

        return redirect()->back()->with('success', 'DVS pusėje pakeitimų nerasta.');
    }

    public function pullFromGis(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isInstitutionAdminOrSystemAdmin()) {
            abort(403);
        }
        $this->scopeService->authorizeProblemAccess($user, $problem);

        $problem->loadMissing('gisTransfer');
        if (! $problem->gisTransfer || ! filled($problem->gisTransfer->external_object_id)) {
            return redirect()->back()->with('error', 'Problema dar nebuvo perduota į GIS.');
        }

        try {
            $result = $this->arcGisProblemTransferService->syncFromGis($problem->gisTransfer, [
                'trigger' => 'manual',
                'actor' => $user,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $parts = [];

        if ($result['status_changed']) {
            if ((string) ($result['new_status'] ?? '') === ProblemStatus::RESOLVED->value) {
                return redirect()
                    ->route('problems.show', $problem)
                    ->with('success', 'Atnaujinta iš GIS: būsena pakeista į „Išnagrinėta“, problema uždaryta.');
            }

            $newLabel = ProblemStatus::tryFrom((string) $result['new_status'])?->label() ?? (string) $result['new_status'];
            $parts[] = 'būsena pakeista į „'.$newLabel.'"';
        }

        if ($result['deadline_changed']) {
            $parts[] = 'atnaujintas atlikimo terminas';
        }

        if (($result['attachments_downloaded'] ?? 0) > 0) {
            $parts[] = 'atsisiųsta priedų: '.$result['attachments_downloaded'];
        }

        $message = $parts === []
            ? 'GIS pusėje pakeitimų nerasta.'
            : 'Atnaujinta iš GIS: '.implode(', ', $parts).'.';

        return redirect()->back()->with('success', $message);
    }

    public function returnToAdmin(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->canReassignInstitutions()) {
            abort(403);
        }
        if ($user->institution_id === null) {
            abort(403);
        }
        if ((int) $problem->institution_id !== (int) $user->institution_id) {
            abort(403);
        }

        $problem->refresh();
        $this->reviewHistoryService->storeAutomatedInternalNote(
            $problem,
            $user,
            ProblemReviewHistoryService::INTERNAL_NOTE_RETURNED_TO_ADMIN,
        );

        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
            'institution_id' => null,
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
            'status' => ProblemStatus::RETURNED_FOR_REVIEW->value,
            'status_updated_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Problema grąžinta problemų administratoriui.');
    }

    public function reassignInstitution(Request $request, Problem $problem): RedirectResponse
    {
        $user = $request->user();
        $canReassign = $user->canReassignInstitutions()
            || ($this->scopeService->isInstitutionScoped($user)
                && $user->institution_id !== null
                && (int) $problem->institution_id === (int) $user->institution_id);

        if (! $canReassign) {
            abort(403);
        }

        if ($problem->status === ProblemStatus::RESOLVED->value) {
            return redirect()->back()->with('error', 'Išnagrinėtos problemos perduoti negalima.');
        }

        $validated = $request->validate([
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
        ]);

        if ((int) $problem->institution_id === (int) $validated['institution_id']) {
            return redirect()->back()->with('info', 'Problema jau priskirta šiai įstaigai.');
        }

        $this->reviewHistoryService->logInternalEventEntry(
            $problem,
            $user,
            ProblemReviewHistoryService::institutionChangeNote(
                Institution::query()->whereKey($problem->institution_id)->value('name'),
                Institution::query()->whereKey($validated['institution_id'])->value('name'),
            ),
        );

        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
            'institution_id' => $validated['institution_id'],
            'assigned_employee_id' => null,
            'assigned_institution_team_id' => null,
        ]);

        $this->assignmentNotifier->notifyInstitutionOfAssignment([$problem->id], $validated['institution_id']);

        return redirect()->route('dashboard')->with('success', 'Problema perduota kitai įstaigai.');
    }

    /**
     * Būsena ir atlikimo terminas galioja visai sujungimo grupei; konkretus
     * darbuotojas/komanda lieka priskirti tik tai problemai, kurioje priskirta.
     */
    private function applyAssignmentToMergeGroup(Problem $problem, ?string $deadline): void
    {
        Problem::query()->whereIn('id', $problem->getMergeGroupIds())->update([
            'completion_deadline' => $deadline,
            'status' => ProblemStatus::ASSIGNED->value,
            'status_updated_at' => now(),
        ]);

        $problem->refresh();
    }

    private function markProblemInReviewAfterTransfer(Problem $problem, User $user): void
    {
        $problem->refresh();

        $newStatus = ProblemStatus::IN_REVIEW->value;
        if ((string) $problem->status === $newStatus) {
            return;
        }

        $ids = $problem->getMergeGroupIds();
        $mainId = $problem->merged_into_problem_id ?? $problem->id;
        $oldMainStatus = Problem::where('id', $mainId)->value('status');

        $query = Problem::whereIn('id', $ids);
        $this->scopeService->scopeProblemsForUser($query, $user);
        $this->scopeService->ensureScopeMatchesCount($query, count($ids));
        $query->update([
            'status' => $newStatus,
            'status_updated_at' => now(),
        ]);

        if ($oldMainStatus !== null && $oldMainStatus !== $newStatus) {
            $main = Problem::find($mainId);
            if ($main) {
                $this->reporterStatusNotifier->notifyReportersAndWatchersWhenStatusChanges($main, $oldMainStatus, $newStatus);
                $this->reviewHistoryService->logStatusChangeEntry($main, $user, $newStatus);
            }
        }
    }

}
