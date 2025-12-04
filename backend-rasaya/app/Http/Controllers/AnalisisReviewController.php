<?php

namespace App\Http\Controllers;

use App\Models\AnalisisEntry;
use App\Models\AnalisisRevision;
use App\Models\AnalisisRekomendasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    /**
     * Revise analysis - update kategori and/or rekomendasi
     */
    public function reviseAnalysis(Request $request, int $id)
    {
        $request->validate([
            'revised_kategori' => 'required|string',
            'revised_rekomendasi' => 'required|array',
            'revised_rekomendasi.*.kategori_masalah_id' => 'required|exists:kategori_masalahs,id',
            'revised_rekomendasi.*.rekomendasi_text' => 'required|string',
            'revision_notes' => 'nullable|string',
        ]);

        $entry = AnalisisEntry::with('rekomendasis.kategoriMasalah')->findOrFail($id);

        DB::beginTransaction();
        try {
            // Get original data for ML feedback
            $originalKategori = $entry->categories_overview[0]['name'] ?? 'Unknown';
            $originalRekomendasi = $entry->rekomendasis->map(function($r) {
                return [
                    'kategori' => $r->kategoriMasalah->name ?? 'Unknown',
                    'text' => $r->rekomendasi_text
                ];
            })->toArray();

            // Get original text from summary
            $originalText = $entry->summary['full_text'] ?? $entry->auto_summary ?? '';

            // Create revision record for ML feedback
            $revision = AnalisisRevision::create([
                'analisis_entry_id' => $entry->id,
                'original_kategori' => $originalKategori,
                'original_rekomendasi' => json_encode($originalRekomendasi),
                'revised_kategori' => $request->revised_kategori,
                'revised_rekomendasi' => json_encode($request->revised_rekomendasi),
                'original_text' => $originalText,
                'revised_by' => Auth::id(),
                'revision_notes' => $request->revision_notes,
                'sent_to_ml' => false,
            ]);

            // Update entry status
            $entry->update([
                'review_status' => 'revised',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            // Update categories_overview with revised kategori
            $categoriesOverview = $entry->categories_overview;
            if (isset($categoriesOverview[0])) {
                $categoriesOverview[0]['name'] = $request->revised_kategori;
                $entry->categories_overview = $categoriesOverview;
                $entry->save();
            }

            // Delete old rekomendasi and create new ones
            $entry->rekomendasis()->delete();
            
            foreach ($request->revised_rekomendasi as $rek) {
                AnalisisRekomendasi::create([
                    'analisis_entry_id' => $entry->id,
                    'kategori_masalah_id' => $rek['kategori_masalah_id'],
                    'rekomendasi_text' => $rek['rekomendasi_text'],
                ]);
            }

            DB::commit();

            // Send feedback to ML service asynchronously
            $this->sendFeedbackToML($revision);

            return response()->json([
                'message' => 'Analisis berhasil direvisi dan feedback dikirim ke ML service',
                'entry' => $entry->fresh()->load(['siswaKelas.siswa', 'rekomendasis.kategoriMasalah']),
                'revision' => $revision
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to revise analysis: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal melakukan revisi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flexible edit: allow partial updates to kategori, rekomendasi, and keywords.
     * This is a separate action from revise (accept-with-revision) and does not change review_status unless provided.
     */
    public function flexibleEditAnalysis(Request $request, int $id)
    {
        $request->validate([
            'kategori' => 'nullable|string',
            'rekomendasi' => 'nullable|array',
            'rekomendasi.*.kategori_masalah_id' => 'required_with:rekomendasi|exists:kategori_masalahs,id',
            // Allow either selecting existing master recommendation or providing free text
            'rekomendasi.*.master_rekomendasi_id' => 'nullable|integer',
            'rekomendasi.*.rekomendasi_text' => 'nullable|string',
            'add_keywords' => 'nullable|array',
            'add_keywords.*.term' => 'required|string',
            'add_keywords.*.count' => 'nullable|integer',
        ]);

        $entry = AnalisisEntry::with('rekomendasis')->findOrFail($id);

        DB::beginTransaction();
        try {
            // Update kategori if provided (top of categories_overview)
            if ($request->filled('kategori')) {
                $categoriesOverview = $entry->categories_overview ?? [];
                if (isset($categoriesOverview[0])) {
                    $categoriesOverview[0]['name'] = $request->kategori;
                } else {
                    $categoriesOverview = [['name' => $request->kategori, 'score' => 1.0]];
                }
                $entry->categories_overview = $categoriesOverview;
            }

            // Submit new recommendation requests to admin (DO NOT create AnalisisRekomendasi entries here)
            if (is_array($request->rekomendasi) && count($request->rekomendasi) > 0) {
                foreach ($request->rekomendasi as $rek) {
                    $text = trim((string)($rek['rekomendasi_text'] ?? ''));
                    if ($text === '') continue;

                    try {
                        // Parse judul, deskripsi, severity, min score from composed text
                        $judul = null;
                        $deskripsi = $text;
                        $severity = null;
                        $minNegScore = null;

                        $parts = explode(' - ', $text, 2);
                        if (count($parts) === 2) {
                            $judul = trim($parts[0]);
                            $deskripsi = trim($parts[1]);
                        }
                        if (preg_match('/\[severity:([^\]]+)\]/i', $text, $m)) {
                            $severity = strtolower(trim($m[1]));
                        }
                        if (preg_match('/\[min:([-]?[0-9]+(?:\.[0-9]+)?)\]/i', $text, $m2)) {
                            $minNegScore = (float)$m2[1];
                        }

                        // Strip patterns from deskripsi
                        $deskripsi = preg_replace('/\s*\[severity:[^\]]+\]\s*/i', ' ', $deskripsi);
                        $deskripsi = preg_replace('/\s*\[min:[-]?[0-9]+(?:\.[0-9]+)?\]\s*/i', ' ', $deskripsi);
                        $deskripsi = trim($deskripsi);

                        $rules = [];
                        if (!is_null($minNegScore)) {
                            $rules['min_neg_score'] = $minNegScore;
                        }

                        $created = \App\Models\RekomendasiRequest::create([
                            'kategori_masalah_id' => $rek['kategori_masalah_id'],
                            'requested_by' => Auth::id(),
                            'judul' => $judul,
                            'deskripsi' => $deskripsi,
                            'severity' => $severity,
                            'rules' => $rules,
                            'status' => 'pending',
                        ]);

                        // Notify admins
                        try {
                            $katName = optional(\App\Models\KategoriMasalah::find($rek['kategori_masalah_id']))->nama;
                            NotificationHelper::notifyAdminRecommendationRequestSubmitted($created->id, $katName, $judul);
                        } catch (\Throwable $e) {
                            Log::warning('Failed to notify admins: ' . $e->getMessage());
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to create RekomendasiRequest: ' . $e->getMessage());
                    }
                }
            }

            // Append keywords if provided
            if (is_array($request->add_keywords) && count($request->add_keywords) > 0) {
                $existing = collect($entry->kata_kunci ?? []);
                $incoming = collect($request->add_keywords)
                    ->map(function ($k) {
                        return [
                            'term' => (string)($k['term'] ?? ''),
                            'count' => (int)($k['count'] ?? 1),
                        ];
                    })
                    ->filter(fn($x) => !empty($x['term']))
                    ->values();

                // merge counts for same term
                $merged = $existing->concat($incoming)
                    ->groupBy('term')
                    ->map(function ($items, $term) {
                        $total = collect($items)->sum('count');
                        return ['term' => $term, 'count' => (int)$total];
                    })
                    ->values()
                    ->all();
                $entry->kata_kunci = $merged;
            }

            // No-op: review status and notes removed per user request

            $entry->save();
            DB::commit();

            return response()->json([
                'message' => 'Analisis berhasil diupdate secara fleksibel',
                'entry' => $entry->fresh()->load(['siswaKelas.siswa', 'rekomendasis.kategoriMasalah'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Flexible edit failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengedit analisis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send feedback to ML service for continuous learning
     */
    protected function sendFeedbackToML(AnalisisRevision $revision)
    {
        try {
            $mlServiceUrl = env('ML_SERVICE_URL', 'http://localhost:5000');
            
            $response = Http::timeout(10)->post("{$mlServiceUrl}/feedback", [
                'revision_id' => $revision->id,
                'original_text' => $revision->original_text,
                'original_kategori' => $revision->original_kategori,
                'original_rekomendasi' => json_decode($revision->original_rekomendasi, true),
                'revised_kategori' => $revision->revised_kategori,
                'revised_rekomendasi' => json_decode($revision->revised_rekomendasi, true),
                'revision_notes' => $revision->revision_notes,
            ]);

            if ($response->successful()) {
                $revision->update([
                    'sent_to_ml' => true,
                    'sent_to_ml_at' => now(),
                ]);
                Log::info("Feedback sent to ML service for revision #{$revision->id}");
            } else {
                Log::warning("Failed to send feedback to ML service: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Error sending feedback to ML service: " . $e->getMessage());
            // Don't throw - this is async operation
        }
    }

    /**
     * Get revision history for an analisis entry
     */
    public function getRevisionHistory(int $id)
    {
        $entry = AnalisisEntry::with('revisions.revisedBy')->findOrFail($id);
        
        return response()->json([
            'entry_id' => $entry->id,
            'current_status' => $entry->review_status,
            'revisions' => $entry->revisions
        ]);
    }
}
