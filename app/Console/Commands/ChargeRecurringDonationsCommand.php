<?php

namespace App\Console\Commands;

use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Jobs\ChargeRecurringDonationJob;
use App\Models\Donation;
use Illuminate\Console\Command;

/**
 * Finds every recurring donation due for its next cycle and queues one
 * {@see ChargeRecurringDonationJob} per donation. Only registered donors are eligible; guests
 * never get a saved payment method to charge off-session.
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
