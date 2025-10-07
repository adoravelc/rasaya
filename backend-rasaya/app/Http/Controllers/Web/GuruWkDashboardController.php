<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class GuruWkDashboardController extends Controller
{
    public function index(){ return view('roles.guru.wali_kelas.dashboard'); }
}

