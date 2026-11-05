@php
    $amount = $payment->totalPay() !== null ? ' ($'.number_format($payment->totalPay(), 2).')' : '';
@endphp

<x-layout title="Payment">
    <div class="flash paid-banner" role="status">
        <strong>Paid.</strong>
        Paid on {{ $payment->payday->format('l, F j, Y') }}
        for {{ \App\Support\Timesheet::formatHours((float) $payment->total_hours) }} hours{{ $amount }}.
        These days are locked and can’t be changed.
    </div>

    <section class="period-header">
        <div>
            <p class="eyebrow">Payment <span class="badge paid">Paid</span></p>
            <h1>{{ $payment->payday->format('D, M j, Y') }}</h1>
            @if ($payment->note)
                <p class="muted">{{ $payment->note }}</p>
            @endif
        </div>
        <div class="period-actions">
            <a class="button secondary" href="{{ route('payments.index') }}">All payments</a>
        </div>
    </section>

    <section class="stats">
        <div class="stat">
            <span class="stat-label">Hours paid</span>
            <span class="stat-value">{{ \App\Support\Timesheet::formatHours((float) $payment->total_hours) }}</span>
        </div>
        @if ($payment->totalPay() !== null)
            <div class="stat">
                <span class="stat-label">Paid at ${{ number_format((float) $payment->hourly_rate, 2) }}/h</span>
                <span class="stat-value">${{ number_format($payment->totalPay(), 2) }}</span>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-header">
            <h2>{{ $payment->days->count() }} {{ \Illuminate\Support\Str::plural('day', $payment->days->count()) }}</h2>
        </div>
        <ul class="days">
            @foreach ($payment->days as $workDay)
                <li @class(['day', 'empty' => $workDay->blocks->isEmpty()])>
                    <div class="day-date">
                        <a class="day-name" href="{{ route('calendar.show', ['date' => $workDay->date->toDateString()]) }}">{{ $workDay->date->format('l') }}</a>
                        <span class="muted">{{ $workDay->date->format('M j, Y') }}</span>
                    </div>
                    <div class="day-detail">
                        @forelse ($workDay->blocks as $block)
                            <div class="entry">
                                <span>{{ $block->time_range }}</span>
                                <x-hours :value="$block->hours" class="muted" />
                                @if ($block->label)
                                    <span class="muted">{{ $block->label }}</span>
                                @endif
                            </div>
                        @empty
                            <div class="entry muted">No hours</div>
                        @endforelse
                    </div>
                    <div class="day-total">
                        <x-hours :value="round($workDay->blocks->sum('hours'), 2)" />
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="period-footer">
        <form method="POST" action="{{ route('payments.destroy', $payment) }}"
              onsubmit="return confirm('Delete this payment? Its days will be unlocked and count as unpaid again.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="button danger">Delete payment</button>
        </form>
    </section>
</x-layout>
