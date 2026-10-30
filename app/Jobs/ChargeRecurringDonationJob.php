<?php

namespace App\Jobs;

use App\Models\Donation;
use App\Services\Fundraising\DonationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Charges one due cycle of one recurring donation; see
 * {@see DonationService::chargeRecurringDonation()} for the charge/settlement logic. One job per
 * donation, so one gateway failure can't take the rest of the day's batch down with it.
 */
class ChargeRecurringDonationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Guards against the daily command somehow dispatching the same donation twice in one run. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly string $donationUuid) {}

    public function uniqueId(): string
    {
        return 'charge-recurring-donation:'.$this->donationUuid;
    }

    public function handle(DonationService $donationService): void
    {
        $donation = Donation::query()->where('uuid', $this->donationUuid)->first();

        if ($donation === null) {
            return;
        }

        try {
            $donationService->chargeRecurringDonation($donation, Request::create('/'));
        } catch (\Throwable $th) {
            Log::error('Recurring donation charge job failed.', [
                'donation_uuid' => $donation->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);

            throw $th;
        }
    }
}
