<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalisisEntry;
use Illuminate\Support\Facades\Storage;

class ExportMlDataset extends Command
{
    protected $signature = 'ml:export-dataset 
                            {--limit=500 : Maximum entries to export}
                            {--output=ml_dataset.json : Output filename}';

    protected $description = 'Export revised analyses as ML training dataset (text + category + keywords)';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $output = $this->option('output');

        $this->info("Exporting ML dataset from revised analyses...");
        
        // Query revised entries with relations
        $entries = AnalisisEntry::whereNotNull('revised_kategori_id')
            ->with(['revisedKategori', 'siswaKelas.siswa'])
            ->latest('revised_at')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            $this->warn("No revised entries found.");
            return 0;
        }

        $dataset = [];
        $bar = $this->output->createProgressBar($entries->count());
        $bar->start();

        foreach ($entries as $entry) {
            // Extract text from inputs
            $texts = [];
            foreach (($entry->used_items ?? []) as $item) {
                if (!empty($item['text'])) {
                    $texts[] = $item['text'];
                }
            }
            $combined_text = implode(" ", $texts);

            // Extract ML-generated keywords
            $ml_keywords = collect($entry->kata_kunci ?? [])
                ->pluck('term')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Extract manual keywords from revision_reason
            $manual_keywords = [];
            if (!empty($entry->revision_reason)) {
                $manual_keywords = collect(preg_split('/[\s,;]+/', $entry->revision_reason))
                    ->map(fn($w) => trim(strtolower($w)))
                    ->filter(fn($w) => strlen($w) >= 3)
                    ->unique()
                    ->values()
                    ->all();
            }

            $dataset[] = [
                'id' => $entry->id,
                'siswa_id' => $entry->siswaKelas?->siswa_id,
                'siswa_nama' => $entry->siswaKelas?->siswa?->nama,
                'date_range' => [
                    'from' => $entry->from_date,
                    'to' => $entry->to_date,
                ],
                'text' => $combined_text,
                'sentiment_score' => $entry->avg_sentiment,
                'ml_predicted_categories' => $entry->categories_overview ?? [],
                'final_category' => [
                    'id' => $entry->revised_kategori_id,
                    'nama' => $entry->revisedKategori?->nama,
                    'kode' => $entry->revisedKategori?->kode,
                ],
                'keywords' => [
                    'ml_generated' => $ml_keywords,
                    'manual_added' => $manual_keywords,
                    'combined' => array_values(array_unique(array_merge($ml_keywords, $manual_keywords))),
                ],
                'revised_by' => [
                    'user_id' => $entry->revised_by,
                    'at' => $entry->revised_at?->toIso8601String(),
                ],
                'metadata' => [
                    'severity' => $entry->severity,
                    'needs_attention' => $entry->needs_attention,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ],
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Save to storage
        $path = "ml-datasets/{$output}";
        Storage::disk('local')->put($path, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $fullPath = Storage::disk('local')->path($path);
        $this->info("✅ Exported {$entries->count()} entries to:");
        $this->line("   {$fullPath}");
        
        // Summary stats
        $categories = collect($dataset)->pluck('final_category.nama')->filter()->countBy();
        $this->newLine();
        $this->info("📊 Category distribution:");
        foreach ($categories->sortDesc()->take(10) as $cat => $count) {
            $this->line("   {$cat}: {$count}");
        }

        return 0;
    }
}
