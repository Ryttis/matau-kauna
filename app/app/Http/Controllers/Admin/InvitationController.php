<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\InvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }

        $query = Invitation::query()
            ->with(['institution:id,name', 'role:id,role,name', 'creator:id,name,email'])
            ->orderBy('created_at', 'desc');

        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            $query->where('institution_id', $user->institution_id);
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'role_id' => ['required', 'uuid', 'exists:user_role,id'],
        ]);

        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            $data['institution_id'] = $user->institution_id;
        }
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && (int) $data['institution_id'] !== (int) $user->institution_id) {
            abort(403);
        }

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['message' => 'Naudotojas su tokiu el. paštu jau egzistuoja.'], 422);
        }

        $role = UserRole::findOrFail($data['role_id']);
        $allowedRoles = [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE];
        if (! in_array($role->role, $allowedRoles, true)) {
            return response()->json(['message' => 'Galima kviesti tik įstaigos administratorių arba darbuotoją.'], 422);
        }

        $invitation = Invitation::create([
            'email' => $data['email'],
            'institution_id' => $data['institution_id'],
            'role_id' => $data['role_id'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation));

        return $invitation->load(['institution:id,name', 'role:id,role,name']);
    }

    public function resend(Request $request, Invitation $invitation)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }

        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && (int) $invitation->institution_id !== (int) $user->institution_id) {
            abort(403);
        }

        if ($invitation->used_at) {
            return response()->json(['message' => 'Pakvietimas jau panaudotas.'], 422);
        }

        if (! $invitation->expires_at || $invitation->expires_at->isPast()) {
            $invitation->update([
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
            ]);
        }

        \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
            ->notify(new InvitationNotification($invitation));

        return $invitation->load(['institution:id,name', 'role:id,role,name']);
    }
}
