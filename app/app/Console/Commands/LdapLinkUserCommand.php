<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ldap\LdapIdentity;
use App\Services\Ldap\LdapSettings;
use Illuminate\Console\Command;

/**
 * Administrative pre-provisioning of the explicit directory binding (ADR-0001).
 * Linking only records which directory account may authenticate as an existing staff user;
 * it assigns no role, permission or institution and creates no user.
 */
class LdapLinkUserCommand extends Command
{
    protected $signature = 'ldap:link-user
        {user : Existing MatauKauna user id or email}
        {username? : Directory account name (sAMAccountName); omit with --unlink}
        {--unlink : Remove the directory binding from the user}';

    protected $description = 'Susieja esamą vidinį naudotoją su katalogo (AD) paskyra LDAP autentifikacijai arba panaikina susiejimą.';

    public function handle(LdapSettings $settings): int
    {
        $user = $this->findUser((string) $this->argument('user'));
        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if ($this->option('unlink')) {
            $user->forceFill(['ldap_source' => null, 'ldap_username' => null, 'ldap_linked_at' => null])->save();
            $this->info(sprintf('Removed directory binding from user #%d (%s).', $user->id, $user->email));

            return self::SUCCESS;
        }

        $username = LdapIdentity::normalizeUsername((string) $this->argument('username'));
        if ($username === '') {
            $this->error('A directory account name is required (or use --unlink).');

            return self::FAILURE;
        }

        if (! $user->hasStaffRole()) {
            $this->error('Only users with an internal (staff) role can be linked; reporter-only accounts are never LDAP-managed.');

            return self::FAILURE;
        }

        $conflict = User::query()
            ->whereLdapIdentity($settings->source, $username)
            ->where('id', '!=', $user->id)
            ->first();
        if ($conflict !== null) {
            $this->error(sprintf('Directory account "%s" is already linked to user #%d.', $username, $conflict->id));

            return self::FAILURE;
        }

        $user->forceFill([
            'ldap_source' => $settings->source,
            'ldap_username' => $username,
            'ldap_linked_at' => now(),
        ])->save();

        $this->info(sprintf('Linked user #%d (%s) to directory account "%s" (%s). Roles and permissions are unchanged.', $user->id, $user->email, $username, $settings->source));
        if (! $settings->enabled) {
            $this->warn('LDAP_ENABLED is false: the local password remains in use until LDAP is enabled.');
        }

        return self::SUCCESS;
    }

    private function findUser(string $identifier): ?User
    {
        if (ctype_digit($identifier)) {
            return User::query()->find((int) $identifier);
        }

        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($identifier))])->first();
    }
}
