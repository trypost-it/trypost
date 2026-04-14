# Workspace Billing, Plans & Brands

## Overview

Move billing from User to Workspace. Each workspace has its own subscription tied to a plan. Introduce a `plans` table with 4 tiers (Starter, Plus, Pro, Max) that enforce limits on social accounts, members, brands, AI generation, and data retention. Introduce `brands` as a way to group social accounts within a workspace.

## Plans

| Limit | Starter ($19/$190) | Plus ($29/$290) | Pro ($49/$490) | Max ($99/$990) |
|---|---|---|---|---|
| Social accounts | 5 | 10 | 30 | 100 |
| Members | 1 | 5 | 15 | 20 |
| Brands | 0 | 5 | 15 | 50 |
| AI Images/month | 50 | 150 | 500 | 2000 |
| AI Videos/month | 10 | 30 | 100 | 500 |
| Data retention (days) | 30 | 60 | 90 | 730 |
| Monthly price | $19 | $29 | $49 | $99 |
| Yearly price | $190 | $290 | $490 | $990 |

Trial period: 8 days (configurable via `CASHIER_TRIAL_DAYS`).

---

## Database Changes

### New table: `plans`

| Column | Type | Notes |
|---|---|---|
| id | uuid, PK | |
| slug | string, unique | "starter", "plus", "pro", "max" |
| name | string | "Starter", "Plus", "Pro", "Max" |
| stripe_monthly_price_id | string, nullable | Stripe price ID for monthly billing |
| stripe_yearly_price_id | string, nullable | Stripe price ID for yearly billing |
| monthly_price | integer | In cents (1900, 2900, 4900, 9900) |
| yearly_price | integer | In cents (19000, 29000, 49000, 99000) |
| social_account_limit | integer | 5, 10, 30, 100 |
| member_limit | integer | 1, 5, 15, 20 |
| brand_limit | integer | 0, 5, 15, 50 |
| ai_images_limit | integer | 50, 150, 500, 2000 |
| ai_videos_limit | integer | 10, 30, 100, 500 |
| data_retention_days | integer | 30, 60, 90, 730 |
| sort | integer | Display order |
| is_archived | boolean, default false | Hide from selection without deleting |
| timestamps | | |

### New table: `brands`

| Column | Type | Notes |
|---|---|---|
| id | uuid, PK | |
| workspace_id | FK -> workspaces, cascade delete | |
| name | string | |
| timestamps | | |

### Modify table: `social_accounts`

Add `brand_id` (FK -> brands, nullable, set null on delete). Social accounts can optionally belong to a brand for grouping.

### Modify table: `workspaces`

Add columns (Cashier Billable fields + plan reference):

| Column | Type | Notes |
|---|---|---|
| plan_id | FK -> plans, nullable, constrained | Current plan |
| stripe_id | string, nullable, indexed | Stripe customer ID |
| pm_type | string, nullable | Payment method type |
| pm_last_four | string, nullable | Last 4 digits |
| trial_ends_at | timestamp, nullable | |

### Modify table: `subscriptions`

Change FK from `user_id` to `workspace_id` (uuid, FK -> workspaces, cascade delete). Drop the old `user_id` column.

### Modify table: `subscription_items`

No changes needed (references `subscription_id` which remains the same).

### Modify table: `users`

