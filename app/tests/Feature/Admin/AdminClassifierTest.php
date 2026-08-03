<?php

namespace Tests\Feature\Admin;

use App\Models\ClassifierValue;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_delete_nature_archives_it_and_removes_it_from_type_natures(): void
    {
        $admin = $this->createSystemAdmin();
        $type = ClassifierValue::create([
            'name' => 'Apšvietimas',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $nature = ClassifierValue::create([
            'parent_id' => $type->id,
            'name' => 'Neveikianti lempa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson('/admin/classifiers/values/'.$nature->id)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($nature->refresh()->archived_at);

        $this
            ->actingAs($admin)
            ->getJson('/admin/classifiers/types/'.$type->id.'/natures')
            ->assertOk()
            ->assertJsonCount(0, 'natures');
    }

    private function createSystemAdmin(): User
    {
        $permission = UserRolePermission::firstOrCreate(
            ['permission' => 'admin.access'],
            ['name' => 'Admin access']
        );
        $role = UserRole::firstOrCreate(
            ['role' => UserRole::ROLE_SYSTEM_ADMIN],
            ['name' => 'System administrator']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }
}
