<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\SiswaKelas;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RosterImportController extends Controller
{
    public function index()
    {
        $years = TahunAjaran::orderByDesc('id')->get();
        $activeYear = TahunAjaran::where('is_active', true)->first();
        return view('roles.admin.roster.index', compact('years', 'activeYear'));
    }

    // Download CSV template
    public function template()
    {
        $filename = 'roster_template.csv';
        $content = "nis,tingkat,jurusan,rombel\n".
                   "1234567890,X,IPA,1\n".
                   "1234567891,XI,IPS,3\n";
        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran_id' => 'required|exists:tahun_ajarans,id',
            'mode' => 'required|in:merge,replace',
            'auto_create' => 'nullable|boolean',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $yearId = (int)$data['tahun_ajaran_id'];
        $autoCreate = $request->boolean('auto_create');

        $path = $request->file('file')->store('roster_uploads');
        $full = Storage::path($path);

        $fh = fopen($full, 'r');
        if (!$fh) {
            return back()->withErrors(['file' => 'Tidak bisa membaca file.']);
        }

        // Read header
        $header = fgetcsv($fh);
        $normalize = fn($s) => strtolower(trim((string)$s));
        $expected = ['nis','tingkat','jurusan','rombel'];
        $hdr = array_map($normalize, $header ?: []);
        foreach ($expected as $idx => $col) {
            if (!in_array($col, $hdr)) {
                fclose($fh);
                return back()->withErrors(['file' => 'Header CSV tidak valid. Wajib: '.implode(',', $expected)]);
            }
        }
        $idx = array_flip($hdr);

        $rows = [];
        $errors = [];
        $line = 1;
        while (($r = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($r, fn($v)=>trim((string)$v)!==''))===0) continue; // skip blank
            $nis = trim((string)($r[$idx['nis']] ?? ''));
            $tingkat = strtoupper(trim((string)($r[$idx['tingkat']] ?? '')));
            $jurusan = trim((string)($r[$idx['jurusan']] ?? ''));
            $rombel = trim((string)($r[$idx['rombel']] ?? ''));

            if ($nis==='') { $errors[] = "Baris $line: NIS kosong"; continue; }
            if ($tingkat==='') { $errors[] = "Baris $line: Tingkat kosong (X/XI/XII)"; continue; }
            if (!in_array($tingkat, ['X','XI','XII','10','11','12'])) { $errors[] = "Baris $line: Tingkat tidak valid"; continue; }
            if ($jurusan==='') { $errors[] = "Baris $line: Jurusan kosong"; continue; }
            if ($rombel==='') { $errors[] = "Baris $line: Rombel kosong"; continue; }

            $rows[] = compact('nis','tingkat','jurusan','rombel');
        }
        fclose($fh);

        // Validate references & build mapping
        $summary = [
            'total' => count($rows),
            'found_users' => 0,
            'created_jurusan' => 0,
            'created_kelas' => 0,
        ];

        $jurusanCache = [];
        $kelasCache = [];
        $validated = [];

        foreach ($rows as $i => $row) {
            $rowErr = [];
            $user = User::where('identifier', $row['nis'])->where('role','siswa')->first();
            if (!$user) {
                $rowErr[] = 'NIS tidak ditemukan';
            }
            $siswaId = $user?->id;

            // resolve tingkat numeric
            $tingkat = $row['tingkat'];
            if (in_array($tingkat, ['10','11','12'])) $tingkat = ['10'=>'X','11'=>'XI','12'=>'XII'][$tingkat];

            // Jurusan by name in target year
            $jurKey = strtolower($row['jurusan'])."|$yearId";
            if (!isset($jurusanCache[$jurKey])) {
                $j = Jurusan::where('tahun_ajaran_id', $yearId)->where('nama', $row['jurusan'])->first();
                if (!$j && $autoCreate) {
                    $j = Jurusan::create(['tahun_ajaran_id' => $yearId, 'nama' => $row['jurusan']]);
                    $summary['created_jurusan']++;
                }
                if ($j) $jurusanCache[$jurKey] = $j; else $rowErr[] = 'Jurusan tidak ada (non-auto-create)';
            }
            $jurusanModel = $jurusanCache[$jurKey] ?? null;

            // Kelas by (tingkat, jurusan_id, rombel) in target year
            $kelasKey = "$tingkat|".($jurusanModel?->id ?? 0)."|".$row['rombel']."|$yearId";
            if (!isset($kelasCache[$kelasKey])) {
                $k = Kelas::where('tahun_ajaran_id', $yearId)
                    ->where('tingkat', $tingkat)
                    ->where('jurusan_id', $jurusanModel?->id)
                    ->where('rombel', $row['rombel'])->first();
                if (!$k && $autoCreate && $jurusanModel) {
                    $k = Kelas::create([
                        'tahun_ajaran_id' => $yearId,
                        'tingkat' => $tingkat,
                        'jurusan_id' => $jurusanModel->id,
                        'rombel' => $row['rombel'],
                        'kurikulum' => null,
                        'wali_guru_id' => null,
                    ]);
                    $summary['created_kelas']++;
                }
                if ($k) $kelasCache[$kelasKey] = $k; else $rowErr[] = 'Kelas tidak ada (non-auto-create atau jurusan tidak ada)';
            }
            $kelasModel = $kelasCache[$kelasKey] ?? null;

            if ($user && $kelasModel) $summary['found_users']++;

            $validated[] = [
                'row' => $row,
                'user_id' => $user?->id,
                'kelas_id' => $kelasModel?->id,
                'errors' => $rowErr,
            ];
        }

        $token = (string) Str::uuid();
        $payload = [
            'year_id' => $yearId,
            'mode' => $data['mode'],
            'auto_create' => $autoCreate,
            'validated' => $validated,
        ];
        Storage::put("roster_previews/{$token}.json", json_encode($payload));

        return view('roles.admin.roster.preview', [
            'token' => $token,
            'summary' => $summary,
            'validated' => $validated,
            'year' => TahunAjaran::find($yearId),
            'mode' => $data['mode'],
            'autoCreate' => $autoCreate,
            'errors' => $errors,
        ]);
    }

    public function commit(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
        ]);
        $path = "roster_previews/{$data['token']}.json";
        if (!Storage::exists($path)) {
            return back()->withErrors(['token' => 'Preview tidak ditemukan / sudah kedaluwarsa.']);
        }
        $payload = json_decode(Storage::get($path), true);
        $yearId = (int) ($payload['year_id'] ?? 0);
        $mode = $payload['mode'] ?? 'merge';
        $validated = $payload['validated'] ?? [];

        // If replace: clear existing SiswaKelas for target year (soft approach: delete only current active rows)
        if ($mode === 'replace') {
            SiswaKelas::where('tahun_ajaran_id', $yearId)->delete();
        }

        $created = 0; $updated = 0; $skipped = 0; $failed = 0;
        foreach ($validated as $v) {
            if (!empty($v['errors'])) { $failed++; continue; }
            $sid = $v['user_id']; $kid = $v['kelas_id'];
            if (!$sid || !$kid) { $failed++; continue; }

            $exists = SiswaKelas::where('siswa_id', $sid)->where('tahun_ajaran_id', $yearId)->first();
            if ($exists) {
                if ($exists->kelas_id == $kid) { $skipped++; continue; }
                $exists->update(['kelas_id' => $kid, 'is_active' => true, 'left_at' => null]);
                $updated++;
                continue;
            }
            SiswaKelas::create([
                'siswa_id' => $sid,
                'kelas_id' => $kid,
                'tahun_ajaran_id' => $yearId,
                'is_active' => true,
                'joined_at' => now(),
            ]);
            $created++;
        }

        // Cleanup preview file
        Storage::delete($path);

        return redirect()->route('admin.roster.index')
            ->with('status', "Import selesai: dibuat=$created, diupdate=$updated, dilewati=$skipped, gagal=$failed");
    }
}
