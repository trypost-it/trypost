# Account Billing, Plans & Workspaces

## Overview

Introduce an Account entity as the central billing and organizational unit. Account holds the Stripe subscription, plan, and limits. Workspaces group social accounts within an Account. Users belong to one Account with an account-level role (admin/user) and can be assigned to specific workspaces with workspace-level roles (member/viewer). The Account owner has full access to everything.

## Data Model

```
Account (Billable, plan_id, owner_id)
  ├── Users (account_id, account_role: admin/user)
  └── Workspaces
       ├── Members (user_id, workspace_role: member/viewer)
       ├── Invites
       └── Social Accounts
```

## Roles

### Account-level

Owner is determined by `accounts.owner_id`. No account-level role column on users. Only the owner can manage billing, create workspaces, and delete the account. The owner has full access to all workspaces automatically.

### Workspace-level roles (`user_workspace.role`)

| Role | Permissions |
|---|---|
| Admin | Everything in the workspace: manage members, connect accounts, settings |
| Member | Create posts, schedule |
| Viewer | View only (future: comments) |

The Account owner has full access to all workspaces without needing a workspace pivot record.

## Plans

| Limit | Starter ($19/$190) | Plus ($29/$290) | Pro ($49/$490) | Max ($99/$990) |
|---|---|---|---|---|
| Social accounts | 5 | 10 | 30 | 100 |
| Members | 1 | 5 | 15 | 20 |
| Workspaces | 1 | 5 | 15 | 50 |
| AI Images/month | 50 | 150 | 500 | 2000 |
| AI Videos/month | 10 | 30 | 100 | 500 |
| Data retention (days) | 30 | 60 | 90 | 730 |
| Monthly price | $19 | $29 | $49 | $99 |
| Yearly price | $190 | $290 | $490 | $990 |

All limits are at the **Account level** (totals across all workspaces).

Starter plan: 1 member (owner only), 1 workspace (default, UI hides workspace management).

Trial period: 8 days (configurable via `CASHIER_TRIAL_DAYS`).

---

## Database Changes

### New table: `accounts`

| Column | Type | Notes |
|---|---|---|
| id | uuid, PK | |
| owner_id | FK -> users, nullable initially | Set after user creation (chicken-egg) |
| plan_id | FK -> plans, nullable | Current plan |
| name | string | Account/company name |
| stripe_id | string, nullable, indexed | Stripe customer ID |
| pm_type | string, nullable | Payment method type |
| pm_last_four | string, nullable | Last 4 digits |
| trial_ends_at | timestamp, nullable | |
| timestamps | | |

Note: `owner_id` is nullable because during signup the user and account are created in the same transaction. Set `owner_id` after user creation.

### New table: `plans` (unchanged from current)

Already implemented. slug, name, stripe price IDs, all limits, sort, is_archived.

### Modify table: `users`

Add columns:
- `account_id` (FK -> accounts, nullable, constrained)
- `account_role` (string, default 'user') — enum: admin, user

Remove columns:
- `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` (already removed)

### Modify table: `workspaces`

Remove columns:
- `plan_id` (moves to Account)
- `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` (moves to Account)

Add columns:
- `account_id` (FK -> accounts, constrained, cascade delete)

Keep: `user_id` (original creator, not necessarily the owner), `name`, `timezone`

### Modify table: `user_workspace`

Change `role` values from `owner/admin/member` to `member/viewer`. Owner is determined by Account, not workspace pivot.

### Modify table: `subscriptions`

Change FK from `workspace_id` to `account_id`.

### Modify table: `invites` (rename from `workspace_invites`)

| Column | Type | Notes |
|---|---|---|
| id | uuid, PK | |
| account_id | FK -> accounts, cascade delete | |
| email | string | Invited email |
| workspaces | json | Array of {workspace_id, role} |
| invited_by | FK -> users, nullable | Who sent the invite |
| accepted_at | timestamp, nullable | |
| timestamps | | |

### Remove table: `brands`

Brands concept is replaced by Workspaces. Remove brands table, remove `brand_id` from social_accounts.

---

## Models

### Account (new)

- UUID primary key
- `Billable` trait from Cashier
- Constant: `SUBSCRIPTION_NAME = 'default'`
- Fillable: name, owner_id, plan_id
- Relationships: `owner()` belongsTo User, `plan()` belongsTo Plan, `users()` hasMany User, `workspaces()` hasMany Workspace, `invites()` hasMany Invite
- Methods: `hasActiveSubscription()`, `isOnTrial()`, `stripeEmail()` (owner email), `stripeName()` (account name)

### User (modified)

- Remove `Billable` trait (already removed)
- Add `account_id` and `account_role` to fillable
- Add cast: `account_role` to `AccountRole` enum
- Add relationship: `account()` belongsTo Account
- Remove: `HasWorkspace` trait methods related to billing
- Keep: `currentWorkspace()`, `switchWorkspace()`, workspace navigation methods
- Add: `isAccountOwner()` — `$this->id === $this->account?->owner_id`
- Add: `isAccountAdmin()` — `$this->account_role === AccountRole::Admin || $this->isAccountOwner()`

