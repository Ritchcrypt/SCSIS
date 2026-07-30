# Phase 5 — RBAC and IDOR Hardening

## Objective

Enforce authorization at the route, controller, policy, and ownership-query levels
so that changing a URL or record ID cannot expose another role's or user's data.

## Completed controls

- Registered role middleware and active-user middleware.
- Applied role-aware route groups.
- Added policies for sensitive application models.
- Added administrator-only and role-specific gates.
- Required active accounts for protected access.
- Added controller-level `Gate::authorize(...)` checks.
- Enforced resident ownership for incidents and complaints.
- Enforced user ownership for notifications and profile resources.
- Prevented cross-role route access and cross-user IDOR access.
- Protected User Management, reports, system branding, user presence, cases, incidents, complaints, announcements, tanod operations, emergency hotlines, and barangay map/configuration actions.
- Preserved the `official` and `dao` alias behavior.
- Added test-database isolation and database-engine compatibility corrections.
- Avoided relying on hidden buttons or sidebar visibility as authorization.

## Policies and gates

Policies cover:

```text
Announcement
CaseRecord
EmergencyHotline
Incident
ResidentComplaint
TanodProfile
UserNotification
User
```

Additional gates cover:

```text
viewReports
manageSystemBranding
viewUserPresence
viewBarangayMap
manageBarangays
```

## Security tests

The RBAC and route-security suite verifies route uniqueness, role middleware, active-account blocking, cross-role rejection, notification IDOR protection, complaint and incident ownership, and administrator-only gates.

## Phase checkpoints

```text
dbc2bf2 Complete Phase 5 user protection and test database isolation
1afb30f Secure remaining sensitive modules in Phase 5 Batch 7
e18a60a Complete Phase 5 RBAC route and IDOR security hardening
```
