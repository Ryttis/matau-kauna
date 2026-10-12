<?php

namespace App\Console\Commands;

use App\Services\Ldap\LdapAuthenticationException;
use App\Services\Ldap\LdapConnectionFactory;
use App\Services\Ldap\LdapSettings;
use Illuminate\Console\Command;

/**
 * Safe LDAP diagnostic: prints the non-secret configuration and performs an anonymous
 * RootDSE read. It never asks for, accepts or prints a user password.
 */
class LdapCheckCommand extends Command
{
    protected $signature = 'ldap:check';

    protected $description = 'Parodo LDAP konfigūraciją (be paslapčių) ir patikrina ryšį su katalogu per anoniminę RootDSE užklausą.';

    public function handle(LdapSettings $settings, LdapConnectionFactory $connections): int
    {
        $this->table(['Setting', 'Value'], [
            ['Enabled', $settings->enabled ? 'yes' : 'no'],
            ['Source id', $settings->source],
            ['URI', $settings->safeUri()],
            ['Base DN', $settings->baseDn],
            ['Username attribute', $settings->usernameAttribute],
            ['Bind template', $settings->bindTemplate],
            ['Protocol version', (string) $settings->version],
            ['Referrals', $settings->referrals ? 'yes' : 'no'],
            ['TLS verification', $settings->tlsVerify ? 'yes' : 'NO (certificate validation disabled - temporary workaround only)'],
            ['Network timeout', $settings->networkTimeout.'s'],
            ['PHP ext-ldap', function_exists('ldap_connect') ? 'loaded' : 'MISSING'],
        ]);

        if (! function_exists('ldap_connect')) {
            $this->error('PHP ext-ldap is not loaded; LDAP authentication cannot work in this runtime.');

            return self::FAILURE;
        }

        $connection = $connections->create();

        try {
            $connection->bindAnonymously();
            $rootDse = $connection->readRootDse(['defaultNamingContext', 'dnsHostName', 'supportedLDAPVersion']);
        } catch (LdapAuthenticationException $e) {
            $this->error(sprintf('LDAP check failed [%s/%s]: %s', $e->reason, $e->operation, $e->getMessage()));

            return self::FAILURE;
        } finally {
            $connection->close();
        }

        $namingContext = $rootDse['defaultnamingcontext'][0] ?? null;
        $this->info(sprintf(
            'Anonymous bind and RootDSE read succeeded (defaultNamingContext: %s, dnsHostName: %s, supportedLDAPVersion: %s).',
            $namingContext ?? 'n/a',
            $rootDse['dnshostname'][0] ?? 'n/a',
            implode(',', $rootDse['supportedldapversion'] ?? []) ?: 'n/a',
        ));

        if ($namingContext !== null) {
            if (str_ends_with(mb_strtolower(str_replace(' ', '', $settings->baseDn)), mb_strtolower(str_replace(' ', '', $namingContext)))) {
                $this->info('Base DN is within the directory\'s defaultNamingContext.');
            } else {
                $this->warn('Base DN does not end with the directory\'s defaultNamingContext - verify LDAP_BASE_DN.');
            }
        }

        return self::SUCCESS;
    }
}
