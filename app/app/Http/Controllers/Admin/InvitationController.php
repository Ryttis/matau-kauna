<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\UserRole;
use App\Services\InvitationService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }

        return $this->invitationService->getIndexQuery($user)->paginate(20);
    }

    public function store(StoreInvitationRequest $request)
    {
        $user = $request->user();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }

        $data = $request->validated();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            $data['institution_id'] = $user->institution_id;
        }
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && (int) $data['institution_id'] !== (int) $user->institution_id) {
            abort(403);
        }

        $result = $this->invitationService->createInvitation($data, $user);
        if (isset($result['error'])) {
            return response()->json($result['error'], 422);
        }

        $this->invitationService->sendInvitationNotification($result['invitation']);

        return $result['invitation']->load(['institution:id,name', 'role:id,role,name']);
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
                'token' => \Illuminate\Support\Str::random(64),
                'expires_at' => now()->addDays(7),
            ]);
        }

        $this->invitationService->sendInvitationNotification($invitation);

        return $invitation->load(['institution:id,name', 'role:id,role,name']);
    }
}
