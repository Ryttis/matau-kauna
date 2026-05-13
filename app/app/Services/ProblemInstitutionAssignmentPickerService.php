<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\User;

class ProblemInstitutionAssignmentPickerService
{
    public function __construct(
        private ProblemScopeService $scopeService,
    ) {}

    /**
     * @return array{employees: list<array{id: int, name: string, email: string}>, teams: list<array{id: int, name: string, members_count: int}>, institutions: list<array{id: int, name: string}>}
     */
    public function pickerDataFor(User $user): array
    {
        $employees = [];
        $teams = [];

        if ($user->institution_id) {
            $assignableInstitutionUserQuery = User::queryAssignableInstitutionEmployeeUsers(
                (int) $user->institution_id,
                $user->assignableInstitutionExcludedEmail(),
            );

            foreach ($assignableInstitutionUserQuery->get(['id', 'name', 'surname', 'email']) as $u) {
                Employee::firstOrCreate(
                    [
                        'email' => $u->email,
                        'institution_id' => $user->institution_id,
                    ],
                    [
                        'name' => $u->name ?? '',
                        'surname' => $u->surname ?? '',
                    ]
                );
            }

            $assignableEmails = $assignableInstitutionUserQuery->pluck('email');

            if ($assignableEmails->isNotEmpty()) {
                $employees = Employee::query()
                    ->where('institution_id', $user->institution_id)
                    ->whereIn('email', $assignableEmails)
                    ->orderBy('name')
                    ->orderBy('surname')
                    ->get(['id', 'name', 'surname', 'email'])
                    ->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'name' => trim($employee->name.' '.$employee->surname) ?: $employee->email,
                        'email' => $employee->email,
                    ])
                    ->values()
                    ->all();
            }

            if ($assignableEmails->isEmpty()) {
                $teams = [];
            } else {
                $teams = InstitutionTeam::query()
                    ->where('institution_id', $user->institution_id)
                    ->withCount([
                        'employees as members_count' => function ($q) use ($assignableEmails) {
                            $q->whereIn('email', $assignableEmails);
                        },
                    ])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (InstitutionTeam $team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'members_count' => (int) $team->members_count,
                    ])
                    ->values()
                    ->all();
            }
        }

        $institutionsQuery = Institution::query()->notArchived()->orderBy('name');
        if ($this->scopeService->isInstitutionScoped($user)) {
            $institutionsQuery->where('id', '!=', $user->institution_id);
        }
        $institutions = $institutionsQuery->get(['id', 'name'])
            ->map(fn (Institution $i) => ['id' => $i->id, 'name' => $i->name])
            ->values()
            ->all();

        return [
            'employees' => $employees,
            'teams' => $teams,
            'institutions' => $institutions,
        ];
    }
}
