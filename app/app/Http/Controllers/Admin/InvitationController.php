<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\UserRole;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitationService
    ) {}

    public function index(Request $request)
    {
        $this->ensureCanManageInvitations($request->user());

        return $this->invitationService->getIndexQuery($request->user())->paginate(20);
    }

    public function store(StoreInvitationRequest $request)
    {
        $user = $request->user();
        $this->ensureCanManageInvitations($user);

        $data = $request->validated();
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN)) {
            $data['institution_id'] = $user->institution_id;
        }
        $this->ensureSameInstitution($user, $data['institution_id'] ?? null);

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
        $this->ensureCanManageInvitations($user);
        $this->ensureSameInstitution($user, $invitation->institution_id);

        if ($invitation->used_at) {
            return response()->json(['message' => 'Pakvietimas jau panaudotas.'], 422);
        }

        if (! $invitation->expires_at || $invitation->expires_at->isPast()) {
            $invitation->update([
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
            ]);
        }

        $this->invitationService->sendInvitationNotification($invitation);

        return $invitation->load(['institution:id,name', 'role:id,role,name']);
    }

    private function ensureCanManageInvitations($user): void
    {
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && ! $user->hasRole(UserRole::ROLE_INSTITUTION_ADMIN)) {
            abort(403);
        }
    }

    private function ensureSameInstitution($user, ?int $institutionId): void
    {
        if (! $user->hasRole(UserRole::ROLE_SYSTEM_ADMIN) && (int) $institutionId !== (int) $user->institution_id) {
            abort(403);
        }
    }
}
