<?php

namespace App\Jobs;

use App\Models\YearCopyRun;
use App\Models\TahunAjaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class YearRolloverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public YearCopyRun $run) {}

    public function handle(): void
    {
        $this->run->update(['status' => 'running', 'progress' => 0]);

        try {
            DB::transaction(function () {
                $source = TahunAjaran::findOrFail($this->run->source_year_id);
                $target = TahunAjaran::findOrFail($this->run->target_year_id);
                $opts = collect($this->run->options ?? []);

                $mapJurusan = [];
                if ($opts->contains('jurusan')) {
                    Jurusan::where('tahun_ajaran_id', $source->id)
                        ->orderBy('id')
                        ->chunkById(200, function ($rows) use (&$mapJurusan, $target) {
                            foreach ($rows as $j) {
                                $clone = $j->replicate(['id','tahun_ajaran_id','created_at','updated_at']);
                                $clone->tahun_ajaran_id = $target->id;
                                $clone->save();
                                $mapJurusan[$j->id] = $clone->id;
                            }
                        });
                    $this->appendLog(['jurusan_copied' => count($mapJurusan)]);
                }

                if ($opts->contains('kelas')) {
                    $withWali = $opts->contains('wali_kelas');
                    Kelas::where('tahun_ajaran_id', $source->id)
                        ->orderBy('id')
                        ->chunkById(200, function ($rows) use ($target, $withWali, $mapJurusan) {
                            foreach ($rows as $k) {
                                $clone = $k->replicate(['id','tahun_ajaran_id','created_at','updated_at']);
                                $clone->tahun_ajaran_id = $target->id;
                                if (!$withWali) {
                                    $clone->guru_id = null;
                                }
                                if (isset($k->jurusan_id) && isset($mapJurusan[$k->jurusan_id])) {
                                    $clone->jurusan_id = $mapJurusan[$k->jurusan_id];
                                }
                                $clone->save();
                            }
                        });
                }

                // TODO: slot_konseling (template) jika diperlukan
                // TODO: promosi_siswa (SiswaKelas) jika diperlukan
            });

            $this->run->update(['status' => 'succeeded', 'progress' => 100]);
        } catch (Throwable $e) {
            $this->run->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function appendLog(array $data): void
    {
        $log = $this->run->log ?? [];
        $this->run->update(['log' => array_merge($log, $data)]);
    }
}
