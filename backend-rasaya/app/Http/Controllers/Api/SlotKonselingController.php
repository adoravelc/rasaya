<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotKonseling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;

class SlotKonselingController extends Controller
{
    public function index(Request $r)
    {
        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;

        // Waktu sekarang dalam WITA (UTC+8)
        $nowWita = now()->setTimezone('Asia/Makassar');

        $q = SlotKonseling::where('guru_id', $guruId)
            // Filter: hanya tampilkan slot yang end_at belum lewat
                ->where('start_at', '>', $nowWita);
            
        // map availability to current schema (status='published' + booked_count)
        if ($r->filled('availability')) {
            $av = (string) $r->input('availability');
            if ($av === 'available') {
                $q->where('status', 'published')->where(function($qq){
                    $qq->whereNull('booked_count')->orWhere('booked_count', 0);
                });
            } elseif ($av === 'booked') {
                $q->where(function($qq){
                    $qq->where('booked_count', '>', 0);
                });
            }
        }
        if ($r->filled('from'))
            $q->where('start_at', '>=', $r->date('from'));
        if ($r->filled('to'))
            $q->where('start_at', '<=', $r->date('to'));

        // Sort: booked terlebih dahulu (descending booked_count), lalu by start_at
        $q->orderByDesc('booked_count')->orderBy('start_at');

        return response()->json($q->paginate($r->integer('per_page', 20)));
    }

    public function show(Request $r, int $id)
    {
        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;
        $slot = SlotKonseling::with([
                'bookings.siswaKelas.siswa.user',
                'bookings.siswaKelas.kelas.jurusan',
                'bookings.siswaKelas.tahunAjaran',
                'guru.user',
            ])
            ->where('guru_id', $guruId)
            ->findOrFail($id);

        return response()->json($slot);
    }

    /**
     * Publish/generate slots massal ala Google Calendar appointment.
     */
    public function publish(Request $r)
    {
        $data = $r->validate([
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'days' => ['required', 'array', 'min:1'],
            // Accept either ISO 1..7 (Mon..Sun) or JS-style 0..6 (Sun..Sat)
            'days.*' => ['integer', 'between:0,7'],

            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],

            'interval' => ['required', 'integer', 'in:15,20,30,45,60'],
            'durasi' => ['required', 'integer', 'in:15,20,30,45,60'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;

        $tz = new CarbonTimeZone('Asia/Makassar');

        // --- helper parse jam: terima "13:30", "1:30 PM", "01:30 pm", "13.30"
        $parseTime = function (string $s) use ($tz): Carbon {
            $s = trim($s);
            $fmts = ['H:i', 'G:i', 'H.i', 'G.i', 'h:i A', 'g:i A', 'h:i a', 'g:i a'];
            foreach ($fmts as $fmt) {
                try {
                    // return waktu (tanggal dummy hari ini, tapi jam-menit benar)
                    return Carbon::createFromFormat($fmt, $s, $tz);
                } catch (\Exception $e) {
                }
            }
            // fallback ke parser bebas Carbon (bisa "2pm", "14:10")
            return Carbon::parse($s, $tz);
        };

        $d1 = Carbon::parse($data['date_start'], $tz)->startOfDay();
        $d2 = Carbon::parse($data['date_end'], $tz)->endOfDay();

        $startClock = $parseTime($data['start_time']); // jam-menit saja
        $endClock = $parseTime($data['end_time']);

        $interval = (int) $data['interval'];
        $durasi = (int) $data['durasi'];

    $generated = 0;
    $existing = 0;
    $skippedWindow = 0;
    $attempted = 0;

        // Normalize provided days to ISO (1=Mon..7=Sun)
        $daysIso = collect($data['days'])
            ->map(function ($d) {
                $di = (int) $d;
                if ($di === 0) return 7; // map Sunday 0 -> 7
                return max(1, min(7, $di));
            })
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use (&$generated, &$existing, &$skippedWindow, &$attempted, $d1, $d2, $daysIso, $guruId, $tz, $startClock, $endClock, $interval, $durasi, $data) {
            for ($d = $d1->copy(); $d->lte($d2); $d->addDay()) {
                // 1=Mon..7=Sun
                if (!in_array($d->dayOfWeekIso, $daysIso, false)) {
                    continue;
                }

                // gabungkan tanggal + jam
                $startT = $d->copy()->setTime($startClock->hour, $startClock->minute, 0);
                $endT = $d->copy()->setTime($endClock->hour, $endClock->minute, 0);

                // guard: kalau jam selesai <= jam mulai, skip hari ini agar tidak infinite loop
                if ($endT->lte($startT)) {
                    continue;
                }

                for ($cur = $startT->copy(); $cur->lt($endT); $cur->addMinutes($interval)) {
                    $slotStartLocal = $cur->copy();
                    $slotEndLocal = $slotStartLocal->copy()->addMinutes($durasi);

                    // pastikan slot tidak melewati batas window
                    if ($slotEndLocal->gt($endT)) {
                        $skippedWindow++;
                        break;
                    }

                    $attempted++;
                    $slot = SlotKonseling::firstOrCreate(
                        [
                            'guru_id' => $guruId,
                            'start_at' => $slotStartLocal
                        ],
                        [
                            'tanggal' => $slotStartLocal->toDateString(), // tanggal lokal
                            'end_at' => $slotEndLocal,
                            'durasi_menit' => $durasi,
                            'booked_count' => 0,
                            'status' => 'published',
                            'lokasi' => $data['lokasi'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]
                    );

                    $slot->wasRecentlyCreated ? $generated++ : $existing++;
                }
            }
        });

        return response()->json([
            'generated' => $generated,
            'existing' => $existing,
            'skipped' => $skippedWindow,
            'attempted' => $attempted,
            'days_iso' => $daysIso,
        ], 201);
    }

    public function destroy(Request $r, int $id)
    {
        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;
        $slot = SlotKonseling::where('guru_id', $guruId)->findOrFail($id);
        // Hard delete; if there are related bookings, you may want to restrict or cascade
        $slot->delete();
        return response()->noContent();
    }
}