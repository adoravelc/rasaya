<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SiswaDashboardController extends Controller
{
    /**
     * Redirect siswa to Flutter Web app
     * Flutter web will be hosted separately and siswa will access it from there
     */
    public function index()
    {
        // Get Flutter Web URL from config (set in .env: FLUTTER_WEB_URL)
        $flutterWebUrl = config('app.flutter_web_url', 'http://localhost:8080');
        
        // Redirect to Flutter Web with token
        return redirect()->away($flutterWebUrl);
    }
}
