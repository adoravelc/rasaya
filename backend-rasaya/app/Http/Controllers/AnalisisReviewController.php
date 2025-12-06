<?php

namespace App\Http\Controllers;

use App\Models\AnalisisEntry;
use App\Models\AnalisisRekomendasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\NotificationHelper;

class AnalisisReviewController extends Controller
{
    /**
     * Get analisis entry with pending review status for teacher review
     */
    public function getPendingReview(Request $request)
    {
        $query = AnalisisEntry::with([
            'siswaKelas.siswa',
            'rekomendasis.kategoriMasalah',
            'createdBy'
        ])
        ->where('review_status', 'pending_review');

        // Filter by teacher's assigned classes if needed
        if ($request->has('kelas_id')) {
            $query->whereHas('siswaKelas', function($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        $entries = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($entries);
    }

    /**
     * Accept analysis without revision
     */
    public function acceptAnalysis(Request $request, int $id)
    {
        $entry = AnalisisEntry::findOrFail($id);
        
        $entry->update([
            'review_status' => 'accepted',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Analisis diterima tanpa revisi',
            'entry' => $entry->load(['siswaKelas.siswa', 'rekomendasis.kategoriMasalah'])
        ]);
    }

    // Legacy reviseAnalysis flow removed; edits handled via flexibleEditAnalysis.

    /**
     * NEW FLOW: Flexible edit rekomendasi
     * Guru dapat:
     * 1. Pilih kategori kecil
     * 2. Pilih rekomendasi dari master OR submit custom request
     * Min score otomatis dari analisis entry skor sentimen
     */
    public function flexibleEditAnalysis(Request $request, int $id)
    {
        $request->validate([
            'kategori_masalah_id' => 'nullable|exists:kategori_masalahs,id',
            'master_rekomendasi_id' => 'nullable|exists:master_rekomendasis,id',
            'custom_judul' => 'nullable|string',
            'custom_deskripsi' => 'nullable|string',
            'custom_severity' => 'nullable|in:low,medium,high',
        ]);

        $entry = AnalisisEntry::with('rekomendasis')->findOrFail($id);
        $actualScore = (float)($entry->skor_sentimen ?? -0.5);
        $minSentiment = (float)config('rekomendasi.min_sentiment');
        $fallbackEnabled = (bool)config('rekomendasi.fallback_enabled');
        $tolerance = (float)config('rekomendasi.fallback_tolerance');
        $maxFallback = (int)config('rekomendasi.max_fallback');

        DB::beginTransaction();
        try {
            // Case 1: Pilih rekomendasi dari master
            if ($request->filled('master_rekomendasi_id')) {
                $master = \App\Models\MasterRekomendasi::findOrFail($request->master_rekomendasi_id);
                
                // Create AnalisisRekomendasi dengan rules dari master tapi min_score dari analisis
                $rules = $master->rules ?? [];
                $rules['mode'] = ($actualScore >= $minSentiment) ? 'normal' : 'fallback';
                $rules['min_sentiment'] = $minSentiment;
                $rules['actual_score'] = $actualScore;
                if ($rules['mode'] === 'fallback') {
                    $rules['tolerance'] = $tolerance;
                }
                
                AnalisisRekomendasi::create([
                    'analisis_entry_id' => $id,
                    'master_rekomendasi_id' => $master->id,
                    'judul' => $master->judul,
                    'deskripsi' => $master->deskripsi,
                    'severity' => $master->severity ?? 'low',
                    'match_score' => 0.95,
                    'status' => 'suggested',
                    'rules' => $rules,
                ]);
            }
            // Case 2: Submit custom rekomendasi ke admin
            elseif ($request->filled('custom_judul') && $request->filled('custom_deskripsi')) {
                $created = \App\Models\RekomendasiRequest::create([
                    'kategori_masalah_id' => $request->kategori_masalah_id,
                    'requested_by' => Auth::id(),
                    'judul' => $request->custom_judul,
                    'deskripsi' => $request->custom_deskripsi,
                    'severity' => $request->custom_severity ?? 'low',
                    'rules' => [
                        'mode' => ($actualScore >= $minSentiment) ? 'normal' : 'fallback',
                        'min_sentiment' => $minSentiment,
                        'actual_score' => $actualScore,
                        'tolerance' => ($actualScore >= $minSentiment) ? null : $tolerance,
                    ],
                    'status' => 'pending',
                ]);

                try {
                    $katName = optional(\App\Models\KategoriMasalah::find($request->kategori_masalah_id))->nama;
                    NotificationHelper::notifyAdminRecommendationRequestSubmitted(
                        $created->id,
                        $katName,
                        $request->custom_judul
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify admins: ' . $e->getMessage());
                }
            }
            // Case 3: Otomatis sarankan dari master berdasarkan kategori & skor (fallback window)
            elseif ($request->filled('kategori_masalah_id')) {
                $kategoriId = (int)$request->kategori_masalah_id;
                $mode = ($actualScore >= $minSentiment) ? 'normal' : 'fallback';

                // Normal: ambil yang aktif di kategori tsb (tanpa memperluas range)
                if ($mode === 'normal') {
                    $masters = \App\Models\MasterRekomendasi::whereHas('kategoris', function($q) use ($kategoriId) {
                            $q->where('kategori_masalah_id', $kategoriId);
                        })
                        ->where('is_active', true)
                        ->take($maxFallback)
                        ->get();

                    foreach ($masters as $m) {
                        AnalisisRekomendasi::create([
                            'analisis_entry_id' => $id,
                            'master_rekomendasi_id' => $m->id,
                            'judul' => $m->judul,
                            'deskripsi' => $m->deskripsi,
                            'severity' => $m->severity ?? 'low',
                            'match_score' => 0.80,
                            'status' => 'suggested',
                            'rules' => [
                                'mode' => 'normal',
                                'min_sentiment' => $minSentiment,
                                'actual_score' => $actualScore,
                            ],
                        ]);
                    }
                } else if ($fallbackEnabled) {
                    $low = max(-1.0, $actualScore - $tolerance);
                    $high = min(1.0, $actualScore + $tolerance);

                    // Heuristic: use severity ordering and create proximity-based match_score
                    $masters = \App\Models\MasterRekomendasi::whereHas('kategoris', function($q) use ($kategoriId) {
                            $q->where('kategori_masalah_id', $kategoriId);
                        })
                        ->where('is_active', true)
                        ->orderByRaw("CASE severity WHEN 'low' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                        ->take($maxFallback)
                        ->get();

                    foreach ($masters as $m) {
                        // If master has a nominal target_sentiment field use it, else approximate by 0
                        $target = (float)($m->target_sentiment ?? 0.0);
                        if ($target >= $low && $target <= $high) {
                            $distance = abs($target - $actualScore);
                            $score = max(0.5, 1.0 - ($distance / max($tolerance, 0.0001))); // [0.5..1]

                            AnalisisRekomendasi::create([
                                'analisis_entry_id' => $id,
                                'master_rekomendasi_id' => $m->id,
                                'judul' => $m->judul,
                                'deskripsi' => $m->deskripsi,
                                'severity' => $m->severity ?? 'low',
                                'match_score' => round($score, 3),
                                'status' => 'suggested',
                                'rules' => [
                                    'mode' => 'fallback',
                                    'min_sentiment' => $minSentiment,
                                    'actual_score' => $actualScore,
                                    'tolerance' => $tolerance,
                                    'window' => [$low, $high],
                                    'target_sentiment' => $target,
                                ],
                            ]);
                        }
                    }
                }
            }
            // Case 4: Tidak ada input khusus -> hanya tampilkan master severity cocok dalam kategori yang dianalisis
            else {
                $abs = abs($actualScore);
                $derivedSeverity = $abs <= 0.20 ? 'low' : ($abs <= 0.50 ? 'medium' : 'high');

                $kategoriIds = [];
                $overview = $entry->categories_overview ?? [];
                if (is_array($overview) && count($overview) > 0) {
                    foreach ($overview as $item) {
                        $kid = $item['id'] ?? $item['kategori_masalah_id'] ?? null;
                        if (!$kid) {
                            $cname = $item['category'] ?? $item['name'] ?? null;
                            if ($cname) {
                                $resolved = \App\Models\KategoriMasalah::aktif()
                                    ->where(function($q) use ($cname) {
                                        $q->where('nama', $cname)->orWhere('kode', $cname);
                                    })
                                    ->first(['id']);
                                $kid = $resolved?->id;
                            }
                        }
                        if ($kid) $kategoriIds[] = (int)$kid;
                    }
                }

                if (!empty($kategoriIds)) {
                    foreach ($kategoriIds as $kid) {
                        $masters = \App\Models\MasterRekomendasi::whereHas('kategoris', function($q) use ($kid) {
                                $q->where('kategori_masalah_id', $kid);
                            })
                            ->where('is_active', true)
                            ->where('severity', $derivedSeverity)
                            ->get();

                        foreach ($masters as $m) {
                            $exists = $entry->rekomendasis()
                                ->where('master_rekomendasi_id', $m->id)
                                ->where('kategori_masalah_id', $kid)
                                ->exists();
                            if ($exists) { continue; }
                            AnalisisRekomendasi::create([
                                'analisis_entry_id' => $id,
                                'master_rekomendasi_id' => $m->id,
                                'kategori_masalah_id' => $kid,
                                'judul' => $m->judul,
                                'deskripsi' => $m->deskripsi,
                                'severity' => $m->severity ?? $derivedSeverity,
                                'match_score' => 0.75,
                                'status' => 'suggested',
                                'rules' => [
                                    'mode' => 'severity-bucket',
                                    'derived_severity' => $derivedSeverity,
                                    'min_sentiment' => $minSentiment,
                                    'actual_score' => $actualScore,
                                    'kategori_source' => 'overview',
                                ],
                            ]);
                        }
                    }
                }
            }

            $entry->save();
            DB::commit();

            return response()->json([
                'message' => 'Rekomendasi berhasil ditambahkan',
                'entry' => $entry->fresh()->load(['rekomendasis.master'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Flexible edit failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menambahkan rekomendasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get master rekomendasi by kategori
     */
    public function getMasterRekomendasi(int $kategoriId)
    {
        try {
            $masters = \App\Models\MasterRekomendasi::whereHas('kategoris', function($q) use ($kategoriId) {
                $q->where('kategori_masalah_id', $kategoriId);
            })
            ->where('is_active', true)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'judul' => $m->judul,
                'deskripsi' => $m->deskripsi,
                'severity' => $m->severity,
            ]);

            return response()->json(['data' => $masters]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch master rekomendasi: ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send feedback to ML service for continuous learning
     */
    // ML revision feedback method removed; teacher actions send feedback directly elsewhere.

    /**
     * Get revision history for an analisis entry
     */
    // Revision history endpoint removed.
}
