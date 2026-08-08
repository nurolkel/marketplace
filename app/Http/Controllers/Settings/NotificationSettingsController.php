<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class NotificationSettingsController extends Controller
{
    /**
     * Update the user's notification preferences.
     */
    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences updated.')]);

        return to_route('profile.edit');
    }
}
