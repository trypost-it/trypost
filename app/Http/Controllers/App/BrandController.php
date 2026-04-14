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
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('viewAny', [Brand::class, $workspace]);

        $brands = $workspace->brands()
            ->withCount('socialAccounts')
            ->latest()
            ->paginate(config('app.pagination.default'));

        return Inertia::render('brands/Index', [
            'brands' => Inertia::scroll(fn () => $brands),
            'canCreate' => $request->user()->can('create', [Brand::class, $workspace]),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('create', [Brand::class, $workspace]);

        $workspace->brands()->create([
            'name' => data_get($request->validated(), 'name'),
        ]);

        session()->flash('flash.banner', __('Brand created successfully.'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.brands.index');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('update', $brand);

        if ($brand->workspace_id !== $workspace->id) {
            abort(403);
        }

        $brand->update([
            'name' => data_get($request->validated(), 'name'),
        ]);

        session()->flash('flash.banner', __('Brand updated successfully.'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.brands.index');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $workspace = request()->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('delete', $brand);

        if ($brand->workspace_id !== $workspace->id) {
            abort(403);
        }

        $brand->delete();

        session()->flash('flash.banner', __('Brand deleted successfully.'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.brands.index');
    }
}
