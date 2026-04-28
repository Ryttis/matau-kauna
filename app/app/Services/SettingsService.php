<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\InstitutionTeam;
use App\Models\User;
use App\Models\UserRole;

class SettingsService
{
    public function __construct(
        private InstitutionGisConfigService $institutionGisConfigService,
    ) {}

    public function getSettingsPageData(User $user): array
    {
        $user->load('institution.gisConfig');

        $employees = Employee::query()
            ->where('institution_id', $user->institution_id)
            ->get();

        $teams = InstitutionTeam::query()
            ->where('institution_id', $user->institution_id)
            ->with(['employees:id,name,surname,email'])
            ->orderBy('name')
            ->get();

        $inviteRoles = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE])
            ->get(['id', 'role', 'name']);

        return [
            'institution' => $user->institution,
            'institutionGis' => $this->institutionGisConfigService->settingsPayload($user->institution?->gisConfig),
            'employees' => $employees,
            'teams' => $teams,
            'inviteRoles' => $inviteRoles,
        ];
    }

    public function updateInstitutionGisConfig(User $user, array $payload): void
    {
        $institution = $user->institution;
        if (! $institution) {
            return;
        }

        $this->institutionGisConfigService->updateForInstitution($institution, $payload);
    }
}
