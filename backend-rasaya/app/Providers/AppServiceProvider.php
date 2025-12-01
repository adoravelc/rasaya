<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB; // Tambahkan import ini

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