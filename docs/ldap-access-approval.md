# LDAP staff access approval

LDAP authenticates identity. A MatauKauna administrator authorizes application access. AD groups do not determine application roles.

## Normal operational workflow

1. With LDAP enabled, the staff member selects staff login and enters their directory account name (`sAMAccountName`) and LDAP password. First-time enrollment uses the directory username; email login remains available only for already enrolled local accounts.
2. Laravel verifies the credentials using the existing LDAP bind/search. If no local user is linked, it records a durable access request and denies both web and API login with `Paskyra laukia administratoriaus patvirtinimo.` No user, role, session or token is created for that request.
3. An administrator with the existing `admin.access` permission opens **Administravimas → Laukiantys LDAP vartotojai** (`/admin?tab=ldap`). The list shows directory identity, optional display name/email, first/last attempts and status. Repeated verified attempts update the same normalized identity record.
4. The administrator chooses **Patvirtinti**, then either creates a new user with reviewed name/email or explicitly searches for and selects an existing local user. Directory email is only a suggested form value; it never selects a local user automatically. Existing LDAP bindings cannot be overwritten through approval.
5. The administrator selects one or more existing staff roles and an institution. Employee and institution-administrator roles require an institution; other staff roles permit no institution. Reporter roles cannot be selected. For an existing user, the selected roles and institution replace the previous assignments.
6. Approval creates/updates the application user, synchronizes roles and Employee records through RBAC, links the directory identity, and marks the request approved in one database transaction.
7. Subsequent LDAP login resolves the approved user and follows the existing blocked-account, role, TOTP and email-verification rules. New staff retain the normal default of TOTP enabled and receive setup at the next login. Directory email does not mark an address verified; the existing verification screen/resend workflow applies. No local password is displayed or emailed: a newly created account receives an unknown random local password hash.

**Atmesti** preserves the request, records the administrator and rejection time, and grants no access. Further verified login attempts update its last-seen time but leave it rejected and denied. Filter by **Atmestas** to reconsider and approve later; rejection metadata remains as history. Approved requests cannot be approved or rejected again.

## Storage, authorization and concurrency

Run the normal Laravel migrations before using this feature. `2026_10_20_000001_create_ldap_access_requests_table` adds `ldap_access_requests`, including a unique `(ldap_source, ldap_username)` pair, optional directory display name/email, status, observation/decision timestamps, administrator references and linked user reference. Both identity components are trimmed and lowercased. Passwords, bind credentials, raw directory records and distinguished names are not stored here.

The existing authenticated, verified admin middleware protects:

- `GET /admin/ldap-access-requests` (optional `status` filter; paginated)
- `POST /admin/ldap-access-requests/{id}/approve`
- `POST /admin/ldap-access-requests/{id}/reject`

Only a verified unmapped identity reaches observation. Invalid credentials, unavailable LDAP, failed/ambiguous search and duplicate mappings retain their existing errors. Approval uses PostgreSQL transaction advisory locks per normalized identity, row locks on the request/target user and the existing users identity unique index. CLI linking takes the same identity lock and uses the same conflict and staff-role checks. The concurrency guarantee targets the application's PostgreSQL database; the advisory lock is skipped on other drivers.

Events `auth.ldap.access_request.observed`, `.approved`, and `.rejected` use the existing integration audit mechanism, including source, username, target user ID where available and administrator actor ID. These integration events remain enabled independently of optional user-action auditing. Credentials are never included.

## Emergency CLI

`php artisan ldap:link-user <user-id-or-email> <sAMAccountName>` remains available for maintenance. It links an existing staff user and does not assign roles or institutions. `--unlink` still removes the binding. CLI linking does not mark an access request approved; if a request remains pending after a CLI link, a second UI approval fails because the identity is already linked. The user can authenticate through the existing mapping.

## Verification

Focused coverage lives in `LdapAccessRequestTest`, `LdapAccessApprovalTest`, and the existing LDAP web/API/mapping/command suites. The concurrency test uses two PostgreSQL connections to verify a competing approval cannot pass the identity lock, then verifies a single approval succeeds. Run these against the project's test database, alongside existing public/VIISP authentication regressions:

```sh
php artisan test --filter=Ldap
php artisan test tests/Feature/Auth
```

Frontend verification: `npm run build` and ESLint on the changed admin components.

## Changed-file inventory

Paths are relative to the repository root.

| Area | Files |
| --- | --- |
| Storage | `app/database/migrations/2026_10_20_000001_create_ldap_access_requests_table.php`, `app/app/Models/LdapAccessRequest.php`, `app/app/Models/User.php` |
| LDAP authentication and linking | `app/app/Services/Auth/StaffCredentialAuthenticator.php`, `app/app/Services/Ldap/LdapUserResolver.php`, `app/app/Services/Ldap/LdapAuthenticationException.php`, `app/app/Services/Ldap/LdapAccessService.php`, `app/app/Services/Ldap/LdapLinkService.php`, `app/app/Console/Commands/LdapLinkUserCommand.php` |
| Web/API login responses | `app/app/Http/Requests/Auth/LoginRequest.php`, `app/app/Http/Controllers/Api/AuthApiController.php` |
| Admin and RBAC | `app/app/Http/Controllers/Admin/LdapAccessRequestController.php`, `app/app/Http/Controllers/Admin/RbacUserController.php`, `app/app/Services/RbacService.php`, `app/routes/web.php` |
| Admin UI | `app/resources/js/Pages/Admin/Index.vue`, `app/resources/js/Pages/Admin/Tabs/AdminLdapRequests.vue` |
| Tests | `app/tests/Feature/Auth/LdapAccessRequestTest.php`, `app/tests/Feature/Admin/LdapAccessApprovalTest.php`, `app/tests/Feature/Auth/LdapUserMappingTest.php` |
| Documentation | `docs/ldap-access-approval.md`, `docs/architecture.md`, `docs/adr/0001-ldap-authentication.md` |
