<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Support\PayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the settings form.
     */
    public function edit(): View
    {
        $anchor = Setting::payPeriodAnchor();

        return view('settings', [
            'anchor' => $anchor,
            'currentPeriod' => PayPeriod::containing(CarbonImmutable::today(), $anchor),
            'hourlyRate' => Setting::hourlyRate(),
        ]);
    }

    /**
     * Save the settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::store('pay_period_anchor', $request->validated('pay_period_anchor'));
        Setting::store('hourly_rate', $request->validated('hourly_rate'));

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
