<?php

namespace Database\Seeders;

use App\Enums\AuditActionEnum;
use App\Enums\ConstituentTypeEnum;
use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Enums\eRole;
use App\Enums\EventRegistrationStatusEnum;
use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\NetworkingChannelTypeEnum;
use App\Enums\NetworkingReactionEmojiEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Enums\PledgeStatusEnum;
use App\Enums\SocialPlatformEnum;
use App\Enums\UserTypeEnum;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\DonationPayment;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationItem;
use App\Models\EventRegistrationPayment;
use App\Models\EventTicketType;
use App\Models\EventWaitlistEntry;
use App\Models\Institution;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Models\MentorshipReview;
use App\Models\NetworkingChannel;
use App\Models\NetworkingMessage;
use App\Models\Pledge;
use App\Models\User;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeInstallmentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use App\Services\Fundraising\CampaignService;
use App\Services\Mentorship\MentorshipService;
use Carbon\Carbon;
use Database\Seeders\Concerns\DemoDataFaker;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic, interconnected demo data: 3 institutions (University of Lagos + 2 more), each with
 * constituents, campaigns, donations/pledges, events and mentorship activity, plus one shared
 * National Giving Day campaign across all three. Everything flows through the real repository
 * layer (and MentorshipService/CampaignService where no payment gateway is involved) so derived
 * numbers - campaign raised_amount, institution stats, donor tiers, dashboard cards, report
 * datasets - all reconcile against real rows instead of being hand-set separately.
 *
 * Intended to run on a fresh-ish environment (local or staging) after the base seeders
 * (RolesAndPermissionsSeeder, InstitutionSeeder, DonorTierSeeder/CountrySeeder) have already run.
 * Institutions/admins are resolved with firstOrCreate, so running it again is safe but will add a
 * second batch of constituents/campaigns/events rather than silently no-op.
 *
 * php artisan db:seed --class=FmeDemoDataSeeder
 */
class FmeDemoDataSeeder extends Seeder
{
    use DemoDataFaker;

    private const PASSWORD = 'password';

    /**
     * Matches every institution InstitutionSeeder creates, so none are left as bare, zero-stat
     * rows in the admin Partner Institutions table once this seeder runs.
     *
     * @var list<string>
     */
    private const INSTITUTION_NAMES = [
        'University of Lagos',
        'University of Ibadan',
        'University of Nigeria, Nsukka',
        'Ahmadu Bello University',
        'Obafemi Awolowo University',
        'University of Abuja',
        'Covenant University',
        'Lagos State University',
        'University of Benin',
        'Bayero University Kano',
    ];

    /** @var array<string, float> country_of_residence weighting; must sum to 1.0 */
    private const COUNTRY_WEIGHTS = [
        'Nigeria' => 0.70,
        'United Kingdom' => 0.10,
        'United States' => 0.08,
        'Canada' => 0.05,
        'Ghana' => 0.04,
        'South Africa' => 0.03,
    ];

    private CampaignRepositoryInterface $campaignRepository;

    private DonationRepositoryInterface $donationRepository;

    private DonationPaymentRepositoryInterface $paymentRepository;

    private PledgeRepositoryInterface $pledgeRepository;

    private PledgeInstallmentRepositoryInterface $installmentRepository;

    private CampaignService $campaignService;

    private MentorshipService $mentorshipService;

    /** @var array<string, Country> */
    private array $countries = [];

    private Request $request;

