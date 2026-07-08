<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserBlockingService
{
    public const BLOCKED_MESSAGE = 'Paskyra užblokuota. Kreipkitės į administratorių.';

    public const NOT_REPORTER_ONLY_MESSAGE = 'Blokuoti galima tik pareiškėjų paskyras.';

    public function assertCanBlock(User $user): void
    {
        if (! $user->isReporterOnly()) {
            abort(422, self::NOT_REPORTER_ONLY_MESSAGE);
        }
    }

    public function block(User $user, User $actor): User
    {
        $this->assertCanBlock($user);

        return DB::transaction(function () use ($user, $actor): User {
            $user->forceFill([
                'blocked_at' => now(),
                'blocked_by' => $actor->id,
                'unblocked_at' => null,
                'unblocked_by' => null,
            ])->save();

            $this->revokeAccess($user);

            return $user->fresh(['roles:id,role,name', 'institution:id,name', 'acceptedTermsVersion:id,version']);
        });
    }

    public function unblock(User $user, User $actor): User
    {
        $this->assertCanBlock($user);

        $user->forceFill([
            'blocked_at' => null,
            'blocked_by' => null,
            'unblocked_at' => now(),
            'unblocked_by' => $actor->id,
        ])->save();

        return $user->fresh(['roles:id,role,name', 'institution:id,name', 'acceptedTermsVersion:id,version']);
    }

    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
