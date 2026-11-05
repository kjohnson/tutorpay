<x-layout title="Payments">
    <section class="period-header">
        <div>
            <p class="eyebrow">Payments</p>
            <h1>Payment history</h1>
        </div>
        <div class="period-actions">
            <a class="button" href="{{ route('payments.create') }}">Create payment</a>
        </div>
    </section>

    <section class="stats">
        <div class="stat">
            <span class="stat-label">Unpaid hours</span>
            <span class="stat-value">{{ \App\Support\Timesheet::formatHours($unpaidHours) }}</span>
        </div>
        @if ($hourlyRate !== null)
            <div class="stat">
                <span class="stat-label">Unpaid amount</span>
                <span class="stat-value">${{ number_format($unpaidHours * $hourlyRate, 2) }}</span>
            </div>
        @endif
    </section>

    <section class="card">
        @if ($payments->isEmpty())
            <p class="muted">No payments yet.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="numeric">Days</th>
                            <th class="numeric">Hours</th>
                            <th class="numeric">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td><a href="{{ route('payments.show', $payment) }}">{{ $payment->payday->format('D, M j, Y') }}</a></td>
                                <td class="numeric">{{ $payment->days_count }}</td>
                                <td class="numeric">{{ \App\Support\Timesheet::formatHours((float) $payment->total_hours) }}</td>
                                <td class="numeric">{{ $payment->totalPay() === null ? '—' : '$'.number_format($payment->totalPay(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layout>