    public function run(): void
    {
        // Mentorship approval (and anything else routed through NotificationDispatchService with
        // sendMail: true) dispatches via Notification::sendNow(), synchronously, regardless of the
        // queue connection - a real/misconfigured SMTP host can hang the whole seeder waiting on a
        // dead socket. Demo data should never attempt a real network mail send, so redirect
        // outbound mail to the log channel for the duration of this seeder only.
        config(['mail.default' => 'log']);

        $this->campaignRepository = app(CampaignRepositoryInterface::class);
        $this->donationRepository = app(DonationRepositoryInterface::class);
        $this->paymentRepository = app(DonationPaymentRepositoryInterface::class);
        $this->pledgeRepository = app(PledgeRepositoryInterface::class);
        $this->installmentRepository = app(PledgeInstallmentRepositoryInterface::class);
        $this->campaignService = app(CampaignService::class);
        $this->mentorshipService = app(MentorshipService::class);
        $this->request = Request::create('/', 'POST');

        $this->seedFaker();
        $this->loadCountries();

        $superAdmin = Admin::query()->whereHas('roles', fn ($q) => $q->where('name', eRole::SUPER_ADMIN->value))->first();
        if (! $superAdmin instanceof Admin) {
            $this->command?->error('No Super Admin account found - run AdminSeeder/RolesAndPermissionsSeeder first.');

            return;
        }

        $bank = Bank::firstOrCreate(['code' => '058'], ['name' => 'Guaranty Trust Bank', 'is_active' => true]);

        $institutions = [];
        $admins = [];
        $bankAccounts = [];
        $constituentsByInstitution = [];

        foreach (self::INSTITUTION_NAMES as $name) {
            $institution = $this->resolveInstitution($name);
            $admin = $this->resolveInstitutionAdmin($institution, $name);
            $bankAccount = $this->resolveBankAccount($bank, $institution, $admin);

            $institutions[$institution->id] = $institution;
            $admins[$institution->id] = $admin;
            $bankAccounts[$institution->id] = $bankAccount;

            $this->command?->info("Seeding constituents for {$name}...");
            $constituentsByInstitution[$institution->id] = $this->createConstituents($institution, 26);
        }

        foreach ($institutions as $institutionId => $institution) {
            $admin = $admins[$institutionId];
            $bankAccount = $bankAccounts[$institutionId];
            $constituents = $constituentsByInstitution[$institutionId];

            $this->command?->info("Seeding campaigns, events, mentorship and networking for {$institution->name}...");

            $institution->makeCurrent();
            $campaigns = $this->createStandardCampaigns($institution, $admin, $bankAccount);
            Institution::forgetCurrent();
            foreach ($campaigns as $campaign) {
                $this->createDonationsForCampaign($campaign, $constituents, rand(10, 18));
                $this->createPledgesForCampaign($campaign, $constituents, rand(3, 6));
            }

            $events = $this->createEvents($institution, $admin);
            foreach ($events as $event) {
                $this->createRegistrationsForEvent($event, $constituents);
            }

            $this->createMentorshipActivity($constituents);
            $this->createNetworkingActivity($institution, $constituents, $admin);
        }

        $this->command?->info('Seeding shared National Giving Day campaign...');
        $ngd = $this->createNationalGivingDayCampaign($institutions, $admins, $bankAccounts, $superAdmin);
        $allConstituents = collect($constituentsByInstitution)->flatten(1)->all();
        $this->createDonationsForCampaign($ngd, $allConstituents, 30, spreadDays: 21);
        $this->createPledgesForCampaign($ngd, $allConstituents, rand(8, 14));

        $this->command?->info('Demo data seeding complete.');
    }

    private function loadCountries(): void
    {
        foreach (array_keys(self::COUNTRY_WEIGHTS) as $name) {
            $country = Country::query()->where('name', $name)->first();
            if ($country instanceof Country) {
                $this->countries[$name] = $country;
            }
        }
    }

    private function weightedCountry(): ?Country
    {
        $roll = mt_rand() / mt_getrandmax();
        $cumulative = 0.0;
        foreach (self::COUNTRY_WEIGHTS as $name => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative && isset($this->countries[$name])) {
                return $this->countries[$name];
            }
        }

