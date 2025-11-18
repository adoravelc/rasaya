<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\YearRolloverJob;
use App\Models\TahunAjaran;
use App\Models\YearCopyRun;
use App\Models\Jurusan;
use App\Models\Kelas;
use Illuminate\Http\Request;

class YearRolloverController extends Controller
{
    public function create()
    {
        $years = TahunAjaran::orderByDesc('id')->get();
        $activeYear = TahunAjaran::where('is_active', true)->first();
        return view('roles.admin.rollover.index', compact('years', 'activeYear'));
    }

    public function dryRun(Request $request)
    {
        $data = $request->validate([
            'source_year_id' => 'required|exists:tahun_ajarans,id',
            'target_year_id' => 'required|exists:tahun_ajarans,id',
            'copy' => 'array'
        ]);

        $src = (int) $data['source_year_id'];
        $dst = (int) $data['target_year_id'];
        $copy = collect($data['copy'] ?? []);

        $summary = [];
        $conflicts = [];

        if ($copy->contains('jurusan')) {
            $summary['jurusan'] = Jurusan::where('tahun_ajaran_id', $src)->count();
            // konflik: nama jurusan sudah ada di target
            $srcNames = Jurusan::where('tahun_ajaran_id', $src)->pluck('nama')->all();
            if ($srcNames) {
                $dups = Jurusan::where('tahun_ajaran_id', $dst)->whereIn('nama', $srcNames)->pluck('nama')->all();
                foreach ($dups as $n) {
                    $conflicts[] = ['type' => 'jurusan', 'key' => $n, 'reason' => 'nama sudah ada di tahun tujuan'];
                }
            }
        }

        if ($copy->contains('kelas')) {
            $summary['kelas'] = Kelas::where('tahun_ajaran_id', $src)->count();
            $srcNames = Kelas::where('tahun_ajaran_id', $src)->pluck('label')->all();
            if ($srcNames) {
                $dups = Kelas::where('tahun_ajaran_id', $dst)->whereIn('label', $srcNames)->pluck('label')->all();
                foreach ($dups as $n) {
                    $conflicts[] = ['type' => 'kelas', 'key' => $n, 'reason' => 'label sudah ada di tahun tujuan'];
                }
            }
        }

        return response()->json([
            'ok' => true,
            'summary' => $summary,
            'conflicts' => $conflicts,
        ]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'source_year_id' => 'required|exists:tahun_ajarans,id',
            'target_year_id' => 'required|exists:tahun_ajarans,id',
            'copy' => 'array',
            'resolution' => 'array',
        ]);

        $run = YearCopyRun::create([
            'source_year_id' => $data['source_year_id'],
            'target_year_id' => $data['target_year_id'],
            'options' => $data['copy'] ?? [],
            'resolution' => $data['resolution'] ?? [],
            'status' => 'queued',
            'progress' => 0,
            'created_by' => $request->user()->id,
        ]);

        YearRolloverJob::dispatch($run);

        return redirect()->route('admin.rollover.show', $run->id);
    }

    public function show(YearCopyRun $run)
    {
        return view('roles.admin.rollover.show', compact('run'));
    }

    public function json(YearCopyRun $run)
    {
        return response()->json($run->toArray());
    }
}
