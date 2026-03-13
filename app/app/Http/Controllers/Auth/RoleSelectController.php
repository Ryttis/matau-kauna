<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleSelectController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $roles = $user->roles()->get()->map(fn ($r) => ['id' => $r->id, 'role' => $r->role, 'name' => $r->name])->toArray();

        if (count($roles) === 0) {
            return redirect()->route('dashboard');
        }

        if (count($roles) === 1) {
            $request->session()->put('active_role_id', $roles[0]['id']);

            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/RoleSelect', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:user_role,id'],
        ]);

        $user = $request->user();
        if (! $user->roles()->where('user_role.id', $data['role_id'])->exists()) {
            abort(403);
        }

        $request->session()->put('active_role_id', $data['role_id']);

        return redirect()->route('dashboard');
    }
}
