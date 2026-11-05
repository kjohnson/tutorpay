<?php

namespace Tests\Feature;

use App\Models\ClosedPayPeriod;
use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClosePayPeriodTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKED_MESSAGE = 'This pay period has been marked as paid, so its hours can’t be changed.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-11-10 12:00:00');
        Setting::store('pay_period_anchor', '2026-01-01');
    }

    public function test_marking_a_period_as_paid_records_hours_and_rate(): void
    {
        Setting::store('hourly_rate', '40');
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:30')->create();

        $this->post(route('pay-period.close', ['date' => '2026-11-05']))
            ->assertRedirect(route('pay-period.show', ['date' => '2026-11-05']))
            ->assertSessionMissing('status');

        $closed = ClosedPayPeriod::sole();
        $this->assertSame('2026-11-05', $closed->start_date->toDateString());
        $this->assertSame('2026-11-18', $closed->end_date->toDateString());
        $this->assertSame('5.00', $closed->total_hours);
        $this->assertSame('40.00', $closed->hourly_rate);
        $this->assertSame(200.0, $closed->totalPay());
    }

    public function test_closing_freezes_days_against_weekly_schedule_changes(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();

        $this->post(route('pay-period.close', ['date' => '2026-11-05']));

        $this->assertSame(14, WorkDay::count());

        $scheduleBlock->update(['end_time' => '19:00:00']);
        ScheduleBlock::factory()->on(CarbonInterface::TUESDAY, '09:00', '12:00')->create();

        $timesheet = $this->get(route('pay-period.show', ['date' => '2026-11-05']))->viewData('timesheet');
        $this->assertSame(4.0, $timesheet->totalHours());

        $scheduleBlock->delete();
        $timesheet = $this->get(route('pay-period.show', ['date' => '2026-11-05']))->viewData('timesheet');
        $this->assertSame(4.0, $timesheet->totalHours());
    }

    public function test_closed_period_page_is_read_only(): void
    {
        Setting::store('hourly_rate', '40');
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $this->post(route('pay-period.close', ['date' => '2026-11-05']));
        Setting::store('hourly_rate', '99');

        $this->get(route('pay-period.show', ['date' => '2026-11-05']))
            ->assertOk()
            ->assertSee('class="flash paid-banner"', false)
            ->assertSee('This pay period was marked as paid on Tuesday, November 10, 2026')
            ->assertSee('for 4 hours ($160.00).')
            ->assertSee('Paid at $40.00/h')
            ->assertSee('$160.00')
            ->assertDontSee('Mark as paid</button>', false)
            ->assertDontSee('+ Add block')
            ->assertDontSee('edit-hint')
            ->assertDontSee('Reset to weekly schedule');
    }

    public function test_open_period_shows_mark_as_paid_button(): void
    {
        $this->get(route('pay-period.show'))
            ->assertDontSee('paid-banner')
            ->assertSee(route('pay-period.close', ['date' => '2026-11-05']))
            ->assertSee('+ Add block');
    }

    public function test_future_periods_cannot_be_marked_as_paid(): void
    {
        $this->get(route('pay-period.show', ['date' => '2026-11-19']))
            ->assertDontSee(route('pay-period.close', ['date' => '2026-11-19']));

        $this->post(route('pay-period.close', ['date' => '2026-11-19']))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('closed_pay_periods', 0);
        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_period_must_be_closed_by_its_start_date(): void
    {
        $this->post(route('pay-period.close', ['date' => '2026-11-06']))->assertNotFound();
    }

    public function test_period_cannot_be_closed_twice(): void
    {
        ClosedPayPeriod::factory()->startingOn('2026-11-05')->create();

        $this->post(route('pay-period.close', ['date' => '2026-11-05']))
            ->assertSessionHas('error', 'This pay period has already been marked as paid.');

        $this->assertDatabaseCount('closed_pay_periods', 1);
    }

    public function test_blocks_cannot_be_added_to_a_closed_period(): void
    {
        ClosedPayPeriod::factory()->startingOn('2026-11-05')->create();

        $this->post(route('work-blocks.store', ['date' => '2026-11-11']), [
            'start_time' => '15:00',
            'end_time' => '16:00',
        ])->assertSessionHas('error', self::LOCKED_MESSAGE);

        $this->assertDatabaseCount('work_blocks', 0);
        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_blocks_in_a_closed_period_cannot_be_edited_or_deleted(): void
    {
        $block = WorkBlock::factory()
            ->for(WorkDay::factory()->state(['date' => '2026-11-11']))
            ->between('15:00', '16:00')
            ->create();
        ClosedPayPeriod::factory()->startingOn('2026-11-05')->create();

        $this->put(route('work-blocks.update', $block), ['start_time' => '15:00', 'end_time' => '18:00'])
            ->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-blocks.destroy', $block))
            ->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-days.reset', ['date' => '2026-11-11']))
            ->assertSessionHas('error', self::LOCKED_MESSAGE);

        $this->assertSame('16:00:00', $block->fresh()->end_time);
        $this->assertDatabaseCount('work_days', 1);
    }

    public function test_scheduled_blocks_in_a_closed_period_cannot_be_changed(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();
        ClosedPayPeriod::factory()->startingOn('2026-11-05')->create();

        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]), [
            'start_time' => '15:00',
            'end_time' => '18:00',
        ])->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-blocks.destroy-scheduled', ['date' => '2026-11-11', 'scheduleBlock' => $scheduleBlock]))
            ->assertSessionHas('error', self::LOCKED_MESSAGE);

        $this->assertDatabaseCount('work_days', 0);
    }

    public function test_days_outside_a_closed_period_can_still_be_changed(): void
    {
        ClosedPayPeriod::factory()->startingOn('2026-10-22')->create();

        $this->post(route('work-blocks.store', ['date' => '2026-11-05']), [
            'start_time' => '15:00',
            'end_time' => '16:00',
        ])->assertSessionMissing('error');

        $this->assertDatabaseCount('work_blocks', 1);
    }

    public function test_start_date_setting_is_disabled_once_a_period_is_paid(): void
    {
        $this->get(route('settings.edit'))
            ->assertSee('name="pay_period_anchor"', false)
            ->assertDontSee('Locked because a pay period has been marked as paid.');

        ClosedPayPeriod::factory()->startingOn('2026-10-22')->create();

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertDontSee('name="pay_period_anchor"', false)
            ->assertSee('disabled', false)
            ->assertSee('Locked because a pay period has been marked as paid.');
    }

    public function test_start_date_cannot_be_changed_once_a_period_is_paid(): void
    {
        ClosedPayPeriod::factory()->startingOn('2026-10-22')->create();

        $this->put(route('settings.update'), ['pay_period_anchor' => '2026-11-19'])
            ->assertSessionHasErrors(['pay_period_anchor' => 'The pay period start date is locked because a pay period has been marked as paid.']);

        $this->assertSame('2026-01-01', Setting::payPeriodAnchor()->toDateString());
    }

    public function test_hourly_rate_can_still_be_saved_once_a_period_is_paid(): void
    {
        ClosedPayPeriod::factory()->startingOn('2026-10-22')->create();

        $this->put(route('settings.update'), ['hourly_rate' => '45'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.edit'));

        $this->assertSame(45.0, Setting::hourlyRate());
        $this->assertSame('2026-01-01', Setting::payPeriodAnchor()->toDateString());
    }
}
