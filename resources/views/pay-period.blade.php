@php
    $period = $timesheet->period;
    $activeForm = old('form_key');
    $isClosed = $closedPeriod !== null;
    $paidAmount = $isClosed && $closedPeriod->totalPay() !== null
        ? ' ($'.number_format($closedPeriod->totalPay(), 2).')'
        : '';
@endphp

<x-layout title="Pay period">
    @if ($isClosed)
        <div class="flash paid-banner" role="status">
            <strong>Paid.</strong>
            This pay period was marked as paid on {{ $closedPeriod->closed_at->format('l, F j, Y') }}
            for {{ \App\Support\Timesheet::formatHours((float) $closedPeriod->total_hours) }} hours{{ $paidAmount }}.
            Its hours are locked and can’t be changed.
        </div>
    @endif

    <section class="period-header">
        <div>
            <p class="eyebrow">
                Pay period
                @if ($isClosed)
                    <span class="badge paid">Paid</span>
                @elseif ($isCurrentPeriod)
                    <span class="badge">Current</span>
                @endif
            </p>
            <h1>{{ $period->start->format('D, M j') }} – {{ $period->end->format('D, M j, Y') }}</h1>
            @unless ($isClosed)
                <p class="muted">Paid on <strong>{{ $period->payday()->format('l, F j, Y') }}</strong></p>
            @endunless
        </div>
        <div class="period-actions">
            <nav class="pager" aria-label="Pay periods">
                <a class="button secondary" href="{{ route('pay-period.show', ['date' => $period->previous()->start->toDateString()]) }}">← Previous</a>
                @unless ($isCurrentPeriod)
                    <a class="button secondary" href="{{ route('pay-period.show') }}">Current</a>
                @endunless
                <a class="button secondary" href="{{ route('pay-period.show', ['date' => $period->next()->start->toDateString()]) }}">Next →</a>
            </nav>
        </div>
    </section>

    <section class="stats">
        <div class="stat">
            <span class="stat-label">{{ $isClosed ? 'Hours paid' : 'Total hours' }}</span>
            <span class="stat-value">{{ \App\Support\Timesheet::formatHours($isClosed ? (float) $closedPeriod->total_hours : $timesheet->totalHours()) }}</span>
        </div>
        @if ($isClosed && $closedPeriod->totalPay() !== null)
            <div class="stat">
                <span class="stat-label">Paid at ${{ number_format((float) $closedPeriod->hourly_rate, 2) }}/h</span>
                <span class="stat-value">${{ number_format($closedPeriod->totalPay(), 2) }}</span>
            </div>
        @elseif (! $isClosed && $hourlyRate !== null)
            <div class="stat">
                <span class="stat-label">Estimated pay</span>
                <span class="stat-value">${{ number_format($timesheet->totalHours() * $hourlyRate, 2) }}</span>
            </div>
        @endif
    </section>

    @foreach (array_chunk($timesheet->days, 7) as $weekIndex => $week)
        <section class="card">
            <div class="card-header">
                <h2>Week {{ $weekIndex + 1 }}</h2>
                <x-hours :value="$timesheet->weekHours($weekIndex + 1)" class="week-total" />
            </div>
            <ul class="days">
                @foreach ($week as $day)
                    @php
                        $dateString = $day['date']->toDateString();
                    @endphp
                    <li @class(['day', 'today' => $day['date']->isSameDay($today), 'empty' => $day['totalHours'] == 0])>
                        <div class="day-date">
                            <span class="day-name">{{ $day['date']->format('l') }}</span>
                            <span class="muted">{{ $day['date']->format('M j') }}</span>
                        </div>

                        <div class="day-detail">
                            @forelse ($day['blocks'] as $block)
                                @if ($isClosed)
                                    <div class="entry">
                                        <span>{{ $block->time_range }}</span>
                                        <x-hours :value="$block->hours" class="muted" />
                                        @if ($block->label)
                                            <span class="muted">{{ $block->label }}</span>
                                        @endif
                                    </div>
                                @else
                                    @php
                                        $formKey = $day['isCustomized'] ? "edit-block-{$block->id}" : "edit-scheduled-{$dateString}-{$block->id}";
                                    @endphp
                                    <details class="block" @if ($activeForm === $formKey) open @endif>
                                        <summary>
                                            <span>{{ $block->time_range }}</span>
                                            <x-hours :value="$block->hours" class="muted" />
                                            @if ($block->label)
                                                <span class="muted">{{ $block->label }}</span>
                                            @endif
                                            <span class="edit-hint">Edit</span>
                                        </summary>
                                        <div class="block-editor">
                                            <x-block-form
                                                :action="$day['isCustomized']
                                                    ? route('work-blocks.update', $block)
                                                    : route('work-blocks.update-scheduled', ['date' => $dateString, 'scheduleBlock' => $block])"
                                                method="PUT"
                                                :form-key="$formKey"
                                                :block="$block"
                                            />
                                            <form method="POST" class="inline" action="{{ $day['isCustomized']
                                                ? route('work-blocks.destroy', $block)
                                                : route('work-blocks.destroy-scheduled', ['date' => $dateString, 'scheduleBlock' => $block]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="link-button">Delete this block{{ $day['isCustomized'] ? '' : ' for this day' }}</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif
                            @empty
                                <div class="entry muted">No hours</div>
                            @endforelse

                            @unless ($isClosed)
                                <details class="add-block" @if ($activeForm === "add-{$dateString}") open @endif>
                                    <summary>+ Add block</summary>
                                    <div class="block-editor">
                                        <x-block-form
                                            :action="route('work-blocks.store', ['date' => $dateString])"
                                            :form-key="'add-'.$dateString"
                                            submit-label="Add"
                                        />
                                    </div>
                                </details>

                                @if ($day['isCustomized'])
                                    <form method="POST" action="{{ route('work-days.reset', ['date' => $dateString]) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <span class="muted small">Changed from weekly schedule ·</span>
                                        <button type="submit" class="link-button">Reset to weekly schedule</button>
                                    </form>
                                @endif
                            @endunless
                        </div>

                        <div class="day-total">
                            <x-hours :value="$day['totalHours']" />
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    @if (! $isClosed && $hasStarted)
        <section class="period-footer">
            <form method="POST" action="{{ route('pay-period.close', ['date' => $period->start->toDateString()]) }}"
                  onsubmit="return confirm('Mark this pay period as paid? Its hours will be locked and can’t be changed.')">
                @csrf
                <button type="submit" class="button">Mark as paid</button>
            </form>
        </section>
    @endif
</x-layout>
