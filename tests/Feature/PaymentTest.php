<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const LOCKED_MESSAGE = 'This day has been paid, so its hours can’t be changed.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Carbon::setTestNow('2026-11-10 12:00:00');
        Setting::store('track_hours_from', '2026-11-02');
    }

    public function test_create_form_lists_unpaid_days_with_hours_through_the_payday(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        ScheduleBlock::factory()->on(CarbonInterface::THURSDAY, '16:00', '17:00')->create();
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);
        WorkDay::factory()->create(['date' => '2026-11-02', 'payment_id' => $payment->id]);

        $response = $this->get(route('payments.create', ['payday' => '2026-11-19']))->assertOk();

        $this->assertSame(
            ['2026-11-05', '2026-11-09', '2026-11-12', '2026-11-16', '2026-11-19'],
            $response->viewData('payable')->dateStrings(),
        );
        $response->assertSee('name="payday" required value="2026-11-19"', false)
            ->assertSee('value="2026-11-19"', false)
            ->assertDontSee('value="2026-11-02"', false)
            ->assertDontSee('value="2026-11-26"', false);
    }

    public function test_create_form_defaults_to_today(): void
    {
        $this->get(route('payments.create'))
            ->assertOk()
            ->assertSee('name="payday" required value="2026-11-10"', false);
    }

    public function test_create_form_works_for_any_date(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();

        $response = $this->get(route('payments.create', ['payday' => '2026-11-10']))
            ->assertOk();

        $this->assertSame(['2026-11-02', '2026-11-09'], $response->viewData('payable')->dateStrings());
    }

    public function test_create_form_warns_when_a_payment_exists_on_that_date(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);

        $this->get(route('payments.create', ['payday' => '2026-11-05']))
            ->assertOk()
            ->assertSee('A payment has already been made on this date.')
            ->assertSee(route('payments.show', $payment))
            ->assertDontSee('type="checkbox"', false);
    }

    public function test_creating_a_payment_locks_the_selected_days(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:30')->create();
        ScheduleBlock::factory()->on(CarbonInterface::THURSDAY, '16:00', '17:00')->create();

        $response = $this->post(route('payments.store'), [
            'payday' => '2026-11-05',
            'days' => ['2026-11-02', '2026-11-05'],
            'hourly_rate' => '40',
            'note' => 'Venmo',
        ]);

        $payment = Payment::sole();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame('2026-11-05', $payment->payday->toDateString());
        $this->assertSame('3.50', $payment->total_hours);
        $this->assertSame(140.0, $payment->totalPay());
        $this->assertSame('Venmo', $payment->note);
        $this->assertSame(['2026-11-02', '2026-11-05'], $payment->days->map(fn (WorkDay $day): string => $day->date->toDateString())->all());

        $this->assertTrue(WorkDay::isPaid(Carbon::parse('2026-11-02')));
        $this->assertFalse(WorkDay::isPaid(Carbon::parse('2026-11-09')));
    }

    public function test_paid_days_keep_their_hours_when_the_schedule_changes(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();

        $this->post(route('payments.store'), ['payday' => '2026-11-05', 'days' => ['2026-11-02']]);

        $scheduleBlock->update(['end_time' => '19:00:00']);

        $week = $this->get(route('calendar.show', ['date' => '2026-11-02']))->viewData('week');
        $this->assertSame(2.0, $week->totalHours());
    }

    public function test_only_one_payment_per_date(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        Payment::factory()->create(['payday' => '2026-11-05']);

        $this->post(route('payments.store'), ['payday' => '2026-11-05', 'days' => ['2026-11-02']])
            ->assertSessionHasErrors(['payday' => 'A payment has already been made on this date.']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_can_be_made_on_a_day_other_than_thursday(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();

        $this->post(route('payments.store'), ['payday' => '2026-11-10', 'days' => ['2026-11-02', '2026-11-09']])
            ->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('2026-11-10', $payment->payday->toDateString());
        $this->assertSame('4.00', $payment->total_hours);
    }

    public function test_rejects_days_that_cannot_be_paid(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $earlier = Payment::factory()->create(['payday' => '2026-10-22']);
        WorkDay::factory()->create(['date' => '2026-11-03', 'payment_id' => $earlier->id]);

        $invalidSelections = [
            'after the payday' => ['2026-11-09'],
            'already paid' => ['2026-11-03'],
            'no hours' => ['2026-11-04'],
            'before tracking started' => ['2026-10-26'],
        ];

        foreach ($invalidSelections as $days) {
            $this->post(route('payments.store'), ['payday' => '2026-11-05', 'days' => $days])
                ->assertSessionHasErrors('days');
        }

        $this->post(route('payments.store'), ['payday' => '2026-11-05', 'days' => []])
            ->assertSessionHasErrors(['days' => 'Select at least one day to include in this payment.']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_days_reject_every_edit(): void
    {
        $scheduleBlock = ScheduleBlock::factory()->on(CarbonInterface::WEDNESDAY, '15:00', '16:00')->create();
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);
        $workDay = WorkDay::factory()->create(['date' => '2026-11-04', 'payment_id' => $payment->id]);
        $block = WorkBlock::factory()->for($workDay)->between('15:00', '16:00')->create(['schedule_block_id' => $scheduleBlock->id]);
        $times = ['start_time' => '15:00', 'end_time' => '18:00'];

        $this->post(route('work-blocks.store', ['date' => '2026-11-04']), $times)->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->put(route('work-blocks.update', $block), $times)->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->put(route('work-blocks.update-scheduled', ['date' => '2026-11-04', 'scheduleBlock' => $scheduleBlock]), $times)->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-blocks.destroy', $block))->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-blocks.destroy-scheduled', ['date' => '2026-11-04', 'scheduleBlock' => $scheduleBlock]))->assertSessionHas('error', self::LOCKED_MESSAGE);
        $this->delete(route('work-days.reset', ['date' => '2026-11-04']))->assertSessionHas('error', self::LOCKED_MESSAGE);

        $this->assertSame('16:00:00', $block->fresh()->end_time);
        $this->assertDatabaseCount('work_blocks', 1);
        $this->assertModelExists($workDay);
    }

    public function test_unpaid_days_next_to_paid_days_stay_editable(): void
    {
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);
        WorkDay::factory()->create(['date' => '2026-11-04', 'payment_id' => $payment->id]);

        $this->post(route('work-blocks.store', ['date' => '2026-11-05']), ['start_time' => '15:00', 'end_time' => '16:00'])
            ->assertSessionMissing('error');

        $this->assertDatabaseCount('work_blocks', 1);
    }

    public function test_deleting_a_payment_unlocks_its_days(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $this->post(route('payments.store'), ['payday' => '2026-11-05', 'days' => ['2026-11-02']]);
        $payment = Payment::sole();

        $this->get(route('payments.show', $payment))
            ->assertSeeInOrder(['3:00 PM – 5:00 PM', 'action="'.route('payments.destroy', $payment).'"'], false);

        $this->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('status', 'Payment for Thu, Nov 5, 2026 deleted. 1 day is unlocked.');

        $this->assertModelMissing($payment);
        $this->assertFalse(WorkDay::isPaid(Carbon::parse('2026-11-02')));
        $this->assertSame(2.0, WorkDay::sole()->blocks->sum('hours'));
        $this->assertContains('2026-11-02', Timesheet::unpaidThrough(CarbonImmutable::today())->dateStrings());

        $this->post(route('work-blocks.store', ['date' => '2026-11-02']), ['start_time' => '18:00', 'end_time' => '19:00'])
            ->assertSessionMissing('error');
        $this->assertSame(3.0, WorkDay::sole()->blocks()->get()->sum('hours'));
    }

    public function test_index_shows_the_running_unpaid_total(): void
    {
        Setting::store('hourly_rate', '40');
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '17:00')->create();
        $customizedTuesday = WorkDay::factory()->create(['date' => '2026-11-03']);
        WorkBlock::factory()->for($customizedTuesday)->between('10:00', '11:30')->create();

        $response = $this->get(route('payments.index'))->assertOk();

        $this->assertSame(5.5, $response->viewData('unpaidHours'));
        $response->assertSeeInOrder(['Unpaid hours', '5.5', 'Unpaid amount', '$220.00']);
    }

    public function test_unpaid_total_ignores_paid_days_future_days_and_days_before_tracking(): void
    {
        ScheduleBlock::factory()->on(CarbonInterface::MONDAY, '15:00', '16:00')->create();
        $payment = Payment::factory()->create(['payday' => '2026-11-05']);
        WorkBlock::factory()
            ->for(WorkDay::factory()->state(['date' => '2026-11-02', 'payment_id' => $payment->id]))
            ->between('15:00', '16:00')
            ->create();

        $this->assertSame(1.0, $this->get(route('payments.index'))->viewData('unpaidHours'));
    }

    public function test_index_lists_payments(): void
    {
        $payment = Payment::factory()->create(['payday' => '2026-11-05', 'total_hours' => 4, 'hourly_rate' => 40]);
        WorkDay::factory()->count(2)->sequence(['date' => '2026-11-02'], ['date' => '2026-11-03'])->create(['payment_id' => $payment->id]);

        $this->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Thu, Nov 5, 2026')
            ->assertSee('<th>Date</th>', false)
            ->assertSee(route('payments.show', $payment))
            ->assertSee('$160.00');
    }

    public function test_show_displays_a_paid_banner_and_the_days(): void
    {
        $payment = Payment::factory()->create(['payday' => '2026-11-05', 'total_hours' => 1, 'hourly_rate' => 40]);
        WorkBlock::factory()
            ->for(WorkDay::factory()->state(['date' => '2026-11-02', 'payment_id' => $payment->id]))
            ->between('15:00', '16:00')
            ->create();

        $this->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('class="flash paid-banner"', false)
            ->assertSee('Paid on Thursday, November 5, 2026')
            ->assertSee('for 1 hours ($40.00).')
            ->assertSee('3:00 PM – 4:00 PM');
    }
}
