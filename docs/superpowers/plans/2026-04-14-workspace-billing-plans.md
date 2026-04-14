# Workspace Billing, Plans & Brands — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move billing from User to Workspace, introduce tiered plans with limits, and add Brands for grouping social accounts.

**Architecture:** Workspace becomes the Cashier Billable entity with a `plan_id` FK. Plans table stores limits (social accounts, members, brands, AI images/videos, data retention). Laravel Pennant features resolve plan limits per workspace. Brands group social accounts within a workspace. Policies enforce limits using Pennant.

**Tech Stack:** Laravel 13, Cashier (Stripe), Pennant, Pest, Vue 3 + Inertia v3

---

### Task 1: Create Plans Migration and Model

**Files:**
- Create: `database/migrations/2026_04_14_000001_create_plans_table.php`
- Create: `app/Models/Plan.php`
- Create: `app/Enums/Plan/Slug.php`

- [ ] **Step 1: Create migration**

Run: `php artisan make:migration create_plans_table --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();
            $table->integer('monthly_price');
            $table->integer('yearly_price');
            $table->integer('social_account_limit');
            $table->integer('member_limit');
            $table->integer('brand_limit');
            $table->integer('ai_images_limit');
            $table->integer('ai_videos_limit');
            $table->integer('data_retention_days');
            $table->integer('sort')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

- [ ] **Step 2: Create Slug enum**

Create `app/Enums/Plan/Slug.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums\Plan;

enum Slug: string
{
    case Starter = 'starter';
    case Plus = 'plus';
    case Pro = 'pro';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Plus => 'Plus',
            self::Pro => 'Pro',
            self::Max => 'Max',
        };
    }
}
```

- [ ] **Step 3: Create Plan model**

Create `app/Models/Plan.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Plan\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'monthly_price',
        'yearly_price',
        'social_account_limit',
        'member_limit',
        'brand_limit',
        'ai_images_limit',
        'ai_videos_limit',
        'data_retention_days',
        'sort',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'slug' => Slug::class,
            'monthly_price' => 'integer',
            'yearly_price' => 'integer',
            'social_account_limit' => 'integer',
            'member_limit' => 'integer',
            'brand_limit' => 'integer',
            'ai_images_limit' => 'integer',
            'ai_videos_limit' => 'integer',
            'data_retention_days' => 'integer',
            'sort' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function formattedMonthlyPrice(): string
    {
        return '$' . number_format($this->monthly_price / 100, 0);
    }

    public function formattedYearlyPrice(): string
    {
        return '$' . number_format($this->yearly_price / 100, 0);
    }
}
```

- [ ] **Step 4: Add Plan to morph map**

In `app/Providers/AppServiceProvider.php`, add to the `enforceMorphMap` array (inside `configureMorphMap` method):

```php
'plan' => Plan::class,
```

And add the import at the top:

```php
use App\Models\Plan;
```

- [ ] **Step 5: Run migration**

Run: `php artisan migrate`
Expected: `plans` table created.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: create plans table, model, and slug enum"
```

---

### Task 2: Create PlanSeeder

**Files:**
- Create: `database/seeders/PlanSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/PlanSeederTest.php`

- [ ] **Step 1: Write the failing test**

Run: `php artisan make:test PlanSeederTest --pest --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Models\Plan;

test('plan seeder creates 4 plans', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

    expect(Plan::count())->toBe(4);
});

test('plan seeder creates plans with correct limits', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

    $starter = Plan::where('slug', Slug::Starter)->first();
    expect($starter)->not->toBeNull()
        ->and($starter->name)->toBe('Starter')
        ->and($starter->monthly_price)->toBe(1900)
        ->and($starter->yearly_price)->toBe(19000)
        ->and($starter->social_account_limit)->toBe(5)
        ->and($starter->member_limit)->toBe(1)
        ->and($starter->brand_limit)->toBe(0)
        ->and($starter->ai_images_limit)->toBe(50)
        ->and($starter->ai_videos_limit)->toBe(10)
        ->and($starter->data_retention_days)->toBe(30);

    $max = Plan::where('slug', Slug::Max)->first();
    expect($max)->not->toBeNull()
        ->and($max->monthly_price)->toBe(9900)
        ->and($max->social_account_limit)->toBe(100)
        ->and($max->member_limit)->toBe(20)
        ->and($max->brand_limit)->toBe(50)
        ->and($max->data_retention_days)->toBe(730);
});

test('plan seeder is idempotent', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
    $this->seed(\Database\Seeders\PlanSeeder::class);

    expect(Plan::count())->toBe(4);
});

test('plan active scope excludes archived plans', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

    Plan::where('slug', Slug::Starter)->update(['is_archived' => true]);

    expect(Plan::active()->count())->toBe(3);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/PlanSeederTest.php`
