<x-layout title="Create payment">
    <section class="period-header">
        <div>
            <p class="eyebrow">New payment</p>
            <h1>Create payment</h1>
            <p class="muted">Select the days this payment covers. Paid days are locked and can’t be changed.</p>
        </div>
    </section>

    <section class="card">
        <form method="GET" action="{{ route('payments.create') }}" class="row-form">
            <label class="field">
                <span>Payment date</span>
                <input type="date" name="payday" required value="{{ $payday->toDateString() }}" onchange="this.form.submit()">
            </label>
            <noscript><button type="submit" class="button secondary">Show days</button></noscript>
            <p class="muted small">Days up to and including this date can be paid.</p>
        </form>
    </section>

    <form method="POST" action="{{ route('payments.store') }}" id="payment-form">
        @csrf
        <input type="hidden" name="payday" value="{{ $payday->toDateString() }}">

        @if ($errors->any())
            <div class="flash error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($existingPayment !== null)
            <div class="flash error" role="alert">
                A payment has already been made on this date.
                <a href="{{ route('payments.show', $existingPayment) }}">View it</a> or choose another date.
            </div>
        @elseif ($payable->days === [])
            <section class="card">
                <p class="muted">There are no unpaid days with hours up to this date.</p>
            </section>
        @else
            @php
                $selected = old('days', $payable->dateStrings());
            @endphp
            @foreach ($payable->byWeek() as $monday => $days)
                <section class="card">
                    <div class="card-header">
                        <h2>Week of {{ \Carbon\CarbonImmutable::parse($monday)->format('M j') }}</h2>
                    </div>
                    <ul class="days">
                        @foreach ($days as $day)
                            <li class="day selectable">
                                <label class="day-date day-check">
                                    <input type="checkbox" name="days[]" value="{{ $day['date']->toDateString() }}"
                                           data-hours="{{ $day['totalHours'] }}" @checked(in_array($day['date']->toDateString(), $selected, true))>
                                    <span>
                                        <span class="day-name">{{ $day['date']->format('l') }}</span>
                                        <span class="muted">{{ $day['date']->format('M j') }}</span>
                                    </span>
                                </label>
                                <div class="day-detail">
                                    @foreach ($day['blocks'] as $block)
                                        <div class="entry">
                                            <span>{{ $block->time_range }}</span>
                                            @if ($block->label)
                                                <span class="muted">{{ $block->label }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <div class="day-total">
                                    <x-hours :value="$day['totalHours']" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <section class="card">
                <div class="row-form">
                    <label class="field">
                        <span>Hourly rate</span>
                        <input type="number" name="hourly_rate" id="hourly-rate" step="0.01" min="0" placeholder="Optional" value="{{ old('hourly_rate', $hourlyRate) }}">
                    </label>
                    <label class="field grow">
                        <span>Note</span>
                        <input type="text" name="note" maxlength="255" placeholder="Optional" value="{{ old('note') }}">
                    </label>
                </div>
            </section>

            <section class="stats">
                <div class="stat">
                    <span class="stat-label">Selected hours</span>
                    <span class="stat-value" id="selected-hours">{{ \App\Support\Timesheet::formatHours($payable->totalHours()) }}</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Amount</span>
                    <span class="stat-value" id="selected-amount">—</span>
                </div>
            </section>

            <section class="period-footer">
                <button type="submit" class="button"
                        onclick="return confirm('Create this payment? The selected days will be locked and can’t be changed.')">
                    Create payment
                </button>
            </section>
        @endif
    </form>

    <script>
        (function () {
            const form = document.getElementById('payment-form');
            const hoursOut = document.getElementById('selected-hours');
            const amountOut = document.getElementById('selected-amount');
            const rateInput = document.getElementById('hourly-rate');
            if (!form || !hoursOut) {
                return;
            }
            const format = (value) => String(Math.round(value * 100) / 100);
            const update = () => {
                let hours = 0;
                form.querySelectorAll('input[name="days[]"]:checked').forEach((box) => {
                    hours += parseFloat(box.dataset.hours);
                });
                hoursOut.textContent = format(hours);
                const rate = parseFloat(rateInput.value);
                amountOut.textContent = Number.isNaN(rate)
                    ? '—'
                    : '$' + (hours * rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
            form.addEventListener('input', update);
            update();
        })();
    </script>
</x-layout>
