<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CounselingReferral;
use App\Models\SiswaKelas;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounselingReferralController extends Controller
{
    private function isGuestGuruBk(Request $request): bool
    {
        return $request->hasSession()
            && (bool) $request->session()->get('guest_mode', false)
            && $request->session()->get('guest_role') === 'guru-bk';
    }

    /**
     * Store a new referral (wali kelas or non-BK guru marks student needs attention).
     */
    public function store(Request $request)
    {
        if ($this->isGuestGuruBk($request)) {
            return redirect()->back()->with('warning', 'Mode guest: pengajuan/jadwal konseling privat dinonaktifkan.');
        }

        try {
            $data = $request->validate([
                'siswa_kelas_id' => ['required','integer','exists:siswa_kelass,id'],
                'notes' => ['nullable','string','max:1000'],
            ]);

            $user = $request->user();
            // Guard: hanya guru non-BK yang mengajukan referral.
            // Guru BK diarahkan untuk menjadwalkan langsung dari analisis / dashboard BK.
            if ($user->role !== 'guru') {
                return redirect()->back()->with('error', 'Hanya guru yang dapat mengajukan referral.');
            }
            $guru = $user->guru;
            if ($guru && $guru->jenis === 'bk') {
                return redirect()->back()->with('error', 'Guru BK dapat langsung menjadwalkan konseling privat tanpa mengajukan referral. Gunakan tombol "Jadwalkan Konseling Privat" pada analisis atau dashboard BK.');
            }
            
            // Validasi siswa_kelas exists
            $siswaKelas = SiswaKelas::find($data['siswa_kelas_id']);
            if (!$siswaKelas) {
                return redirect()->back()->with('error', 'Data siswa tidak ditemukan.');
            }
            
            // Cek apakah sudah ada referral pending/accepted untuk siswa ini
            $existingReferral = CounselingReferral::where('siswa_kelas_id', $data['siswa_kelas_id'])
                ->whereIn('status', ['pending', 'accepted'])
                ->first();
            
            if ($existingReferral) {
                return redirect()->back()->with('warning', 'Referral untuk siswa ini sedang dalam proses.');
            }

            $ref = CounselingReferral::create([
                'siswa_kelas_id' => $data['siswa_kelas_id'],
                'submitted_by_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);
            // Notifikasi ke seluruh Guru BK
            NotificationHelper::notifyReferralSubmitted($ref);

            return redirect()->back()->with('success','Referral konseling berhasil diajukan ke Guru BK.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error creating referral: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'data' => $data ?? [],
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Gagal mengajukan referral: ' . $e->getMessage());
        }
    }

    /**
     * Accept referral (Guru BK) -> redirect to private slot creation form.
     */
    public function accept(Request $request, int $id)
    {
        if ($this->isGuestGuruBk($request)) {
            return redirect()->back()->with('warning', 'Mode guest: pengajuan/jadwal konseling privat dinonaktifkan.');
        }

        $ref = CounselingReferral::pending()->findOrFail($id);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru || $user->guru->jenis !== 'bk') {
            abort(403,'Hanya Guru BK yang dapat menerima referral.');
        }
        // Mark accepted
        $ref->markAccepted($user);

        // Notifikasi ke pengaju referral
        NotificationHelper::notifyReferralAccepted($ref);

        // Redirect ke form pembuatan slot privat (param route adalah {id})
        return redirect()->route('guru.guru_bk.private_slots.create',[ 'id' => $ref->id ])
            ->with('info','Referral diterima. Silakan jadwalkan slot privat.');
    }

    /**
     * Show private scheduling form (Guru BK) for a referral.
     */
    public function createPrivateSlot(Request $request, int $referralId)
    {
        if ($this->isGuestGuruBk($request)) {
            return redirect()->route('guru.guru_bk.slots.view')->with('warning', 'Mode guest: pengajuan/jadwal konseling privat dinonaktifkan.');
        }

        $ref = CounselingReferral::findOrFail($referralId);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru || $user->guru->jenis !== 'bk') {
            abort(403,'Akses ditolak.');
        }
        if (!in_array($ref->status,['accepted','pending'])) {
            return redirect()->back()->with('warning','Referral sudah diproses.');
        }
        // Ensure accepted - auto accept if still pending and user is BK
        if ($ref->status === 'pending') {
            $ref->markAccepted($user);
        }
        $siswaKelas = $ref->siswaKelas()->with(['siswa.user','kelas'])->first();

        // Ringkasan jadwal Guru BK (7 hari ke depan) untuk membantu memilih waktu
        $upcomingSlots = collect();
        $guru = $user->guru;
        if ($guru) {
            $nowWita = now()->setTimezone('Asia/Makassar');
            $upcomingSlots = SlotKonseling::with(['bookings.siswaKelas.siswa.user'])
                ->where('guru_id', $guru->user_id)
                ->where('status', 'published')
                ->where('start_at', '>=', $nowWita->copy()->startOfDay())
                ->where('start_at', '<=', $nowWita->copy()->addDays(7)->endOfDay())
                ->orderBy('start_at')
                ->get();
        }

        return view('roles.guru.guru_bk.private_slot_create',[
            'referral' => $ref,
            'siswaKelas' => $siswaKelas,
            'upcomingSlots' => $upcomingSlots,
        ]);
    }

    /**
     * Store private slot and booking for accepted referral (Guru BK).
     */
    public function schedule(Request $request, int $referralId)
    {
        if ($this->isGuestGuruBk($request)) {
            return redirect()->back()->with('warning', 'Mode guest: pengajuan/jadwal konseling privat dinonaktifkan.');
        }

        $ref = CounselingReferral::findOrFail($referralId);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru || $user->guru->jenis !== 'bk') {
            abort(403,'Akses ditolak.');
        }
        if (!in_array($ref->status,['accepted','pending'])) {
            return redirect()->back()->with('warning','Referral sudah diproses.');
        }

        $data = $request->validate([
            'tanggal' => ['required','date'],
            'start_time' => ['required','date_format:H:i'],
            'durasi_menit' => ['required','integer','min:10','max:240'],
            'lokasi' => ['nullable','string','max:191'],
            'notes' => ['nullable','string','max:1000'],
        ]);
        // Pastikan durasi integer (Laravel validation masih bisa mengembalikan string numerik)
        $duration = (int) $data['durasi_menit'];
        $startAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $data['tanggal'].' '.$data['start_time'], 'Asia/Makassar');
        $endAt = (clone $startAt)->addMinutes($duration);

        // Cek bentrok jadwal: guru_id + start_at sudah ada (massal / privat)
        $guruId = $user->guru->user_id;
        $conflict = SlotKonseling::where('guru_id', $guruId)
            ->where('start_at', $startAt)
            ->exists();
        if ($conflict) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Jadwal tersebut sudah terpakai (ada slot konseling lain pada waktu yang sama). Silakan pilih jam lain atau sesuaikan durasi.');
        }

        DB::beginTransaction();
        try {
            // Create slot private
            /** @var SlotKonseling $slot */
            $slot = SlotKonseling::create([
                'guru_id' => $user->guru->user_id,
                'tanggal' => $data['tanggal'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'durasi_menit' => $duration,
                'booked_count' => 0,
                'status' => 'published',
                'lokasi' => $data['lokasi'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_private' => true,
                'target_siswa_kelas_id' => $ref->siswa_kelas_id,
            ]);

            // Create booking for the student
            /** @var SlotBooking $booking */
            $booking = SlotBooking::create([
                'slot_id' => $slot->id,
                'siswa_kelas_id' => $ref->siswa_kelas_id,
                'status' => 'booked',
            ]);
            // update slot booked count
            $slot->booked_count = 1;
            $slot->save();

            // Link back to referral
            $ref->attachSchedule($slot,$booking);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            // Tangani error unik jadwal dengan pesan yang lebih ramah
            if ($e instanceof \Illuminate\Database\QueryException && $e->getCode() === '23000' && str_contains($e->getMessage(), 'slot_konselings_guru_id_start_at_unique')) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Jadwal tersebut sudah terpakai untuk Guru BK ini. Silakan pilih waktu lain yang masih kosong.');
            }

            return redirect()->back()->with('error','Gagal menjadwalkan: '.$e->getMessage());
        }

        // Notifikasi jadwal privat (siswa, wali kelas, guru BK)
        NotificationHelper::notifyPrivateSessionScheduled($ref, $slot, $booking);

        // Redirect ke tampilan daftar slot konseling BK agar guru langsung melihat slot baru
        return redirect()->route('guru.guru_bk.slots.view')->with('success','Konseling privat berhasil dijadwalkan');
    }

    /**
     * Guru BK langsung membuat referral ter-accept dari halaman analisis (tanpa pengajuan pihak lain)
     * lalu diarahkan ke form jadwal privat.
     */
    public function createFromAnalysis(Request $request, int $analisisId)
    {
        if ($this->isGuestGuruBk($request)) {
            return redirect()->back()->with('warning', 'Mode guest: pengajuan/jadwal konseling privat dinonaktifkan.');
        }

        try {
            $user = $request->user();
            if ($user->role !== 'guru' || !$user->guru || $user->guru->jenis !== 'bk') {
                return redirect()->back()->with('error', 'Hanya Guru BK yang dapat menjadwalkan konseling privat.');
            }

            $analisis = \App\Models\AnalisisEntry::with('siswaKelas.siswa.user','siswaKelas.kelas')->findOrFail($analisisId);
            
            // Validasi siswaKelas exists
            if (!$analisis->siswaKelas) {
                return redirect()->back()->with('error', 'Data siswa tidak ditemukan.');
            }

            // Cari referral existing yang belum dijadwalkan untuk siswa ini
            $existing = CounselingReferral::where('siswa_kelas_id',$analisis->siswa_kelas_id)
                ->whereIn('status',['pending','accepted'])
                ->first();
            
            if (!$existing) {
                $ref = CounselingReferral::create([
                    'siswa_kelas_id' => $analisis->siswa_kelas_id,
                    'submitted_by_user_id' => $user->id, // self-init
                    'status' => 'pending',
                    'notes' => 'Auto referral dari AnalisisEntry #'.$analisis->id,
                ]);
                // langsung accept
                $ref->markAccepted($user);
                // tidak kirim notifikasi submitted (redundan) namun tetap informasikan acceptance ke diri sendiri? Skip.
            } else {
                // Pastikan accepted jika masih pending
                if ($existing->status === 'pending') {
                    $existing->markAccepted($user);
                }
                $ref = $existing;
            }
            
            return redirect()->route('guru.guru_bk.private_slots.create', ['id' => $ref->id])
                ->with('info', 'Silakan jadwalkan konseling privat untuk siswa ini.');
        } catch (\Exception $e) {
            Log::error('Error creating referral from analysis: ' . $e->getMessage(), [
                'analisis_id' => $analisisId,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Gagal membuat referral: ' . $e->getMessage());
        }
    }
}