Expected: FAIL (PlanSeeder class not found)

- [ ] **Step 3: Create PlanSeeder**

Create `database/seeders/PlanSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
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
                'stripe_monthly_price_id' => env('STRIPE_PLUS_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_PLUS_YEARLY'),
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
                'stripe_monthly_price_id' => env('STRIPE_PRO_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_PRO_YEARLY'),
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
                'stripe_monthly_price_id' => env('STRIPE_MAX_MONTHLY'),
                'stripe_yearly_price_id' => env('STRIPE_MAX_YEARLY'),
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
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
```

- [ ] **Step 4: Register in DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, add inside `run()`:

```php
$this->call([
    PlanSeeder::class,
]);
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact tests/Feature/PlanSeederTest.php`
Expected: 4 passed

- [ ] **Step 6: Run seeder**

Run: `php artisan db:seed --class=PlanSeeder`

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: create PlanSeeder with 4 tiered plans"
```

---

### Task 3: Create Brands Migration, Model, and CRUD

**Files:**
- Create: `database/migrations/2026_04_14_000002_create_brands_table.php`
- Create: `database/migration/2026_04_14_000003_add_brand_id_to_social_accounts_table.php`
- Create: `app/Models/Brand.php`
- Create: `app/Http/Controllers/App/BrandController.php`
- Create: `app/Http/Requests/App/Brand/StoreBrandRequest.php`
- Create: `app/Http/Requests/App/Brand/UpdateBrandRequest.php`
- Create: `app/Policies/BrandPolicy.php`
- Modify: `app/Models/Workspace.php` — add `brands()` relationship
- Modify: `app/Models/SocialAccount.php` — add `brand()` relationship
- Modify: `routes/app.php` — add brand routes
- Modify: `app/Providers/AppServiceProvider.php` — add Brand to morph map
- Create: `tests/Feature/BrandControllerTest.php`

- [ ] **Step 1: Create brands migration**

Run: `php artisan make:migration create_brands_table --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
```

- [ ] **Step 2: Create brand_id migration for social_accounts**

Run: `php artisan make:migration add_brand_id_to_social_accounts_table --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->foreignUuid('brand_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
```

- [ ] **Step 3: Create Brand model**

Create `app/Models/Brand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
```

- [ ] **Step 4: Add relationships to Workspace and SocialAccount**

In `app/Models/Workspace.php`, add method:

```php
public function brands(): HasMany
{
    return $this->hasMany(Brand::class);
}
```

In `app/Models/SocialAccount.php`, add `brand_id` to fillable and add method:

```php
public function brand(): BelongsTo
{
    return $this->belongsTo(Brand::class);
}
```

- [ ] **Step 5: Add Brand to morph map**

In `app/Providers/AppServiceProvider.php`, add to `enforceMorphMap`:

```php
'brand' => Brand::class,
```

And import `use App\Models\Brand;`

- [ ] **Step 6: Create BrandPolicy**

Run: `php artisan make:policy BrandPolicy --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserWorkspace\Role;
use App\Models\Brand;
use App\Models\User;
use App\Models\Workspace;

class BrandPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        if (! $this->canManage($user, $workspace)) {
            return false;
        }

        if (config('trypost.self_hosted')) {
            return true;
        }

        $limit = $workspace->plan?->brand_limit ?? 0;

        return $workspace->brands()->count() < $limit;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->canManage($user, $brand->workspace);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->canManage($user, $brand->workspace);
    }

    private function canManage(User $user, Workspace $workspace): bool
    {
        $member = $workspace->members()->where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        return in_array(Role::tryFrom($member->pivot->role), [Role::Owner, Role::Admin]);
    }
}
```

- [ ] **Step 7: Create Form Requests**

Create `app/Http/Requests/App/Brand/StoreBrandRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Brand;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