        return $this->countries['Nigeria'] ?? null;
    }

    private function resolveInstitution(string $name): Institution
    {
        $tertiaryInstitutionRepository = app(TertiaryInstitutionRepositoryInterface::class);
        $canonicalNames = [
            'University of Lagos' => 'University of Lagos, Lagos',
            'University of Ibadan' => 'University of Ibadan, Ibadan',
            'Ahmadu Bello University' => 'Ahmadu Bello University, Zaria',
            'Obafemi Awolowo University' => 'Obafemi Awolowo University, Ile-Ife',
            'University of Abuja' => 'University of Abuja, Abuja',
            'Covenant University' => 'Covenant University, Ota',
            'Lagos State University' => 'Lagos State University, Ojo',
            'University of Benin' => 'University of Benin, Benin City',
            'Bayero University Kano' => 'Bayero University, Kano',
        ];
        $tertiaryInstitution = $tertiaryInstitutionRepository->findOrCreateByName($canonicalNames[$name] ?? $name);

        // updateOrCreate, not firstOrCreate: InstitutionSeeder (which runs first, in
        // DatabaseSeeder's base list) already creates a bare row for every one of these names
        // with only is_active/tertiary_institution_id set - firstOrCreate would silently no-op
        // against that existing row and never apply status/slug/email/onboarded_at below.
        return Institution::updateOrCreate(
            ['name' => $name],
            [
                'is_active' => true,
                'tertiary_institution_id' => $tertiaryInstitution->id,
                'status' => 'active',
                'slug' => Str::slug($name),
                'email' => 'admin-'.Str::slug($name).'@yopmail.com',
                'onboarded_at' => now()->subMonths(rand(2, 8)),
            ]
        );
    }

    private function resolveInstitutionAdmin(Institution $institution, string $institutionName): Admin
    {
        $slug = Str::slug($institutionName);
        $email = "demo-admin-{$slug}@yopmail.com";

        $admin = Admin::firstOrCreate(
            ['email' => $email],
            [
                'name' => $institutionName.' Admin',
                'email_verified_at' => now(),
                'is_active' => true,
                'can_login' => true,
                'institution_id' => $institution->id,
                'password' => Hash::make(self::PASSWORD),
                '2fa' => false,
            ]
        );

        if ($admin->roles()->count() === 0) {
            $admin->syncRoles([eRole::INSTITUTION_ADMIN->value]);
        }

        return $admin;
    }

    private function resolveBankAccount(Bank $bank, Institution $institution, Admin $admin): BankAccount
    {
        return BankAccount::firstOrCreate(
            ['bank_id' => $bank->id, 'account_number' => $this->accountNumberFor($institution)],
            ['account_name' => $institution->name.' Endowment Account', 'created_by' => $admin->uuid]
        );
    }

    private function accountNumberFor(Institution $institution): string
    {
        return str_pad((string) (1000000000 + $institution->id), 10, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<User>
     */
    private function createConstituents(Institution $institution, int $count): array
    {
        $tertiaryInstitutionId = $institution->tertiary_institution_id;
        $constituents = [];

        for ($i = 0; $i < $count; $i++) {
            $roll = $i / $count;
            $type = match (true) {
                $roll < 0.60 => ConstituentTypeEnum::ALUMNI,
                $roll < 0.85 => ConstituentTypeEnum::NON_ALUMNI,
                default => ConstituentTypeEnum::ORGANIZATION,
            };

            $country = $this->weightedCountry();
            $createdAt = Carbon::now()->subDays(rand(14, 220));
            $isOrg = $type === ConstituentTypeEnum::ORGANIZATION;

            $firstname = $isOrg ? null : $this->fakerName('firstName');
            $lastname = $isOrg ? null : $this->fakerName('lastName');
            $organisationName = $isOrg ? $this->fakerName('company') : null;

            $attributes = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'organisation_name' => $organisationName,
                // Not fakerName('unique.safeEmail'): Faker's uniqueness is per-process only, so it
                // can collide with rows a previous run of this seeder already left in the
                // database (the class docblock explicitly promises re-running it is safe). A
                // random suffix guarantees uniqueness regardless of run count.
                'email' => Str::lower(Str::slug($isOrg ? $organisationName : $firstname.' '.$lastname)).'.'.Str::random(6).'@example.test',
                'phone_number' => $this->nigerianPhoneNumber(),
                'constituent_type' => $type->value,
                // Required for all three constituent types in the real registration flow
                // (CustomerRegisterRequest: "Required across all three 'Sign Up as' flavours").
                'tertiary_institution_id' => $tertiaryInstitutionId,
                'department' => $type !== ConstituentTypeEnum::ORGANIZATION ? $this->fakerName('randomElement', [['Computer Science', 'Economics', 'Law', 'Medicine', 'Mechanical Engineering', 'Mass Communication', 'Accounting', 'Political Science']]) : null,
                'year_of_graduation' => $type === ConstituentTypeEnum::ALUMNI ? rand(1995, 2023) : null,
                'degree_earned' => $type === ConstituentTypeEnum::ALUMNI ? $this->fakerName('randomElement', [['B.Sc', 'B.A', 'B.Eng', 'LLB', 'MBBS', 'M.Sc']]) : null,
                'employment_status' => $type !== ConstituentTypeEnum::ORGANIZATION ? $this->fakerName('randomElement', [['Employed', 'Self-employed', 'Unemployed', 'Retired']]) : null,
                'position' => $isOrg ? 'Partner Liaison' : ($type === ConstituentTypeEnum::ALUMNI ? $this->fakerName('jobTitle') : null),
                'country_of_residence_id' => ! $isOrg ? $country?->id : null,
                'country_of_operation_id' => $isOrg ? $country?->id : null,
                'status' => 'active',
                'is_active' => true,
                'email_verified_at' => $createdAt,
                'password' => Hash::make(self::PASSWORD),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'onboarded_at' => (clone $createdAt)->addDays(rand(0, 3)),
                'last_active_at' => Carbon::now()->subDays(rand(0, 30)),
            ];

            $constituents[] = User::query()->create($attributes);
        }

        return $constituents;
    }

    /**
     * @return list<Campaign>
     */
    private function createStandardCampaigns(Institution $institution, Admin $admin, BankAccount $bankAccount): array
    {
        $titles = [
            $institution->name.' Scholarship Fund',
            $institution->name.' Library & Research Upgrade',
        ];

        $campaigns = [];
        foreach ($titles as $title) {
            $startedDaysAgo = rand(40, 90);
            $goal = [2_000_000, 8_000_000, 15_000_000][array_rand([2_000_000, 8_000_000, 15_000_000])];

            $campaign = $this->campaignService->create([
                'title' => $title,
                'description' => "Supporting {$institution->name}'s students and faculty through direct, transparent giving.",
                'currency' => 'NGN',
                'goal_amount' => $goal,
                'starts_at' => Carbon::now()->subDays($startedDaysAgo)->toDateString(),
                'ends_at' => Carbon::now()->addMonths(3)->toDateString(),
                'assigned_admin_id' => $admin->uuid,
                'bank_account_id' => $bankAccount->uuid,
            ], $admin, $this->request);

            $campaign->forceFill(['created_at' => Carbon::now()->subDays($startedDaysAgo)])->save();
            $campaigns[] = $campaign;
        }

        return $campaigns;
    }

    /**
     * @param  array<int, Institution>  $institutions
     * @param  array<int, Admin>  $admins
     * @param  array<int, BankAccount>  $bankAccounts
     */
    private function createNationalGivingDayCampaign(array $institutions, array $admins, array $bankAccounts, Admin $superAdmin): Campaign
    {
        $institutionRows = [];
        foreach ($institutions as $institutionId => $institution) {
            $institutionRows[] = [
                'institution_id' => $institution->uuid,
                'goal_amount' => [8_000_000, 12_000_000, 10_000_000][array_rand([8_000_000, 12_000_000, 10_000_000])],
                'currency' => 'NGN',
                'bank_account_id' => $bankAccounts[$institutionId]->uuid,
            ];
        }

        $campaign = $this->campaignService->createNationalGivingDay([
            'title' => 'NHEF National Giving Day 2026',
            'description' => 'A coordinated day of giving across NHEF partner institutions, with funds routed to each institution\'s own recipient account.',
            'starts_at' => Carbon::now()->subDays(14)->toDateString(),
            'ends_at' => Carbon::now()->subDays(12)->toDateString(),
            'timer_lead_hours' => 72,
            'assigned_admin_id' => reset($admins)->uuid,
            'institutions' => $institutionRows,
        ], $superAdmin, $this->request);

        $campaign->forceFill(['created_at' => Carbon::now()->subDays(20)])->save();

        return $campaign;
    }

    /**
     * @param  list<User>  $constituents
     */
    private function createDonationsForCampaign(Campaign $campaign, array $constituents, int $count, int $spreadDays = 56): void
    {
        $donors = (count($constituents) >= $count) ? $this->fakerShuffle($constituents) : $constituents;

        for ($i = 0; $i < $count; $i++) {
            $donor = $donors[$i % count($donors)];
            $amount = $this->weightedDonationAmount();
            $paidAt = Carbon::now()->subDays(rand(0, $spreadDays))->setTime(rand(7, 21), rand(0, 59));

            $donation = $this->donationRepository->create([
                'campaign_id' => $campaign->id,
                'user_id' => $donor->id,
                'frequency' => DonationFrequencyEnum::ONE_TIME->value,
                'currency' => 'NGN',
                'amount' => $amount,
                'total_received' => 0,
                'status' => DonationStatusEnum::PENDING->value,
                'is_anonymous' => false,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);

            $payment = $this->paymentRepository->create([
                'donation_id' => $donation->id,
                'user_id' => $donor->id,
                'amount' => $amount,
                'currency' => 'NGN',
                'method' => 'card',
                'gateway' => 'paystack',
                'gateway_reference' => 'DEMO_'.strtoupper(Str::random(20)),
                'status' => PaymentStatusEnum::PENDING->value,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);

            $this->paymentRepository->markSuccessful($payment, ['paid_at' => $paidAt]);
            $this->donationRepository->incrementTotalReceived($donation, (string) $amount);
            $this->donationRepository->update($donation, ['status' => DonationStatusEnum::COMPLETED->value, 'completed_at' => $paidAt]);
            $this->campaignRepository->incrementRaisedAmount($campaign, (string) $amount);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::CUSTOMER,
                AuditActionEnum::PAYMENT_SUCCEEDED,
                $this->request,
                $donor->uuid,
                ['reference' => $payment->gateway_reference, 'amount' => (string) $amount, 'donor_email' => $donor->email],
                $donor->displayName().' made a donation to '.$campaign->title.'.',
                DonationPayment::class,
                $payment->uuid,
                ModuleEnums::fundraising,
                200,
            );
        }
    }

    private function weightedDonationAmount(): string
    {
        $roll = mt_rand(1, 100);
        $amount = match (true) {
            $roll <= 55 => rand(10_000, 190_000),
            $roll <= 80 => rand(200_000, 950_000),
            $roll <= 93 => rand(1_000_000, 4_500_000),
            $roll <= 98 => rand(5_000_000, 9_500_000),
            default => rand(10_000_000, 20_000_000),
        };

        return (string) $amount;
    }

    /**
     * @param  list<User>  $constituents
     */
    private function createPledgesForCampaign(Campaign $campaign, array $constituents, int $count): void
    {
        $pledgers = $this->fakerShuffle($constituents);

        for ($i = 0; $i < $count; $i++) {
            $donor = $pledgers[$i % count($pledgers)];
            $totalAmount = (string) ([500_000, 1_200_000, 2_400_000, 3_600_000][array_rand([500_000, 1_200_000, 2_400_000, 3_600_000])]);
            $installmentCount = [3, 4, 6][array_rand([3, 4, 6])];
            $frequency = PledgeFrequencyEnum::MONTHLY;
            $startDate = Carbon::now()->subMonths(2);
            $perInstallment = round(((float) $totalAmount) / $installmentCount, 2);

            $pledge = $this->pledgeRepository->create([
                'campaign_id' => $campaign->id,
                'user_id' => $donor->id,
                'frequency' => $frequency->value,
                'currency' => 'NGN',
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'installment_count' => $installmentCount,
                'status' => PledgeStatusEnum::ON_TRACK->value,
                'is_anonymous' => false,
                'start_date' => $startDate->toDateString(),
                'end_date' => (clone $startDate)->addMonths($installmentCount)->toDateString(),
                'next_installment_due_at' => (clone $startDate)->addMonth()->toDateString(),
                'created_at' => $startDate,
                'updated_at' => $startDate,
            ]);

            $paidInstallments = rand(1, max(1, $installmentCount - 1));

            for ($sequence = 1; $sequence <= $installmentCount; $sequence++) {
                $dueDate = (clone $startDate)->addMonths($sequence);
                $installment = $this->installmentRepository->create([
                    'pledge_id' => $pledge->id,
                    'sequence' => $sequence,
                    'amount' => $perInstallment,
                    'currency' => 'NGN',
                    'due_date' => $dueDate->toDateString(),
                ]);

                if ($sequence <= $paidInstallments) {
                    $this->installmentRepository->markPaid($installment);
                    $this->pledgeRepository->incrementAmountPaid($pledge, (string) $perInstallment);
                }
            }

            $isComplete = $paidInstallments === $installmentCount;
            $this->pledgeRepository->update($pledge, [
                'status' => $isComplete ? PledgeStatusEnum::COMPLETED->value : PledgeStatusEnum::ON_TRACK->value,
            ]);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::CUSTOMER,
                AuditActionEnum::PLEDGE_CREATED,
                $this->request,
                $donor->uuid,
                ['campaign_uuid' => $campaign->uuid, 'total_amount' => $totalAmount, 'donor_email' => $donor->email],
                $donor->displayName().' created a pledge for '.$campaign->title.'.',
                Pledge::class,
                $pledge->uuid,
                ModuleEnums::fundraising,
                201,
            );
        }
    }

    /**
     * @return list<Event>
     */
    private function createEvents(Institution $institution, Admin $admin): array
    {
        $specs = [
            ['title' => $institution->name.' Alumni Homecoming 2026', 'capacity' => 10, 'price' => 15_000, 'waitlist' => true],
            ['title' => $institution->name.' Career Development Workshop', 'capacity' => 60, 'price' => 0, 'waitlist' => false],
        ];

        $events = [];
        foreach ($specs as $spec) {
            $startsAt = Carbon::now()->addDays(rand(10, 45));
            $event = Event::query()->create([
                'title' => $spec['title'],
                'slug' => Str::slug($spec['title']).'-'.Str::random(4),
                'description' => 'Join fellow '.$institution->name.' constituents for an evening of connection and giving.',
                'event_type' => 'physical',
                'venue_name' => $institution->name.' Alumni Hall',
                'venue_address' => $institution->name.' Campus',
                'starts_at' => $startsAt,
                'ends_at' => (clone $startsAt)->addHours(4),
                'registration_ends_at' => (clone $startsAt)->subDay(),
                'capacity' => $spec['capacity'],
                'seats_taken' => 0,
                'status' => 'published',
                'waitlist_enabled' => $spec['waitlist'],
                'institution_id' => $institution->id,
                'created_by' => $admin->uuid,
            ]);

            EventTicketType::query()->create([
                'event_id' => $event->id,
                'name' => $spec['price'] > 0 ? 'Standard Ticket' : 'Free Entry',
                'price' => $spec['price'],
                'currency' => 'NGN',
                'quantity' => $spec['capacity'],
                'quantity_sold' => 0,
            ]);

            $events[] = $event;
        }

        return $events;
    }

    /**
     * @param  list<User>  $constituents
     */
    private function createRegistrationsForEvent(Event $event, array $constituents): void
    {
        $ticketType = $event->ticketTypes()->first();
        if (! $ticketType instanceof EventTicketType) {
            return;
        }

        $attendees = $this->fakerShuffle($constituents);
        $capacity = $event->capacity ?? count($attendees);
        $demand = $event->waitlist_enabled ? $capacity + rand(3, 6) : min($capacity, rand(5, 15));
        $seatsTaken = 0;

        for ($i = 0; $i < $demand && $i < count($attendees); $i++) {
            $attendee = $attendees[$i];
            $registeredAt = Carbon::now()->subDays(rand(1, 20));
            $amount = (string) $ticketType->price;

            if ($seatsTaken >= $capacity) {
                EventWaitlistEntry::query()->create([
                    'event_id' => $event->id,
                    'event_ticket_type_id' => $ticketType->id,
                    'user_id' => $attendee->id,
                    'quantity_requested' => 1,
                    'currency' => 'NGN',
                    'projected_value' => $amount,
                    'position' => ($i - $capacity) + 1,
                    'status' => 'pending',
                    'created_at' => $registeredAt,
                    'updated_at' => $registeredAt,
                ]);

                continue;
            }

            $registration = EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $attendee->id,
                'currency' => 'NGN',
                'amount' => $amount,
                'status' => EventRegistrationStatusEnum::PENDING->value,
                'created_at' => $registeredAt,
                'updated_at' => $registeredAt,
            ]);

            EventRegistrationItem::query()->create([
                'event_registration_id' => $registration->id,
                'event_ticket_type_id' => $ticketType->id,
                'quantity' => 1,
                'unit_price' => $ticketType->price,
                'currency' => 'NGN',
                'subtotal' => $ticketType->price,
            ]);

            if ((float) $amount > 0) {
                EventRegistrationPayment::query()->create([
                    'event_registration_id' => $registration->id,
                    'user_id' => $attendee->id,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'method' => 'card',
                    'gateway' => 'paystack',
                    'gateway_reference' => 'DEMOTIX_'.strtoupper(Str::random(20)),
                    'status' => PaymentStatusEnum::SUCCESSFUL->value,
                    'paid_at' => $registeredAt,
                    'created_at' => $registeredAt,
                    'updated_at' => $registeredAt,
                ]);
            }

            $registration->forceFill(['status' => EventRegistrationStatusEnum::COMPLETED->value, 'completed_at' => $registeredAt])->save();

            app(EventTicketTypeRepositoryInterface::class)->incrementQuantitySold($ticketType, 1);
            app(EventRepositoryInterface::class)->incrementSeatsTaken($event, 1);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::CUSTOMER,
                AuditActionEnum::EVENT_REGISTRATION_COMPLETED,
                $this->request,
                $attendee->uuid,
                ['event_uuid' => $event->uuid, 'registration_uuid' => $registration->uuid],
                $attendee->displayName().' registered for "'.$event->title.'".',
                EventRegistration::class,
                $registration->uuid,
                ModuleEnums::events,
                200,
            );

            $seatsTaken++;
        }
    }

    /**
     * @param  list<User>  $constituents
     */
    private function createMentorshipActivity(array $constituents): void
    {
        $pool = $this->fakerShuffle($constituents);
        $mentorCandidates = array_slice($pool, 0, 2);
        $menteeCandidates = array_slice($pool, 2, 5);

        $mentors = [];
        foreach ($mentorCandidates as $user) {
            if (MentorProfile::query()->where('user_id', $user->id)->exists()) {
                continue;
            }

            $mentor = $this->mentorshipService->applyAsMentor($user, [
                'current_job_title' => $this->fakerName('jobTitle'),
                'company' => $this->fakerName('company'),
                'years_of_experience' => rand(5, 20),
                'professional_summary' => 'Experienced professional eager to guide the next generation of alumni.',
                'major_achievements' => 'Led cross-functional teams to deliver major projects on schedule and under budget.',
                'expertise_tags' => ['Career growth', 'Leadership'],
                'guidance_areas' => ['Career growth', 'Technical interviews'],
                'max_capacity' => 5,
                'available_days' => ['monday', 'wednesday'],
                'frequency_of_interaction' => 'weekly',
                'program_commitment' => 'six_months',
                'socials' => [
                    ['platform' => SocialPlatformEnum::LINKEDIN->value, 'value' => 'https://linkedin.com/in/'.Str::slug($user->displayName())],
                ],
                'portfolio_url' => 'https://'.Str::slug($user->displayName()).'.example.com',
            ], $this->request);

            $this->mentorshipService->approveMentorApplication(
                Admin::query()->whereHas('roles', fn ($q) => $q->where('name', eRole::SUPER_ADMIN->value))->first(),
                $mentor->uuid,
                $this->request,
            );

            $mentors[] = $mentor->fresh();
        }

        $mentees = [];
        foreach ($menteeCandidates as $user) {
            $existing = MenteeProfile::query()->where('user_id', $user->id)->first();
            if ($existing instanceof MenteeProfile) {
                $mentees[] = $existing;

                continue;
            }

            $this->mentorshipService->applyAsMentee($user, [
                'interest_areas' => ['Career growth', 'Skill development'],
                'skills' => ['Communication', 'Research'],
                'professional_summary' => 'Looking to grow professionally with guidance from an experienced mentor.',
                'why_mentor_needed' => 'Seeking direction on career progression.',
                'available_days' => ['monday', 'wednesday'],
                'frequency_of_interaction' => 'weekly',
                'socials' => [
                    ['platform' => SocialPlatformEnum::LINKEDIN->value, 'value' => 'https://linkedin.com/in/'.Str::slug($user->displayName())],
                ],
                'portfolio_url' => 'https://'.Str::slug($user->displayName()).'.example.com',
            ], $this->request);

            $mentees[] = MenteeProfile::query()->where('user_id', $user->id)->first();
        }

        if ($mentors === [] || $mentees === []) {
            return;
        }

        $this->createMentorshipMatch($mentors[0], $mentees[0]);
    }

    private function createMentorshipMatch(MentorProfile $mentor, MenteeProfile $mentee): void
    {
        $match = MentorshipMatch::firstOrCreate(
            ['mentor_profile_id' => $mentor->id, 'mentee_profile_id' => $mentee->id],
            [
                'status' => MentorshipMatchStatusEnum::COMPLETED->value,
                'matched_by' => MentorshipMatchedByEnum::SYSTEM->value,
                'matched_at' => Carbon::now()->subMonths(4),
                'completed_at' => Carbon::now()->subDays(rand(1, 20)),
            ]
        );

        MentorshipReview::firstOrCreate(
            ['mentorship_match_id' => $match->id],
            [
                'quality_rating' => rand(4, 5),
                'communication_rating' => rand(4, 5),
                'responsiveness_rating' => rand(3, 5),
                'professionalism_rating' => rand(4, 5),
                'description' => 'Excellent mentor, very responsive and gave practical, actionable career advice.',
            ]
        );
    }

    /**
     * A Forum and a Community channel per institution, with a handful of members and a short
     * realistic exchange - so Networking isn't an entirely empty module once this seeder runs.
     *
     * @param  list<User>  $constituents
     */
    private function createNetworkingActivity(Institution $institution, array $constituents, Admin $admin): void
    {
        $members = array_slice($this->fakerShuffle($constituents), 0, min(6, count($constituents)));
        if (count($members) < 2) {
            return;
        }

        $forum = NetworkingChannel::firstOrCreate(
            ['type' => NetworkingChannelTypeEnum::FORUM->value, 'name' => $institution->name.' Alumni Forum'],
            [
                'institution_id' => $institution->id,
                'description' => 'Open discussion space for '.$institution->name.' alumni.',
                'created_by_admin_id' => $admin->id,
            ]
        );

        $community = NetworkingChannel::firstOrCreate(
            ['type' => NetworkingChannelTypeEnum::COMMUNITY->value, 'name' => $institution->name.' Giving Circle'],
            [
                'institution_id' => $institution->id,
                'description' => 'A space for '.$institution->name.' alumni to coordinate giving and mentorship.',
                'created_by_admin_id' => $admin->id,
            ]
        );

        foreach ([$forum, $community] as $channel) {
            $joinRows = [];
            foreach ($members as $member) {
                $joinRows[$member->id] = ['joined_at' => Carbon::now()->subDays(rand(5, 60))];
            }
            $channel->members()->syncWithoutDetaching($joinRows);
        }

        [$first, $second] = [$members[0], $members[1]];
        $direct = NetworkingChannel::query()
            ->where('type', NetworkingChannelTypeEnum::DIRECT->value)
            ->whereHas('members', fn ($q) => $q->where('users.id', $first->id))
            ->whereHas('members', fn ($q) => $q->where('users.id', $second->id))
            ->first();

        if ($direct instanceof NetworkingChannel) {
            return;
        }

        $direct = NetworkingChannel::create(['type' => NetworkingChannelTypeEnum::DIRECT->value, 'institution_id' => $institution->id]);
        $direct->members()->attach([
            $first->id => ['joined_at' => Carbon::now()->subDays(10)],
            $second->id => ['joined_at' => Carbon::now()->subDays(10)],
        ]);

        $openers = [
            "Hey, great seeing everyone at the last {$institution->name} event! Are you joining the next giving circle call?",
            'Quick one, did you get a chance to look at the mentorship sign-up? I think a few more of us should apply.',
        ];
        $replies = [
            'Yes, count me in! It was great reconnecting with everyone.',
            "I did, I'll sign up this week. Thanks for the nudge!",
        ];

        $firstMessage = NetworkingMessage::create([
            'channel_id' => $direct->id,
            'sender_id' => $first->id,
            'body' => $openers[array_rand($openers)],
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $secondMessage = NetworkingMessage::create([
            'channel_id' => $direct->id,
            'sender_id' => $second->id,
            'body' => $replies[array_rand($replies)],
            'created_at' => Carbon::now()->subHours(20),
        ]);

        $secondMessage->reactions()->create([
            'user_id' => $first->id,
            'emoji' => NetworkingReactionEmojiEnum::LOVE->value,
        ]);

        unset($firstMessage);
    }
}
