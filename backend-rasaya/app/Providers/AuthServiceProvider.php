<?php

namespace App\Providers;

use App\Models\Kelas;
use App\Policies\KelasPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Map policies to models.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Kelas::class => KelasPolicy::class,
        // SiswaKelasPolicy dipakai manual per-aksi (tidak dibound ke model)
    ];

    public function boot(): void
    {
        // Kalau mau define Gate tambahan, taruh di sini.
        // Gate::define('something', fn ($user) => ...);
    }
}
