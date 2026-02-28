<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Models\UserLoginHistory;

class LoginHistoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            try {
                $req = request();
                if ($req && $req->hasSession() && (bool) $req->session()->get('guest_mode', false)) {
                    return;
                }

                UserLoginHistory::create([
                    'user_id' => method_exists($event->user, 'getAuthIdentifier') ? $event->user->getAuthIdentifier() : ($event->user->id ?? null),
                    'ip_address' => $req ? $req->ip() : null,
                    'user_agent' => $req ? $req->userAgent() : null,
                    'logged_in_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // do not block login on failure
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            try {
                $req = request();
                if ($req && $req->hasSession() && (bool) $req->session()->get('guest_mode', false)) {
                    return;
                }

                $uid = method_exists($event->user, 'getAuthIdentifier') ? $event->user->getAuthIdentifier() : ($event->user->id ?? null);
                $last = UserLoginHistory::where('user_id', $uid)
                    ->whereNull('logged_out_at')
                    ->latest()
                    ->first();
                if ($last) {
                    $last->update(['logged_out_at' => now()]);
                }
            } catch (\Throwable $e) {}
        });
    }
}
