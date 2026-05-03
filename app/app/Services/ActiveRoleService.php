<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ActiveRoleService
{
    private const CACHE_KEY = '_resolved_active_role_context';

    public function resolveAndBind(Request $request, ?User $user): array
    {
        if ($user === null) {
            return $this->guestAuth();
        }

        $cached = $request->attributes->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $rolesCollection = $user->roles()->get(['id', 'role', 'name']);
        $roleIds = $rolesCollection->pluck('id')->map(fn ($id) => (string) $id)->all();

        if ($rolesCollection->isEmpty()) {
            $user->bindActiveRoleContext(null, []);
            $payload = $this->makePayload($rolesCollection, null, null, []);
            $request->attributes->set(self::CACHE_KEY, $payload);

            return $payload;
        }

        $sessionId = $request->session()->get('active_role_id');
        $sessionId = is_string($sessionId) ? $sessionId : null;
        $activeId = ($sessionId !== null && in_array($sessionId, $roleIds, true))
            ? $sessionId
            : $this->defaultPersonalActiveRoleId($rolesCollection);

        if ($sessionId !== $activeId) {
            $request->session()->put('active_role_id', $activeId);
        }

        $activeModel = $rolesCollection->firstWhere('id', $activeId);
        $activeKey = $activeModel?->role;
        $permissions = $user->permissionsForRole($activeId);
        $user->bindActiveRoleContext($activeKey, $permissions);

        $payload = $this->makePayload($rolesCollection, $activeId, $activeModel, $permissions);
        $request->attributes->set(self::CACHE_KEY, $payload);

        return $payload;
    }

    public function personalDefaultRoleIdForUser(User $user): ?string
    {
        $roles = $user->roles()->get(['id', 'role']);
        if ($roles->isEmpty()) {
            return null;
        }

        return $this->defaultPersonalActiveRoleId($roles);
    }

    private function defaultPersonalActiveRoleId(Collection $roles): string
    {
        foreach (UserRoleEnum::staffPersonalDefaultOrder() as $enum) {
            $found = $roles->firstWhere('role', $enum->value);
            if ($found !== null) {
                return (string) $found->id;
            }
        }

        return (string) $roles->first()->id;
    }

    private function makePayload(Collection $rolesCollection, ?string $activeId, ?object $activeModel, array $permissions): array
    {
        $rolesPayload = $rolesCollection->map(fn ($r) => [
            'id' => (string) $r->id,
            'role' => (string) $r->role,
            'name' => (string) $r->name,
        ])->values()->all();

        $activePayload = $activeModel ? [
            'id' => (string) $activeModel->id,
            'role' => (string) $activeModel->role,
            'name' => (string) $activeModel->name,
        ] : null;

        return [
            'roles' => $rolesPayload,
            'permissions' => $permissions,
            'activeRoleId' => $activeId,
            'activeRole' => $activePayload,
            'isSystemAdmin' => in_array('admin.access', $permissions, true),
        ];
    }

    private function guestAuth(): array
    {
        return [
            'roles' => [],
            'permissions' => [],
            'activeRoleId' => null,
            'activeRole' => null,
            'isSystemAdmin' => false,
        ];
    }
}
