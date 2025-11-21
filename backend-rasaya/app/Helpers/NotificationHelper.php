<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

class NotificationHelper
{
    /**
     * Notifikasi untuk Admin - Password Reset Request
     */
    public static function notifyAdminPasswordResetRequest($userId, $requestedByUserId)
    {
        $requestedBy = User::find($requestedByUserId);
        
        // Kirim ke semua admin
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'password_reset_request',
                'title' => 'Permintaan Reset Password',
                'message' => "{$requestedBy->name} ({$requestedBy->identifier}) meminta reset password",
                'data' => [
                    'user_id' => $requestedByUserId,
                    'name' => $requestedBy->name,
                    'identifier' => $requestedBy->identifier,
                ],
                'link' => route('admin.user.management.index'),
            ]);
        }
    }

    /**
     * Notifikasi untuk Admin - Password Changed
     */
    public static function notifyAdminPasswordChanged($userId)
    {
        $user = User::find($userId);
        
        // Kirim ke semua admin
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'password_changed',
                'title' => 'Password Diubah',
                'message' => "{$user->name} ({$user->identifier}) telah mengubah password",
                'data' => [
                    'user_id' => $userId,
                    'name' => $user->name,
                ],
                'link' => null,
            ]);
        }
    }

    /**
     * Notifikasi untuk Guru BK - Konseling Request
     */
    public static function notifyGuruBkKonselingRequest($guruId, $bookingId, $siswaName, $slotTime)
    {
        Notification::create([
            'user_id' => $guruId,
            'type' => 'konseling_request',
            'title' => 'Permintaan Konseling Baru',
            'message' => "{$siswaName} telah memesan slot konseling pada {$slotTime}",
            'data' => [
                'booking_id' => $bookingId,
                'siswa_name' => $siswaName,
                'slot_time' => $slotTime,
            ],
            'link' => route('guru.guru_bk.slots.view'),
        ]);
    }

    /**
     * Notifikasi untuk Guru BK - Konseling Reminder (H-5 menit)
     */
    public static function notifyGuruBkKonselingReminder($guruId, $bookingId, $siswaName, $slotTime)
    {
        Notification::create([
            'user_id' => $guruId,
            'type' => 'konseling_reminder',
            'title' => 'Reminder Konseling',
            'message' => "Konseling dengan {$siswaName} akan dimulai 5 menit lagi",
            'data' => [
                'booking_id' => $bookingId,
                'siswa_name' => $siswaName,
                'slot_time' => $slotTime,
            ],
            'link' => route('guru.guru_bk.slots.view'),
        ]);
    }

    /**
     * Notifikasi untuk Guru WK - Low Mood Alert (skor <5)
     */
    public static function notifyGuruWkLowMood($guruWkId, $siswaName, $moodScore, $siswaKelasId)
    {
        Notification::create([
            'user_id' => $guruWkId,
            'type' => 'low_mood_alert',
            'title' => 'Peringatan Mood Siswa',
            'message' => "{$siswaName} melaporkan mood dengan skor {$moodScore}/10",
            'data' => [
                'siswa_name' => $siswaName,
                'mood_score' => $moodScore,
                'siswa_kelas_id' => $siswaKelasId,
            ],
            'link' => route('guru.tren_emosi.index'),
        ]);
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($userId)
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
