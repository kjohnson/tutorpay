<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
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
     * Save the settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::store('track_hours_from', $request->validated('track_hours_from'));
        Setting::store('hourly_rate', $request->validated('hourly_rate'));

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
