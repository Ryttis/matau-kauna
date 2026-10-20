<?php

/*
|--------------------------------------------------------------------------
| LDAP credential verification for internal (staff) users
|--------------------------------------------------------------------------
|
| LDAP only verifies the primary credential of explicitly enrolled staff
| accounts (users.ldap_source / users.ldap_username). MatauKauna keeps
| ownership of application users, roles, permissions, institution scope,
| TOTP, sessions, Sanctum tokens and login auditing. See ADR-0001.
|
*/

return [

    // Rollout / rollback switch. When false every login path keeps its
    // existing local-password behaviour, including for enrolled accounts.
    'enabled' => (bool) env('LDAP_ENABLED', false),

    // Logical identifier of the directory stored next to each binding
    // (users.ldap_source) so a future second directory cannot collide.
    'source' => env('LDAP_SOURCE', 'kaunas-ad'),

    'uri' => env('LDAP_URI', 'ldaps://ldap.example.internal:636'),

    'base_dn' => env('LDAP_BASE_DN', 'CN=Users,DC=example,DC=internal'),

    // Attribute that carries the login name; interpolated into the search filter.
    'username_attribute' => env('LDAP_USERNAME_ATTRIBUTE', 'sAMAccountName'),

    // Bind DN/identity template; {username} is replaced with the submitted name.
    'bind_template' => env('LDAP_BIND_TEMPLATE', '{username}'),

    'version' => (int) env('LDAP_VERSION', 3),

    'referrals' => (bool) env('LDAP_REFERRALS', false),

    // Certificate verification for ldaps:// / STARTTLS. Disabling it is a
    // temporary infrastructure workaround and is reported by `ldap:check`.
    'tls_verify' => (bool) env('LDAP_TLS_VERIFY', true),

    // Bounded connect/operation timeouts in seconds.
    'network_timeout' => (int) env('LDAP_NETWORK_TIMEOUT', 10),

];
