<?php

namespace App\Http\Controllers;

use App\Models\RekomendasiRequest;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $pendingCount = RekomendasiRequest::where('status', 'pending')->count();
        return view('admin.dashboard', compact('pendingCount'));
    }
}
