<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockGuestWriteRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $isGuestMode = $request->hasSession() && (bool) $request->session()->get('guest_mode', false);

        if (!$isGuestMode) {
            return $next($request);
        }

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($request->routeIs('logout') || $request->routeIs('login.attempt') || $request->routeIs('guest.enter')) {
            return $next($request);
        }

        $message = 'Mode guest hanya bisa read-only. Perubahan data tidak disimpan.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->back()->withErrors(['guest' => $message]);
    }
}
