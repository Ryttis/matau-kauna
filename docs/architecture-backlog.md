# Architecture backlog

## Severity definitions

Severity measures **architectural impact**, not implementation effort, cost or delivery order. Assess the affected trust/data boundary, breadth of impact, effect on core workflows and recoverability using the recorded evidence. Status expresses confidence/progress separately. A production incident is not required for a Critical rating, but an unknown deployment or a hypothetical worst case alone does not justify one.

| Severity | Architectural impact |
| --- | --- |
| Critical | Evidence establishes a credible path to system-wide compromise, major irreversible data loss, or loss of an essential service without viable containment/recovery. The consequence warrants immediate containment or blocking the affected deployment. |
| High | A core authentication, authorization, data-integrity or execution boundary has a material weakness, or a planned change materially affects that boundary. Failure can compromise an important user population or core workflow; the evidence does not establish Critical impact. |
| Medium | A bounded cross-component contract, consistency, lifecycle, testability or operational gap creates meaningful failure/recovery risk, without an established failure of the core boundaries covered by High. |
| Low | A preventive maintainability or release-management weakness adds avoidable drift or future change risk, without a separately demonstrated material core-workflow failure in this item's scope. |
| Nice-to-have | An optional improvement with no evidenced architectural defect or meaningful reliability, integrity or access-control consequence if deferred. |

## Review metadata

- Last reviewed: 2026-09-16
- Total open items: 17
- Resolved items: 0

Open items have status Identified, Confirmed, Planned, In progress or Blocked. Accepted items remain recorded but are counted as neither open nor resolved. Update these counts when statuses change.

## Summary

