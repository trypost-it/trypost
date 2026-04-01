<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\App\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'post_published' => true,
                'post_failed' => true,
                'account_disconnected' => true,
            ],
        );

        return Inertia::render('settings/Notifications', [
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'post_published' => ['required', 'boolean'],
            'post_failed' => ['required', 'boolean'],
            'account_disconnected' => ['required', 'boolean'],
        ]);

        NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        session()->flash('flash.banner', __('settings.flash.notifications_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
