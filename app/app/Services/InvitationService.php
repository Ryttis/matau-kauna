<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\InvitationNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InvitationService
{
    public function getIndexQuery($user): Builder
    {
        $query = Invitation::query()
            ->with(['institution:id,name', 'role:id,role,name', 'creator:id,name,email'])
            ->orderBy('created_at', 'desc');

        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            $query->where('institution_id', $user->institution_id);
        }

        return $query;
    }

    /**
     * Returns the created invitation or validation error array.
     *
     * @return array{invitation?: Invitation, error?: array{message: string}}
     */
    public function createInvitation(array $data, $creator): array
    {
        if (User::where('email', $data['email'])->exists()) {
            return ['error' => ['message' => 'Naudotojas su tokiu el. paštu jau egzistuoja.']];
        }

        $role = UserRole::findOrFail($data['role_id']);
        $allowedRoles = [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE];
        if (! in_array($role->role, $allowedRoles, true)) {
            return ['error' => ['message' => 'Galima kviesti tik įstaigos administratorių arba darbuotoją.']];
        }

        $invitation = Invitation::create([
            'email' => $data['email'],
            'institution_id' => $data['institution_id'],
            'role_id' => $data['role_id'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $creator->id,
        ]);

        return ['invitation' => $invitation];
    }

    public function sendInvitationNotification(Invitation $invitation): void
    {
        \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation));
    }
}
