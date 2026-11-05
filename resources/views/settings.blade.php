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
                <input type="date" name="pay_period_anchor" required value="{{ old('pay_period_anchor', $anchor->toDateString()) }}">
                <small class="muted">Any day of the week works. Pick the first day of any past or upcoming pay period; every other period is calculated from it.</small>
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
</x-layout>
