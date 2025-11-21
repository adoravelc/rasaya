# Sistem Notifikasi RASAYA

## Overview
Sistem notifikasi real-time untuk memberitahu user tentang event penting dalam aplikasi.

## Jenis Notifikasi

### Admin
- **Password Reset Request**: Ketika user meminta reset password
- **Password Changed**: Ketika user berhasil mengubah password

### Guru BK
- **Konseling Request**: Ketika siswa booking slot konseling
- **Konseling Reminder**: 5 menit sebelum jadwal konseling dimulai

### Guru WK (Wali Kelas)
- **Low Mood Alert**: Ketika siswa mengisi mood tracker dengan skor <5

## Cara Menggunakan

### 1. Membuat Notifikasi

```php
use App\Helpers\NotificationHelper;

// Notifikasi untuk Admin - Password Reset Request
NotificationHelper::notifyAdminPasswordResetRequest($userId, $requestedByUserId);

// Notifikasi untuk Admin - Password Changed
NotificationHelper::notifyAdminPasswordChanged($userId);

// Notifikasi untuk Guru BK - Konseling Request
NotificationHelper::notifyGuruBkKonselingRequest($guruId, $bookingId, $siswaName, $slotTime);

// Notifikasi untuk Guru BK - Konseling Reminder
NotificationHelper::notifyGuruBkKonselingReminder($guruId, $bookingId, $siswaName, $slotTime);

// Notifikasi untuk Guru WK - Low Mood Alert
NotificationHelper::notifyGuruWkLowMood($guruWkId, $siswaName, $moodScore, $siswaKelasId);
```

### 2. Mark as Read

Notifikasi secara otomatis di-mark as read ketika user mengklik notifikasi tersebut.

Manual mark as read:
```php
use App\Helpers\NotificationHelper;

// Mark single notification as read
NotificationHelper::markAsRead($notificationId);

// Mark all notifications as read for current user
NotificationHelper::markAllAsRead(auth()->id());
```

### 3. Contoh Implementasi

#### Admin - Password Reset Request
```php
// Di ForgotPasswordController.php
public function requestReset(Request $request)
{
    // ... validasi dan proses reset password
    
    // Kirim notifikasi ke semua admin
    NotificationHelper::notifyAdminPasswordResetRequest($user->id, $user->id);
    
    return redirect()->route('password.forgot.done');
}
```

#### Guru BK - Konseling Request
```php
// Di SlotBookingController.php (ketika siswa booking)
public function store(Request $request)
{
    $booking = SlotBooking::create([...]);
    
    $slot = $booking->slot;
    $siswa = $booking->siswaKelas->siswa;
    
    // Kirim notifikasi ke guru BK
    NotificationHelper::notifyGuruBkKonselingRequest(
        $slot->guru_id,
        $booking->id,
        $siswa->user->name,
        $slot->start_at->format('d M Y H:i')
    );
    
    return redirect()->back()->with('success', 'Booking berhasil!');
}
```

#### Guru WK - Low Mood Alert
```php
// Di PemantauanEmosiSiswaController.php (ketika siswa submit mood)
public function store(Request $request)
{
    $pemantauan = PemantauanEmosiSiswa::create([...]);
    
    // Jika mood score < 5, kirim notifikasi ke wali kelas
    if ($pemantauan->mood_score < 5) {
        $siswaKelas = $pemantauan->siswaKelas;
        $kelas = $siswaKelas->kelas;
        
        if ($kelas && $kelas->guruWaliKelas) {
            NotificationHelper::notifyGuruWkLowMood(
                $kelas->guruWaliKelas->user_id,
                $pemantauan->siswaKelas->siswa->user->name,
                $pemantauan->mood_score,
                $siswaKelas->id
            );
        }
    }
    
    return redirect()->back();
}
```

## Database Schema

```sql
notifications
├── id
├── user_id (FK to users)
├── type (string)
├── title (string)
├── message (text)
├── data (json)
├── is_read (boolean, default: false)
├── read_at (timestamp, nullable)
├── link (string, nullable)
├── created_at
└── updated_at
```

## Frontend Integration

Notifikasi sudah terintegrasi di topbar untuk semua role (Admin, Guru BK, Guru WK, Siswa).

- Badge merah menunjukkan jumlah notifikasi yang belum dibaca
- Klik notifikasi akan redirect ke link terkait dan mark as read
- Dropdown menampilkan 10 notifikasi terbaru

## Scheduled Tasks (Future)

Untuk konseling reminder (H-5 menit), perlu setup Laravel Scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Check setiap menit untuk konseling yang akan dimulai dalam 5 menit
    $schedule->call(function () {
        $fiveMinutesLater = now()->addMinutes(5);
        
        $upcomingBookings = SlotBooking::with(['slot', 'siswaKelas.siswa.user'])
            ->whereHas('slot', function($q) use ($fiveMinutesLater) {
                $q->whereBetween('start_at', [
                    $fiveMinutesLater->copy()->subMinute(),
                    $fiveMinutesLater->copy()->addMinute()
                ]);
            })
            ->whereIn('status', ['booked'])
            ->get();
        
        foreach ($upcomingBookings as $booking) {
            NotificationHelper::notifyGuruBkKonselingReminder(
                $booking->slot->guru_id,
                $booking->id,
                $booking->siswaKelas->siswa->user->name,
                $booking->slot->start_at->format('H:i')
            );
        }
    })->everyMinute();
}
```

Jalankan scheduler dengan:
```bash
php artisan schedule:work
```

Atau setup cron job:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```
