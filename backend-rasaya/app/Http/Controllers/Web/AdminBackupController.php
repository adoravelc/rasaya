<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBackupController extends Controller
{
    private array $datasets = [
        'users' => ['label' => 'Users', 'table' => 'users'],
        'user_login_histories' => ['label' => 'Login History', 'table' => 'user_login_histories'],
        'siswas' => ['label' => 'Siswa', 'table' => 'siswas'],
        'gurus' => ['label' => 'Guru', 'table' => 'gurus'],
        'jurusans' => ['label' => 'Jurusan', 'table' => 'jurusans'],
        'tahun_ajarans' => ['label' => 'Tahun Ajaran', 'table' => 'tahun_ajarans'],
        'kelas' => ['label' => 'Kelas', 'table' => 'kelas'],
        'siswa_kelass' => ['label' => 'Siswa-Kelas', 'table' => 'siswa_kelass'],
        'input_siswas' => ['label' => 'Input Siswa (Refleksi)', 'table' => 'input_siswas'],
        'pemantauan_emosi_siswas' => ['label' => 'Mood Tracker', 'table' => 'pemantauan_emosi_siswas'],
        'analisis_entries' => ['label' => 'Analisis Input', 'table' => 'analisis_entries'],
        'analisis_rekomendasis' => ['label' => 'Analisis Rekomendasi', 'table' => 'analisis_rekomendasis'],
        'kategori_masalahs' => ['label' => 'Kategori Kecil', 'table' => 'kategori_masalahs'],
        'master_kategori_masalahs' => ['label' => 'Kategori Besar', 'table' => 'master_kategori_masalahs'],
        'master_kategori_masalah_kategori_masalah' => [
            'label' => 'Pivot: Besar-Kecil', 'table' => 'master_kategori_masalah_kategori_masalah'
        ],
        'master_rekomendasis' => ['label' => 'Master Rekomendasi', 'table' => 'master_rekomendasis'],
        'kategori_masalah_master_rekomendasi' => [
            'label' => 'Pivot: Kategori-Rekomendasi', 'table' => 'kategori_masalah_master_rekomendasi'
        ],
        'slot_konselings' => ['label' => 'Slot Konseling', 'table' => 'slot_konselings'],
        'slot_bookings' => ['label' => 'Booking Konseling', 'table' => 'slot_bookings'],
    ];

    public function index()
    {
        $datasets = $this->datasets;
        return view('roles.admin.backup.index', compact('datasets'));
    }

    public function export(Request $request, string $dataset, string $format)
    {
        abort_unless(isset($this->datasets[$dataset]), 404);
        $meta = $this->datasets[$dataset];
        $table = $meta['table'];
        $label = $meta['label'];

        if ($format === 'csv') {
            $filename = $dataset . '-' . now()->format('Ymd_His') . '.csv';
            $response = new StreamedResponse(function () use ($table) {
                $handle = fopen('php://output', 'w');
                // Write BOM for Excel UTF-8 compatibility
                fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                $chunks = DB::table($table)->orderBy('id')->chunk(1000, function ($rows) use ($handle) {
                    static $headerWritten = false;
                    foreach ($rows as $row) {
                        $arr = (array) $row;
                        if (!$headerWritten) {
                            fputcsv($handle, array_keys($arr));
                            $headerWritten = true;
                        }
                        // Normalize JSON/arrays to string
                        $vals = array_map(function ($v) {
                            if (is_array($v) || is_object($v)) return json_encode($v);
                            return $v;
                        }, array_values($arr));
                        fputcsv($handle, $vals);
                    }
                });
                fclose($handle);
            });
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return $response;
        }

        if ($format === 'pdf') {
            $rows = DB::table($table)->orderBy('id')->limit(5000)->get();
            if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf') || app()->bound('dompdf.wrapper')) {
                $html = view('roles.admin.backup.pdf', compact('rows', 'label', 'table'))->render();
                // Use container if available to avoid static typehint
                $dompdf = app()->bound('dompdf.wrapper') ? app('dompdf.wrapper') : null;
                if ($dompdf) {
                    $dompdf->loadHTML($html)->setPaper('a4', 'landscape');
                    $filename = $dataset . '-' . now()->format('Ymd_His') . '.pdf';
                    return $dompdf->download($filename);
                }
            }
            return response()->json([
                'ok' => false,
                'message' => 'PDF export requires barryvdh/laravel-dompdf. Install via composer and try again.'
            ], 501);
        }

        abort(400, 'Unsupported format');
    }
}
