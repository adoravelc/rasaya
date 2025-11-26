<?php

namespace App\Services;

use App\Models\KategoriMasalah;
use App\Models\MasterKategoriMasalah;
use Illuminate\Support\Facades\Log;

class TaxonomySync
{
    protected string $taxonomyPath;

    public function __construct()
    {
        // Path ke taxonomy.json di ML service
        $this->taxonomyPath = base_path('../ml-rasaya/taxonomy.json');
    }

    /**
     * Sync semua kategori dari database ke taxonomy.json
     */
    public function syncAll(): bool
    {
        try {
            $topics = [];
            
            // Load semua kategori kecil (subcategories) dengan master kategori
            $smallCategories = KategoriMasalah::with('topikBesars')
                ->orderBy('kode')
                ->get();

            foreach ($smallCategories as $cat) {
                // Ambil master pertama sebagai bucket (jika ada)
                $bucket = $cat->topikBesars->first()?->nama ?? 'UMUM';
                
                // Ensure kata_kunci is always array
                $keywords = $cat->kata_kunci;
                if (!is_array($keywords)) {
                    $keywords = [];
                }
                
                $topics[] = [
                    'id' => $cat->kode,
                    'name' => $cat->nama,
                    'bucket' => strtoupper($bucket),
                    'keywords' => $keywords,
                    'deskripsi' => $cat->deskripsi ?? '',
                ];
            }

            // Load semua master kategori (big categories) sebagai buckets
            $buckets = [];
            $masterCategories = MasterKategoriMasalah::orderBy('kode')->get();
            
            foreach ($masterCategories as $master) {
                $buckets[] = [
                    'id' => $master->kode,
                    'name' => $master->nama,
                    'deskripsi' => $master->deskripsi ?? '',
                ];
            }

            // Struktur taxonomy.json
            $taxonomy = [
                'topics' => $topics,
                'buckets' => $buckets,
                'meta' => [
                    'last_synced' => now()->toIso8601String(),
                    'version' => '2.0',
                    'source' => 'database',
                ],
            ];

            // Write ke file dengan pretty print
            $json = json_encode($taxonomy, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if ($json === false) {
                Log::error('TaxonomySync: Failed to encode JSON', ['error' => json_last_error_msg()]);
                return false;
            }

            // Pastikan direktori exists
            $dir = dirname($this->taxonomyPath);
            if (!is_dir($dir)) {
                Log::error('TaxonomySync: ML directory not found', ['path' => $dir]);
                return false;
            }

            $result = file_put_contents($this->taxonomyPath, $json);
            
            if ($result === false) {
                Log::error('TaxonomySync: Failed to write taxonomy.json', ['path' => $this->taxonomyPath]);
                return false;
            }

            Log::info('TaxonomySync: Successfully synced taxonomy.json', [
                'topics_count' => count($topics),
                'buckets_count' => count($buckets),
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('TaxonomySync: Exception during sync', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Check if taxonomy file exists and is writable
     */
    public function canSync(): bool
    {
        $dir = dirname($this->taxonomyPath);
        return is_dir($dir) && is_writable($dir);
    }

    /**
     * Get the taxonomy file path
     */
    public function getTaxonomyPath(): string
    {
        return $this->taxonomyPath;
    }
}