Create `app/Http/Requests/App/Brand/UpdateBrandRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Brand;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 8: Create BrandController**

Create `app/Http/Controllers/App/BrandController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Brand\StoreBrandRequest;
use App\Http\Requests\App\Brand\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('viewAny', [Brand::class, $workspace]);

        $brands = $workspace->brands()
            ->withCount('socialAccounts')
            ->orderBy('name')
            ->get();

        return Inertia::render('brands/Index', [
            'brands' => $brands,
            'canCreate' => $request->user()->can('create', [Brand::class, $workspace]),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('create', [Brand::class, $workspace]);

        $workspace->brands()->create([
            'name' => data_get($request->validated(), 'name'),
        ]);

        return back();
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $brand->update([
            'name' => data_get($request->validated(), 'name'),
        ]);

        return back();
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return back();
    }
}
```

- [ ] **Step 9: Add routes**

In `routes/app.php`, inside the subscribed middleware group (after the Labels section, around line 164), add:

```php
// Brands
Route::get('brands', [BrandController::class, 'index'])->name('app.brands.index');
Route::post('brands', [BrandController::class, 'store'])->name('app.brands.store');
Route::put('brands/{brand}', [BrandController::class, 'update'])->name('app.brands.update');
Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('app.brands.destroy');
```

Add the import at the top: `use App\Http\Controllers\App\BrandController;`

- [ ] **Step 10: Run migrations**

Run: `php artisan migrate`

- [ ] **Step 11: Write tests**

Run: `php artisan make:test BrandControllerTest --pest --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
});

test('can list brands', function () {
    Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Brand A']);
    Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Brand B']);

    $response = $this->actingAs($this->user)->get(route('app.brands.index'));

    $response->assertOk();
    $brands = $response->original->getData()['page']['props']['brands'];
    expect($brands)->toHaveCount(2);
});

test('can create brand', function () {
    $response = $this->actingAs($this->user)
        ->post(route('app.brands.store'), ['name' => 'My Brand']);

    $response->assertRedirect();
    expect($this->workspace->brands()->count())->toBe(1)
        ->and($this->workspace->brands()->first()->name)->toBe('My Brand');
});

test('can update brand', function () {
    $brand = Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Old Name']);

    $response = $this->actingAs($this->user)
        ->put(route('app.brands.update', $brand), ['name' => 'New Name']);

    $response->assertRedirect();
    expect($brand->fresh()->name)->toBe('New Name');
});

test('can delete brand', function () {
    $brand = Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'To Delete']);

    $response = $this->actingAs($this->user)
        ->delete(route('app.brands.destroy', $brand));

    $response->assertRedirect();
    expect(Brand::find($brand->id))->toBeNull();
});

test('deleting brand nullifies social account brand_id', function () {
    $brand = Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Test Brand']);

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'brand_id' => $brand->id,
        'platform' => Platform::Instagram,
        'status' => AccountStatus::Connected,
    ]);

    $this->actingAs($this->user)->delete(route('app.brands.destroy', $brand));

    expect($account->fresh()->brand_id)->toBeNull();
});

test('cannot create brand beyond plan limit', function () {
    config(['trypost.self_hosted' => false]);

    $plan = Plan::create([
        'slug' => 'starter', 'name' => 'Starter',
        'monthly_price' => 1900, 'yearly_price' => 19000,
        'social_account_limit' => 5, 'member_limit' => 1,
        'brand_limit' => 0, 'ai_images_limit' => 50,
        'ai_videos_limit' => 10, 'data_retention_days' => 30,
        'sort' => 1,
    ]);
    $this->workspace->update(['plan_id' => $plan->id]);

    $response = $this->actingAs($this->user)
        ->post(route('app.brands.store'), ['name' => 'Should Fail']);

    $response->assertForbidden();
});

test('cannot access brands from another workspace', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Owner->value]);

    $brand = Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Not Yours']);

    $response = $this->actingAs($otherUser)
        ->put(route('app.brands.update', $brand), ['name' => 'Hacked']);

    $response->assertForbidden();
});
```

- [ ] **Step 12: Run tests**

Run: `php artisan test --compact tests/Feature/BrandControllerTest.php`
Expected: All passed

- [ ] **Step 13: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 14: Commit**

```bash
git add -A && git commit -m "feat: create brands with CRUD, policy, and tests"
```

---

### Task 4: Move Billable from User to Workspace

**Files:**
- Create: `database/migrations/2026_04_14_000004_add_billing_columns_to_workspaces_table.php`
- Create: `database/migrations/2026_04_14_000005_migrate_subscriptions_to_workspace.php`
- Create: `database/migrations/2026_04_14_000006_remove_billing_columns_from_users_table.php`
- Modify: `app/Models/Workspace.php` — add Billable trait, plan relationship, billing methods
- Modify: `app/Models/User.php` — remove Billable trait and billing methods
- Modify: `app/Models/Traits/HasWorkspace.php` — remove workspace quantity methods
- Modify: `app/Providers/AppServiceProvider.php` — set Cashier customer model to Workspace
- Modify: `app/Http/Middleware/App/EnsureSubscribed.php` — check workspace subscription
- Modify: `app/Http/Controllers/App/BillingController.php` — use workspace for billing
- Modify: `app/Http/Controllers/App/OnboardingController.php` — use workspace for checkout
- Modify: `app/Listeners/StripeEventListener.php` — find Workspace by stripe_id
- Modify: `config/cashier.php` — remove plans section
- Modify: `database/factories/WorkspaceFactory.php` — add plan_id
- Create: `tests/Feature/WorkspaceBillingTest.php`

- [ ] **Step 1: Create migration to add billing columns to workspaces**

Run: `php artisan make:migration add_billing_columns_to_workspaces_table --no-interaction`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('timezone')->constrained()->nullOnDelete();
            $table->string('stripe_id')->nullable()->after('plan_id')->index();
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four')->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
```

