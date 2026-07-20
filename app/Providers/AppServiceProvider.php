<?php

namespace App\Providers;

use App\Repositories\ApiUser\ApiUserRepository;
use App\Repositories\Auth\OtpRepository;
use App\Repositories\Campaign\CampaignRepository;
use App\Repositories\Contracts\ApiUser\ApiUserRepositoryInterface;
use App\Repositories\Contracts\Auth\OtpRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\PaymentMethod\PaymentMethodRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeInstallmentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgePaymentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\Theme\ThemeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Repositories\Donation\DonationPaymentRepository;
use App\Repositories\Donation\DonationRepository;
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
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(OtpRepositoryInterface::class, OtpRepository::class);
        $this->app->bind(ApiUserRepositoryInterface::class, ApiUserRepository::class);
        $this->app->bind(ThemeRepositoryInterface::class, ThemeRepository::class);
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);
        $this->app->bind(PledgeRepositoryInterface::class, PledgeRepository::class);
        $this->app->bind(PledgeInstallmentRepositoryInterface::class, PledgeInstallmentRepository::class);
        $this->app->bind(PledgePaymentRepositoryInterface::class, PledgePaymentRepository::class);
        $this->app->bind(DonationRepositoryInterface::class, DonationRepository::class);
        $this->app->bind(DonationPaymentRepositoryInterface::class, DonationPaymentRepository::class);
        $this->app->bind(PaymentMethodRepositoryInterface::class, PaymentMethodRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
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

        // No account to key off for guests, so this is IP-only — tighter than the
        // authenticated limit since it's also the only real abuse guard on this endpoint.
        RateLimiter::for('guest-pledge-create', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('customer-donation-create', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(10)->by($userId !== null ? 'user:'.$userId : (string) $request->ip());
        });

        // No account to key off for guests, so this is IP-only — tighter than the
        // authenticated limit since it's also the only real abuse guard on this endpoint.
        RateLimiter::for('guest-donation-create', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });
    }

    /**
     * Postman convenience: on `scribe:generate`, patch the generated Postman collection so the
     * "Login" request stores its `access_token` into an `accessToken` collection variable, which
     * every protected request then reads via the {{accessToken}} auth placeholder (config/scribe.php).
     * Guarded by class_exists() since knuckleswtf/scribe is a require-dev package.
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
}
