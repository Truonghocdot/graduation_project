<?php

namespace App\Providers;

use App\Contracts\Auth\PhoneOtpSender;
use App\Services\Auth\DevelopmentPhoneOtpSender;
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
    }

    private function authenticationRateLimitKey(Request $request): string
    {
        $phone = PhoneNumber::normalize($request->string('phone')->toString());

        return hash('sha256', Str::lower($phone).'|'.$request->ip());
    }
}
