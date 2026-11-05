<x-layout title="Settings">
    <section class="period-header">
        <div>
            <p class="eyebrow">Settings</p>
            <h1>Pay schedule</h1>
            <p class="muted">
                Pay periods run for two weeks starting on the date below, and are paid the day after
                each period ends. The current period is
                <strong>{{ $currentPeriod->start->format('D, M j') }} – {{ $currentPeriod->end->format('D, M j, Y') }}</strong>,
                paid {{ $currentPeriod->payday()->format('D, M j') }}.
            </p>
        </div>
    </section>

    <section class="card">
        <form method="POST" action="{{ route('settings.update') }}" class="stack-form">
            @csrf
            @method('PUT')
            <label class="field">
                <span>Pay period start date</span>
                @if ($anchorIsLocked)
                    <input type="date" value="{{ $anchor->toDateString() }}" disabled>
                    <small class="muted">Locked because a pay period has been marked as paid. Changing it would shift periods that have already been paid.</small>
                @else
                    <input type="date" name="pay_period_anchor" required value="{{ old('pay_period_anchor', $anchor->toDateString()) }}">
                    <small class="muted">Any day of the week works. Pick the first day of any past or upcoming pay period; every other period is calculated from it.</small>
                @endif
            </label>
            <label class="field">
                <span>Hourly rate (optional)</span>
                <input type="number" name="hourly_rate" step="0.01" min="0" placeholder="e.g. 35.00" value="{{ old('hourly_rate', $hourlyRate) }}">
                <small class="muted">Used to show an estimated gross pay for each period.</small>
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
</x-layout>
