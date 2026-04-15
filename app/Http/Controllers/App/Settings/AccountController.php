<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\App\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $account = $request->user()->account;

        return Inertia::render('settings/Account', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'billing_email' => $account->billing_email,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
        ]);

        $account = $request->user()->account;

        $account->update([
            'name' => data_get($validated, 'name'),
            'billing_email' => data_get($validated, 'billing_email'),
        ]);

        if ($account->hasStripeId()) {
            $account->updateStripeCustomer([
                'name' => data_get($validated, 'name'),
                'email' => data_get($validated, 'billing_email'),
            ]);
        }

        session()->flash('flash.banner', __('settings.flash.account_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