### Workspace (modified)

- Remove `Billable` trait
- Remove billing fields from fillable (stripe_id, pm_type, pm_last_four, trial_ends_at, plan_id)
- Add `account_id` to fillable
- Add relationship: `account()` belongsTo Account
- Keep: members(), socialAccounts(), posts(), invites is now on Account

### Invite (modified from WorkspaceInvite)

- Rename model from WorkspaceInvite to Invite
- Add: `account_id`, `workspaces` (json), `invited_by`
- Add cast: `workspaces` to array
- Relationship: `account()` belongsTo Account, `invitedBy()` belongsTo User

---

## Enums

### AccountRole (new)

```php
enum AccountRole: string
{
    case Admin = 'admin';
    case User = 'user';
}
```

### WorkspaceRole (modify existing)

```php
enum WorkspaceRole: string
{
    case Member = 'member';
    case Viewer = 'viewer';
}
```

Remove `Owner` and `Admin` cases.

---

## Cashier Configuration

### AppServiceProvider

```php
Cashier::useCustomerModel(Account::class);
```

---

## Middleware: EnsureSubscribed

1. If `config('trypost.self_hosted')` → pass
2. Get `$user->account`
3. If account has active subscription or trial → pass
4. Redirect to `/subscribe`

---

## Pennant Features

Scope changes from Workspace to Account:

```php
Feature::resolveScopeUsing(fn () => auth()->user()?->account);
```

All 6 feature classes receive `Account $scope` instead of `Workspace $scope`:
- `SocialAccountLimit` — `$scope->plan?->social_account_limit ?? 5`
- `MemberLimit` — `$scope->plan?->member_limit ?? 1`
- `WorkspaceLimit` — `$scope->plan?->workspace_limit ?? 1` (replaces BrandLimit)
- `AiImagesLimit` — `$scope->plan?->ai_images_limit ?? 50`
- `AiVideosLimit` — `$scope->plan?->ai_videos_limit ?? 10`
- `DataRetentionDays` — `$scope->plan?->data_retention_days ?? 30`

---

## Limit Enforcement

All limits checked at Account level:

- **Social accounts**: total across all workspaces in the account
- **Members**: unique users in the account (excluding owner) — `$account->users()->where('id', '!=', $account->owner_id)->count()`
- **Workspaces**: `$account->workspaces()->count()`
- **AI images/videos**: monthly usage tracked per account
- **Data retention**: applied per account

---

## Controllers

### BillingController

All operations on `$user->account`:
- `$account->subscribed()`, `$account->subscription()`, `$account->invoices()`

### OnboardingController

Signup flow:
1. Create Account
2. Create User with `account_id` and `account_role = admin` (owner)
3. Set `account.owner_id`
4. Create default Workspace with `account_id`
5. Stripe checkout on Account

### StripeEventListener

Find `Account::where('stripe_id', $stripeCustomerId)` instead of Workspace.

### InviteController (replaces WorkspaceInviteController)

- `store()`: create invite on Account with workspaces + roles array
- On accept: set user's `account_id`, create workspace pivot records

---

## Signup Flow

1. User registers (name, email, password)
2. `CreateUser` action:
   - Creates Account (name = user's name)
   - Creates User with `account_id`, `account_role = admin`
   - Sets `account.owner_id = user.id`
   - Creates default Workspace within Account
   - Sets `user.current_workspace_id`
3. Onboarding: role → connect accounts → Stripe checkout (on Account)

## Invite Flow

1. Owner/Admin creates invite: email + [{workspace_id, role}]
2. Email sent with invite link
3. Person clicks link:
   - If no TryPost account: register with `account_id` pre-set, `account_role = user`
   - If has TryPost account with same Account: add workspace assignments
   - If has TryPost account with different Account: error — must use different email
4. Workspace pivot records created per the invite's workspaces array

---

## UI Behavior by Plan

### Starter (1 workspace, 1 member)
- Workspace switcher hidden
- "Create workspace" hidden
- Invite members hidden
- Simple single-workspace experience

### Plus and above
- Workspace switcher visible
- Create workspace button visible
- Invite members visible
- Full multi-workspace experience

---

## Plans table: workspace_limit replaces brand_limit

| Column change | Old | New |
|---|---|---|
| brand_limit | 0, 5, 15, 50 | renamed to workspace_limit: 1, 5, 15, 50 |

---

## Self-hosted mode

All limits bypassed. Account still exists but no billing. Unlimited workspaces, members, social accounts.

---

## What does NOT change

- Social account OAuth flows (connect/disconnect)
- Post creation and publishing
- Analytics
- Multi-account social connections (multiple accounts per platform)
