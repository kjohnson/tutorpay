<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-11-10 12:00:00');
        Setting::store('track_hours_from', '2026-11-02');
    }

    public function test_shows_the_current_monday_to_sunday_week(): void
    {
        $this->get(route('calendar.show'))
            ->assertOk()
            ->assertSee('Mon, Nov 9 – Sun, Nov 15, 2026')
            ->assertSee('This week')
            ->assertSeeInOrder(
                array_map(
                    fn (string $day): string => "<span class=\"day-name\">{$day}</span>",
                    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                ),
                false,
            );
    }

    public function test_can_navigate_to_another_week_by_date(): void
    {
        $this->get(route('calendar.show', ['date' => '2026-11-20']))
            ->assertOk()
            ->assertSee('Mon, Nov 16 – Sun, Nov 22, 2026')
            ->assertSee('Go to current week')
            ->assertSee('href="'.route('calendar.show').'">Go to current week</a>', false)
            ->assertDontSee('<span class="badge">This week</span>', false)
            ->assertDontSee('>This week</a>', false);

        $this->get(route('calendar.show'))
            ->assertSee('<span class="badge">This week</span>', false)
            ->assertDontSee('Go to current week');
    }

    public function test_shows_the_weeks_hours_instead_of_unpaid_totals(): void
    {
        Setting::store('hourly_rate', '40');
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $customizedWednesday = WorkDay::factory()->create(['date' => '2026-11-11']);
        WorkBlock::factory()->for($customizedWednesday)->between('10:00', '11:30')->create();

        $this->get(route('calendar.show'))
            ->assertOk()
            ->assertSeeInOrder(['Hours this week', '3.5'])
            ->assertDontSee('Unpaid hours')
            ->assertDontSee('Unpaid amount');

        $this->get(route('calendar.show', ['date' => '2026-11-16']))
            ->assertSeeInOrder(['<span class="stat-label">Hours</span>', '<span class="stat-value">2</span>'], false);
    }

    public function test_calendar_has_no_create_payment_button(): void
    {
        $this->get(route('calendar.show'))
            ->assertOk()
            ->assertDontSee('Create payment')
            ->assertDontSee(route('payments.create'));
    }

    public function test_adding_a_block_copies_the_schedule_onto_the_day(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create(['label' => 'Math']);

        $this->post(route('work-blocks.store', ['date' => '2026-11-11']), [
            'start_time' => '17:00',
            'end_time' => '18:30',
            'label' => 'Extra session',
        ])->assertRedirect(route('calendar.show', ['date' => '2026-11-11']));

        $blocks = WorkDay::sole()->blocks;
        $this->assertCount(2, $blocks);
        $this->assertSame($scheduleBlock->id, $blocks[0]->schedule_block_id);
        $this->assertSame('Math', $blocks[0]->label);
        $this->assertSame(1.5, $blocks[1]->hours);
    }

    public function test_editing_a_scheduled_block_changes_only_that_day(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]), [
            'start_time' => '15:00',
            'end_time' => '17:15',
        ])->assertRedirect(route('calendar.show', ['date' => '2026-11-11']));

        $this->assertSame(2.25, WorkBlock::sole()->hours);
        $this->assertSame('16:00:00', $scheduleBlock->fresh()->end_time);

        $nextWeek = $this->get(route('calendar.show', ['date' => '2026-11-18']))->viewData('week');
        $this->assertSame(1.0, $nextWeek->totalHours());
    }

    public function test_cannot_edit_a_scheduled_block_on_a_different_weekday(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-11-10', 'scheduleBlock' => $scheduleBlock]), [
            'start_time' => '15:00',
            'end_time' => '17:00',
        ])->assertNotFound();

        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_edits_a_block_on_a_customized_day(): void
    {
        $block = WorkBlock::factory()->for(WorkDay::factory()->state(['date' => '2026-11-11']))->between('10:00', '11:00')->create();

        $this->put(route('work-blocks.update', $block), [
            'start_time' => '10:30',
            'end_time' => '12:00',
            'label' => 'Chemistry',
        ])->assertRedirect();

        $block->refresh();
        $this->assertSame('10:30:00', $block->start_time);
        $this->assertSame(1.5, $block->hours);
        $this->assertSame('Chemistry', $block->label);
    }

    public function test_block_end_time_must_be_after_start_time(): void
    {
        $this->from(route('calendar.show'))
            ->post(route('work-blocks.store', ['date' => '2026-11-11']), [
                'form_key' => 'add-2026-11-11',
                'start_time' => '16:00',
                'end_time' => '15:00',
            ])
            ->assertSessionHasErrors(['end_time' => 'The end time must be later than the start time.']);

        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_rejects_invalid_dates(): void
    {
        $this->post('/days/2026-02-30/blocks', ['start_time' => '15:00', 'end_time' => '16:00'])->assertNotFound();
    }

    public function test_deletes_a_scheduled_block_for_one_day(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->delete(route('work-blocks.destroy-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]))
            ->assertRedirect();

        $this->assertModelExists($scheduleBlock);
        $this->assertCount(0, WorkDay::sole()->blocks);
    }

    public function test_deletes_a_block_on_a_customized_day(): void
    {
        $block = WorkBlock::factory()->for(WorkDay::factory()->state(['date' => '2026-11-11']))->create();

        $this->delete(route('work-blocks.destroy', $block))->assertRedirect();

        $this->assertModelMissing($block);
    }

    public function test_resetting_a_day_returns_it_to_the_weekly_schedule(): void
    {
        $workDay = WorkDay::factory()->create(['date' => '2026-11-11']);
        WorkBlock::factory()->for($workDay)->create();

        $this->delete(route('work-days.reset', ['date' => '2026-11-11']))
            ->assertRedirect(route('calendar.show', ['date' => '2026-11-11']));

        $this->assertModelMissing($workDay);
        $this->assertDatabaseCount('work_blocks', 0);
    }

    public function test_reset_note_only_shows_when_a_day_differs_from_the_schedule(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create(['label' => null]);

        $matchingMonday = WorkDay::factory()->create(['date' => '2026-11-09']);
        WorkBlock::factory()->for($matchingMonday)->between('15:00', '17:00')->create(['label' => null]);
        WorkDay::factory()->create(['date' => '2026-11-11']);

        $this->get(route('calendar.show'))
            ->assertOk()
            ->assertDontSee('Differs from weekly schedule')
            ->assertDontSee('action="'.route('work-days.reset', ['date' => '2026-11-09']).'"', false)
            ->assertDontSee('action="'.route('work-days.reset', ['date' => '2026-11-11']).'"', false);

        WorkDay::factory()->create(['date' => '2026-11-16']);
        $longerMonday = WorkDay::factory()->create(['date' => '2026-11-23']);
        WorkBlock::factory()->for($longerMonday)->between('15:00', '18:00')->create(['label' => null]);

        $this->get(route('calendar.show', ['date' => '2026-11-16']))
            ->assertSee('Differs from weekly schedule')
            ->assertSee('onsubmit="return confirm(\'Reset Monday, Nov 16 to the weekly schedule? Changes made to this day will be lost.\')"', false)
            ->assertSee('action="'.route('work-days.reset', ['date' => '2026-11-16']).'"', false);
        $this->get(route('calendar.show', ['date' => '2026-11-23']))
            ->assertSee('action="'.route('work-days.reset', ['date' => '2026-11-23']).'"', false);
    }

    public function test_schedule_does_not_apply_before_the_track_hours_from_date(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        ScheduleBlock::factory()->on(CarbonInterface::SUNDAY, '10:00', '11:00')->create();

        $response = $this->get(route('calendar.show', ['date' => '2026-10-26']))->assertOk();

        $days = collect($response->viewData('week')->days)->keyBy(fn (array $day): string => $day['date']->toDateString());
        $this->assertSame(0.0, $days['2026-10-26']['totalHours']);
        $this->assertFalse($days['2026-10-26']['isTracked']);
        $this->assertSame(0.0, $days['2026-11-01']['totalHours']);

        $response->assertSee('Not tracked')
            ->assertSee('class="day empty untracked"', false)
            ->assertDontSee('+ Add block')
            ->assertDontSee(route('work-blocks.store', ['date' => '2026-10-26']));

        $this->get(route('calendar.show', ['date' => '2026-11-02']))
            ->assertSee(route('work-blocks.store', ['date' => '2026-11-02']));
        $this->assertSame(2.0, $this->get(route('calendar.show', ['date' => '2026-11-02']))->viewData('week')->days[0]['totalHours']);
    }

    public function test_days_before_the_track_hours_from_date_cannot_be_edited(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $message = 'This day is before the date you track hours from (see Settings), so its hours can’t be changed.';

        $this->post(route('work-blocks.store', ['date' => '2026-10-26']), ['start_time' => '15:00', 'end_time' => '16:00'])
            ->assertSessionHas('error', $message);
        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-10-26', 'scheduleBlock' => $scheduleBlock]), ['start_time' => '15:00', 'end_time' => '16:00'])
            ->assertSessionHas('error', $message);

        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_page_shows_edit_and_add_forms_for_unpaid_days(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->get(route('calendar.show'))
            ->assertOk()
            ->assertSee(route('work-blocks.update-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]))
            ->assertSee(route('work-blocks.store', ['date' => '2026-11-11']))
            ->assertSee('+ Add block');
    }

    public function test_paid_days_are_read_only_and_link_to_their_payment(): void
    {
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);
        $block = WorkBlock::factory()
            ->for(WorkDay::factory()->state(['date' => '2026-11-10', 'payment_id' => $payment->id]))
            ->between('15:00', '16:00')
            ->create();

        $this->get(route('calendar.show'))
            ->assertSee('class="day today paid"', false)
            ->assertSee('class="lock"', false)
            ->assertDontSee('tag paid')
            ->assertSee(route('payments.show', $payment))
            ->assertDontSee(route('work-blocks.update', $block))
            ->assertDontSee(route('work-blocks.store', ['date' => '2026-11-10']))
            ->assertSee(route('work-blocks.store', ['date' => '2026-11-11']));
    }
}
