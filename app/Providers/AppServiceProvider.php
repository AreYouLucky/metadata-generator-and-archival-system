<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureDevCommands();
    }

    /**
     * Processes started by `composer run dev` (php artisan dev).
     *
     * Horizon needs the pcntl extension, which Windows PHP does not have. There,
     * Horizon is dropped, so the framework's default `queue:listen` runs instead.
     * The scheduler is added so local runs match the Docker stack.
     */
    protected function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function (): void {
            DevCommands::artisan('schedule:work', 'scheduler');

            if (! function_exists('pcntl_fork')) {
                DevCommands::except('horizon');
            }
        });
    }

    /**
     * Signed AI-service callbacks arrive in bursts while a pipeline runs, so the
     * limit is generous; it only stops a flood of unsigned junk from one host.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('internal', fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()));

        // Anything that starts paid AI work or heavy processing (new records, regenerations).
        RateLimiter::for('ai', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // Chat questions (Phase 7): each answer makes several AI calls.
        RateLimiter::for('chat', fn (Request $request): Limit => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));

        // Chunked uploads: one request per 8 MB chunk.
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(600)->by($request->user()?->id ?: $request->ip()));

        // YouTube uploads (Phase 7): each costs a large share of the daily API quota.
        RateLimiter::for('youtube', fn (Request $request): Limit => Limit::perHour(10)->by($request->user()?->id ?: $request->ip()));

        // Public API (Phase 7): per token, so one integration can't starve another.
        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $key = $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('archive.api.requests_per_minute'))->by($key);
        });

        // Public catalogue pages, and the player's media requests (seeking sends many Range requests).
        RateLimiter::for('catalogue', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('catalogue-media', fn (Request $request): Limit => Limit::perMinute(600)->by($request->user()?->id ?: $request->ip()));
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
    }
}
