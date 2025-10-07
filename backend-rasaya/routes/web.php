<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthWebController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthWebController::class,'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class,'doLogin']);
Route::post('/logout', [AuthWebController::class,'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => view('dashboard')); // sementara
});