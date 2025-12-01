<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use App\Models\Guru;
use App\Models\CounselingReferral;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;

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
     * Notifikasi ke seluruh Guru BK saat referral baru diajukan.
     */
    public static function notifyReferralSubmitted(CounselingReferral $ref)
    {
        $siswaName = optional($ref->siswaKelas->siswa->user)->name;
        $submittedBy = optional($ref->submittedBy)->name;
        $kelasLabel = optional($ref->siswaKelas->kelas)->label;

        $bkGurus = Guru::where('jenis','bk')->with('user')->get()->pluck('user')->filter();
        foreach ($bkGurus as $bkUser) {
            Notification::create([
                'user_id' => $bkUser->id,
                'type' => 'referral_submitted',
                'title' => 'Referral Konseling Baru',
                'message' => "{$submittedBy} mengajukan konseling untuk {$siswaName} ({$kelasLabel})",
                'data' => [
                    'referral_id' => $ref->id,
                    'siswa_name' => $siswaName,
                    'submitted_by' => $submittedBy,
                ],
                'link' => route('guru.bk.dashboard'),
            ]);
        }
    }

    /**
     * Notifikasi ke pengaju referral saat diterima oleh Guru BK.
     */
    public static function notifyReferralAccepted(CounselingReferral $ref)
    {
        if (!$ref->submittedBy) return;
        $siswaName = optional($ref->siswaKelas->siswa->user)->name;
        Notification::create([
            'user_id' => $ref->submittedBy->id,
            'type' => 'referral_accepted',
            'title' => 'Referral Diterima',
            'message' => "Referral untuk {$siswaName} telah diterima Guru BK",
            'data' => [
                'referral_id' => $ref->id,
                'siswa_name' => $siswaName,
                'status' => $ref->status,
            ],
            'link' => route('guru.analisis.index'),
        ]);
    }

    /**
     * Notifikasi saat konseling privat dijadwalkan (siswa, wali kelas, guru BK penganggar).
     */
    public static function notifyPrivateSessionScheduled(CounselingReferral $ref, SlotKonseling $slot, SlotBooking $booking)
    {
        $siswaUser = optional($ref->siswaKelas->siswa)->user;
        $waliGuruUser = optional(optional($ref->siswaKelas->kelas)->waliGuru);
        $guruBkUser = optional($slot->guru)->user ?? optional($slot->guru); // tergantung relasi guru_id => user

        $start = optional($slot->start_at)->format('d M Y H:i');
        $siswaName = optional($siswaUser)->name;

        // Ke siswa
        if ($siswaUser) {
            Notification::create([
                'user_id' => $siswaUser->id,
                'type' => 'private_session_scheduled',
                'title' => 'Jadwal Konseling Privat',
                'message' => "Konseling privat dijadwalkan pada {$start}",
                'data' => [
                    'slot_id' => $slot->id,
                    'booking_id' => $booking->id,
                    'referral_id' => $ref->id,
                ],
                'link' => null,
            ]);
        }

        // Ke wali kelas (jika ada)
        if ($waliGuruUser) {
            Notification::create([
                'user_id' => $waliGuruUser->id,
                'type' => 'private_session_scheduled_wk',
                'title' => 'Jadwal Konseling Siswa',
                'message' => "{$siswaName} dijadwalkan konseling privat {$start}",
                'data' => [
                    'slot_id' => $slot->id,
                    'booking_id' => $booking->id,
                    'referral_id' => $ref->id,
                ],
                'link' => route('guru.tren_emosi.index'),
            ]);
        }

        // Ke Guru BK (penganggar) - memastikan user id terset
        if ($guruBkUser) {
            Notification::create([
                'user_id' => $guruBkUser->id,
                'type' => 'private_session_scheduled_bk',
                'title' => 'Konseling Privat Dijadwalkan',
                'message' => "Slot privat dengan {$siswaName} berhasil dibuat untuk {$start}",
                'data' => [
                    'slot_id' => $slot->id,
                    'booking_id' => $booking->id,
                    'referral_id' => $ref->id,
                ],
                'link' => route('guru.guru_bk.slots.view'),
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

    /**
     * Notifikasi ke siswa saat booking konseling dibatalkan oleh Guru BK
     */
    public static function notifyBookingCanceled(SlotBooking $booking, $cancelReason)
    {
        $siswaUser = $booking->siswaKelas->siswa->user ?? null;
        if (!$siswaUser) return;

        $guruName = optional($booking->slot->guru->user)->name ?? 'Guru BK';
        $slotDate = $booking->slot->start_at->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $slotTime = $booking->slot->start_at->format('H:i') . ' - ' . $booking->slot->end_at->format('H:i');

        Notification::create([
            'user_id' => $siswaUser->id,
            'type' => 'booking_canceled',
            'title' => 'Konseling Dibatalkan',
            'message' => "Konseling dengan {$guruName} pada {$slotDate} pukul {$slotTime} telah dibatalkan.\nAlasan: {$cancelReason}",
            'data' => [
                'booking_id' => $booking->id,
                'slot_id' => $booking->slot_id,
                'cancel_reason' => $cancelReason,
                'guru_name' => $guruName,
            ],
            'link' => null, // atau bisa link ke halaman private session siswa
        ]);
    }

    /**
     * Notifikasi ke Guru BK saat siswa membatalkan booking konseling
     */
    public static function notifyGuruBkBookingCanceledBySiswa(SlotBooking $booking, $cancelReason)
    {
        $guruUser = $booking->slot->guru->user ?? null;
        if (!$guruUser) return;

        $siswaName = optional($booking->siswaKelas->siswa->user)->name ?? 'Siswa';
        $siswaIdentifier = optional($booking->siswaKelas->siswa->user)->identifier ?? '';
        $kelasLabel = optional($booking->siswaKelas->kelas)->label ?? '';
        $slotDate = $booking->slot->start_at->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $slotTime = $booking->slot->start_at->format('H:i') . ' - ' . $booking->slot->end_at->format('H:i');

        Notification::create([
            'user_id' => $guruUser->id,
            'type' => 'booking_canceled_by_siswa',
            'title' => 'Siswa Membatalkan Booking Konseling',
            'message' => "{$siswaName} ({$siswaIdentifier}) dari kelas {$kelasLabel} membatalkan booking konseling pada {$slotDate} pukul {$slotTime}.\nAlasan: {$cancelReason}",
            'data' => [
                'booking_id' => $booking->id,
                'slot_id' => $booking->slot_id,
                'siswa_name' => $siswaName,
                'siswa_identifier' => $siswaIdentifier,
                'kelas_label' => $kelasLabel,
                'cancel_reason' => $cancelReason,
            ],
            'link' => route('guru.bk.dashboard'),
        ]);
    }
}