Remove billing columns: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`.

---

## Models

### Plan (new)

- UUID primary key
- Fillable: slug, name, prices, all limits, sort, is_archived
- Casts: is_archived (boolean), monthly_price (integer), yearly_price (integer)
- Scopes: `active()` (where is_archived = false)
- Relationship: `workspaces()` hasMany
- Method: `formattedMonthlyPrice()`, `formattedYearlyPrice()`

### Brand (new)

- UUID primary key
- Fillable: workspace_id, name
- Relationship: `workspace()` belongsTo, `socialAccounts()` hasMany

### Workspace (modified)

- Add `Billable` trait from Laravel Cashier
- Add to fillable: `plan_id`, `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`
- New relationships: `plan()` belongsTo, `brands()` hasMany
- New constant: `SUBSCRIPTION_NAME = 'default'`
- Methods: `hasActiveSubscription()`, `isOnTrial()`, `stripeEmail()` (returns owner's email), `stripeName()` (returns workspace name)

### User (modified)

- Remove `Billable` trait
- Remove `SUBSCRIPTION_NAME` constant
- Remove `hasActiveSubscription()`, `hasEverSubscribed()` methods
- Remove billing fields from fillable
- Simplify `HasWorkspace` trait: remove `canCreateWorkspace()`, `incrementWorkspaceQuantity()`, `decrementWorkspaceQuantity()`, `syncWorkspaceQuantity()`

### SocialAccount (modified)

- Add `brand_id` to fillable
- New relationship: `brand()` belongsTo (nullable)

---

## Cashier Configuration

### AppServiceProvider

```php
Cashier::useCustomerModel(Workspace::class);
```

### config/cashier.php

Remove the `plans` section (plans come from the database now). Keep Stripe keys, webhook, currency, trial_days, invoice settings.

---

## Middleware: EnsureSubscribed

Simplified logic:

1. If `config('trypost.self_hosted')` is true -> pass through
2. Get `$user->currentWorkspace`
3. If workspace has active subscription or is on trial -> pass through
4. Redirect to `/subscribe`

No more checking the workspace owner's subscription — the workspace IS the subscriber.

---

## Controllers

### BillingController (modified)

All billing operations change from `$user` to `$workspace`:

- `subscribe()` — show plan selection page with all active plans
- `checkout(Plan $plan)` — create Stripe Checkout for workspace with selected plan's price ID
- `processing()` — check `$workspace->subscribed()` instead of `$user->subscribed()`
- `index()` — show workspace subscription, invoices, payment method, current plan details with limits and usage
- `portal()` — `$workspace->redirectToBillingPortal()`
- `swap(Plan $plan)` — swap workspace subscription to a different plan

### OnboardingController (modified)

`storeConnect()` changes from `$user->newSubscription(...)` to `$workspace->newSubscription(...)`. The plan selection needs to happen during onboarding — default to Starter plan or let user choose.

### StripeEventListener (modified)

Change `User::where('stripe_id', ...)` to `Workspace::where('stripe_id', ...)`. Update `handleSubscriptionCreated` to work with workspace context (still updates user setup to Completed).

### BrandController (new)

Full CRUD:

- `index()` — list brands for current workspace with social account counts
- `store(StoreBrandRequest)` — create brand (enforce plan limit via policy)
- `update(UpdateBrandRequest, Brand)` — rename brand
- `destroy(Brand)` — delete brand (social accounts get `brand_id = null`, not deleted)

### SocialAccountController (modified if exists)

Add ability to assign/unassign a social account to a brand.

---

## Policies

### BrandPolicy

- `viewAny(User, Workspace)` — user belongs to workspace
- `create(User, Workspace)` — user can manage accounts AND `$workspace->brands()->count() < $workspace->plan->brand_limit`
- `update(User, Workspace, Brand)` — user can manage accounts AND brand belongs to workspace
- `delete(User, Workspace, Brand)` — same as update

### WorkspacePolicy (modified)

- `manageBilling(User, Workspace)` — only owner (unchanged)
- `inviteMember(User, Workspace)` — enforce member limit: `$workspace->members()->count() < $workspace->plan->member_limit`

### SocialAccountPolicy (new or modified)

- Enforce social account limit on connect: `$workspace->socialAccounts()->count() < $workspace->plan->social_account_limit`

---

## Pennant Features (app/Features/)

Use Laravel Pennant to resolve plan limits per workspace. Each feature class resolves the limit value from the workspace's plan, with a sensible fallback for self-hosted mode (unlimited).

### SocialAccountLimit

Returns `$workspace->plan->social_account_limit` (fallback: `PHP_INT_MAX` for self-hosted).

### MemberLimit

Returns `$workspace->plan->member_limit` (fallback: `PHP_INT_MAX`).

### BrandLimit

Returns `$workspace->plan->brand_limit` (fallback: `PHP_INT_MAX`).

### AiImagesLimit

Returns `$workspace->plan->ai_images_limit` (fallback: `PHP_INT_MAX`).

### AiVideosLimit

Returns `$workspace->plan->ai_videos_limit` (fallback: `PHP_INT_MAX`).

### DataRetentionDays

Returns `$workspace->plan->data_retention_days` (fallback: `PHP_INT_MAX` for unlimited).

### Pennant Configuration

In `AppServiceProvider`:

```php
Feature::resolveScopeUsing(fn () => auth()->user()?->currentWorkspace);
Feature::discover();
```

### Usage in Policies

Policies use Pennant to resolve limits:

```php
use Laravel\Pennant\Feature;
use App\Features\BrandLimit;

