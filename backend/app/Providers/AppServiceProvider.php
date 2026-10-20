<?php

namespace App\Providers;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        // When accessed via public tunnel (ngrok, cloudflare) or remote domain,
        // bypass Vite hot reloading to prevent browser Private Network Access (PNA)
        // CORS blocks (browsers prohibit public HTTPS fetching http://[::1]:5173).
        $host = Request::getHost();
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        $isForwarded = Request::hasHeader('X-Forwarded-Host') || str_contains($host, 'ngrok');

        if (! $isLocalHost || $isForwarded || $this->app->environment('production')) {
            Vite::useHotFile(storage_path('framework/cache/vite.hot.disabled'));
        }
    }
}
