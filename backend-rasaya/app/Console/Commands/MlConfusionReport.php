<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalisisEntry;
use Illuminate\Support\Facades\DB;

class MlConfusionReport extends Command
{
    protected $signature = 'ml:confusion-report 
                            {--days=30 : Number of days to analyze}
                            {--min-samples=3 : Minimum samples per category to include}';

    protected $description = 'Generate confusion matrix and accuracy report for ML categorization';

    public function handle()
    {
        $days = (int) $this->option('days');
        $minSamples = (int) $this->option('min-samples');
        $since = now()->subDays($days);

        $this->info("Analyzing ML predictions vs revisions (last {$days} days)...");
        $this->newLine();

        // Get revised entries with predicted categories
        $entries = AnalisisEntry::whereNotNull('revised_kategori_id')
            ->where('revised_at', '>=', $since)
            ->with('revisedKategori')
            ->get();

        if ($entries->isEmpty()) {
            $this->warn("No revised entries in the selected period.");
            return 0;
        }

        // Build confusion data
        $confusion = [];
        $categoryStats = [];

        foreach ($entries as $entry) {
            $predicted = $this->getTopPredicted($entry);
            $actual = $entry->revisedKategori?->nama;

            if (!$predicted || !$actual) {
                continue;
            }

            // Confusion matrix
            if (!isset($confusion[$actual])) {
                $confusion[$actual] = [];
            }
            if (!isset($confusion[$actual][$predicted])) {
                $confusion[$actual][$predicted] = 0;
            }
            $confusion[$actual][$predicted]++;

            // Category stats
            if (!isset($categoryStats[$actual])) {
                $categoryStats[$actual] = ['total' => 0, 'correct' => 0];
            }
            $categoryStats[$actual]['total']++;
            if ($predicted === $actual) {
                $categoryStats[$actual]['correct']++;
            }
        }

        // Filter categories with enough samples
        $categoryStats = array_filter($categoryStats, fn($stats) => $stats['total'] >= $minSamples);

        if (empty($categoryStats)) {
            $this->warn("No categories with at least {$minSamples} samples.");
            return 0;
        }

        // Calculate metrics
        $metrics = [];
        foreach ($categoryStats as $cat => $stats) {
            $accuracy = $stats['correct'] / $stats['total'];
            $metrics[$cat] = [
                'samples' => $stats['total'],
                'correct' => $stats['correct'],
                'accuracy' => round($accuracy, 3),
            ];
        }

        // Sort by accuracy (worst first)
        uasort($metrics, fn($a, $b) => $a['accuracy'] <=> $b['accuracy']);

        // Display results
        $this->info("📊 ML Categorization Performance");
        $this->table(
            ['Category', 'Samples', 'Correct', 'Accuracy'],
            collect($metrics)->map(fn($m, $cat) => [
                $cat,
                $m['samples'],
                $m['correct'],
                number_format($m['accuracy'] * 100, 1) . '%'
            ])->values()
        );

        $this->newLine();

        // Overall stats
        $totalSamples = array_sum(array_column($categoryStats, 'total'));
        $totalCorrect = array_sum(array_column($categoryStats, 'correct'));
        $overallAccuracy = $totalCorrect / $totalSamples;

        $this->info("Overall Accuracy: " . number_format($overallAccuracy * 100, 1) . "% ({$totalCorrect}/{$totalSamples})");
        $this->newLine();

        // Show worst confusions
        $this->info("🔍 Most Common Misclassifications:");
        $misclassifications = [];
        foreach ($confusion as $actual => $predictions) {
            foreach ($predictions as $predicted => $count) {
                if ($actual !== $predicted) {
                    $misclassifications[] = [
                        'actual' => $actual,
                        'predicted' => $predicted,
                        'count' => $count,
                    ];
                }
            }
        }

        usort($misclassifications, fn($a, $b) => $b['count'] <=> $a['count']);
        $topMisclassifications = array_slice($misclassifications, 0, 10);

        $this->table(
            ['Should Be', 'ML Said', 'Count'],
            collect($topMisclassifications)->map(fn($m) => [
                $m['actual'],
                $m['predicted'],
                $m['count'],
            ])
        );

        // Recommendations
        $this->newLine();
        $this->info("💡 Recommendations:");
        $worstCategories = array_slice($metrics, 0, 3, true);
        foreach ($worstCategories as $cat => $m) {
            if ($m['accuracy'] < 0.5) {
                $this->warn("   • {$cat}: Review keywords and add more specific n-grams");
            }
        }

        return 0;
    }

    private function getTopPredicted(AnalisisEntry $entry): ?string
    {
        $overview = $entry->categories_overview ?? [];
        if (empty($overview)) {
            return null;
        }

        $topCategory = collect($overview)
            ->sortByDesc('score')
            ->first();

        return $topCategory['category'] ?? null;
    }
}
