<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AnalisisEntry;
use App\Models\InputGuru;
use App\Models\InputSiswa;
use App\Models\SiswaKelas;
use App\Services\AnalisisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnalisisEntryController extends Controller
{
    public function __construct(private AnalisisService $svc)
    {
    }

    public function index(Request $r)
    {
        // daftar hasil analisis; wali kelas hanya melihat siswanya sendiri
        $q = AnalisisEntry::query()->with(['rekomendasis', 'siswaKelas.kelas', 'siswaKelas.siswa.user'])->latest()
            ->where('created_by', $r->user()->id);

        $guru = optional($r->user())->guru;
        if ($guru && $guru->jenis === 'wali_kelas') {
            $userId = $r->user()->id;
            $q->whereHas('siswaKelas.kelas', function ($qq) use ($userId) {
                $qq->where('wali_guru_id', $userId);
            });
        }

        return view('roles.guru.analisis.index', [
            'rows' => $q->paginate(12)
        ]);
    }

    public function create(Request $r)
    {
        // daftar siswa untuk dropdown
        $guru = optional($r->user())->guru;
        $q = SiswaKelas::with(['siswa.user', 'kelas'])->orderBy('id', 'desc');
        if ($guru && $guru->jenis === 'wali_kelas') {
            $q->whereHas('kelas', function ($qq) use ($r) {
                $qq->where('wali_guru_id', $r->user()->id);
            });
        }
        $siswas = $q->limit(200)->get();
        return view('roles.guru.analisis.create', compact('siswas'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'siswa_kelas_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $isWali = optional($r->user()->guru)->jenis === 'wali_kelas';

        $entry = $this->svc->analisisRentang(
            (int) $data['siswa_kelas_id'],
            $data['from'],
            $data['to'],
            (int) $r->user()->id,
            $isWali,
        );

        return redirect()
            ->route('guru.analisis.show', $entry->id)
            ->with('ok', 'Analisis selesai.');
    }

    public function show(AnalisisEntry $analisis)
    {
        $analisis->load(['rekomendasis', 'siswaKelas.siswa.user', 'siswaKelas.kelas.jurusan', 'createdBy']);

        $isWali = optional(Auth::user()->guru)->jenis === 'wali_kelas';

        // Kumpulkan semua input yang termasuk dalam rentang analisis ini
        $from = optional($analisis->tanggal_awal_proses)?->toDateString();
        $to = optional($analisis->tanggal_akhir_proses)?->toDateString();

        $refleksisSelf = collect();
        $friendReports = collect();
        $guruNotes = collect();

        $used = collect($analisis->used_items ?? []);
        if ($used->isNotEmpty()) {
            // Ambil data tepat yang dipakai saat analisis (snapshot IDs)
            $selfIds = $used->where('type', 'ref_self')->pluck('id')->all();
            $friendIds = $used->where('type', 'ref_friend')->pluck('id')->all();
            $guruIds = $used->where('type', 'guru')->pluck('id')->all();

            if (!empty($selfIds)) {
                $refleksisSelf = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user'])
                    ->whereIn('id', $selfIds)
                    ->where('is_friend', false) // jaga-jaga: filter hanya refleksi diri
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
            if (!empty($friendIds)) {
                $friendReports = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                    ->whereIn('id', $friendIds)
                    ->where('is_friend', true) // jaga-jaga: pastikan ini laporan teman
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
            if (!empty($guruIds)) {
                $guruNotes = InputGuru::with(['kategoris', 'siswaKelas.siswa.user'])
                    ->whereIn('id', $guruIds)
                    ->orderBy('tanggal', 'desc')
                    ->get();
            }
        } elseif ($from && $to) {
            // Fallback untuk analisis lama (sebelum ada snapshot): gunakan rentang tanggal
            $refleksisSelf = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', false)
                ->orderBy('tanggal', 'desc')
                ->get();

            $friendReports = InputSiswa::with(['kategoris', 'siswaKelas.siswa.user', 'siswaDilaporKelas.siswa.user'])
                ->where('siswa_dilapor_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->where('is_friend', true)
                ->orderBy('tanggal', 'desc')
                ->get();

            $gq = InputGuru::with(['kategoris', 'siswaKelas.siswa.user'])
                ->where('siswa_kelas_id', $analisis->siswa_kelas_id)
                ->whereBetween('tanggal', [$from, $to])
                ->orderBy('tanggal', 'desc');
            if (!$isWali) {
                $gq->where('guru_id', $analisis->created_by);
            }
            $guruNotes = $gq->get();
        }

        return view('roles.guru.analisis.show', [
            'analisis' => $analisis,
            'refleksisSelf' => $refleksisSelf,
            'friendReports' => $friendReports,
            'guruNotes' => $guruNotes,
            'isWali' => $isWali,
        ]);
    }

    public function decide(Request $r, AnalisisEntry $analisis, int $rekomId)
    {
        $r->validate(['action' => ['required', Rule::in(['accept', 'reject'])]]);
        $rec = $analisis->rekomendasis()->findOrFail($rekomId);
        $rec->update([
            'status' => $r->action === 'accept' ? 'accepted' : 'rejected',
            'decided_by' => Auth::id(),
            'decided_at' => now(),
        ]);
        return back()->with('ok', 'Keputusan tersimpan.');
    }
}
