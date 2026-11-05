<x-layout title="Weekly schedule">
    <section class="period-header">
        <div>
            <p class="eyebrow">Weekly schedule</p>
            <h1>{{ \App\Support\Timesheet::formatHours($weeklyHours) }} hours per week</h1>
            <p class="muted">This schedule repeats every week. Use the pay period page to add or remove hours on a specific day.</p>
        </div>
    </section>

    <section class="card">
        <h2>Add time</h2>
        <form method="POST" action="{{ route('schedule.store') }}" class="row-form">
            @csrf
            <label class="field">
                <span>Day</span>
                <select name="day_of_week" required>
                    @foreach (\App\Models\ScheduleBlock::DAYS as $number => $name)
                        <option value="{{ $number }}" @selected((string) old('day_of_week') === (string) $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Start</span>
                <input type="time" name="start_time" required value="{{ old('start_time', '15:00') }}">
            </label>
            <label class="field">
                <span>End</span>
                <input type="time" name="end_time" required value="{{ old('end_time', '17:00') }}">
            </label>
            <label class="field grow">
                <span>Label</span>
                <input type="text" name="label" maxlength="255" placeholder="Optional, e.g. student or subject" value="{{ old('label') }}">
            </label>
            <button type="submit" class="button">Add</button>
        </form>
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>Week</h2>
        <ul class="days">
            @foreach (\App\Models\ScheduleBlock::DAYS as $number => $name)
                @php($blocks = $blocksByDay->get($number, collect()))
                <li @class(['day', 'empty' => $blocks->isEmpty()])>
                    <div class="day-date">
                        <span class="day-name">{{ $name }}</span>
                    </div>
                    <div class="day-detail">
                        @forelse ($blocks as $block)
                            <div class="entry">
                                <span>{{ \App\Models\ScheduleBlock::formatTime($block->start_time) }} – {{ \App\Models\ScheduleBlock::formatTime($block->end_time) }}</span>
                                <x-hours :value="$block->hours" class="muted" />
                                @if ($block->label)
                                    <span class="muted">{{ $block->label }}</span>
                                @endif
                                <form method="POST" action="{{ route('schedule.destroy', $block) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button" aria-label="Remove this time">Remove</button>
                                </form>
                            </div>
                        @empty
                            <div class="entry muted">Off</div>
                        @endforelse
                    </div>
                    <div class="day-total">
                        <x-hours :value="round($blocks->sum('hours'), 2)" />
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</x-layout>
