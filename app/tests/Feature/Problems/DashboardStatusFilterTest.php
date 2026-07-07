<?php

namespace Tests\Feature\Problems;

use App\Enums\ProblemStatus;
use App\Models\Employee;
use App\Models\Institution;
use App\Models\InstitutionTeam;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_exposes_all_status_options_and_filters_by_multiple_statuses(): void
    {
        $user = $this->staffUser(['issues.view']);
        $this->makeProblem(['status' => ProblemStatus::NEW->value]);
        $this->makeProblem(['status' => ProblemStatus::IN_REVIEW->value]);
        $this->makeProblem(['status' => ProblemStatus::RESOLVED->value]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', [
                'statuses' => [
                    ProblemStatus::NEW->value,
                    ProblemStatus::IN_REVIEW->value,
                ],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('reporterView', false)
                ->where('statusOptions', ProblemStatus::options())
                ->where('availableStatusCounts.'.ProblemStatus::NEW->value, 1)
                ->where('availableStatusCounts.'.ProblemStatus::IN_REVIEW->value, 1)
                ->where('availableStatusCounts.'.ProblemStatus::RESOLVED->value, 1)
                ->where('filters.statuses', [
                    ProblemStatus::NEW->value,
                    ProblemStatus::IN_REVIEW->value,
                ])
                ->where('problems.total', 2)
                ->has('problems.data', 2)
                ->etc());
    }

    public function test_staff_dashboard_status_counts_ignore_status_filter(): void
    {
        $user = $this->staffUser(['issues.view']);
        $new = $this->makeProblem(['status' => ProblemStatus::NEW->value]);
        $this->makeProblem(['status' => ProblemStatus::IN_REVIEW->value]);
        $this->makeProblem(['status' => ProblemStatus::UNREGISTERED->value]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['statuses' => [ProblemStatus::NEW->value]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.statuses', [ProblemStatus::NEW->value])
                ->where('problems.total', 1)
                ->where('problems.data.0.id', $new->id)
                ->where('availableStatusCounts.'.ProblemStatus::NEW->value, 1)
                ->where('availableStatusCounts.'.ProblemStatus::IN_REVIEW->value, 1)
                ->where('availableStatusCounts.'.ProblemStatus::UNREGISTERED->value, 1)
                ->missing('availableStatusCounts.'.ProblemStatus::INACTIVE->value)
                ->etc());
    }

    public function test_staff_dashboard_status_counts_follow_non_status_filters(): void
    {
        $user = $this->staffUser(['issues.view']);
        $this->makeProblem([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
        ]);
        $this->makeProblem([
            'status' => ProblemStatus::INACTIVE->value,
            'category' => 'Aplinkos tvarkymas',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['category' => 'Keliai']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.category', 'Keliai')
                ->where('availableStatusCounts.'.ProblemStatus::NEW->value, 1)
                ->missing('availableStatusCounts.'.ProblemStatus::INACTIVE->value)
                ->etc());
    }

    public function test_employee_dashboard_status_counts_only_include_visible_assignments(): void
    {
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $user = $this->employeeUser(['issues.view'], $institution);
        $employee = Employee::create([
            'institution_id' => $institution->id,
            'name' => 'Ema',
            'surname' => 'Darbuotoja',
            'email' => $user->email,
        ]);
        $team = InstitutionTeam::create([
            'institution_id' => $institution->id,
            'name' => 'Gatviu komanda',
        ]);
        $team->employees()->attach($employee->id);

        $this->makeProblem([
            'status' => ProblemStatus::UNREGISTERED->value,
            'institution_id' => $institution->id,
            'assigned_employee_id' => $employee->id,
        ]);
        $this->makeProblem([
            'status' => ProblemStatus::IN_REVIEW->value,
            'institution_id' => $institution->id,
            'assigned_institution_team_id' => $team->id,
        ]);
        $this->makeProblem([
            'status' => ProblemStatus::INACTIVE->value,
            'institution_id' => $institution->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('availableStatusCounts.'.ProblemStatus::UNREGISTERED->value, 1)
                ->where('availableStatusCounts.'.ProblemStatus::IN_REVIEW->value, 1)
                ->missing('availableStatusCounts.'.ProblemStatus::INACTIVE->value)
                ->etc());
    }

    public function test_staff_dashboard_accepts_legacy_single_status_filter(): void
    {
        $user = $this->staffUser(['issues.view']);
        $new = $this->makeProblem(['status' => ProblemStatus::NEW->value]);
        $this->makeProblem(['status' => ProblemStatus::IN_REVIEW->value]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['status' => ProblemStatus::NEW->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('reporterView', false)
                ->where('filters.statuses', [ProblemStatus::NEW->value])
                ->where('problems.total', 1)
                ->where('problems.data.0.id', $new->id)
                ->etc());
    }

    public function test_staff_dashboard_includes_assignment_relations_for_table_display(): void
    {
        $user = $this->staffUser(['issues.view']);
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $employee = Employee::create([
            'institution_id' => $institution->id,
            'name' => 'Jonas',
            'surname' => 'Jonaitis',
            'email' => 'jonas@example.test',
        ]);
        $team = InstitutionTeam::create([
            'institution_id' => $institution->id,
            'name' => 'Gatviu komanda',
        ]);

        $problem = $this->makeProblem([
            'institution_id' => $institution->id,
            'assigned_employee_id' => $employee->id,
            'assigned_institution_team_id' => $team->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('problems.data.0.id', $problem->id)
                ->where('problems.data.0.institution.name', 'Kauno tvarkymas')
                ->where('problems.data.0.assigned_employee.name', 'Jonas')
                ->where('problems.data.0.assigned_employee.surname', 'Jonaitis')
                ->where('problems.data.0.assigned_institution_team.name', 'Gatviu komanda')
                ->etc());
    }

    public function test_staff_dashboard_filters_by_assigned_institution_and_preserves_filter(): void
    {
        $user = $this->staffUser(['issues.view']);
        $matchingInstitution = Institution::create(['name' => 'Kauno tvarkymas']);
        $otherInstitution = Institution::create(['name' => 'Kita imone']);
        $matching = $this->makeProblem(['institution_id' => $matchingInstitution->id]);
        $this->makeProblem(['institution_id' => $otherInstitution->id]);
        $this->makeProblem(['institution_id' => null]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['institution_id' => $matchingInstitution->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.institution_id', (string) $matchingInstitution->id)
                ->where('problems.total', 1)
                ->where('problems.data.0.id', $matching->id)
                ->where('institutions.0.id', $matchingInstitution->id)
                ->etc());
    }

    public function test_staff_dashboard_filters_unassigned_problems_with_none_value(): void
    {
        $user = $this->staffUser(['issues.view']);
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $this->makeProblem(['institution_id' => $institution->id]);
        $unassigned = $this->makeProblem(['institution_id' => null]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['institution_id' => 'none']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.institution_id', 'none')
                ->where('problems.total', 1)
                ->where('problems.data.0.id', $unassigned->id)
                ->where('problems.data.0.institution', null)
                ->etc());
    }

    public function test_staff_dashboard_institution_filter_matches_merged_child_problem(): void
    {
        $user = $this->staffUser(['issues.view']);
        $institution = Institution::create(['name' => 'Kauno tvarkymas']);
        $root = $this->makeProblem(['institution_id' => null]);
        $child = $this->makeProblem([
            'institution_id' => $institution->id,
            'merged_into_problem_id' => $root->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard', ['institution_id' => $institution->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('problems.total', 1)
                ->where('problems.data.0.id', $root->id)
                ->where('problems.data.0.merged_problems.0.id', $child->id)
                ->where('problems.data.0.merged_problems.0.institution.name', 'Kauno tvarkymas')
                ->etc());
    }

    private function makeProblem(array $attributes = []): Problem
    {
        return Problem::create(array_merge([
            'status' => ProblemStatus::NEW->value,
            'category' => 'Keliai',
            'address' => 'Laisves al., Kaunas',
            'description' => 'Bandomoji problema',
            'lat' => 54.8985,
            'lng' => 23.9036,
        ], $attributes));
    }

    private function staffUser(array $permissions): User
    {
        $user = User::factory()->create();
        $this->attachRoleWithPermissions($user, UserRole::ROLE_PROBLEM_ADMIN, 'Problem administrator', $permissions);

        return $user->fresh();
    }

    private function employeeUser(array $permissions, Institution $institution): User
    {
        $user = User::factory()->create([
            'institution_id' => $institution->id,
        ]);
        $this->attachRoleWithPermissions($user, UserRole::ROLE_EMPLOYEE, 'Employee', $permissions);

        return $user->fresh();
    }

    private function attachRoleWithPermissions(User $user, string $roleKey, string $roleName, array $permissions): void
    {
        $role = UserRole::firstOrCreate(
            ['role' => $roleKey],
            ['name' => $roleName],
        );
        foreach ($permissions as $key) {
            $permission = UserRolePermission::firstOrCreate(
                ['permission' => $key],
                ['name' => $key],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $role->users()->attach($user->id);
    }
}