- [ ] **Step 2: Create migration to change subscriptions FK**

Run: `php artisan make:migration migrate_subscriptions_to_workspace --no-interaction`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->foreignUuid('workspace_id')->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
            $table->foreignUuid('user_id')->after('id')->constrained()->cascadeOnDelete();
        });
    }
};
```

- [ ] **Step 3: Create migration to remove billing columns from users**

Run: `php artisan make:migration remove_billing_columns_from_users_table --no-interaction`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }
};
```

- [ ] **Step 4: Update Workspace model**

Replace `app/Models/Workspace.php` with:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasMedia;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use Billable, HasFactory, HasMedia, HasUuids;

    public const SUBSCRIPTION_NAME = 'default';

    protected $fillable = [
        'user_id',
        'name',
        'timezone',
        'plan_id',
    ];

    protected $appends = ['has_logo', 'logo_url'];

    public function getHasLogoAttribute(): bool
    {
        return $this->getFirstMedia('logo') !== null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WorkspaceInvite::class);
    }

    public function hashtags(): HasMany
    {
        return $this->hasMany(WorkspaceHashtag::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(WorkspaceLabel::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->user_id === $user->id || $this->members()->where('user_id', $user->id)->exists();
    }

    public function hasConnectedPlatform(string $platform): bool
    {
        return $this->socialAccounts()->where('platform', $platform)->exists();
    }

    public function getSocialAccount(string $platform): ?SocialAccount
    {
        return $this->socialAccounts()->where('platform', $platform)->first();
    }

    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    public function isOnTrial(): bool
    {
        $subscription = $this->subscription(self::SUBSCRIPTION_NAME);

        return $subscription?->onTrial() ?? false;
    }

    public function stripeEmail(): string
    {
        return $this->owner?->email ?? '';
    }

    public function stripeName(): string
    {
        return $this->name;
    }
}
```

- [ ] **Step 5: Update User model — remove Billable**

In `app/Models/User.php`:

1. Remove `use Laravel\Cashier\Billable;` import (line 20)
2. Remove `Billable` from the trait use line (line 25): change to `use HasFactory, HasMedia, HasUuids, HasWorkspace, Notifiable;`
3. Remove `SUBSCRIPTION_NAME` constant (line 27)
4. Remove `hasActiveSubscription()` method (lines 120-127)
5. Remove `hasEverSubscribed()` method (lines 132-135)

- [ ] **Step 6: Simplify HasWorkspace trait**

Replace `app/Models/Traits/HasWorkspace.php` — remove all quantity/billing methods, keep only workspace navigation:

```php
<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserWorkspace\Role;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasWorkspace
{
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'user_workspace')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function switchWorkspace(Workspace $workspace): void
    {
        $this->update(['current_workspace_id' => $workspace->id]);
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    public function ownedWorkspacesCount(): int
    {
        return $this->workspaces()->wherePivot('role', Role::Owner->value)->count();
    }
}
```

- [ ] **Step 7: Update AppServiceProvider — Cashier customer model**

In `app/Providers/AppServiceProvider.php`, add after line 77 (`Cashier::useSubscriptionItemModel`):

```php
Cashier::useCustomerModel(Workspace::class);
```

- [ ] **Step 8: Update EnsureSubscribed middleware**

Replace `app/Http/Middleware/App/EnsureSubscribed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('trypost.self_hosted')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $workspace = $user->currentWorkspace;

        if ($workspace && $workspace->hasActiveSubscription()) {
            return $next($request);
        }

        return redirect()->route('app.subscribe');
    }
}
```

- [ ] **Step 9: Update BillingController**

Replace `app/Http/Controllers/App/BillingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function subscribe(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($workspace && $workspace->hasActiveSubscription()) {
            return redirect()->route('app.billing.index');
        }

        return Inertia::render('billing/Subscribe', [
            'plans' => Plan::active()->orderBy('sort')->get(),
            'trialDays' => config('cashier.trial_days'),
        ]);
    }

    public function checkout(Request $request, Plan $plan): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $workspace->createOrGetStripeCustomer([
            'email' => $workspace->stripeEmail(),
            'name' => $workspace->stripeName(),
        ]);

        $subscription = $workspace->newSubscription(Workspace::SUBSCRIPTION_NAME, $priceId)
            ->allowPromotionCodes()
            ->trialDays(config('cashier.trial_days'));

        $checkoutSession = $subscription->checkout([
            'success_url' => route('app.billing.processing') . '?status=success',
            'cancel_url' => route('app.billing.processing') . '?status=cancelled',
        ]);

        $workspace->update(['plan_id' => $plan->id]);

        return Inertia::location($checkoutSession->url);
    }

    public function processing(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $status = $request->query('status', 'processing');

        if ($workspace && $workspace->subscribed(Workspace::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        if (! in_array($status, ['processing', 'success', 'cancelled'])) {
            $status = 'processing';
        }

        return Inertia::render('billing/Processing', [
            'workspaceId' => $workspace?->id,
            'status' => $status,
        ]);
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        $subscription = $workspace->subscription(Workspace::SUBSCRIPTION_NAME);

        return Inertia::render('billing/Index', [
            'hasSubscription' => $workspace->subscribed(Workspace::SUBSCRIPTION_NAME),
            'onTrial' => $subscription?->onTrial() ?? false,
            'trialEndsAt' => $subscription?->trial_ends_at?->toFormattedDateString(),
            'subscription' => $subscription?->only([
                'stripe_status',
                'ends_at',
            ]),
            'plan' => $workspace->plan,
            'plans' => Plan::active()->orderBy('sort')->get(),
            'invoices' => $workspace->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toFormattedDateString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]),
            'defaultPaymentMethod' => $workspace->defaultPaymentMethod()?->card?->only([
                'brand',
                'last4',
                'exp_month',
                'exp_year',
            ]),
        ]);
    }

    public function swap(Request $request, Plan $plan): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        abort_unless($workspace->subscribed(Workspace::SUBSCRIPTION_NAME), 422, 'No active subscription');

        $priceId = $request->input('interval', 'monthly') === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        abort_if(! $priceId, 422, 'Plan price not configured');

        $workspace->subscription(Workspace::SUBSCRIPTION_NAME)->swap($priceId);
        $workspace->update(['plan_id' => $plan->id]);

        return redirect()->route('app.billing.index');
    }

    public function portal(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageBilling', $workspace);

        return $workspace->redirectToBillingPortal(
            route('app.billing.index')
        );
    }
}
```

- [ ] **Step 10: Update OnboardingController**

In `app/Http/Controllers/App/OnboardingController.php`, update `storeConnect()` method (lines 76-106). Change from `$user->newSubscription(...)` to `$workspace->newSubscription(...)`:

```php
public function storeConnect(Request $request): SymfonyResponse|RedirectResponse
{
    $user = $request->user();
    $workspace = $user->currentWorkspace;

    if (config('trypost.self_hosted')) {
        $user->update([
            'setup' => Setup::Completed,
        ]);

        session()->flash('flash.banner', __('auth.flash.welcome'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.calendar');
    }

    $user->update([
        'setup' => Setup::Subscription,
    ]);

    $defaultPlan = Plan::where('slug', PlanSlug::Starter)->first();

    $workspace->createOrGetStripeCustomer([
        'email' => $workspace->stripeEmail(),
        'name' => $workspace->stripeName(),
    ]);

    $subscription = $workspace->newSubscription(Workspace::SUBSCRIPTION_NAME, $defaultPlan->stripe_monthly_price_id)
        ->allowPromotionCodes()
        ->trialDays(config('cashier.trial_days'));

    $checkoutSession = $subscription->checkout([
        'success_url' => route('app.onboarding.complete') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => route('app.onboarding.connect'),
    ]);

    $workspace->update(['plan_id' => $defaultPlan->id]);

    return Inertia::location($checkoutSession->url);
}
```

Add imports at the top:
```php
use App\Enums\Plan\Slug as PlanSlug;
use App\Models\Plan;
use App\Models\Workspace;
```

- [ ] **Step 11: Update StripeEventListener**

Replace `app/Listeners/StripeEventListener.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\User\Setup;
use App\Events\SubscriptionCreated;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    public function handle(WebhookReceived $event): void
    {
        try {
            $type = data_get($event->payload, 'type');
            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (! $stripeCustomerId) {
                return;
            }

            $workspace = Workspace::where('stripe_id', $stripeCustomerId)->first();

            if (! $workspace) {
                return;
            }

            match ($type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($workspace, $event->payload),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($workspace, $event->payload),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($workspace, $event->payload),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage(), [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    protected function handleSubscriptionCreated(Workspace $workspace, array $payload): void
    {
        $owner = $workspace->owner;

        if ($owner && $owner->setup === Setup::Subscription) {
            $owner->update(['setup' => Setup::Completed]);
        }

        SubscriptionCreated::dispatch($workspace);
    }

    protected function handleSubscriptionUpdated(Workspace $workspace, array $payload): void
    {
        //
    }

    protected function handleSubscriptionDeleted(Workspace $workspace, array $payload): void
    {
        //
    }
}
```

- [ ] **Step 12: Update SubscriptionCreated event**

Check and update `app/Events/SubscriptionCreated.php` to accept Workspace instead of User. (If it broadcasts to the user, it should get the owner from the workspace.)

- [ ] **Step 13: Update routes**

In `routes/app.php`, update billing routes:

Replace line 42:
```php
Route::post('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('app.billing.checkout');
```

Inside the subscribed middleware group, add after the billing portal route (line 173):
```php
Route::post('settings/billing/swap/{plan}', [BillingController::class, 'swap'])->name('app.billing.swap');
```

- [ ] **Step 14: Remove plans config from cashier.php**

In `config/cashier.php`, remove the entire `plans` section (lines 129-163) and the `trial_days` section below it. Keep `trial_days` as a standalone config:

Remove the `plans` array. Keep:
```php
'trial_days' => env('CASHIER_TRIAL_DAYS', 8),
```

- [ ] **Step 15: Update WorkspaceFactory**

In `database/factories/WorkspaceFactory.php`, add `plan_id` to definition:

```php
'plan_id' => null,
```

- [ ] **Step 16: Run migrations**

Run: `php artisan migrate`

- [ ] **Step 17: Write tests**

Run: `php artisan make:test WorkspaceBillingTest --pest --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
});

test('workspace has active subscription in self hosted mode', function () {
    expect($this->workspace->hasActiveSubscription())->toBeTrue();
});

test('workspace without subscription is not active in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    expect($this->workspace->hasActiveSubscription())->toBeFalse();
});

test('workspace belongs to plan', function () {
    $plan = Plan::create([
        'slug' => 'starter', 'name' => 'Starter',
        'monthly_price' => 1900, 'yearly_price' => 19000,
        'social_account_limit' => 5, 'member_limit' => 1,
        'brand_limit' => 0, 'ai_images_limit' => 50,
        'ai_videos_limit' => 10, 'data_retention_days' => 30,
        'sort' => 1,
    ]);

    $this->workspace->update(['plan_id' => $plan->id]);

    expect($this->workspace->fresh()->plan->name)->toBe('Starter');
});

test('ensure subscribed middleware passes in self hosted mode', function () {
    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertOk();
});

test('ensure subscribed middleware redirects without subscription in saas mode', function () {
    config(['trypost.self_hosted' => false]);

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertRedirect(route('app.subscribe'));
});

test('billing page is accessible by workspace owner', function () {
    $response = $this->actingAs($this->user)->get(route('app.billing.index'));

    $response->assertOk();
});

test('billing page is not accessible by workspace member', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($member)->get(route('app.billing.index'));

    $response->assertForbidden();
});

test('subscribe page shows plans', function () {
    config(['trypost.self_hosted' => false]);

    $this->seed(\Database\Seeders\PlanSeeder::class);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertOk();
    $plans = $response->original->getData()['page']['props']['plans'];
    expect($plans)->toHaveCount(4);
});

test('stripe email returns workspace owner email', function () {
    expect($this->workspace->stripeEmail())->toBe($this->user->email);
});

test('stripe name returns workspace name', function () {
    expect($this->workspace->stripeName())->toBe($this->workspace->name);
});
```

- [ ] **Step 18: Run all tests**

Run: `php artisan test --compact`
Expected: All passed (fix any failures from the billing migration)

- [ ] **Step 19: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 20: Commit**

```bash
git add -A && git commit -m "feat: move Billable from User to Workspace with plan support"
```

---

### Task 5: Create Pennant Features

**Files:**
- Create: `app/Features/SocialAccountLimit.php`
- Create: `app/Features/MemberLimit.php`
- Create: `app/Features/BrandLimit.php`
- Create: `app/Features/AiImagesLimit.php`
- Create: `app/Features/AiVideosLimit.php`
- Create: `app/Features/DataRetentionDays.php`
- Modify: `app/Providers/AppServiceProvider.php` — configure Pennant
- Create: `tests/Unit/Features/SocialAccountLimitTest.php`
- Create: `tests/Unit/Features/MemberLimitTest.php`
- Create: `tests/Unit/Features/BrandLimitTest.php`
- Create: `tests/Unit/Features/AiImagesLimitTest.php`
- Create: `tests/Unit/Features/AiVideosLimitTest.php`
- Create: `tests/Unit/Features/DataRetentionDaysTest.php`

- [ ] **Step 1: Create feature classes**

Create `app/Features/SocialAccountLimit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class SocialAccountLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->social_account_limit ?? 5;
    }
}
```

Create `app/Features/MemberLimit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class MemberLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->member_limit ?? 1;
    }
}
```

Create `app/Features/BrandLimit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class BrandLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->brand_limit ?? 0;
    }
}
```

Create `app/Features/AiImagesLimit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class AiImagesLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->ai_images_limit ?? 50;
    }
}
```

Create `app/Features/AiVideosLimit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class AiVideosLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->ai_videos_limit ?? 10;
    }
}
```

Create `app/Features/DataRetentionDays.php`:

```php
<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class DataRetentionDays
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->data_retention_days ?? 30;
    }
}
```

- [ ] **Step 2: Configure Pennant in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add at the end of `boot()` method (after the Cashier lines):

```php
Feature::resolveScopeUsing(fn () => auth()->user()?->currentWorkspace);
Feature::useMorphMap();
Feature::discover();
```

Add import at top:
```php
use Laravel\Pennant\Feature;
```

- [ ] **Step 3: Write unit tests**

Create `tests/Unit/Features/SocialAccountLimitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\SocialAccountLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan social account limit', function () {
    $plan = new Plan(['social_account_limit' => 30]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new SocialAccountLimit)->resolve($workspace))->toBe(30);
});

