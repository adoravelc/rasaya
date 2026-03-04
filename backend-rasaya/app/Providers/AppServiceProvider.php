<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB; // Tambahkan import ini
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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
        // Use Bootstrap 5 styles for Laravel pagination links
        Paginator::useBootstrapFive();

        RateLimiter::for('web-login', function (Request $request) {
            $identifier = Str::lower((string) $request->input('identifier', ''));

            return [
                Limit::perMinute(5)->by($request->ip() . '|web-login|' . $identifier),
                Limit::perMinute(20)->by($request->ip() . '|web-login'),
            ];
        });

        RateLimiter::for('guest-enter', function (Request $request) {
            $role = (string) $request->route('role', 'guest');

            return [
                Limit::perMinute(6)->by($request->ip() . '|guest-enter|' . $role),
                Limit::perMinute(15)->by($request->ip() . '|guest-enter'),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $key = Str::lower((string) $request->input('identifier', (string) $request->input('email', '')));

            return [
                Limit::perMinute(4)->by($request->ip() . '|forgot|' . $key),
                Limit::perMinute(10)->by($request->ip() . '|forgot'),
            ];
        });

        RateLimiter::for('reset-password', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($request->ip() . '|reset|' . $email),
                Limit::perMinute(12)->by($request->ip() . '|reset'),
            ];
        });

        RateLimiter::for('api-login', function (Request $request) {
            $identifier = Str::lower((string) $request->input('identifier', ''));

            return [
                Limit::perMinute(8)->by($request->ip() . '|api-login|' . $identifier),
                Limit::perMinute(20)->by($request->ip() . '|api-login'),
            ];
        });

        RateLimiter::for('api-guest-login', function (Request $request) {
            return [
                Limit::perMinute(8)->by($request->ip() . '|api-guest-login'),
            ];
        });

        RateLimiter::for('api-forgot-password', function (Request $request) {
            $key = Str::lower((string) $request->input('identifier', (string) $request->input('email', '')));

            return [
                Limit::perMinute(4)->by($request->ip() . '|api-forgot|' . $key),
                Limit::perMinute(10)->by($request->ip() . '|api-forgot'),
            ];
        });

        // Bungkus dalam try-catch agar tidak error saat running artisan commands atau saat DB down
        try {
            // Cek koneksi dulu sebelum set timezone
            DB::connection()->getPdo();
            DB::statement("SET time_zone = '+08:00'");
        } catch (\Exception $e) {
            // Biarkan kosong agar aplikasi tetap jalan meskipun DB belum siap
            // Ini sangat penting saat menjalankan 'php artisan migrate' pertama kali
        }
    }
}