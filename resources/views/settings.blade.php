<x-layout title="Settings">
    <section class="period-header">
        <div>
            <p class="eyebrow">Settings</p>
            <h1>Pay settings</h1>
        </div>
    </section>

    <section class="card">
        <form method="POST" action="{{ route('settings.update') }}" class="stack-form">
            @csrf
            @method('PUT')
            <label class="field">
                <span>Track hours from</span>
                <input type="date" name="track_hours_from" required max="{{ now()->toDateString() }}" value="{{ old('track_hours_from', $trackHoursFrom->toDateString()) }}">
                <small class="muted">Days before this never count as unpaid. The weekly schedule repeats forever, so this keeps older days out of your totals.</small>
            </label>
            <label class="field">
                <span>Hourly rate (optional)</span>
                <input type="number" name="hourly_rate" step="0.01" min="0" placeholder="e.g. 35.00" value="{{ old('hourly_rate', $hourlyRate) }}">
                <small class="muted">Used for unpaid amounts and as the default rate for new payments.</small>
            </label>
            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div>
                <button type="submit" class="button">Save settings</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Change password</h2>
        <p class="muted small">Logged in as {{ auth()->user()->email }}</p>
        <form method="POST" action="{{ route('settings.password.update') }}" class="stack-form">
            @csrf
            @method('PUT')
            <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden>
            <label class="field">
                <span>Current password</span>
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label class="field">
                <span>New password</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <small class="muted">At least 8 characters.</small>
            </label>
            <label class="field">
                <span>Confirm new password</span>
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>
            @if ($errors->updatePassword->any())
                <ul class="errors">
                    @foreach ($errors->updatePassword->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div>
                <button type="submit" class="button">Change password</button>
            </div>
        </form>
    </section>

    <section class="period-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button secondary">Log out</button>
        </form>
    </section>
</x-layout>
