# ADR-0001: LDAP Authentication for Internal Users

Status: Proposed (initial implementation delivered behind `LDAP_ENABLED`; see [Implementation Status](#implementation-status-2026-10-12))

Date: 2026-09-16 (implementation notes added 2026-10-12)

Related work: [ARCH-010](../architecture-backlog.md#arch-010--introduce-ldap-authentication-for-internal-users). Current baseline: [architecture.md](../architecture.md).

This remains a proposed architectural decision: the policy questions listed under [Open Questions](#open-questions) that are not marked resolved below are still unresolved. The sections from Context to Open Questions are the original proposal; the Implementation Status section records what the code now does.

## Context

The requested first architecture feature is LDAP authentication for internal users. Its purpose is to move credential verification for eligible staff to the organization's directory while retaining MatauKauna application access management. The directory's operator, schema and eligible staff population are **Unknown / requires confirmation**; no existing LDAP infrastructure is inferred from this request.

Today both staff and citizens use `App\Models\User` / `users`. [`LoginRequest::authenticate`](../../app/app/Http/Requests/Auth/LoginRequest.php) validates email/password and calls `Auth::attempt` through the Eloquent session provider. [`AuthApiController::login`](../../app/app/Http/Controllers/Api/AuthApiController.php) independently calls `Hash::check`, then issues Sanctum tokens. `login_type=darbuotojas` requires any non-reporter assigned role, including custom roles. The Vue login input and both server paths currently require an email address.

`RbacService` and `InvitationService` create local staff accounts/passwords and enable TOTP for new staff. Existing authorization depends on local user IDs, roles, active-role selection, institutions and employee assignment records. Local password resets, profile password changes and administrative password changes already exist. LDAP must account for these paths rather than merely replacing one password check.

There are additional login paths. VIISP can map to a staff-bearing account, and signed email verification and invitation acceptance can establish web sessions. They do not all invoke the normal staff TOTP challenge. `AuthenticationTest::test_viisp_login_restores_saved_active_role` confirms that staff use through VIISP is represented in the existing tests. Preserving public VIISP functionality does not settle which methods should confer internal access (ARCH-001).

## Existing Authentication Boundaries

| Boundary | Current behavior | Constraint on LDAP |
| --- | --- | --- |
| Laravel | Owns local credential checks, application user mapping, account checks and TOTP | Keep new internal credential verification here. |
| VIISP | External identity source reached through a SOAP adapter | Preserve public flow and identity contract; decide treatment of mixed citizen/staff accounts explicitly. |
| Node gateway | Stores VIISP ticket/callback/exchange state and returns normalized identity; no application RBAC/user persistence | Do not add directory credentials or application role decisions. Its source/compiled artifact discrepancy needs independent confirmation (ARCH-002). |
| Browser session | Laravel `web` guard; regenerated on successful completion | Directory success alone must not create an unrestricted session before application checks/TOTP. |
| API access | Sanctum bearer tokens for the same users; optional pending TOTP challenge and sliding expiry | Internal API login must use the same chosen credential policy; changing only web login leaves a local-password route. |
| Authorization | Local role/permission tables, active role and institution/assignment scope | LDAP authenticates; MatauKauna authorizes. |
| Account lifecycle | Archiving, reporter-only blocking and differing activity updates | LDAP disablement is not automatically reflected in existing sessions/tokens; define revocation policy (ARCH-005). |

## Decision Drivers

- Minimize impact on working citizen VIISP and local reporter authentication.
- Separate credential verification from application permissions and institution scope.
- Reuse existing Laravel sessions, Sanctum and TOTP without adding a network service.
- Make identity mapping explicit and durable through account renames and email changes.
- Fail closed for LDAP authentication failures and outages; prevent hidden password fallback.
- Keep browser and API behavior testable against the same credential boundary.
- Define provisioning, deactivation, account recovery and role ownership before migration.
- Minimize infrastructure and operational dependencies; make failures observable without exposing credentials.

## Candidate Designs

### Option A — LDAP directly from Laravel

Introduce a narrowly scoped LDAP credential-verification service in Laravel and call it from internal login orchestration. Encapsulate LDAP transport, search/bind behavior, timeouts, identity normalization and error translation there; do not put raw LDAP calls into controllers. Continue resolving a local `User` through the existing Eloquent provider and use the current session guard / Sanctum for authenticated access.

Benefits: one new integration boundary; no extra HTTP service or duplicated identity database; existing application checks can remain local. Both browser/API paths can share the verifier. A small client seam is sufficient to fake directory outcomes in tests.

Costs: Laravel runtime connectivity and the eventual client/library requirements must be provided. Both credential entry points and password lifecycle paths must be updated consistently. The current Dockerfile installs no LDAP extension, and Composer declares no LDAP client; required runtime changes depend on the client selected later.

### Option B — Authentication abstraction/provider layer in Laravel

Create an application-level `AuthenticationProvider` contract with `LocalAuthenticationProvider` and `LdapAuthenticationProvider`, selected by explicit account/provider policy. This would be distinct from Laravel's existing Eloquent user provider and must not replace local user retrieval accidentally.

Benefits: a common result contract and test seam could unify duplicated local login behavior and support multiple providers over time. The repository does demonstrate drift between web/API completion and audit behavior.

Costs: a general provider registry adds dispatch and lifecycle rules before directory requirements are known. VIISP is a multi-step identity exchange, not a username/password provider; forcing it through that interface would broaden the change and threaten existing flows.

Do not introduce this full hierarchy initially. Share a focused internal authentication service and completion policy where required by the two entry points. Consider the broader interface only if implementation proves that local and LDAP provider selection is substantial, reusable behavior rather than a small explicit branch.

### Option C — LDAP through the existing Node gateway

Laravel would send staff credentials to Node, and Node would bind/search LDAP and return an identity to Laravel.

This expands a VIISP-specific protocol adapter into a general authentication service. The inspected gateway has only `/gateway/viisp/*` routes, a VIISP client and in-memory exchange state; it has no staff lifecycle, local user or authorization ownership. It would introduce another credential-bearing hop, service availability dependency, contract and deployment responsibility while Laravel still needs to map users and enforce TOTP/authorization.

Not recommended. The repository supplies no shared identity-platform requirement that justifies this responsibility expansion. No additional candidate is needed on the available evidence.

## Recommended Design

Recommend **Option A**, with a focused, testable Laravel service used by both internal browser and API login paths. Keep the existing Eloquent user provider and session guard. Do not make Node or the Vue frontend an LDAP client.

### Verification and identity mapping

The LDAP adapter verifies directory credentials and returns a normalized identity containing a directory source identifier and a stable directory subject identifier. Search attributes, bind procedure, allowed user population and disabled/locked/expired-account semantics depend on the actual directory and remain **Unknown / requires confirmation**. Reject blank credentials and ambiguous/multiple search results. Require authenticated encrypted transport with certificate verification; the TLS mode and trust material have not been chosen.

Map that subject to an explicitly provisioned local `User` before granting access. Preserve its existing user ID, history and application relationships. Email and display name are attributes, not sufficient proof for automatic linking. Do not reuse `viisp_personal_code_hash`, reinterpret its MD5 values, or overwrite a citizen account on an email match. Future persistence for the source/subject binding and provider eligibility must enforce uniqueness; its concrete schema is deferred to implementation design.

For the initial proposal, reject a successfully authenticated but unmapped LDAP identity with a generic access-denied response and an auditable mapping reason. Do not automatically provision roles or elevated access on first login. Pre-provision and reconcile a pilot set administratively. This conservative behavior is a proposal, not an assertion about business requirements.

### Authorization and session ownership

LDAP proves identity. MatauKauna owns eligibility to use internal features, assigned roles, permissions, active-role selection, institution membership and report scope. Keep role assignments local initially; **do not automatically map LDAP groups to roles**. A future approved group mapping needs explicit allowlisted mappings, removal semantics, custom-role scope rules and tests; none is invented here.

After mapping, apply the approved account-enabled/internal-eligibility checks and existing TOTP requirement. Only then complete the web session (`Auth::login`, session regeneration, remember handling) or issue the Sanctum token. Preserve email-address verification requirements unless a separate approved decision changes them; an LDAP email attribute alone must not silently set verification state. Session creation by the verification endpoint must still follow the alternative-login policy below. Normalize login activity/audit recording across both paths.

Resolve the permitted authentication methods for mixed VIISP/staff accounts and the email/invitation bootstrap paths before rollout. For an LDAP-managed internal account, local login, password recovery or a lower-assurance alternative must not silently circumvent the chosen directory/TOTP policy. Whether VIISP remains a permitted internal method, requires additional checks, or grants only public capabilities is **Unknown / requires confirmation** (ARCH-001). Preserve the established public VIISP protocol and reporter behavior while making that explicit decision.

### Failure, local passwords and lifecycle

Invalid credentials, untrusted TLS, LDAP timeouts or directory unavailability must not create a session/token and must not trigger a local-password fallback. Use bounded connection/operation timeouts and distinguish unavailable versus invalid-credential outcomes internally, without disclosing account existence or secrets. Do not queue plaintext login credentials or cache successful passwords for offline use.

Local credentials remain for local citizen accounts and staff explicitly outside the LDAP rollout, if such a staff population is approved. LDAP-managed accounts must not accept their existing local password once enrolled; reset/profile/admin password paths need the same restriction. Do not store directory passwords or synchronize them into `users.password`. Historical password-field treatment and any necessary placeholder for the currently required column are implementation details to resolve without making that value a login fallback.

An emergency local administrator, if operationally required, must be explicitly designated, controlled and audited. This ADR does not assume one exists or authorize an automatic outage bypass. Migration rollback should be a controlled operator action, never automatic provider switching after a failed bind.

Under this proposal, LDAP credential verification happens at login; subsequent application requests would use Laravel sessions or Sanctum tokens without routinely rebinding to LDAP. No LDAP checks exist today. Directory disablement would therefore not immediately revoke existing application access without a chosen lifecycle mechanism. Define the allowed revocation delay, local enabled/disabled state, session/token/remember-cookie revocation, account reactivation and ownership. Do not use `archived_at` as the new disable flag: existing login paths can clear it, and `UserBlockingService` currently only supports reporter blocking.

## Proposed Flow

```mermaid
sequenceDiagram
    actor U as Internal user
    participant E as Laravel web or API entry point
    participant A as Internal authentication service
    participant L as LDAP adapter and directory
    participant D as Local identity mapping and User
    participant C as Laravel completion and authorization
    U->>E: Submit credentials for internal login
    E->>A: Validated request; throttled attempt; explicit provider policy
    A->>L: Verify over authenticated encrypted connection
    alt Invalid credentials, unavailable directory or invalid TLS
        L-->>A: Controlled failure
        A-->>E: Failure; no local fallback
        E-->>U: Login denied or temporarily unavailable
    else Verified directory identity
        L-->>A: Directory source and stable subject
        A->>D: Resolve existing explicit binding
        alt Missing/ambiguous binding or ineligible local account
            D-->>A: Deny
            A-->>E: No session or token
            E-->>U: Access denied
        else Eligible internal user
            D-->>A: Local User; local roles unchanged
            A->>C: Prepare existing application TOTP challenge if required
            opt TOTP required by application policy
                C-->>U: Pending challenge only; no final access
                U->>C: Submit challenge response
            end
            C->>D: Recheck eligibility before completion
            alt Required TOTP not satisfied or eligibility lost
                C-->>U: Deny completion; no authenticated session or API access token
            else All required checks satisfied
                C->>D: Record login activity/audit
                C-->>U: Regenerated web session or Sanctum token
                U->>C: Request application operation
                C->>D: Resolve active role, permissions and data scope
                C-->>U: Authorized response or forbidden
            end
        end
    end
```

The local-user provider policy must be authoritative server-side. A login tab or client-supplied provider name cannot authorize bypassing LDAP. Local-only users use their separately permitted path; they are not a fallback branch in this sequence.

## Components Affected

Probable touchpoints for later implementation, not changes made by this review:

| Module/file below `app/` unless stated otherwise | Expected concern |
| --- | --- |
| New focused service/client under `app/Services` and a configuration file | LDAP verification, normalized result, timeouts, dependency injection and environment contract. |
| `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Internal provider selection, username format, web completion. |
| `app/Http/Controllers/Api/AuthApiController.php` | Apply the same staff credential policy and failures to API login. |
| `app/Services/TwoFactorService.php`, `Auth/TwoFactorChallengeController.php` | Preserve TOTP and recheck account eligibility at completion. |
| `app/Models/User.php`, future migrations, `RbacService.php`, `InvitationService.php` | Explicit directory binding, provisioning/lifecycle and local-password eligibility. |
| Auth password-reset controllers, `SettingsController.php`, `Api/SettingsApiController.php`, RBAC user requests/controllers | Prevent local-password reset/update paths from reopening LDAP-managed access. |
| `Auth/VerifyEmailController.php`, `Auth/InvitationRegistrationController.php`, both VIISP controllers | Enforce the approved alternative-login policy without changing the VIISP protocol. |
| `ActiveRoleService.php`, relevant middleware, `ProblemScopeService.php` | Regression coverage for existing authorization; no group-role redesign is implied. |
| `resources/js/Pages/Auth/Login.vue`, onboarding/password UI, `docs/openapi.yaml` | Approved identifier format, errors and unavailable local-password actions. |
| `tests/Feature/Auth`, relevant admin/authorization tests | Characterization and LDAP outcome/regression coverage. |
| Root `Dockerfile`, deployment/configuration documentation, `composer.json` | Potential client runtime/extension, trusted CA and connectivity requirements after client selection. |

The Node gateway and handler need no LDAP behavior. Their existing artifact/contract backlog remains separate.

## Migration Strategy

1. Resolve the rollout-blocking questions below and accept/amend this ADR. Inventory staff accounts, mixed identities, alternative session paths and actual API consumers. Specify the allowed authentication-method/account-state matrix (ARCH-001, ARCH-005).
2. Establish isolated PostgreSQL tests and characterize current web/API, TOTP, authorization and VIISP behavior. Confirm the gateway artifact used for regression testing (ARCH-002, ARCH-013). Do not run credential-triggered external-effect tests as ordinary regressions.
3. Choose the LDAP client against confirmed directory requirements and runtime compatibility. Define the configuration/secret/trust contract, explicit mapping storage and approved lifecycle behavior. This step produces a concrete implementation design, not an assumed schema or role mapping.
4. Add the focused verifier and shared internal policy checks behind an explicit rollout control. Preserve current local behavior for accounts outside the pilot. Reconcile pilot identity mappings and employee/institution relationships before enabling access.
5. Enable web and API credential checks together for the pilot, including all alternative-login and password-management rules. Disable local-password acceptance for each account when it is enrolled, not after the wider rollout. Verify TOTP, denials, outage behavior, revocation and rollback in an isolated environment; never reuse production credentials in fixtures.
6. Expand enrollment only after operational validation, applying the same restrictions at each account's enrollment, and update support/onboarding procedures. Resolve ARCH-010 when rollout and its acceptance criteria are verified; update the baseline and ADR status to reflect reality.

## Testing Strategy

| Case | Required result |
| --- | --- |
| Valid LDAP authentication | Correct pre-mapped user in web/API, unchanged local roles, required TOTP completed before final access. |
| Invalid credentials, blank password, malformed search input | Denial; no anonymous-bind success, no session/token, bounded/throttled attempts and no credential leakage. |
| LDAP unavailable, timeout or certificate validation failure | Controlled unavailable result, no fallback, no new access; agreed behavior for existing sessions. |
| Unknown LDAP user | Directory rejection and verified-but-unmapped identity both deny access; no implicit user creation. |
| User mapping | Stable subject survives approved rename/email change; duplicate/ambiguous subjects, conflicting citizen accounts, cross-directory collisions and missing mappings deny safely. |
| User lifecycle | Disabled/locked directory account and disabled local account cannot complete login; pending TOTP and existing sessions/tokens obey the agreed revocation window. |
| Password lifecycle | Local login/reset/profile/admin password changes cannot restore local authentication for enrolled accounts; approved local-only accounts still work. |
| Existing authorization regression | Built-in/custom roles, role switching/removal, institution/team/employee scope, forbidden actions and web/API parity remain as approved. Directory membership alone grants no permission. |
| Existing VIISP regression | Preserve supported public login-init, callback and gateway identity-token exchange behavior, including missing/expired/replayed tokens, identity conflict, new-user terms/registration, blocked reporter and local-password opt-in. Test both web/mobile plus the explicitly approved mixed staff policy. Characterize the separate `/api/viisp/exchange` route under ARCH-015; no producer for its codes was found, so do not assume it is a working baseline flow. |
| Alternative session paths | Email verification and invitation acceptance honor the approved staff assurance policy without breaking reporter onboarding. |
| Operational behavior | Sanitized outcome/correlation logs, timeout bounds, mapping audit, restart/configuration failure and controlled rollback are observable. |

Use fake directory outcomes for application tests and an isolated directory for protocol/TLS tests once its type is known. Test through both route sets, not only the adapter. Add no LDAP tests or dependencies in this documentation task.

## Implementation Status (2026-10-12)

Delivered under ARCH-010 (status In progress). All paths are relative to `app/`.

### Components

| Component | Responsibility |
| --- | --- |
| `config/ldap.php`, `.env.example` `LDAP_*` | Environment-driven contract: `LDAP_ENABLED`, `LDAP_URI`, `LDAP_BASE_DN`, `LDAP_USERNAME_ATTRIBUTE`, `LDAP_BIND_TEMPLATE`, `LDAP_VERSION`, `LDAP_REFERRALS`, `LDAP_TLS_VERIFY`, plus optional `LDAP_SOURCE` (binding source id, default `kaunas-ad`) and `LDAP_NETWORK_TIMEOUT` (default 10 s). |
| `app/Services/Ldap/LdapSettings` | Validated settings value object; bind identity from the template; RFC 4515-escaped search filter; log-safe URI. |
| `app/Services/Ldap/LdapConnection`, `ExtLdapConnection`, `LdapConnectionFactory` | Thin ext-ldap transport (no Composer LDAP package). Fresh connection per attempt; bounded network/operation timeouts; `LDAP_OPT_X_TLS_REQUIRE_CERT` set from `LDAP_TLS_VERIFY`; failures classified into `invalid_credentials` (LDAP error 49, with the AD `data` sub-code) or `ldap_unavailable` / `search_failed`. |
| `app/Services/Ldap/LdapAuthenticator` | Rejects empty username/password and account names containing AD-forbidden characters, whitespace or `@` before any network contact; performs exactly one bind with the configured template; searches `(&(objectClass=user)(<attr>=<escaped>))` under the base DN for `sAMAccountName`, `displayName`, `mail`, `distinguishedName` only; requires exactly one entry matching the bound name; returns `LdapIdentity`. `memberOf` is not read. |
| `app/Services/Ldap/LdapUserResolver` | Maps the identity to the pre-provisioned user; fails with `user_mapping_failed` when none or more than one match. Never creates users. |
| `app/Services/Auth/StaffCredentialAuthenticator` | Shared boundary used by both web (`LoginRequest`) and API (`AuthApiController::login`): decides server-side whether an attempt is LDAP-verified, performs verification + mapping, writes sanitized logs (username, bind identity, operation, reason, safe URI, AD sub-code; never the password) and an audit `auth.ldap.user_mapping_failed` system entry. |
| Migration `2026_10_12_120000_add_ldap_identity_to_users_table` | `users.ldap_source`, `users.ldap_username`, `users.ldap_linked_at`; unique index on `(ldap_source, ldap_username)`. |
| `php artisan ldap:check` | Prints the non-secret configuration and performs an anonymous RootDSE read (defaultNamingContext, dnsHostName, supportedLDAPVersion); compares the base DN to the naming context. Takes no credentials. |
| `php artisan ldap:link-user {user} {username} [--unlink]` | Administrative pre-provisioning of the binding. Refuses reporter-only accounts and duplicate directory names; changes no role, permission or institution. |
| Root `Dockerfile`, `composer.json` (`ext-ldap`) | PHP image now builds `ext-ldap`. |

### Decisions as implemented

- **Mapping policy.** The directory subject is the configured login attribute (`sAMAccountName`, stored lowercase) together with the directory source id. A user authenticates via LDAP only if an administrator linked that pair to the existing `User` beforehand. Email, display name and DN are returned in `LdapIdentity` but are not used for lookup, linking, creation or verification state. A verified but unlinked identity is denied with the generic login error and logged/audited as `user_mapping_failed`.
- **Provider selection (server-side).** With `LDAP_ENABLED=true` and `login_type=darbuotojas`: an identifier without `@` is always verified by LDAP; an email identifier is verified by LDAP (binding with the linked account name) when that local account is enrolled, otherwise the existing local-password path applies (staff outside the rollout). Reporter logins never use LDAP. With `LDAP_ENABLED=false` every path behaves exactly as before, including for enrolled accounts (controlled rollback).
- **No fallback.** Invalid credentials, outage, search failures, non-unique or unmapped identities all produce the same generic failure, hit the existing web rate limiter, and never consult `users.password`. An enrolled account's local password is not accepted while LDAP is enabled.
- **Unchanged application ownership.** Blocked-account check, staff/reporter login-type rules, `TwoFactorService` TOTP preparation and challenge (web session and `POST /api/two-factor/challenge`), session regeneration/remember handling, Sanctum token issuance, active-role restoration, `login_logs` and audit `auth.login` all run after mapping exactly as for local logins. No LDAP group is read or mapped to a role.
- **Identifier format.** `LoginRequest` and `POST /api/login` accept a non-email identifier only while LDAP is enabled and the login type is staff; `Login.vue` switches the staff tab input to free text based on the `staffUsernameLogin` page prop.

### Verified against the Kaunas directory (2026-10-12, local container)

`ldap:check` with the documented contract: with `LDAP_TLS_VERIFY=true` the server cannot be contacted (certificate not trusted by the PHP image); with `LDAP_TLS_VERIFY=false` the anonymous RootDSE read succeeds (`defaultNamingContext=DC=sav,DC=kaunas,DC=lt`, `dnsHostName=TAURAS.sav.kaunas.lt`, LDAP v3/v2) and the base DN is inside it. No real user login was executed in this task.

### Still unresolved (not decided by the implementation)

- Durable subject through renames/recreation: the binding uses `sAMAccountName`, which does not survive an AD rename; `objectGUID` or an equivalent stable attribute has not been approved.
- Alternative session paths for enrolled accounts (VIISP, invitation acceptance, signed email verification) are unchanged (ARCH-001).
- Local password reset/profile/admin password paths are unchanged; they cannot restore local login while LDAP is enabled, but the ADR's explicit restriction of those paths is not implemented.
- Directory disablement does not revoke existing sessions/tokens (ARCH-005); no emergency local administrator policy exists.
- TLS trust material so `LDAP_TLS_VERIFY=true` works in deployment; `LDAP_TLS_VERIFY=false` remains a temporary workaround. Observed on 2026-10-12: the server certificate (`CN=TAURAS.sav.kaunas.lt`) is issued by the internal CA `CN=KMSAAD,DC=sav,DC=kaunas,DC=lt`, which is not in the PHP image's trust store, and OpenSSL additionally reports `EE certificate key too weak` at its default security level. Verified TLS therefore needs both the CA chain in the image and a directory certificate (or an explicitly accepted security-level change) that passes that check.
- `POST /api/login` still has no explicit rate limiter (pre-existing), which now also affects AD lockout exposure.
- Account population/pilot list, provisioning ownership and the rollout/rollback procedure.

## Open Questions

Every unresolved value below is **Unknown / requires confirmation**. Directory credentials must be supplied through the approved secret mechanism, not recorded in this ADR.

- Who operates the directory, who owns the integration, and which internal institutions/users must use LDAP? Are contractors, partner institutions or local-only administrators in scope?
- What are the host(s), port(s), directory type/schema, base DN, search scope and filters? What is the supported login identifier (email, account name or another attribute)?
- Is a search/bind account needed? What permissions, secret source, rotation and lockout behavior apply? What are the stable subject attribute and rename/recreation semantics?
- Which TLS mode, trusted CA chain, certificate hostname verification, client certificates (if any), referral rules, network routes and firewall changes are required?
- Which disabled/locked/expired-account responses or attributes are authoritative? What timeouts, availability expectations and support procedures apply?
- Who approves existing-user mappings and resolves duplicate emails, multiple institutions and citizen/staff identities? Who owns profile attributes after binding?
- Is initial pre-provisioning acceptable, or is later just-in-time provisioning required? The recommendation here is pre-provisioning without automatic role assignment.
- May VIISP, signed email verification or invitation acceptance confer access to LDAP-managed internal accounts? What assurance/TOTP is required across those paths?
- Does application TOTP remain mandatory under the same policy, and how is recovery handled? The proposal preserves it; LDAP alone does not prove a second factor.
- What is the required disablement/revocation window for active web sessions, remembered sessions and Sanctum tokens? Who initiates/reactivates local account lifecycle changes?
- Which local-password accounts remain, is an explicit emergency administrator required, and what is the authorized rollback procedure?
- Are any LDAP groups intended to affect eligibility or roles? Exact groups/mappings/removal rules are unresolved; no automatic mapping is proposed initially.
- How do actual mobile/internal clients consume login/TOTP/VIISP results, and what compatibility constraints exist? Which compiled gateway release and deployment topology form the regression baseline?
- What isolated directory/database and non-production accounts are available for acceptance testing?

The repository is sufficient to recommend the Laravel boundary and identify touchpoints. It is **not sufficient to begin a safe production-capable LDAP implementation** until the essential directory, identity, alternative-login and lifecycle decisions are confirmed. Characterization and implementation planning can proceed without inventing those answers.
