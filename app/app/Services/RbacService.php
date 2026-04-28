<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Hash;

class RbacService
{
    public function ensureEmployeeForInstitution(User $user): void
    {
        if (! $user->institution_id) {
            Employee::where('email', $user->email)->delete();

            return;
        }

        $employee = Employee::firstOrCreate(
            [
                'email' => $user->email,
                'institution_id' => $user->institution_id,
            ],
            [
                'name' => $user->name ?? '',
                'surname' => '',
            ]
        );

        if ($employee->wasRecentlyCreated) {
            return;
        }

        $employee->name = $user->name ?? $employee->name;
        $employee->save();
    }

    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $data['institution_id'] ?? null,
        ]);

        if (! empty($data['role_id'])) {
            $user->roles()->sync([$data['role_id']]);
        }

        $this->ensureEmployeeForInstitution($user);

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        $user->notify(new AccountCreatedNotification);

        return $user;
    }

    public function updateUser(User $user, array $data): void
    {
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->institution_id = array_key_exists('institution_id', $data) ? $data['institution_id'] : $user->institution_id;

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $this->ensureEmployeeForInstitution($user);
    }
}
