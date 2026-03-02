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
    public function index(Request $request)
    {
        $flutterWebUrl = config('app.flutter_web_url', 'http://localhost:8080');

        $isGuestSiswa = (bool) $request->session()->get('guest_mode', false)
            && $request->session()->get('guest_role') === 'siswa';

        if (!$isGuestSiswa) {
            return redirect()->away($flutterWebUrl);
        }

        $query = http_build_query([
            'guest' => 1,
            'auto_guest' => 1,
            'home_url' => route('guest.exit'),
        ]);

        return redirect()->away(rtrim($flutterWebUrl, '/') . '/?' . $query);
    }
}