// In BrandPolicy::create()
$limit = Feature::for($workspace)->value(BrandLimit::class);
return $workspace->brands()->count() < $limit;
```

This decouples limit enforcement from direct plan access, making it testable and overridable per workspace if needed.

---

## Routes

### Billing routes (modified)

```
GET    /subscribe                    BillingController@subscribe
POST   /billing/checkout/{plan}      BillingController@checkout
GET    /billing/processing           BillingController@processing
GET    /settings/billing             BillingController@index
GET    /settings/billing/portal      BillingController@portal
POST   /settings/billing/swap/{plan} BillingController@swap
```

### Brand routes (new)

```
GET    /brands                       BrandController@index
POST   /brands                       BrandController@store
PUT    /brands/{brand}               BrandController@update
DELETE /brands/{brand}               BrandController@destroy
```

---

## Frontend (Vue)

### Subscribe page (modified)

Show plan selection cards (4 plans) with monthly/yearly toggle instead of a single checkout button. Each card shows limits and price. User selects plan -> goes to Stripe Checkout.

### Billing settings page (modified)

Show current plan name, limits with usage bars (e.g., "3/5 social accounts"), subscription status, invoices, payment method. Add "Change Plan" button that shows plan comparison.

### Brands pages (new)

- **Brands list** — cards/list showing brands with social account count per brand. Create button (disabled if at limit with tooltip explaining).
- **Brand create/edit** — simple form with name field.
- **Social account assignment** — in the accounts page, ability to assign accounts to brands (dropdown or drag).

### Sidebar (modified)

Add "Brands" link in the sidebar navigation (only visible if plan allows brands, i.e., brand_limit > 0).

---

## Seeder: PlanSeeder

Creates the 4 plans:

```php
[
    [
        'slug' => 'starter',
        'name' => 'Starter',
        'stripe_monthly_price_id' => env('STRIPE_STARTER_MONTHLY'),
        'stripe_yearly_price_id' => env('STRIPE_STARTER_YEARLY'),
        'monthly_price' => 1900,
        'yearly_price' => 19000,
        'social_account_limit' => 5,
        'member_limit' => 1,
        'brand_limit' => 0,
        'ai_images_limit' => 50,
        'ai_videos_limit' => 10,
        'data_retention_days' => 30,
        'sort' => 1,
    ],
    [
        'slug' => 'plus',
        'name' => 'Plus',
        'monthly_price' => 2900,
        'yearly_price' => 29000,
        'social_account_limit' => 10,
        'member_limit' => 5,
        'brand_limit' => 5,
        'ai_images_limit' => 150,
        'ai_videos_limit' => 30,
        'data_retention_days' => 60,
        'sort' => 2,
    ],
    [
        'slug' => 'pro',
        'name' => 'Pro',
        'monthly_price' => 4900,
        'yearly_price' => 49000,
        'social_account_limit' => 30,
        'member_limit' => 15,
        'brand_limit' => 15,
        'ai_images_limit' => 500,
        'ai_videos_limit' => 100,
        'data_retention_days' => 90,
        'sort' => 3,
    ],
    [
        'slug' => 'max',
        'name' => 'Max',
        'monthly_price' => 9900,
        'yearly_price' => 99000,
        'social_account_limit' => 100,
        'member_limit' => 20,
        'brand_limit' => 50,
        'ai_images_limit' => 2000,
        'ai_videos_limit' => 500,
        'data_retention_days' => 730,
        'sort' => 4,
    ],
]
```

---

## Tests

### Plan tests
- Plan seeder creates 4 plans with correct limits
- Plan `active()` scope excludes archived plans
- Plan formatted prices return correct values

### Brand CRUD tests
- Create brand (success + limit enforcement)
- List brands for workspace
- Update brand name
- Delete brand (social accounts get null brand_id)
- Cannot create brand on Starter plan (limit = 0)
- Cannot create brand beyond plan limit
- Cannot access brands from another workspace

### Billing tests
- Workspace checkout creates Stripe session
- Workspace subscription grants access via middleware
- No subscription redirects to /subscribe
- Self-hosted mode bypasses subscription check
- Plan swap updates workspace plan_id
- Invoices returned for workspace
- Only owner can manage billing

### Limit enforcement tests
- Cannot connect social account beyond plan limit
- Cannot invite member beyond plan limit
- Cannot create brand beyond plan limit

### Migration tests
- Workspace has billing columns after migration
- User no longer has billing columns
- Subscriptions reference workspace_id

---

## Self-hosted mode

All plan limits and subscription checks are bypassed when `config('trypost.self_hosted')` is true. In self-hosted mode:

- No plans table interaction needed
- No subscription checks
- Unlimited social accounts, members, brands
- Unlimited data retention
- Brand feature is still available (just no limit)

---

## What does NOT change

- Onboarding flow steps (Role -> Connect -> Payment -> Complete)
- Social account OAuth flows
- Post creation and publishing
- Analytics
- Workspace creation flow
- User authentication
