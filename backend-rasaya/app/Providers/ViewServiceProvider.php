<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share notifications to all views
        View::composer('*', function ($view) {
            if (Auth::check()) {
                $userId = Auth::id();
                $unreadNotifications = Notification::forUser($userId)
                    ->unread()
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
                
                $unreadCount = Notification::forUser($userId)
                    ->unread()
                    ->count();
                
                $view->with([
                    'unreadNotifications' => $unreadNotifications,
                    'unreadCount' => $unreadCount,
                ]);
            }
        });
    }
}
