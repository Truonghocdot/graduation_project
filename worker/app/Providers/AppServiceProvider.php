<?php

namespace App\Providers;

use App\Contracts\Auth\PhoneOtpSender;
use App\Contracts\Maps\MapProvider;
use App\Contracts\Matching\DriverPresenceStore;
use App\Contracts\Realtime\LocationPublisher;
use App\Services\Auth\DevelopmentPhoneOtpSender;
use App\Services\Execution\NullLocationPublisher;
use App\Services\Execution\RedisLocationPublisher;
use App\Services\Maps\GoongMapProvider;
use App\Services\Matching\NullDriverPresenceStore;
use App\Services\Matching\RedisDriverPresenceStore;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PhoneOtpSender::class, DevelopmentPhoneOtpSender::class);
        $this->app->bind(MapProvider::class, GoongMapProvider::class);
        $this->app->bind(
            DriverPresenceStore::class,
            app()->environment('testing')
                ? NullDriverPresenceStore::class
                : RedisDriverPresenceStore::class,
        );
        $this->app->bind(
            LocationPublisher::class,
            app()->environment('testing')
                ? NullLocationPublisher::class
                : RedisLocationPublisher::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('auth-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->authenticationRateLimitKey($request));
        });

        RateLimiter::for('auth-otp', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->authenticationRateLimitKey($request));
        });

        RateLimiter::for('quotes', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
            );
        });
    }

    private function authenticationRateLimitKey(Request $request): string
    {
        $phone = PhoneNumber::normalize($request->string('phone')->toString());

        return hash('sha256', Str::lower($phone).'|'.$request->ip());
    }
}
