<?php

namespace App\Services;

use App\Models\EmailNotificationTemplate;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\InvitationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    public function getIndexQuery($user): Builder
    {
        $query = Invitation::query()
            ->with(['institution:id,name', 'role:id,role,name', 'creator:id,name,email'])
            ->orderBy('created_at', 'desc');

        if (! $user->canAccessAdmin()) {
            $query->where('institution_id', $user->institution_id);
        }

        return $query;
    }

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
        $template = EmailNotificationTemplate::getByKey(EmailNotificationTemplate::KEY_INSTITUTION_INVITATION);
        if ($template && ! $template->is_enabled) {
            return;
        }

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation));
    }

    public function registerFromInvitation(Invitation $invitation, array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $invitation->institution_id,
        ]);

        $user->roles()->sync([$invitation->role_id]);
        $this->twoFactorService->enforceGlobalRequirement($user);

        if ($invitation->institution_id) {
            Employee::firstOrCreate(
                [
                    'email' => $user->email,
                    'institution_id' => $invitation->institution_id,
                ],
                [
                    'name' => $user->name ?? '',
                    'surname' => '',
                ]
            );
        }

        $invitation->update(['used_at' => now()]);

        return $user;
    }
}
