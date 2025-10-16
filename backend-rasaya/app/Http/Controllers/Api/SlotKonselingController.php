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

        $q = SlotKonseling::where('guru_id', $guruId)->orderBy('start_at');
        if ($r->filled('status'))
            $q->where('status', $r->string('status'));
        if ($r->filled('from'))
            $q->where('start_at', '>=', $r->date('from'));
        if ($r->filled('to'))
            $q->where('start_at', '<=', $r->date('to'));

        return response()->json($q->paginate($r->integer('per_page', 20)));
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
            'days.*' => ['integer', 'between:1,7'],

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

        DB::transaction(function () use (&$generated, $d1, $d2, $data, $guruId, $tz, $startClock, $endClock, $interval, $durasi) {
            for ($d = $d1->copy(); $d->lte($d2); $d->addDay()) {
                // 1=Mon..7=Sun
                if (!in_array($d->dayOfWeekIso, $data['days'], false)) {
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
                        break;
                    }

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

                    if ($slot->wasRecentlyCreated) {
                        $generated++;
                    }
                }
            }
        });

        return response()->json(['generated' => $generated], 201);
    }

    public function cancel(Request $r, int $id)
    {
        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;

        $slot = SlotKonseling::where('guru_id', $guruId)->findOrFail($id);
        if (in_array($slot->status, ['canceled', 'archived'])) {
            return response()->json(['message' => 'Slot sudah tidak aktif'], 422);
        }

        DB::transaction(function () use ($slot) {
            $slot->update(['status' => 'canceled']);

            // auto-cancel booking aktif (jika ada)
            $slot->bookings()
                ->whereIn('status', ['booked', 'held'])
                ->update(['status' => 'canceled', 'cancel_reason' => 'dibatalkan oleh guru']);
        });

        return response()->json(['ok' => true]);
    }

    public function archive(Request $r, int $id)
    {
        $guruId = optional($r->user()->guru)->user_id ?? $r->user()->id;

        $slot = SlotKonseling::where('guru_id', $guruId)->findOrFail($id);
        $slot->update(['status' => 'archived']);

        return response()->json(['ok' => true]);
    }
}