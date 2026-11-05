<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\WorkDay;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * List every payment, newest first, with the running unpaid total.
     */
    public function index(): View
    {
        return view('payments.index', [
            'payments' => Payment::query()->withCount('days')->latest('payday')->get(),
            'unpaidHours' => Timesheet::unpaidThrough(CarbonImmutable::today())->totalHours(),
            'hourlyRate' => Setting::hourlyRate(),
        ]);
    }

    /**
     * Show the days that can be included in a payment made on the given date
     * (today by default).
     */
    public function create(Request $request): View
    {
        $validated = $request->validate(['payday' => ['nullable', 'date_format:Y-m-d']]);
        $date = isset($validated['payday']) ? CarbonImmutable::parse($validated['payday']) : CarbonImmutable::today();

        return view('payments.create', [
            'payday' => $date,
            'existingPayment' => Payment::query()->whereDate('payday', $date->toDateString())->first(),
            'payable' => Timesheet::unpaidThrough($date),
            'hourlyRate' => Setting::hourlyRate(),
        ]);
    }

    /**
     * Pay the selected days and lock them.
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payday = $request->payday();

        $payment = DB::transaction(function () use ($request, $payday): Payment {
            $selected = array_flip($request->validated('days'));
            $payable = array_values(array_filter(
                Timesheet::unpaidThrough($payday)->days,
                fn (array $day): bool => isset($selected[$day['date']->toDateString()]),
            ));

            if (count($payable) !== count($selected)) {
                throw ValidationException::withMessages(['days' => 'Some of the selected days can no longer be paid.']);
            }

            $payment = Payment::create([
                'payday' => $payday->toDateString(),
                'total_hours' => round(array_sum(array_column($payable, 'totalHours')), 2),
                'hourly_rate' => $request->validated('hourly_rate'),
                'note' => $request->validated('note'),
            ]);

            foreach ($payable as $day) {
                WorkDay::forDate($day['date'])->update(['payment_id' => $payment->id]);
            }

            return $payment;
        });

        return redirect()->route('payments.show', $payment);
    }

    /**
     * Delete a payment and unlock its days. The days keep the hours they had
     * when paid, so they count toward unpaid hours again.
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $dayCount = DB::transaction(function () use ($payment): int {
            $dayCount = $payment->days()->update(['payment_id' => null]);
            $payment->delete();

            return $dayCount;
        });

        return redirect()
            ->route('payments.index')
            ->with('status', sprintf(
                'Payment for %s deleted. %d %s unlocked.',
                $payment->payday->format('D, M j, Y'),
                $dayCount,
                $dayCount === 1 ? 'day is' : 'days are',
            ));
    }

    /**
     * Show a payment and the days it covered.
     */
    public function show(Payment $payment): View
    {
        $payment->load('days.blocks');

        return view('payments.show', ['payment' => $payment]);
    }
}