| ID | Severity | Status | Area | Summary |
| -- | -------- | ------ | ---- | ------- |
| [ARCH-001](#arch-001--define-consistent-assurance-across-session-creation-paths) | High | Confirmed | Authentication boundaries | Staff access can originate from paths with different credential/TOTP requirements. |
| [ARCH-002](#arch-002--make-the-executable-viisp-handler-reproducible) | High | Confirmed | VIISP runtime | Loaded JavaScript artifacts differ from the TypeScript source. |
| [ARCH-003](#arch-003--separate-attachment-delivery-from-public-and-executable-files) | High | Confirmed | Public/storage boundary | Direct public files bypass report/review authorization and share the PHP document root. |
| [ARCH-004](#arch-004--make-authorization-context-and-data-scope-explicit) | High | Confirmed | Authorization | Active-role, permission and named-role scope rules differ by entry point. |
| [ARCH-005](#arch-005--define-account-disablement-and-activity-lifecycle) | High | Confirmed | User lifecycle | Staff disablement and consistent activity tracking lack one authoritative policy. |
| [ARCH-006](#arch-006--keep-totp-enrollment-secrets-within-the-authentication-boundary) | High | Confirmed | Authentication integration | TOTP setup sends the secret-bearing URI to an external QR renderer. |
| [ARCH-007](#arch-007--establish-a-deployable-queue-and-scheduler-contract) | High | Confirmed | Background execution | Scheduled jobs have no declared worker/scheduler and timeout defaults conflict. |
| [ARCH-008](#arch-008--define-recoverable-external-write-and-notification-semantics) | High | Confirmed | Integration consistency | Local transfer records do not settle concurrent or uncertain remote outcomes. |
| [ARCH-009](#arch-009--define-the-viisp-gateway-trust-and-handoff-state-contract) | High | Confirmed | Laravel / Node boundary | Caller-supplied handoff configuration and in-memory state carry implicit trust/availability assumptions. |
| [ARCH-010](#arch-010--introduce-ldap-authentication-for-internal-users) | High | In progress | Internal authentication | Laravel LDAP credential boundary implemented behind `LDAP_ENABLED`; policy decisions in ADR-0001 still open. |
| [ARCH-011](#arch-011--replace-email-based-useremployee-identity-linkage) | Medium | Confirmed | Persistence / identity | User and assignment identities are joined and synchronized by mutable email. |
| [ARCH-012](#arch-012--coordinate-record-file-and-external-copy-retention) | Medium | Confirmed | Data lifecycle | Database retention does not coordinate attachment or external-copy cleanup. |
| [ARCH-013](#arch-013--establish-isolated-architecture-regression-tests) | Medium | Confirmed | Testability | Useful local tests do not cover deployed cross-process/queue boundaries safely. |
| [ARCH-014](#arch-014--document-and-validate-runtime-configuration-ownership) | Medium | Identified | Runtime / configuration | Supplied topology and configuration defaults do not form a complete deployment contract. |
| [ARCH-015](#arch-015--reconcile-the-mobile-viisp-contract-and-shared-identity-mapping) | Medium | Confirmed | API / identity contract | Direct callback token delivery coexists with an exchange route without a code producer. |
| [ARCH-016](#arch-016--add-cross-component-operational-signals) | Medium | Identified | Observability | Existing audit records do not establish end-to-end auth/worker/integration health. |
| [ARCH-017](#arch-017--establish-runtime-and-dependency-release-ownership) | Low | Confirmed | Dependency lifecycle | Floating build inputs and split repositories lack a documented tested release set. |

Baseline: 2026-09-16; see [architecture.md](architecture.md) for scope, inspected revisions and sibling working-tree caveats. Repository paths below are relative to the MatauKauna root; paths starting `../` refer to the Compose-referenced sibling repositories, inspected read-only. No listed item was implemented by this review.

IDs are permanent: never renumber or reuse one, including after resolution. Next unused ID: **ARCH-018**. Reorder entries as priorities change without changing their IDs. Sort primarily by severity, then architectural impact. This is not necessarily execution order: LDAP is the first planned feature, with prerequisite decisions/tests identified below.

Allowed severities: Critical, High, Medium, Low, Nice-to-have. Allowed statuses: Identified, Confirmed, Planned, In progress, Blocked, Resolved, Accepted. `Confirmed` means the structural behavior is evidenced in inspected code, not that a production incident or exploit has been demonstrated. `Identified` marks an operational gap whose deployed mitigations are **Unknown / requires confirmation**. No Critical item is asserted on the available evidence.

### Severity review rationale

All 17 existing items were reassessed against the definitions above; ratings remain unchanged: Critical 0, High 10, Medium 6, Low 1, Nice-to-have 0. In particular:

- **ARCH-001 remains High:** inconsistent staff assurance is evidenced, but the alternative paths still require VIISP identity, a signed verification link or an invitation. The evidence does not establish arbitrary system-wide access; the approved equivalence of these methods remains unresolved.
- **ARCH-003 remains High:** known attachment URLs bypass application visibility, and uploads share the executable document root. Upload validation exists; a path to arbitrary code execution or catastrophic disclosure has not been established. This does not reduce the significance of restricted-file exposure.
- **ARCH-004 remains High:** custom-role scope and active-role inconsistencies affect an authoritative access boundary. Intended custom-role scope and deployed assignments are unresolved; the evidence does not establish universal privilege escalation or system-wide compromise.
- **ARCH-002, ARCH-005 through ARCH-009 remain High:** their evidence concerns the executable identity adapter, account eligibility/deletion, second-factor secret custody, essential background execution, external-write integrity and VIISP handoff trust respectively. These are core boundaries, without established Critical impact.
- **ARCH-010 remains High and Planned:** LDAP changes internal credential authority and access lifecycle across browser/API entry points. Its architectural reach warrants High; this is not a claim that lacking LDAP is itself a vulnerability or that implementation is urgent or difficult.
- **ARCH-011 through ARCH-016 remain Medium:** the evidence identifies bounded identity-linkage, retention coordination, regression-test, configuration, mobile-contract and observability gaps. Their core-boundary consequences are tracked separately in the referenced High items; these ratings do not assume a production incident.
- **ARCH-017 remains Low:** its remaining scope is preventive release/dependency ownership. The concrete executable VIISP mismatch is already High in ARCH-002; no additional vulnerability or support-status failure was established here.

## ARCH-001 — Define consistent assurance across session-creation paths

Severity: High

Status: Confirmed

Area: Authentication boundaries

### Problem

Staff authentication is not bounded by the staff login form. Several paths can establish access to the same user with different password, TOTP and eligibility checks. Adding LDAP only to ordinary login would not define which other methods remain valid for enrolled staff.

### Evidence

- `app/app/Http/Requests/Auth/LoginRequest.php::authenticate` and `AuthApiController::login` check local credentials and login-type eligibility; their completion paths call `TwoFactorService`.
- `app/app/Http/Controllers/Auth/ViispAuthController.php::callback` and `Api/ViispApiController.php::callback` match identity hash, then email, without a reporter-only restriction. `linkViispToExistingUser` adds a reporter role without removing staff roles. Their session/token finalizers do not challenge TOTP.
- `app/tests/Feature/Auth/AuthenticationTest.php::test_viisp_login_restores_saved_active_role` explicitly logs in a staff user through VIISP.
- `Auth/InvitationRegistrationController::store` and `Auth/VerifyEmailController::__invoke` directly call `Auth::login`; signed email verification checks signature/hash through route/controller but does not perform the normal TOTP challenge. `routes/auth.php` exposes the verification route without requiring an existing session.

### Consequence

The effective assurance for staff access depends on the entry path. A directory migration or mandatory TOTP policy can be undermined by leaving alternative session-creation paths with different rules. Whether current VIISP access is an approved equivalent assurance is **Unknown / requires confirmation**.

### Recommended Direction

Define the allowed authentication methods for each account population and a shared completion/eligibility policy. Preserve public VIISP while explicitly deciding mixed staff/citizen behavior, invitation bootstrap and signed-email login. Record the authentication method/assurance if necessary to enforce the agreed boundary; do not assume a UI tab establishes it.

### Dependencies

[ADR-0001](adr/0001-ldap-authentication.md), ARCH-004, ARCH-005. LDAP rollout depends on this policy decision, not on a wholesale authentication rewrite.

### Next Action

Review a matrix of account populations versus password, LDAP, VIISP, invitation and email-verification entry points with the application owner; specify required TOTP and permitted access for each cell.

## ARCH-002 — Make the executable VIISP handler reproducible

Severity: High

Status: Confirmed

Area: VIISP runtime

### Problem

The handler's readable TypeScript source is not the behavior selected by its package entry point. Build and mount arrangements do not establish a source-to-executable release relationship.

### Evidence

- `../kauno_problemos_viisp_handler/package.json` declares `main: ./dist/index.js` and a separate `build` script.
- `src/client.ts` configures `ClientSSLSecurity`, resolves an optional SOAP endpoint and reads the certificate from `cert.cert`. `dist/client.js` instead sets the endpoint to `wsdlUrl`, reads `cert.key` into both buffers and contains no corresponding TLS-client-security setup.
- `src/utils.ts` supports company identities and multiple contact formats; `dist/utils.js` requires a personal code and older value shapes.
- Root `docker-compose.yml` mounts the sibling handler into gateway `node_modules`; the inspected local dependency is also a symlink to that sibling. Gateway `Dockerfile` installs/copies the package without explicitly running its TypeScript build.

### Consequence

Reviewing or testing source alone can give a false baseline for identity mapping and transport behavior. Deployment may load a different contract than developers expect. The actual production artifact is **Unknown / requires confirmation**.

### Recommended Direction

Establish a versioned build artifact tied to a source revision and lockfile, and test the package's actual entry point. Keep local development mounts clearly distinct from the deployed artifact contract. Do not rebuild or substitute the handler during this review.

### Dependencies

ARCH-013, ARCH-014, ARCH-017. Relevant to VIISP regression evidence for ADR-0001.

### Next Action

Identify the production handler package/image and compare its loaded `dist` files to a recorded source revision; capture the exact reproducible regression artifact.

## ARCH-003 — Separate attachment delivery from public and executable files

Severity: High

Status: Confirmed

Area: Public/storage boundary

### Problem

Business visibility is checked when reading report/review data, but attachment bytes are served directly from the public document root. Writable user-content storage also shares Nginx's generic PHP execution namespace.

### Evidence

- `app/app/Services/ReporterFileUploadService.php::storeOne` writes under `public/files/problems`; `ReviewEntryFileUploadService::storeOne` writes review attachments under the public tree.
- `app/app/Models/ProblemAttachment.php::publicUrl` returns a direct path; `GuestProblemController::publicShow` separately checks publication/ownership/archive state.
- `docker/nginx/conf.d/nginx.conf` serves existing files using `try_files` and forwards any matching `.php` location to FPM; it has no dedicated non-executable upload location.
- Upload services do perform MIME, extension, size and other checks. This finding does not assert that a PHP upload currently passes them; the problem is the shared delivery/execution boundary and lack of per-file access enforcement.

### Consequence

A known file URL can remain readable independently of report visibility or internal review permissions. Unpublishing a report does not revoke direct byte access. Upload-validation changes could also affect the executable namespace.

### Recommended Direction

Define public versus restricted attachment ownership and delivery rules. Place restricted files behind authorized delivery and keep all user content outside executable application paths. Preserve intentional public-media behavior through an explicit policy.

### Dependencies

ARCH-004 for visibility rules, ARCH-012 for deletion/retention, ARCH-014 for deployed storage and ingress.

### Next Action

Inventory report/review/GIS attachment classes and agree which remain public, which require authorization, and what must happen to existing URLs when visibility changes.

## ARCH-004 — Make authorization context and data scope explicit

Severity: High

Status: Confirmed

Area: Authorization

### Problem

Authorization combines active-role permissions, all-assigned-role checks, permission bypasses and named-role data restrictions. Custom roles can have operation permissions without an explicit institution/assignment scope. Entry-point middleware determines which interpretation applies.

### Evidence

- `app/app/Models/User.php::permissions` unions all assigned permissions until `bindActiveRoleContext` is called; `hasRole` also changes semantics after binding, while `hasAssignedRole` does not.
- `HandleInertiaRequests::share` binds web context; `routes/api.php` applies `ApplyActiveRoleContext` only to the internal group, with selected other API methods binding explicitly.
- `EnsurePermission` bypasses a requested permission for `admin.access`; `InternalProblemApiController::index` directly requires `issues.view`.
- `UserRole::isStaffRole` accepts every non-reporter role, including custom RBAC roles. `ProblemScopeService::scopeProblemsForUser` restricts the named institution-admin and employee roles; other roles fall through without those restrictions.
- `StoreProblemReviewHistoryRequest::authorize`, controller checks and `ProblemHttpSupport` add further capability/ownership checks. No application Gate registrations or policy classes were found; their absence alone is not the issue.

### Consequence

Role changes or new LDAP-linked staff can receive surprising data scope even when operation permissions appear correct. Web/API parity is difficult to reason about. Intended scope for custom roles is **Unknown / requires confirmation**; no unauthorized production access is asserted.

### Recommended Direction

Specify operation permission and data scope independently; resolve active context explicitly before enforcement. Consolidate repeated decisions behind focused policies/services incrementally, preserving approved behavior. Do not derive data scope from an LDAP group without an application decision.

### Dependencies

ADR-0001 for local authorization ownership; ARCH-011 for employee linkage; ARCH-013 for regression tests.

### Next Action

Create an access matrix for built-in/custom roles and representative web/API list, detail and mutation routes, including missing institution and role-switch cases.

## ARCH-005 — Define account disablement and activity lifecycle

Severity: High

Status: Confirmed

Area: User lifecycle

### Problem

Archiving, blocking and inactivity deletion use different definitions of account state and population. Staff lack an equivalent supported blocking workflow, and login activity updates depend on the authentication path.

### Evidence

- `app/app/Services/UserBlockingService.php::assertCanBlock` restricts blocking to reporter-only users; `revokeAccess` deletes Sanctum tokens and database sessions.
- `AuthenticatedSessionController`, `TwoFactorChallengeController`, `ViispAuthController::finalizeViispLogin` and `VerifyEmailController` can clear `archived_at`. Login lookup does not generally exclude archived users.
- `AuthApiController::login` without 2FA returns a token without updating `last_login_at`/`LoginLog`, while `verifyTwoFactorLogin` does update them. `ViispApiController::authTokenResponse` also omits those updates.
- `InactiveReporterDeletionService` selects users by absence of `issues.view`, not `isReporterOnly`; deletion relies on `last_login_at`. This can include custom staff roles without that permission. `ExtendTokenExpiration` renews token expiry without updating that activity field.

### Consequence

Archiving cannot be relied on to disable staff, mobile activity can be missed, and a permission change can affect deletion eligibility. Directory disablement would not automatically revoke existing application access. The intended inactivity definition and production use of these settings are **Unknown / requires confirmation**.

### Recommended Direction

Define distinct account eligibility, archival and retention states, with an authoritative lifecycle owner. Normalize authentication/activity recording and revocation across sessions, remembered access and API tokens. Define explicit staff versus reporter lifecycle selection before LDAP enrollment.

### Dependencies

ADR-0001, ARCH-001, ARCH-004, ARCH-012.

### Next Action

Agree staff disablement/reactivation and the inactivity clock; trace each existing login, renewal and deletion path against those definitions.

## ARCH-006 — Keep TOTP enrollment secrets within the authentication boundary

Severity: High

Status: Confirmed

Area: Authentication integration

### Problem

An external presentation service receives the material needed to generate staff TOTP codes during enrollment.

### Evidence

- `app/app/Services/TwoFactorService.php::otpauthUrl` embeds the account's TOTP secret in its enrollment URI.
- `qrImageUrl` URL-encodes that full URI into the `data` query parameter for `https://api.qrserver.com/v1/create-qr-code/`.
- `Auth/TwoFactorChallengeController::create` supplies that image URL to the browser; `Api/AuthApiController::login` returns it for initial API setup. The web `resources/js/Pages/Auth/TwoFactorChallenge.vue` renders the supplied image URL.

### Consequence

TOTP secret custody and enrollment availability include a third party beyond Laravel and the user's authenticator. That dependency remains after LDAP replaces the password check.

### Recommended Direction

Generate enrollment QR images within the application/browser trust boundary, with no secret-bearing third-party request. Assess the already-enrolled population and any required secret re-enrollment separately with the system owner.

### Dependencies

ADR-0001's preservation of application TOTP; ARCH-001 for assurance policy.

### Next Action

Confirm deployed enrollment behavior and agree an internal QR-rendering boundary and treatment of previously enrolled secrets.

## ARCH-007 — Establish a deployable queue and scheduler contract

Severity: High

Status: Confirmed

Area: Background execution

### Problem

Business lifecycle work depends on scheduled commands and workers that are not declared in the supplied runtime. Job timing and retry defaults are inconsistent.

### Evidence

- `app/routes/console.php` schedules deadline, archive, privacy, inactivity, audit, GIS and DVS commands with `withoutOverlapping`.
- `ProcessArchiveCommand` dispatches a chain including `RunArchiveDeletionJob`; other commands/services dispatch reminder, deletion and privacy jobs.
- `app/config/queue.php` defaults to the database queue with `retry_after=90` seconds; `RunArchiveDeletionJob` declares `timeout=180` seconds and three tries.
- Root Compose defines no queue worker or scheduler. `app/composer.json` starts `queue:listen --tries=1` only in its development script and does not start a scheduler. Tests use `QUEUE_CONNECTION=sync`.

### Consequence

The declared stack alone does not execute these recurring workflows. Under default timing and multiple workers, a long job can become reservable before its declared timeout. Scheduled-command overlap protection does not imply uniqueness of jobs after dispatch. Actual external supervision and overrides are **Unknown / requires confirmation**.

### Recommended Direction

Declare queue backend, workers, scheduler ownership, restart policy, timeouts/retry visibility and failed-job handling as one operational contract. Preserve job idempotency independently of scheduler locks. Validate with asynchronous execution, not only the sync test driver.

### Dependencies

ARCH-012, ARCH-013, ARCH-014, ARCH-016.

### Next Action

Obtain the deployed scheduler/worker definitions and effective timeout/retry settings; reconcile them with the longest existing job before adding more background work.

## ARCH-008 — Define recoverable external-write and notification semantics

Severity: High

Status: Confirmed

Area: Integration consistency

### Problem

External creation, local persistence and notification delivery are separate effects without a complete uncertain-outcome or concurrent-send contract. Existing transfer tracking helps retries but does not make those effects atomic.

### Evidence

- `app/app/Services/DvsProblemTransferService.php::transferProblem` uses `firstOrCreate`, sends when `external_object_id` is empty, then saves the returned ID. `ArcGisProblemTransferService::transferProblem` follows the same pattern and separately tracks attachments.
- Transfer migrations make `problem_id` unique. This prevents duplicate local tracking rows, not two callers sending against one row with an empty external ID. The inspected send methods do not claim a send lock or use an application idempotency key.
- `ProblemInstitutionAssignmentController::sendToDvs/sendToGis` performs transfers within HTTP requests; remote calls use substantial timeouts.
- `InternalProblemApiController::assignSendEmail` changes assignment, sends mail and then changes status in successive steps. `TransactionalMail::send` is synchronous; `PushNotification` has no `ShouldQueue` contract.

### Consequence

A remote success followed by a timeout/local-save failure can lead to a duplicate on retry; concurrent requests can also duplicate remote effects. Notification failure can leave a partially completed application operation. External support for deduplication/reconciliation is **Unknown / requires confirmation**.

### Recommended Direction

Define pending/in-flight/succeeded/uncertain outcomes, single-writer ownership and reconciliation around the existing transfer records. Confirm external idempotency facilities before relying on them. Consider durable delivery intent for notifications and remote writes where justified; do not require a new broker or rewrite.

### Dependencies

ARCH-007 for deferred execution if selected, ARCH-013 for failure/concurrency tests, ARCH-016 for outcome monitoring.

### Next Action

For one DVS create operation, document the remote-success/local-timeout case and confirm how an already-created object can be identified before retrying.

## ARCH-009 — Define the VIISP gateway trust and handoff state contract

Severity: High

Status: Confirmed

Area: Laravel / Node boundary

### Problem

The gateway's handoff trust and availability rely on implicit deployment assumptions. Initiating callers choose callback/postback destinations and the secret later used to authorize identity exchange; pending state resides only in one process.

### Evidence

- `../kauno_problemos_gateway/midleware/viisp_middleware.js::checkLogin` checks required values and `VIISP_PID`, but does not authenticate the initiating application or validate callback ownership.
- `controllers/viisp_controller.js::signInUsingViisp` accepts/stores `postbackUrl`, `callbackUrl` and `exchangeSecret`. `exchangeIdentity` compares `X-Viisp-Exchange` against that caller-supplied secret and consumes a 120-second exchange token.
- `utils/viisp_handler.js` exports `Object.create(null)` as session storage. Pending ticket/callback entries have no explicit expiry; exchange entries are lazily cleaned. Restart loses all state; independent replicas do not share it.
- Root Compose publishes gateway port 3000. Laravel's web callback accepts a gateway token without a browser-session-bound initiation state; the mobile path additionally consumes Laravel cache state.

### Consequence

The secret comparison protects redemption within the created exchange but does not by itself establish a trusted Laravel caller or callback destination. Restarts/routing across replicas can interrupt login. Actual ingress restrictions, VIISP callback constraints and production replica count are **Unknown / requires confirmation**; public exploitability is not asserted.

### Recommended Direction

Specify trusted callers/destinations, browser correlation, transport protection, token lifetime and replay behavior. Declare a supported single-process limitation or choose durable bounded shared handoff state with atomic consumption when availability requires it. Keep this boundary VIISP-specific; do not expand it for LDAP.

### Dependencies

ARCH-002, ARCH-013, ARCH-014; ADR-0001 rejects adding LDAP responsibilities here.

### Next Action

Review the deployed ingress and callback allowlist/VIISP registration with the gateway owner, then document and test one complete handoff contract, including restart and replay.

## ARCH-010 — Introduce LDAP authentication for internal users

Severity: High

Status: In progress (initial implementation 2026-10-12; rollout decisions pending)

Area: Internal authentication

### Problem

The requested directory-backed internal authentication does not exist. Staff credentials are verified locally in two entry paths, and application users have no explicit LDAP identity binding or provider policy.

### Evidence

- `app/config/auth.php` defines the session guard with an Eloquent `User` provider.
- `LoginRequest::authenticate` uses `Auth::attempt`; `AuthApiController::login` uses `Hash::check`.
- `app/app/Models/User.php`, user migrations, `RbacService` and `InvitationService` model local/VIISP identities and local passwords, with no LDAP subject mapping.
- `app/composer.json` declares no LDAP client; root `Dockerfile` does not install an LDAP extension. This records the current baseline, not a choice of future library.

Progress (2026-10-12), detailed in the ADR's [Implementation Status](adr/0001-ldap-authentication.md#implementation-status-2026-10-12):

- `app/config/ldap.php`, `app/app/Services/Ldap/*` (ext-ldap wrapper, authenticator, identity, resolver) and `app/app/Services/Auth/StaffCredentialAuthenticator.php` provide one credential boundary used by both `LoginRequest::authenticate` and `AuthApiController::login`.
- `users.ldap_source` / `users.ldap_username` (unique pair) hold the explicit binding; `php artisan ldap:link-user` provisions it, `php artisan ldap:check` diagnoses connectivity without credentials.
- Root `Dockerfile` builds `ext-ldap`; `composer.json` requires it. Tests default to `LDAP_ENABLED=false` and use an in-memory directory double.
- `ldap:check` against the Kaunas directory succeeds only with `LDAP_TLS_VERIFY=false`; certificate trust for verified TLS is not in place.

### Consequence

Directory authentication and lifecycle cannot govern eligible internal access until identity mapping, alternative-login rules and operational connectivity are defined.

### Recommended Direction

Follow [ADR-0001](adr/0001-ldap-authentication.md): a focused LDAP verifier inside Laravel, local application users and authorization, existing TOTP/session/Sanctum ownership, no automatic outage fallback or group-role assignment. The ADR contains the design tradeoffs and rollout/testing strategy; this item tracks delivery.

### Dependencies

ADR-0001 acceptance and its directory/identity/lifecycle answers; ARCH-001, ARCH-004, ARCH-005 and ARCH-011 for the affected policy/mapping decisions; ARCH-002 and ARCH-013 for reliable VIISP/auth regression evidence. These do not mandate resolving all unrelated backlog items first.

### Next Action

Decide the remaining ADR questions before enabling `LDAP_ENABLED` outside a pilot: durable subject attribute (rename semantics), permitted alternative session paths for enrolled accounts (ARCH-001), password-management restrictions, revocation on directory disablement (ARCH-005), TLS trust material, and the pilot account list. Then run the controlled stage login with an approved directory account and update this item and the ADR status.

## ARCH-011 — Replace email-based User–Employee identity linkage

Severity: Medium

Status: Confirmed

Area: Persistence / identity

### Problem

Authentication users and assignment/team employees are separate records joined by mutable email rather than a durable identity relationship. Synchronization ownership and transaction boundaries are inconsistent.

### Evidence

- `app/app/Models/Employee.php` has email/institution/name fields but no `User` relationship.
- `ProblemScopeService::employeeIdForUser` looks up `Employee` by user email and institution, or requires a single matching employee when institution is absent.
- `RbacService::ensureEmployeeForInstitution` uses `updateOrCreate(['email' => $user->email])`; `updateUser` changes user email before synchronization and does not reconcile the old email record. With no institution it deletes employees matching the current email.
- `InvitationService::registerFromInvitation` independently creates/synchronizes user, roles, employee and invitation use. These service methods have no encompassing transaction around those writes. `ProblemInstitutionAssignmentPickerService` can also create employee records.

### Consequence

Email or institution changes can strand/retarget assignment identities and affect authorization scope. LDAP profile synchronization could amplify this coupling or create duplicate users if email is treated as a stable subject.

### Recommended Direction

Define the User–Employee cardinality and stable linkage explicitly, preserving assignment history. Give one application boundary responsibility for coordinated provisioning/profile updates and recovery from partial writes. LDAP mapping must not use email as its durable identity key.

### Dependencies

ADR-0001, ARCH-004, ARCH-005.

### Next Action

Inspect a read-only account/employee reconciliation report for duplicate emails, missing links and multi-institution cases before designing the eventual mapping migration.

## ARCH-012 — Coordinate record, file and external-copy retention

Severity: Medium

Status: Confirmed

Area: Data lifecycle

### Problem

Archival, unpublication, account deletion, database deletion and physical file removal are separate paths. The code does not express a complete lifecycle for retained bytes or remote copies.

### Evidence

- `DataRetentionService::deleteArchivedProblems` performs bulk query deletion. Attachment migrations cascade database rows; no corresponding public-directory cleanup is called there or registered as an application deletion observer.
- Individual attachment deletion in `ProblemController`, `ProblemReviewController` and `InternalProblemApiController` explicitly deletes files, demonstrating a different lifecycle path.
- `ResolvedProblemPrivacyService` changes `is_published_publicly`, while file URLs continue to address public storage.
- `ReporterProblemController::reporterReportStore` creates a report, writes files/attachment rows and applies rules in separate steps. Upload services perform some per-file cleanup, but no encompassing report/filesystem compensation is present.
- GIS/DVS transfer services retain references to copies sent to external systems; no external deletion workflow was found in the inspected retention path.

### Consequence

Retention can remove database visibility without removing bytes, and partial submission failures can leave incomplete local state. External copy retention cannot be inferred from local deletion. Required periods, external obligations and backup retention are **Unknown / requires confirmation**.

### Recommended Direction

Define lifecycle ownership for metadata, files, audit records, backups and remote copies. Coordinate deletion/compensation with retryable, observable operations; distinguish archive/unpublish from erasure. Do not invent retention periods.

### Dependencies

ARCH-003, ARCH-005, ARCH-007, ARCH-008.

### Next Action

Trace one report and its attachments through submission, publication, archive and deletion, then agree which bytes/references should exist at each stage with the data owner.

## ARCH-013 — Establish isolated architecture regression tests

Severity: Medium

Status: Confirmed

Area: Testability

### Problem

Existing tests are useful but do not establish a safe, reproducible cross-component baseline for authentication changes, actual queue semantics or the gateway executable artifact.

### Evidence

- `app/tests/Feature/Auth/AuthenticationTest.php` covers password login, role restoration and mocked VIISP login; `MobileSessionRenewalTest` covers token lifetimes and mocked VIISP. Blocking, TOTP, GIS, merge/status and notification tests also exist.
- `app/phpunit.xml` targets PostgreSQL at the Compose service name and a separate test database; `RefreshDatabase` is widely used. Tests substitute array sessions/cache and synchronous queues.
- `app/tests/Feature/FirebasePushNotificationRealTest.php` sends to a fixed device token whenever credentials are available; it is included by the general Feature directory suite, not explicitly opt-in by test-suite configuration.
- The gateway's `package.json` test script is a failing placeholder. Handler Jest tests exercise TypeScript/client stubs; that alone does not establish the behavior of the differing `dist` package entry point.
- No CI workflow or dedicated test-database provisioning was found in the tracked deployment files inspected.

### Consequence

An indiscriminate test run can mutate a configured database or send external notifications. Passing isolated Laravel tests would not demonstrate real gateway/queue behavior or LDAP/VIISP parity. This review did not run the application suites and makes no pass/fail claim.

### Recommended Direction

Define an isolated, repeatable PostgreSQL test environment, deny unintended network effects, and make live integration tests explicitly opt-in. Retain current tests and add focused cross-boundary and compiled-artifact coverage, including the ADR's auth/authorization matrix.

### Dependencies

ARCH-002, ARCH-007, ARCH-014; supports ADR-0001.

### Next Action

Define a safe test command/environment that guarantees an isolated database and excludes live Firebase/network effects, then record the existing auth regression results before implementation.

## ARCH-014 — Document and validate runtime configuration ownership

Severity: Medium

Status: Identified

Area: Runtime / configuration

### Problem

The supplied development-oriented topology, configuration defaults and minimal setup guidance do not specify a reproducible operational deployment or ownership of configuration across Laravel and Node.

### Evidence

- `docker-compose.yml` requires an external `proxy` network and two sibling source trees, binds application code, publishes gateway/database/debug ports and declares startup dependencies without readiness checks.
- `docker/nginx/conf.d/nginx.conf` has HTTP-only ingress and no gateway proxy route. TLS/callback routing may exist elsewhere but is not represented here.
- `app/config/services.php` defaults the gateway to `127.0.0.1:3000`; `config/database.php` defaults to SQLite although Compose supplies PostgreSQL. `app/.env.example` omits DB, VIISP, queue and session settings.
- Compose maps Vite port 5174 while `app/vite.config.js` binds/advertises 5173.
- Root README is a GitLab starter template; `app/README.md` is empty. Institution GIS/DVS credentials instead live in encrypted DB fields and rely on `APP_KEY`, adding a separate configuration/recovery dependency.

### Consequence

Operators must supply unstated knowledge to establish connectivity, callbacks, persistence and recovery. Development defaults can be mistaken for production configuration. Actual ingress, effective settings, backup/restore practices and deployment owners are **Unknown / requires confirmation**.

### Recommended Direction

Document environment-specific responsibilities, required configuration keys, secret/trust sources, build outputs, data/key recovery and health/readiness expectations. Validate required settings on deployment; distinguish browser-reachable callbacks from private service URLs. Preserve current local functionality while making production assumptions explicit.

### Dependencies

ARCH-002, ARCH-007, ARCH-009, ARCH-017; directory connectivity/trust requirements in ADR-0001.

### Next Action

Collect the deployed process/ingress/configuration inventory without secret values and compare it to the supplied Compose topology, including worker/scheduler and database/key/file recovery ownership.

## ARCH-015 — Reconcile the mobile VIISP contract and shared identity mapping

Severity: Medium

Status: Confirmed

Area: API / identity contract

### Problem

Mobile authentication exposes remnants of a second handoff design, while browser/mobile identity-linking code has already diverged. Client compatibility cannot be derived from route names alone.

### Evidence

- `Api/ViispApiController::init` stores `redirect_uri` and points Node at `/viisp/mobile-callback?state=...`.
- Its callback returns a bearer token or registration token as JSON; it does not issue a success deep-link redirect. `exchange` reads `viisp_mobile_exchange:` cache entries, but no producer of those entries was found under `app/app` or routes.
- `app/docs/openapi.yaml` documents both direct callback token delivery and `/api/viisp/exchange` code redemption. The latter cannot be completed from the inspected producer flow.
- Browser/API VIISP controllers each implement `resolveEmail`, `linkViispToExistingUser` and `syncViispProfile`. Browser synthetic-to-real email updates clear verification and resend email; the API counterpart updates email without that reset.
- Mobile client source and its WebView/deep-link handling are not present in this repository.

### Consequence

Clients can implement a documented exchange path that the application does not populate. Duplicated mapping can alter account verification depending on entry point. Actual clients relying on either design are **Unknown / requires confirmation**.

### Recommended Direction

Choose and document the supported handoff after checking consumers. Deprecate obsolete behavior deliberately, and consolidate identity mapping behind a shared application boundary while preserving different session/token transports. Keep LDAP credential verification independent of this VIISP protocol work.

### Dependencies

ARCH-001, ARCH-009, ARCH-013; VIISP regression requirements in ADR-0001.

### Next Action

Capture the current mobile client's successful login/registration request sequence and compare it with callback and exchange implementations before selecting any contract change.

## ARCH-016 — Add cross-component operational signals

Severity: Medium

Status: Identified

Area: Observability

### Problem

Audit and transfer logging exist, but the inspected system does not define end-to-end correlation, dependency availability or worker/scheduler health signals. Authentication paths record different levels of evidence.

### Evidence

- `AuditLogService` persists application audit events and falls back to Laravel error logging if writes fail. `IntegrationAuditService` sanitizes/caps metadata for GIS/DVS, and transfer rows retain statuses/errors.
- Browser `ViispAuthController` audits login-init and exchange; `Api/ViispApiController` has no equivalent integration-audit dependency. Gateway controllers primarily emit `console.error(formatError(...))` on failure.
- `AuthApiController::login` and `verifyTwoFactorLogin` differ in login audit/activity recording (ARCH-005).
- `app/bootstrap/app.php` registers `/up`; Compose declares no component healthchecks, metrics service or monitoring setup. `config/logging.php` defaults to a local single-file channel through the stack.

### Consequence

A stalled worker, failing gateway, directory outage or missing audit writes may require manual log correlation. Existing logs are not evidence that alerting is absent in production; deployed monitoring is **Unknown / requires confirmation**.

### Recommended Direction

Build on existing audit/transfer data with consistent sanitized outcomes and correlation identifiers across Laravel, gateway and jobs. Define actionable signals for queue delay/failure, scheduler execution and integration/auth availability. Separate operational health from business audit retention.

### Dependencies

ARCH-005, ARCH-007, ARCH-009, ARCH-014; LDAP failure semantics in ADR-0001.

### Next Action

Walk through one failed VIISP login and one missed scheduled run with operations, identify available signals, and specify the minimum missing correlation/alert contract.

## ARCH-017 — Establish runtime and dependency release ownership

Severity: Low

Status: Confirmed

Area: Dependency lifecycle

### Problem

The stack spans PHP, frontend and separately versioned Node/TypeScript repositories without a documented tested release set. Some image/tool inputs are floating and local gateway installation does not consistently use locked immutable inputs.

### Evidence

- Root `Dockerfile` installs Node through `setup_lts.x`, `npm@latest`, unversioned PECL packages and the Composer installer. Compose uses `nginx:latest` and source bind mounts.
- Laravel declares PHP `^8.2`, Laravel `^11.31`, Sanctum `^4.0`; the image uses PHP 8.3. Frontend uses Vue 3/Inertia 2/Vite 6. Composer/npm lockfiles are present and should be preserved.
- Sibling gateway `Dockerfile` uses `node:23.6.1-alpine` and `npm install --legacy-peer-deps`; the handler is a local file dependency. Handler `package.json` pins older SOAP/XML libraries and mixes independently versioned build/test tooling.
- The handler executable/source discrepancy is tracked separately in ARCH-002. No current vulnerability or vendor-support status was established by this review.

### Consequence

Rebuilding at another time or from another sibling checkout may change tools or protocol behavior. Upgrading one repository may invalidate assumptions in another without a recorded compatibility baseline.

### Recommended Direction

Assign ownership of the combined release set, pin reproducible build inputs and validate upgrades against actual loaded artifacts and integration tests. Assess vendor support/security status against authoritative current information during that work; do not infer it from version age alone. Prefer staged upgrades over a rewrite.

### Dependencies

ARCH-002, ARCH-013, ARCH-014.

### Next Action

Record the deployed PHP/Node/frontend/handler versions and lockfile/image identifiers, then assign an owner for verifying the combined build and upgrade cadence.
