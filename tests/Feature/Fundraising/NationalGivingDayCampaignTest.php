<?php

namespace Tests\Feature\Fundraising;

use App\Enums\CampaignTypeEnum;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentStatusEnum;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\Institution;
use App\Models\Pledge;
use App\Models\User;
use App\Services\Fundraising\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NationalGivingDayCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_national_giving_day_campaign_with_institutions(): void
    {
        $admin = $this->makeAdmin();
        $lagos = Institution::create(['name' => 'University of Lagos', 'is_active' => true]);
        $abuja = Institution::create(['name' => 'University of Abuja', 'is_active' => true]);
        $lagosAccount = $this->makeBankAccount($admin);
        $abujaAccount = $this->makeBankAccount($admin);

        $service = app(CampaignService::class);
        $request = Request::create('/');

        $campaign = $service->createNationalGivingDay([
            'title' => 'Feed a Child',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'description' => 'Test national giving day campaign.',
            'allocated_admin_id' => $admin->uuid,
            'institutions' => [
                ['institution_id' => $lagos->uuid, 'goal_amount' => 2000, 'currency' => CurrencyEnum::NGN->value, 'bank_account_id' => $lagosAccount->uuid],
                ['institution_id' => $abuja->uuid, 'goal_amount' => 1500, 'currency' => CurrencyEnum::NGN->value, 'bank_account_id' => $abujaAccount->uuid],
            ],
        ], $admin, $request);

        $this->assertSame(CampaignTypeEnum::NATIONAL_GIVING_DAY->value, $campaign->type);
        $this->assertNull($campaign->goal_amount);
        $this->assertNull($campaign->currency);
        $this->assertSame(2, $campaign->campaignInstitutions()->count());
        $this->assertDatabaseHas('campaign_institutions', [
            'campaign_id' => $campaign->id,
            'institution_id' => $lagos->id,
            'goal_amount' => 2000,
        ]);
    }

    public function test_admin_can_add_update_and_remove_a_campaign_institution(): void
    {
        $admin = $this->makeAdmin();
        $campaign = $this->makeNationalGivingDayCampaign($admin);
        $institution = Institution::create(['name' => 'Covenant University', 'is_active' => true]);
        $bankAccount = $this->makeBankAccount($admin);

        $service = app(CampaignService::class);
        $request = Request::create('/');

        $campaignInstitution = $service->addInstitution($campaign->uuid, [
            'institution_id' => $institution->uuid,
            'goal_amount' => 1000,
            'currency' => CurrencyEnum::NGN->value,
            'bank_account_id' => $bankAccount->uuid,
        ], $admin, $request);

        $this->assertSame('1000.00', (string) $campaignInstitution->goal_amount);

        $campaignInstitution = $service->updateInstitution($campaign->uuid, $campaignInstitution->uuid, [
            'goal_amount' => 1750,
        ], $admin, $request);

        $this->assertSame('1750.00', (string) $campaignInstitution->goal_amount);

        $service->removeInstitution($campaign->uuid, $campaignInstitution->uuid, $admin, $request);

        $this->assertDatabaseMissing('campaign_institutions', ['uuid' => $campaignInstitution->uuid]);
    }

    public function test_institutions_can_only_be_managed_on_a_national_giving_day_campaign(): void
    {
        $admin = $this->makeAdmin();
        $bank = Bank::create(['name' => 'Test Bank', 'code' => 'TB01', 'is_active' => true]);
        $bankAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'account_number' => '1234567890',
            'account_name' => 'Standard Campaign Account',
            'created_by' => $admin->uuid,
        ]);
        $standardCampaign = Campaign::create([
            'title' => 'Standard Campaign',
            'slug' => 'standard-campaign',
            'description' => 'A regular single-institution campaign.',
            'type' => CampaignTypeEnum::STANDARD->value,
            'currency' => CurrencyEnum::NGN->value,
            'goal_amount' => 10000,
            'raised_amount' => 0,
            'status' => 'active',
            'created_by' => $admin->uuid,
            'allocated_admin_id' => $admin->id,
            'bank_account_id' => $bankAccount->id,
        ]);
        $institution = Institution::create(['name' => 'University of Ibadan', 'is_active' => true]);

        $service = app(CampaignService::class);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Institutions can only be managed on a National Giving Day campaign.');

        $service->addInstitution($standardCampaign->uuid, [
            'institution_id' => $institution->uuid,
            'goal_amount' => 500,
            'currency' => CurrencyEnum::NGN->value,
            'bank_account_id' => $bankAccount->uuid,
        ], $admin, Request::create('/'));
    }

    public function test_admin_campaign_listing_can_be_filtered_by_type(): void
    {
        $admin = $this->makeAdmin();
        $bank = Bank::create(['name' => 'Test Bank', 'code' => 'TB02', 'is_active' => true]);
        $bankAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'account_number' => '1234567891',
            'account_name' => 'Standard Campaign Account',
            'created_by' => $admin->uuid,
        ]);
        Campaign::create([
            'title' => 'Standard Campaign',
            'slug' => 'standard-campaign-2',
            'description' => 'A regular single-institution campaign.',
            'type' => CampaignTypeEnum::STANDARD->value,
            'currency' => CurrencyEnum::NGN->value,
            'goal_amount' => 10000,
            'raised_amount' => 0,
            'status' => 'active',
            'created_by' => $admin->uuid,
            'allocated_admin_id' => $admin->id,
            'bank_account_id' => $bankAccount->id,
        ]);
        $this->makeNationalGivingDayCampaign($admin);

        $service = app(CampaignService::class);

        $paginator = $service->paginateForAdmin(['filters' => ['type' => CampaignTypeEnum::NATIONAL_GIVING_DAY->value]]);

        $this->assertSame(1, $paginator->total());
        $this->assertSame(CampaignTypeEnum::NATIONAL_GIVING_DAY->value, $paginator->items()[0]->type);
    }

    public function test_institution_progress_is_derived_from_donations_and_pledges_by_donor_university(): void
    {
        $admin = $this->makeAdmin();
        $campaign = $this->makeNationalGivingDayCampaign($admin);
        $institution = Institution::create(['name' => 'University of Lagos', 'is_active' => true]);
        $bankAccount = $this->makeBankAccount($admin);
        $campaign->campaignInstitutions()->create([
            'institution_id' => $institution->id,
            'goal_amount' => 10000,
            'currency' => CurrencyEnum::NGN->value,
            'bank_account_id' => $bankAccount->id,
        ]);

        $donor = User::factory()->create(['university' => 'University of Lagos']);
        $donation = Donation::create([
            'campaign_id' => $campaign->id,
            'user_id' => $donor->id,
            'frequency' => 'one_time',
            'currency' => CurrencyEnum::NGN->value,
            'amount' => 3000,
            'total_received' => 3000,
            'status' => 'completed',
            'is_anonymous' => false,
        ]);
        DonationPayment::create([
            'donation_id' => $donation->id,
            'user_id' => $donor->id,
            'amount' => 3000,
            'currency' => CurrencyEnum::NGN->value,
            'status' => PaymentStatusEnum::SUCCESSFUL->value,
            'gateway_reference' => 'DON_TEST_'.uniqid(),
            'paid_at' => now(),
        ]);

        $pledger = User::factory()->create(['university' => 'University of Lagos']);
        Pledge::create([
            'campaign_id' => $campaign->id,
            'user_id' => $pledger->id,
            'frequency' => 'one_time',
            'currency' => CurrencyEnum::NGN->value,
            'total_amount' => 5000,
            'amount_paid' => 1500,
            'installment_count' => 1,
            'status' => 'on_track',
            'is_anonymous' => false,
        ]);

        $service = app(CampaignService::class);
        $paginator = $service->listInstitutions($campaign->uuid, []);

        $row = $paginator->items()[0];
        $this->assertSame('4500', $row->raised_amount);
    }

    private function makeNationalGivingDayCampaign(Admin $admin): Campaign
    {
        return Campaign::create([
            'title' => 'NHEF National Giving Day '.uniqid(),
            'slug' => 'nhef-national-giving-day-'.uniqid(),
            'description' => 'Test national giving day campaign.',
            'type' => CampaignTypeEnum::NATIONAL_GIVING_DAY->value,
            'currency' => null,
            'goal_amount' => null,
            'raised_amount' => 0,
            'status' => 'active',
            'created_by' => $admin->uuid,
            'allocated_admin_id' => $admin->id,
            'bank_account_id' => null,
        ]);
    }

    private function makeBankAccount(Admin $admin): BankAccount
    {
        $bank = Bank::create(['name' => 'Bank '.uniqid(), 'code' => 'C'.substr(uniqid(), -6), 'is_active' => true]);

        return BankAccount::create([
            'bank_id' => $bank->id,
            'account_number' => (string) random_int(1000000000, 9999999999),
            'account_name' => 'Test Account',
            'created_by' => $admin->uuid,
        ]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'can_login' => true,
        ]);
    }
}
