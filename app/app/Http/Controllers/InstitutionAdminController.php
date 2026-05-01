<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitutionAdminInstitutionId;
use App\Models\Institution;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserRole;
use App\Services\InstitutionGisConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionAdminController extends Controller
{
    use ResolvesInstitutionAdminInstitutionId;

    public function __construct(
        private InstitutionGisConfigService $gisConfigService,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! ($user instanceof User)) {
            abort(403);
        }

        if (! $user->canManageTeams() && ! $user->canManageInvitations()) {
            abort(403);
        }

        $institutionId = $this->resolveInstitutionId($request, $user);

        $institution = Institution::query()
            ->notArchived()
            ->with('gisConfig')
            ->findOrFail($institutionId);

        $inviteRoles = UserRole::query()
            ->whereIn('role', [UserRole::ROLE_INSTITUTION_ADMIN, UserRole::ROLE_EMPLOYEE])
            ->orderBy('name')
            ->get(['id', 'role', 'name']);

        $institutionUsers = User::query()
            ->notArchived()
            ->where('institution_id', $institutionId)
            ->with(['roles:id,role,name'])
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name', 'email', 'institution_id', 'two_factor_enabled']);

        return Inertia::render('InstitutionAdmin/Index', [
            'activeInstitutionId' => $institutionId,
            'profileSaveMode' => $user->canAccessAdmin() ? 'admin' : 'settings',
            'institutionRecord' => $institution,
            'institutionGis' => $this->gisConfigService->settingsPayload($institution->gisConfig),
            'institutionUsers' => $institutionUsers,
            'inviteRoles' => $inviteRoles,
        ]);
    }

    public function invitations(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! ($user instanceof User) || ! $user->canManageInvitations()) {
            abort(403);
        }

        $institutionId = $this->resolveInstitutionId($request, $user);

        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        $paginator = Invitation::query()
            ->where('institution_id', $institutionId)
            ->with(['institution:id,name', 'role:id,role,name', 'creator:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($paginator);
    }
}
