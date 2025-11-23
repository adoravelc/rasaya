<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CounselingReferral;
use App\Models\SiswaKelas;
use App\Models\SlotKonseling;
use App\Models\SlotBooking;
use App\Helpers\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounselingReferralController extends Controller
{
    /**
     * Store a new referral (wali kelas or non-BK guru marks student needs attention).
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'siswa_kelas_id' => ['required','integer','exists:siswa_kelass,id'],
                'notes' => ['nullable','string','max:1000'],
            ]);

            $user = $request->user();
            // Basic guard: must be guru (role guru) and NOT guru BK for referral; guru BK can schedule directly.
            if ($user->role !== 'guru') {
                return redirect()->back()->with('error', 'Hanya guru yang dapat mengajukan referral.');
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
        $ref = CounselingReferral::pending()->findOrFail($id);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru) {
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
        $ref = CounselingReferral::findOrFail($referralId);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru) {
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

        return view('roles.guru.guru_bk.private_slot_create',[
            'referral' => $ref,
            'siswaKelas' => $siswaKelas,
        ]);
    }

    /**
     * Store private slot and booking for accepted referral (Guru BK).
     */
    public function schedule(Request $request, int $referralId)
    {
        $ref = CounselingReferral::findOrFail($referralId);
        $user = $request->user();
        if ($user->role !== 'guru' || !$user->guru) {
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
