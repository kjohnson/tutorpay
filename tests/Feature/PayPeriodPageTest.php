<?php

namespace Tests\Feature;

use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayPeriodPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-11-10 12:00:00');
        Setting::store('pay_period_anchor', '2026-01-01');
    }

    public function test_shows_the_current_pay_period_and_payday(): void
    {
        $this->get(route('pay-period.show'))
            ->assertOk()
            ->assertSee('Thu, Nov 5 – Wed, Nov 18, 2026')
            ->assertSee('Thursday, November 19, 2026');
    }

    public function test_can_navigate_to_another_period_by_date(): void
    {
        $this->get(route('pay-period.show', ['date' => '2026-11-25']))
            ->assertOk()
            ->assertSee('Thu, Nov 19 – Wed, Dec 2, 2026');
    }

    public function test_totals_use_the_weekly_schedule_and_customized_days(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:30')->create();
        ScheduleBlock::factory()->on(CarbonInterface::THURSDAY, '16:00', '17:00')->create();

        $customizedMonday = WorkDay::factory()->create(['date' => '2026-11-09']);
        WorkBlock::factory()->for($customizedMonday)->between('15:00', '16:00')->create();
        WorkDay::factory()->create(['date' => '2026-11-12']);
        $outsidePeriod = WorkDay::factory()->create(['date' => '2026-11-19']);
        WorkBlock::factory()->for($outsidePeriod)->between('09:00', '14:00')->create();

        $response = $this->get(route('pay-period.show'))->assertOk();
        $timesheet = $response->viewData('timesheet');

        $response
            ->assertSeeInOrder(['<h2>Week 1</h2>', 'week-total">2&nbsp;h', '<h2>Week 2</h2>', 'week-total">2.5&nbsp;h'], false)
            ->assertDontSee('stat-label">Week', false);
        $this->assertSame(2.0, $timesheet->weekHours(1));
        $this->assertSame(2.5, $timesheet->weekHours(2));
        $this->assertSame(4.5, $timesheet->totalHours());
    }

    public function test_adding_a_block_copies_the_schedule_onto_the_day(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create(['label' => 'Math']);

        $this->post(route('work-blocks.store', ['date' => '2026-11-11']), [
            'start_time' => '17:00',
            'end_time' => '18:30',
            'label' => 'Extra session',
        ])->assertRedirect(route('pay-period.show', ['date' => '2026-11-11']));

        $blocks = WorkDay::sole()->blocks;
        $this->assertSame('2026-11-11', WorkDay::sole()->date->toDateString());
        $this->assertCount(2, $blocks);
        $this->assertSame($scheduleBlock->id, $blocks[0]->schedule_block_id);
        $this->assertSame('Math', $blocks[0]->label);
        $this->assertSame('Extra session', $blocks[1]->label);
        $this->assertSame(1.5, $blocks[1]->hours);
    }

    public function test_editing_a_scheduled_block_changes_only_that_day(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]), [
            'start_time' => '15:00',
            'end_time' => '17:15',
        ])->assertRedirect(route('pay-period.show', ['date' => '2026-11-11']));

        $this->assertSame(2.25, WorkBlock::sole()->hours);
        $this->assertSame('16:00:00', $scheduleBlock->fresh()->end_time);

        $timesheet = $this->get(route('pay-period.show'))->viewData('timesheet');
        $this->assertSame(2.25, $timesheet->days[6]['totalHours']);
        $this->assertSame(1.0, $timesheet->days[13]['totalHours']);
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
        $block = WorkBlock::factory()->between('10:00', '11:00')->create();

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
        $this->from(route('pay-period.show'))
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
        $block = WorkBlock::factory()->create();

        $this->delete(route('work-blocks.destroy', $block))->assertRedirect();

        $this->assertModelMissing($block);
    }

    public function test_resetting_a_day_returns_it_to_the_weekly_schedule(): void
    {
        $workDay = WorkDay::factory()->create(['date' => '2026-11-11']);
        WorkBlock::factory()->for($workDay)->create();

        $this->delete(route('work-days.reset', ['date' => '2026-11-11']))
            ->assertRedirect(route('pay-period.show', ['date' => '2026-11-11']));

        $this->assertModelMissing($workDay);
        $this->assertDatabaseCount('work_blocks', 0);
    }

    public function test_page_shows_edit_and_add_forms(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();

        $this->get(route('pay-period.show'))
            ->assertOk()
            ->assertSee(route('work-blocks.update-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]))
            ->assertSee(route('work-blocks.store', ['date' => '2026-11-11']))
            ->assertSee('+ Add block')
            ->assertDontSee('Add / remove hours');
    }

    public function test_shows_estimated_pay_when_rate_is_set(): void
    {
        Setting::store('hourly_rate', '40');
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();

        $this->get(route('pay-period.show'))->assertSee('$160.00');
    }
}
