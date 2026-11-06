<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the settings form.
     */
    public function edit(): View
    {
        return view('settings', [
            'trackHoursFrom' => Setting::trackHoursFrom(),
            'hourlyRate' => Setting::hourlyRate(),
        ]);
    }

    /**
     * Save the colour theme for this browser in a cookie. Choosing the
     * system default removes the cookie.
     */
    public function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ]);

        $theme = Theme::from($validated['theme']);

        return redirect()
            ->route('settings.edit')
            ->with('status', 'Appearance saved for this browser.')
            ->withCookie($theme === Theme::System
                ? Cookie::forget(Theme::COOKIE)
                : Cookie::forever(Theme::COOKIE, $theme->value));
    }

    /**
     * Save the settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::store('track_hours_from', $request->validated('track_hours_from'));
        Setting::store('hourly_rate', $request->validated('hourly_rate'));

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
