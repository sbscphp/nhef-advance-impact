<?php

namespace App\Providers;

use App\Repositories\Admin\AdminRepository;
use App\Repositories\ApiUser\ApiUserRepository;
use App\Repositories\Auth\OtpRepository;
use App\Repositories\Bank\BankRepository;
use App\Repositories\BankAccount\BankAccountRepository;
use App\Repositories\Campaign\CampaignRepository;
use App\Repositories\CampaignInstitution\CampaignInstitutionRepository;
use App\Repositories\Communications\CommunicationCallLogRepository;
use App\Repositories\Communications\CommunicationTaskNoteRepository;
use App\Repositories\Communications\CommunicationTaskRepository;
use App\Repositories\Communications\EmailUnsubscribeRepository;
use App\Repositories\Communications\MailRecipientRepository;
use App\Repositories\Communications\MailRepository;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\ApiUser\ApiUserRepositoryInterface;
use App\Repositories\Contracts\Auth\OtpRepositoryInterface;
use App\Repositories\Contracts\Bank\BankRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Communications\CommunicationCallLogRepositoryInterface;
use App\Repositories\Contracts\Communications\CommunicationTaskNoteRepositoryInterface;
use App\Repositories\Contracts\Communications\CommunicationTaskRepositoryInterface;
use App\Repositories\Contracts\Communications\EmailUnsubscribeRepositoryInterface;
use App\Repositories\Contracts\Communications\MailRecipientRepositoryInterface;
use App\Repositories\Contracts\Communications\MailRepositoryInterface;
use App\Repositories\Contracts\Crm\ProposalCollaboratorRepositoryInterface;
use App\Repositories\Contracts\Crm\ProposalRecipientRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectCallLogRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectInviteRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectMessageRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectProposalRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationItemRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationPaymentRepositoryInterface;
use App\Repositories\Contracts\Event\EventRegistrationRepositoryInterface;
use App\Repositories\Contracts\Event\EventRepositoryInterface;
use App\Repositories\Contracts\Event\EventTicketTypeRepositoryInterface;
use App\Repositories\Contracts\Event\EventWaitlistEntryRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MenteeProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipReviewRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingChannelRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageReactionRepositoryInterface;
use App\Repositories\Contracts\Networking\NetworkingMessageRepositoryInterface;
use App\Repositories\Contracts\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeInstallmentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgePaymentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\Theme\ThemeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Repositories\Crm\ProposalCollaboratorRepository;
use App\Repositories\Crm\ProposalRecipientRepository;
use App\Repositories\Crm\ProspectCallLogRepository;
use App\Repositories\Crm\ProspectInviteRepository;
use App\Repositories\Crm\ProspectMessageRepository;
use App\Repositories\Crm\ProspectProposalRepository;
use App\Repositories\Crm\ProspectRepository;
use App\Repositories\Donation\DonationPaymentRepository;
use App\Repositories\Donation\DonationRepository;
use App\Repositories\DonorTier\DonorTierRepository;
use App\Repositories\Event\EventRegistrationItemRepository;
use App\Repositories\Event\EventRegistrationPaymentRepository;
use App\Repositories\Event\EventRegistrationRepository;
use App\Repositories\Event\EventRepository;
use App\Repositories\Event\EventTicketTypeRepository;
use App\Repositories\Event\EventWaitlistEntryRepository;
use App\Repositories\Institution\InstitutionRepository;
use App\Repositories\Mentorship\MenteeProfileRepository;
use App\Repositories\Mentorship\MentorProfileRepository;
use App\Repositories\Mentorship\MentorshipMatchRepository;
use App\Repositories\Mentorship\MentorshipReviewRepository;
use App\Repositories\Networking\NetworkingChannelRepository;
use App\Repositories\Networking\NetworkingMessageReactionRepository;
use App\Repositories\Networking\NetworkingMessageRepository;
use App\Repositories\PaymentMethod\PaymentMethodRepository;
use App\Repositories\Pledge\PledgeInstallmentRepository;
use App\Repositories\Pledge\PledgePaymentRepository;
use App\Repositories\Pledge\PledgeRepository;
use App\Repositories\Theme\ThemeRepository;
use App\Repositories\User\UserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Knuckles\Scribe\Scribe;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(OtpRepositoryInterface::class, OtpRepository::class);
        $this->app->bind(ApiUserRepositoryInterface::class, ApiUserRepository::class);
        $this->app->bind(ThemeRepositoryInterface::class, ThemeRepository::class);
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);
        $this->app->bind(CampaignInstitutionRepositoryInterface::class, CampaignInstitutionRepository::class);
        $this->app->bind(InstitutionRepositoryInterface::class, InstitutionRepository::class);
        $this->app->bind(BankRepositoryInterface::class, BankRepository::class);
        $this->app->bind(BankAccountRepositoryInterface::class, BankAccountRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(PledgeRepositoryInterface::class, PledgeRepository::class);
        $this->app->bind(PledgeInstallmentRepositoryInterface::class, PledgeInstallmentRepository::class);
        $this->app->bind(PledgePaymentRepositoryInterface::class, PledgePaymentRepository::class);
        $this->app->bind(DonationRepositoryInterface::class, DonationRepository::class);
        $this->app->bind(DonationPaymentRepositoryInterface::class, DonationPaymentRepository::class);
        $this->app->bind(PaymentMethodRepositoryInterface::class, PaymentMethodRepository::class);
        $this->app->bind(DonorTierRepositoryInterface::class, DonorTierRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(EventTicketTypeRepositoryInterface::class, EventTicketTypeRepository::class);
        $this->app->bind(EventRegistrationRepositoryInterface::class, EventRegistrationRepository::class);
        $this->app->bind(EventRegistrationItemRepositoryInterface::class, EventRegistrationItemRepository::class);
        $this->app->bind(EventRegistrationPaymentRepositoryInterface::class, EventRegistrationPaymentRepository::class);
        $this->app->bind(EventWaitlistEntryRepositoryInterface::class, EventWaitlistEntryRepository::class);
        $this->app->bind(MentorProfileRepositoryInterface::class, MentorProfileRepository::class);
        $this->app->bind(MenteeProfileRepositoryInterface::class, MenteeProfileRepository::class);
        $this->app->bind(MentorshipMatchRepositoryInterface::class, MentorshipMatchRepository::class);
        $this->app->bind(MentorshipReviewRepositoryInterface::class, MentorshipReviewRepository::class);
        $this->app->bind(ProspectRepositoryInterface::class, ProspectRepository::class);
        $this->app->bind(ProspectCallLogRepositoryInterface::class, ProspectCallLogRepository::class);
        $this->app->bind(ProspectInviteRepositoryInterface::class, ProspectInviteRepository::class);
        $this->app->bind(ProspectProposalRepositoryInterface::class, ProspectProposalRepository::class);
        $this->app->bind(ProposalCollaboratorRepositoryInterface::class, ProposalCollaboratorRepository::class);
        $this->app->bind(ProposalRecipientRepositoryInterface::class, ProposalRecipientRepository::class);
        $this->app->bind(ProspectMessageRepositoryInterface::class, ProspectMessageRepository::class);
        $this->app->bind(MailRepositoryInterface::class, MailRepository::class);
        $this->app->bind(MailRecipientRepositoryInterface::class, MailRecipientRepository::class);
        $this->app->bind(EmailUnsubscribeRepositoryInterface::class, EmailUnsubscribeRepository::class);
        $this->app->bind(CommunicationCallLogRepositoryInterface::class, CommunicationCallLogRepository::class);
        $this->app->bind(CommunicationTaskRepositoryInterface::class, CommunicationTaskRepository::class);
        $this->app->bind(CommunicationTaskNoteRepositoryInterface::class, CommunicationTaskNoteRepository::class);
        $this->app->bind(NetworkingChannelRepositoryInterface::class, NetworkingChannelRepository::class);
        $this->app->bind(NetworkingMessageRepositoryInterface::class, NetworkingMessageRepository::class);
        $this->app->bind(NetworkingMessageReactionRepositoryInterface::class, NetworkingMessageReactionRepository::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureScribePostmanEnhancements();

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url')."/reset-password?token={$token}&email=".urlencode($user->email);
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('customer-login', function (Request $request) {
            return Limit::perMinute(5)->by($this->loginThrottleKey($request));
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($this->loginThrottleKey($request));
        });

        RateLimiter::for('customer-token-refresh', function (Request $request) {
            return Limit::perMinute(10)->by($this->refreshThrottleKey($request));
        });

        RateLimiter::for('admin-token-refresh', function (Request $request) {
            return Limit::perMinute(10)->by($this->refreshThrottleKey($request));
        });

        RateLimiter::for('customer-otp-send', function (Request $request) {
            $window = max(1, (int) config('security.otp_minutes', 5));
            $max = max(1, (int) config('security.otp_send_max_per_window', 3));

            return Limit::perMinutes($window, $max)->by($this->otpThrottleKey($request));
        });

        RateLimiter::for('customer-otp-verify', function (Request $request) {
            $window = max(1, (int) config('security.otp_minutes', 5));
            $max = max(1, (int) config('security.otp_verify_max_per_window', 20));

            return Limit::perMinutes($window, $max)->by($this->otpThrottleKey($request));
        });

        RateLimiter::for('admin-otp-send', function (Request $request) {
            $window = max(1, (int) config('security.otp_minutes', 5));
            $max = max(1, (int) config('security.otp_send_max_per_window', 3));

            return Limit::perMinutes($window, $max)->by($this->otpThrottleKey($request));
        });

        RateLimiter::for('admin-otp-verify', function (Request $request) {
            $window = max(1, (int) config('security.otp_minutes', 5));
            $max = max(1, (int) config('security.otp_verify_max_per_window', 20));

            return Limit::perMinutes($window, $max)->by($this->otpThrottleKey($request));
        });

        RateLimiter::for('admin-reset-token-context', function (Request $request) {
            return Limit::perMinute(20)->by($this->resetTokenThrottleKey($request));
        });

        RateLimiter::for('api-user-dev-registration', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('customer-register', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('customer-pledge-create', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        // No account to key off for guests, so this is IP-only; tighter than the
        // authenticated limit since it's also the only real abuse guard on this endpoint.
        RateLimiter::for('guest-pledge-create', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('customer-donation-create', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        // No account to key off for guests, so this is IP-only; tighter than the
        // authenticated limit since it's also the only real abuse guard on this endpoint.
        RateLimiter::for('guest-donation-create', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('customer-event-register', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        // No account to key off for guests, so this is IP-only; tighter than the
        // authenticated limit since it's also the only real abuse guard on this endpoint.
        RateLimiter::for('guest-event-register', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('customer-mentorship-apply', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        RateLimiter::for('customer-networking-message-send', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(30)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        RateLimiter::for('customer-networking-typing', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(60)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });
    }

    /**
     * Postman convenience: on `scribe:generate`, patches the generated collection so "Login"
     * stores its `access_token` into an `accessToken` variable every protected request reads
     * via {{accessToken}}. Guarded by class_exists() since scribe is a require-dev package.
     */
    protected function configureScribePostmanEnhancements(): void
    {
        if (! class_exists(Scribe::class)) {
            return;
        }

        Scribe::afterGenerating(function (array $paths): void {
            $postmanPath = $paths['postman'] ?? null;

            if (! is_string($postmanPath) || ! is_file($postmanPath)) {
                return;
            }

            $collection = json_decode((string) file_get_contents($postmanPath), true);

            if (! is_array($collection)) {
                return;
            }

            $collection['variable'] = array_values(array_filter(
                (array) ($collection['variable'] ?? []),
                fn ($variable) => ($variable['key'] ?? null) !== 'accessToken'
            ));
            $collection['variable'][] = ['key' => 'accessToken', 'value' => '', 'type' => 'string'];

            if (isset($collection['item']) && is_array($collection['item'])) {
                $this->attachAccessTokenScriptToLogin($collection['item']);
            }

            file_put_contents($postmanPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function attachAccessTokenScriptToLogin(array &$items): void
    {
        foreach ($items as &$item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $this->attachAccessTokenScriptToLogin($item['item']);

                continue;
            }

            if (($item['name'] ?? null) !== 'Login') {
                continue;
            }

            $item['event'] = [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'const json = pm.response.json();',
                        'if (json && json.data && json.data.access_token) {',
                        "    pm.collectionVariables.set('accessToken', json.data.access_token);",
                        '}',
                    ],
                ],
            ]];
        }
    }

    private function loginThrottleKey(Request $request): string
    {
        $ip = (string) $request->ip();
        $email = strtolower(trim((string) $request->input('email')));

        return $email !== '' ? $ip.'|'.$email : $ip;
    }

    private function otpThrottleKey(Request $request): string
    {
        $ip = (string) $request->ip();
        $token = (string) $request->input('challenge_token');

        return $token !== '' ? $ip.'|'.hash('sha256', $token) : $ip;
    }

    private function refreshThrottleKey(Request $request): string
    {
        $ip = (string) $request->ip();
        $token = (string) $request->input('refresh_token');

        return $token !== '' ? $ip.'|'.hash('sha256', $token) : $ip;
    }

    private function resetTokenThrottleKey(Request $request): string
    {
        $ip = (string) $request->ip();
        $token = (string) $request->input('token');

        return $token !== '' ? $ip.'|'.hash('sha256', $token) : $ip;
    }
}