test('falls back to 5 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new SocialAccountLimit)->resolve($workspace))->toBe(5);
});
```

Create `tests/Unit/Features/MemberLimitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\MemberLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan member limit', function () {
    $plan = new Plan(['member_limit' => 15]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new MemberLimit)->resolve($workspace))->toBe(15);
});

test('falls back to 1 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new MemberLimit)->resolve($workspace))->toBe(1);
});
```

Create `tests/Unit/Features/BrandLimitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\BrandLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan brand limit', function () {
    $plan = new Plan(['brand_limit' => 15]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new BrandLimit)->resolve($workspace))->toBe(15);
});

test('falls back to 0 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new BrandLimit)->resolve($workspace))->toBe(0);
});
```

Create `tests/Unit/Features/AiImagesLimitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\AiImagesLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan ai images limit', function () {
    $plan = new Plan(['ai_images_limit' => 500]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new AiImagesLimit)->resolve($workspace))->toBe(500);
});

test('falls back to 50 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new AiImagesLimit)->resolve($workspace))->toBe(50);
});
```

Create `tests/Unit/Features/AiVideosLimitTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\AiVideosLimit;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan ai videos limit', function () {
    $plan = new Plan(['ai_videos_limit' => 100]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new AiVideosLimit)->resolve($workspace))->toBe(100);
});

