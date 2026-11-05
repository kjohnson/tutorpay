<?php

namespace Tests\Feature;

use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->put(route('settings.update'), [
            'pay_period_anchor' => '2026-11-12',
            'hourly_rate' => '35.50',
        ])->assertRedirect(route('settings.edit'));

        $this->assertSame('2026-11-12', Setting::payPeriodAnchor()->toDateString());
        $this->assertSame(35.5, Setting::hourlyRate());
        $this->get(route('settings.edit'))->assertOk();
    }

    public function test_pay_period_can_start_on_any_day(): void
    {
        $this->put(route('settings.update'), [
            'pay_period_anchor' => '2026-11-13',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-13', Setting::payPeriodAnchor()->toDateString());
        $this->get(route('pay-period.show', ['date' => '2026-11-20']))->assertSee('Fri, Nov 13 – Thu, Nov 26, 2026');
    }

    public function test_pay_period_start_must_be_a_valid_date(): void
    {
        $this->put(route('settings.update'), [
            'pay_period_anchor' => 'next week',
        ])->assertSessionHasErrors('pay_period_anchor');
    }
}
