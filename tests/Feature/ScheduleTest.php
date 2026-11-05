<?php

namespace Tests\Feature;

use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_shows_the_weekly_schedule_with_total_hours(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::TUESDAY, '15:00', '16:30')->create(['label' => 'Algebra']);
        ScheduleBlock::factory()->on(CarbonInterface::THURSDAY, '15:00', '17:00')->create();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('3.5 hours per week')
            ->assertSee('Algebra')
            ->assertSee('3:00 PM – 4:30 PM');
    }

    public function test_lists_the_week_monday_through_sunday(): void
    {
        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSeeInOrder(
                array_map(
                    fn (string $day): string => "<span class=\"day-name\">{$day}</span>",
                    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                ),
                false,
            );
    }

    public function test_adds_a_block_to_the_schedule(): void
    {
        $this->post(route('schedule.store'), [
            'day_of_week' => CarbonInterface::MONDAY,
            'start_time' => '15:00',
            'end_time' => '16:45',
            'label' => 'Reading',
        ])->assertRedirect(route('schedule.index'));

        $block = ScheduleBlock::sole();
        $this->assertSame(CarbonInterface::MONDAY, $block->day_of_week);
        $this->assertSame(1.75, $block->hours);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->post(route('schedule.store'), [
            'day_of_week' => CarbonInterface::MONDAY,
            'start_time' => '16:00',
            'end_time' => '15:00',
        ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('schedule_blocks', 0);
    }

    public function test_removes_a_block_from_the_schedule(): void
    {
        $block = ScheduleBlock::factory()->create();

        $this->delete(route('schedule.destroy', $block))->assertRedirect(route('schedule.index'));

        $this->assertModelMissing($block);
    }

    public function test_updates_settings(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->put(route('settings.update'), [
            'track_hours_from' => '2026-11-01',
            'hourly_rate' => '35.50',
        ])->assertRedirect(route('settings.edit'));

        $this->assertSame('2026-11-01', Setting::trackHoursFrom()->toDateString());
        $this->assertSame(35.5, Setting::hourlyRate());
        $this->get(route('settings.edit'))->assertOk()->assertDontSee('payday');
    }

    public function test_track_hours_from_cannot_be_in_the_future(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->put(route('settings.update'), [
            'track_hours_from' => '2026-11-11',
        ])->assertSessionHasErrors(['track_hours_from' => 'The date to track hours from can’t be in the future.']);
    }

    public function test_changing_the_schedule_freezes_past_unpaid_days(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        Setting::store('track_hours_from', '2026-10-26');
        $monday = ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $unpaidBefore = Timesheet::unpaidThrough(CarbonImmutable::today())->totalHours();

        $this->post(route('schedule.store'), [
            'day_of_week' => CarbonInterface::MONDAY,
            'start_time' => '18:00',
            'end_time' => '19:00',
        ])->assertRedirect(route('schedule.index'));

        $this->delete(route('schedule.destroy', $monday))->assertRedirect(route('schedule.index'));

        $this->assertSame(6.0, $unpaidBefore);
        $this->assertSame(6.0, Timesheet::unpaidThrough(CarbonImmutable::today())->totalHours());
        $this->assertSame(
            ['2026-10-26', '2026-11-02', '2026-11-09'],
            WorkDay::query()->orderBy('date')->pluck('date')->map(fn (Carbon $date): string => $date->toDateString())->all(),
        );

        $futureMonday = Timesheet::forRange(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-16'));
        $this->assertSame(1.0, $futureMonday->totalHours());
    }

    public function test_changing_the_schedule_does_not_freeze_today_or_other_weekdays(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');
        Setting::store('track_hours_from', '2026-11-01');

        $this->post(route('schedule.store'), [
            'day_of_week' => CarbonInterface::MONDAY,
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);

        $this->assertSame(['2026-11-02'], WorkDay::query()->pluck('date')->map(fn (Carbon $date): string => $date->toDateString())->all());
        $this->assertSame(1.0, Timesheet::forRange(CarbonImmutable::today(), CarbonImmutable::today())->totalHours());
    }
}