test('falls back to 10 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new AiVideosLimit)->resolve($workspace))->toBe(10);
});
```

Create `tests/Unit/Features/DataRetentionDaysTest.php`:

```php
<?php

declare(strict_types=1);

use App\Features\DataRetentionDays;
use App\Models\Plan;
use App\Models\Workspace;

test('returns plan data retention days', function () {
    $plan = new Plan(['data_retention_days' => 730]);
    $workspace = new Workspace;
    $workspace->setRelation('plan', $plan);

    expect((new DataRetentionDays)->resolve($workspace))->toBe(730);
});

test('falls back to 30 when no plan', function () {
    $workspace = new Workspace;
    $workspace->setRelation('plan', null);

    expect((new DataRetentionDays)->resolve($workspace))->toBe(30);
});
```

- [ ] **Step 4: Run unit tests**

Run: `php artisan test --compact tests/Unit/Features/`
Expected: 12 passed

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: add Pennant features for plan limit resolution"
```

---

### Task 6: Update BrandPolicy to Use Pennant + Limit Enforcement on Social Accounts and Members

**Files:**
- Modify: `app/Policies/BrandPolicy.php` — use Pennant
- Modify: `app/Policies/WorkspacePolicy.php` — add member limit check
- Create: `tests/Feature/LimitEnforcementTest.php`

