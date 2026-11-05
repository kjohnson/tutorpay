@php
    $activeForm = old('form_key');
    $weekEnd = $weekStart->addDays(6);
@endphp

<x-layout title="Calendar">
    <section class="period-header">
        <div>
            <p class="eyebrow">
                Week
                @if ($isCurrentWeek)
                    <span class="badge">This week</span>
                @else
                    <a class="button link eyebrow-link" href="{{ route('calendar.show') }}">Go to current week</a>
                @endif
            </p>
            <h1>{{ $weekStart->format('D, M j') }} – {{ $weekEnd->format('D, M j, Y') }}</h1>
        </div>
        <div class="period-actions">
            <nav class="pager" aria-label="Weeks">
                <a class="button secondary" href="{{ route('calendar.show', ['date' => $weekStart->subWeek()->toDateString()]) }}">← Previous</a>
                <a class="button secondary" href="{{ route('calendar.show', ['date' => $weekStart->addWeek()->toDateString()]) }}">Next →</a>
            </nav>
        </div>
    </section>

    <section class="stats">
        <div class="stat">
            <span class="stat-label">{{ $isCurrentWeek ? 'Hours this week' : 'Hours' }}</span>
            <span class="stat-value">{{ \App\Support\Timesheet::formatHours($week->totalHours()) }}</span>
        </div>
    </section>

    <section class="card">
        <ul class="days">
            @foreach ($week->days as $day)
                @php
                    $dateString = $day['date']->toDateString();
                    $isPaid = $day['paymentId'] !== null;
                    $isLocked = $isPaid || ! $day['isTracked'];
                @endphp
                <li @class(['day', 'today' => $day['date']->isSameDay($today), 'empty' => $day['totalHours'] == 0, 'paid' => $isPaid, 'untracked' => ! $isPaid && ! $day['isTracked']])>
                    <div class="day-date">
                        <span class="day-name">{{ $day['date']->format('l') }}</span>
                        <span class="muted">{{ $day['date']->format('M j') }}</span>
                    </div>

                    <div class="day-detail">
                        @forelse ($day['blocks'] as $block)
                            @if ($isLocked)
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
                            <div class="entry muted">{{ $day['isTracked'] || $isPaid ? 'No hours' : 'Not tracked' }}</div>
                        @endforelse

                        @unless ($isLocked)
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

                            @if ($day['differsFromSchedule'])
                                <form method="POST" action="{{ route('work-days.reset', ['date' => $dateString]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <span class="muted small">Differs from weekly schedule ·</span>
                                    <button type="submit" class="link-button">Reset to weekly schedule</button>
                                </form>
                            @endif
                        @endunless
                    </div>

                    <div class="day-total">
                        @if ($isPaid)
                            <a class="lock" href="{{ route('payments.show', $day['paymentId']) }}" title="Paid — view payment" aria-label="Paid — view payment">
                                <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true"><path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7H4a1.5 1.5 0 0 0-1.5 1.5v5A1.5 1.5 0 0 0 4 15h8a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 12 7h-.5V4.5A3.5 3.5 0 0 0 8 1Zm2 6H6V4.5a2 2 0 1 1 4 0V7Z"/></svg>
                            </a>
                        @endif
                        <x-hours :value="$day['totalHours']" />
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

</x-layout>
