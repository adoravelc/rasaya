<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureGuruJenis
{
    public function handle(Request $request, Closure $next, ...$allowed)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'guru')
            abort(403);

        $jenis = optional($user->guru)->jenis; // 'bk' atau 'wali_kelas'
        if (!$jenis || !in_array($jenis, $allowed, true))
            abort(403);

        return $next($request);
    }
}