- [ ] **Step 1: Update BrandPolicy to use Pennant**

Replace the `create` method in `app/Policies/BrandPolicy.php`:

```php
use App\Features\BrandLimit;
use Laravel\Pennant\Feature;

public function create(User $user, Workspace $workspace): bool
{
    if (! $this->canManage($user, $workspace)) {
        return false;
    }

    if (config('trypost.self_hosted')) {
        return true;
    }

    $limit = Feature::for($workspace)->value(BrandLimit::class);

    return $workspace->brands()->count() < $limit;
}
```

- [ ] **Step 2: Add member limit to WorkspacePolicy**

In `app/Policies/WorkspacePolicy.php`, add method:

```php
use App\Features\MemberLimit;
use Laravel\Pennant\Feature;

public function inviteMember(User $user, Workspace $workspace): bool
{
    if (! $this->hasRole($user, $workspace, [Role::Owner, Role::Admin])) {
        return false;
    }

    if (config('trypost.self_hosted')) {
        return true;
    }

    $limit = Feature::for($workspace)->value(MemberLimit::class);

    return $workspace->members()->count() < $limit;
}
```

- [ ] **Step 3: Write limit enforcement tests**

Run: `php artisan make:test LimitEnforcementTest --pest --no-interaction`

Replace contents with:

