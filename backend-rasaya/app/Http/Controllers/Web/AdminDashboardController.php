<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('roles.admin.dashboard');
    }
}

