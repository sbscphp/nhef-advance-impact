<?php

namespace App\Console\Commands;

use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Jobs\ChargeRecurringDonationJob;
use App\Models\Donation;
use Illuminate\Console\Command;

/**
 * Finds every recurring donation due for its next cycle and queues one
 * {@see ChargeRecurringDonationJob} per donation. Only registered donors are eligible: a
 * recurring donation needs a saved, reusable payment method to charge off-session, and guests
 * never get one saved (no account to attach it to) — so a guest recurring donation has no
 * chargeable path today regardless of this command; that's a separate, pre-existing gap in
 * whether guests should be able to pick "recurring" at all, not something this fixes.
 */
class ChargeRecurringDonationsCommand extends Command
{
    protected $signature = 'donations:charge-recurring';

    protected $description = 'Charge every recurring donation whose next cycle is due today.';

    public function handle(): int
    {
        $donations = Donation::query()
            ->whereNotNull('user_id')
            ->where('status', DonationStatusEnum::ACTIVE->value)
            ->where('frequency', '!=', DonationFrequencyEnum::ONE_TIME->value)
            ->whereNotNull('next_charge_at')
            ->whereDate('next_charge_at', '<=', now()->toDateString())
            ->get();

        foreach ($donations as $donation) {
            ChargeRecurringDonationJob::dispatch($donation->uuid);
        }

        $this->info($donations->count().' recurring donation(s) queued for charging.');

        return self::SUCCESS;
    }
}
