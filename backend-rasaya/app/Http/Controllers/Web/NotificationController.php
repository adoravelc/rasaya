<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Tandai semua notifikasi yang belum dibaca menjadi dibaca.
     */
    public function readAll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->back();
        }

        Notification::forUser($user->id)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return redirect()->back()->with('info','Semua notifikasi telah ditandai dibaca.');
    }

    /**
     * Tandai satu notifikasi sebagai dibaca (opsional jika nanti diperlukan).
     */
    public function markAsRead(Request $request, int $id)
    {
        $notif = Notification::where('id',$id)->where('user_id', Auth::id())->first();
        if ($notif) {
            $notif->markAsRead();
        }
        return redirect()->back();
    }
}
