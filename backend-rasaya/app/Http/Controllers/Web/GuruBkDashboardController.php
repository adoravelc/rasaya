<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class GuruBkDashboardController extends Controller
{
    public function index(){ return view('roles.guru.guru_bk.dashboard'); }
}