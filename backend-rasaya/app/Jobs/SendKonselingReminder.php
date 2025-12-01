<?php

namespace App\Jobs;

use App\Models\SlotBooking;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendKonselingReminder implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     * Send push notification reminder 1 hour before konseling session
     */
    public function handle(): void
    {
        // Get all bookings starting in exactly 1 hour
        $oneHourLater = now()->addHour();
        
        $upcomingBookings = SlotBooking::with(['slot.guru.user', 'siswaKelas.siswa.user'])
            ->where('status', 'booked')
            ->whereHas('slot', function($q) use ($oneHourLater) {
                $q->where('status', 'published')
                  ->whereBetween('start_at', [
                      $oneHourLater->copy()->subMinutes(5),
                      $oneHourLater->copy()->addMinutes(5)
                  ]);
            })
            ->get();
        
        foreach ($upcomingBookings as $booking) {
            try {
                $siswaUser = $booking->siswaKelas->siswa->user;
                $guruName = $booking->slot->guru->user->name ?? 'Guru BK';
                $startTime = $booking->slot->start_at->format('H:i');
                
                // Create notification in database
                \App\Models\Notification::create([
                    'user_id' => $siswaUser->id,
                    'type' => 'konseling_reminder_siswa',
                    'title' => 'Reminder: Konseling 1 Jam Lagi!',
                    'message' => "Anda memiliki jadwal konseling dengan {$guruName} pada pukul {$startTime} WITA. Jangan lupa hadir tepat waktu!",
                    'data' => [
                        'booking_id' => $booking->id,
                        'slot_id' => $booking->slot_id,
                        'guru_name' => $guruName,
                        'start_time' => $startTime,
                    ],
                    'link' => null,
                ]);
                
                $this->sendPushNotification($siswaUser, [
                    'title' => 'Reminder: Konseling 1 Jam Lagi!',
                    'body' => "Konseling dengan {$guruName} pada {$startTime} WITA",
                    'data' => [
                        'type' => 'konseling_reminder',
                        'booking_id' => $booking->id,
                        'slot_id' => $booking->slot_id,
                    ],
                ]);
                
                Log::info("Konseling reminder sent to {$siswaUser->name} for booking #{$booking->id}");
                
            } catch (\Exception $e) {
                Log::error("Failed to send konseling reminder: " . $e->getMessage());
            }
        }
    }
    
    private function sendPushNotification(User $user, array $payload)
    {
        if (!isset($user->fcm_token) || empty($user->fcm_token)) {
            Log::warning("User {$user->id} has no FCM token, skipping push notification");
            return;
        }
        
        $fcmServerKey = env('FIREBASE_SERVER_KEY');
        
        if (empty($fcmServerKey)) {
            Log::warning("FIREBASE_SERVER_KEY not configured");
            return;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $fcmServerKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $user->fcm_token,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                    'sound' => 'default',
                    'badge' => '1',
                ],
                'data' => $payload['data'],
                'priority' => 'high',
            ]);
            
            if ($response->successful()) {
                Log::info("Push notification sent to user {$user->id}");
            } else {
                Log::error("Failed to send push notification: " . $response->body());
            }
            
        } catch (\Exception $e) {
            Log::error("Exception while sending push notification: " . $e->getMessage());
        }
    }
}