```php
<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status as AccountStatus;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->plan = Plan::create([
        'slug' => 'plus', 'name' => 'Plus',
        'monthly_price' => 2900, 'yearly_price' => 29000,
        'social_account_limit' => 10, 'member_limit' => 5,
        'brand_limit' => 5, 'ai_images_limit' => 150,
        'ai_videos_limit' => 30, 'data_retention_days' => 60,
        'sort' => 2,
    ]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
});

test('can create brand within limit', function () {
    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeTrue();
});

test('cannot create brand beyond limit', function () {
    for ($i = 0; $i < 5; $i++) {
        Brand::create(['workspace_id' => $this->workspace->id, 'name' => "Brand {$i}"]);
    }

    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeFalse();
});

test('can invite member within limit', function () {
    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});

test('cannot invite member beyond limit', function () {
    for ($i = 0; $i < 4; $i++) {
        $member = User::factory()->create(['setup' => Setup::Completed]);
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    // Owner (1) + 4 members = 5 = limit
    expect($this->user->can('inviteMember', $this->workspace))->toBeFalse();
});

test('self hosted mode bypasses brand limit', function () {
    config(['trypost.self_hosted' => true]);

    for ($i = 0; $i < 10; $i++) {
        Brand::create(['workspace_id' => $this->workspace->id, 'name' => "Brand {$i}"]);
    }

    expect($this->user->can('create', [Brand::class, $this->workspace]))->toBeTrue();
});

test('self hosted mode bypasses member limit', function () {
    config(['trypost.self_hosted' => true]);

    for ($i = 0; $i < 10; $i++) {
        $member = User::factory()->create(['setup' => Setup::Completed]);
        $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    }

    expect($this->user->can('inviteMember', $this->workspace))->toBeTrue();
});
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact tests/Feature/LimitEnforcementTest.php`
Expected: All passed

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: enforce plan limits via Pennant in policies"
```

---

### Task 7: Run Full Test Suite and Fix Breakages

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`

- [ ] **Step 2: Fix any failures caused by the billing migration**

Common things to check:
- Tests that reference `User::SUBSCRIPTION_NAME` — change to `Workspace::SUBSCRIPTION_NAME`
- Tests that call `$user->subscribed()` — change to `$workspace->subscribed()`
- Tests that reference `$user->subscription()` — change to `$workspace->subscription()`
- Tests that use `$user->stripe_id` — the field no longer exists on User
- Middleware tests that check EnsureSubscribed — now checks workspace
- WorkspaceController tests that call `canCreateWorkspace` — method removed

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run full test suite again**

Run: `php artisan test --compact`
Expected: All passed

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "fix: update existing tests for workspace billing migration"
```
