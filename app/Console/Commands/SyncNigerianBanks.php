<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Services\ThirdParty\Payment\PaystackService;
use Illuminate\Console\Command;

class SyncNigerianBanks extends Command
{
    protected $signature = 'banks:sync';

    protected $description = "Sync the local banks table with Paystack's Nigerian bank list.";

    public function handle(PaystackService $paystackService): int
    {
        try {
            $banks = $paystackService->listBanks();
        } catch (\Throwable $th) {
            $this->error('Unable to fetch the bank list from Paystack: '.$th->getMessage());

            return self::FAILURE;
        }

        foreach ($banks as $bank) {
            Bank::query()->updateOrCreate(
                ['code' => $bank['code']],
                ['name' => $bank['name'], 'is_active' => true]
            );
        }

        $this->info(count($banks).' banks synced from Paystack.');

        return self::SUCCESS;
    }
}
