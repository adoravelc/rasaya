<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\AnalisisEntry;
use App\Models\AnalisisRekomendasi;
use App\Models\MasterRekomendasi;
use App\Models\KategoriMasalah;
use Illuminate\Support\Facades\DB;

class FlexibleEditAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure default config for test
        config([
            'rekomendasi.min_sentiment' => -0.05,
            'rekomendasi.fallback_enabled' => true,
            'rekomendasi.fallback_tolerance' => 0.25,
            'rekomendasi.max_fallback' => 5,
        ]);

        // Create minimal schemas to avoid full migrations (SQLite-friendly)
        Schema::dropAllTables();

        Schema::create('kategori_masalahs', function($t){
            $t->increments('id');
            $t->string('nama');
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('master_rekomendasis', function($t){
            $t->increments('id');
            $t->string('judul');
            $t->text('deskripsi')->nullable();
            $t->string('severity')->default('low');
            $t->boolean('is_active')->default(true);
            $t->json('rules')->nullable();
            $t->timestamps();
        });

        Schema::create('kategori_masalah_master_rekomendasi', function($t){
            $t->integer('kategori_masalah_id');
            $t->integer('master_rekomendasi_id');
        });

        Schema::create('analisis_entries', function($t){
            $t->increments('id');
            $t->json('summary')->nullable();
            $t->decimal('skor_sentimen',5,3)->nullable();
            $t->string('review_status')->default('pending_review');
            $t->timestamps();
        });

        Schema::create('analisis_rekomendasis', function($t){
            $t->increments('id');
            $t->integer('analisis_entry_id');
            $t->integer('master_rekomendasi_id')->nullable();
            $t->integer('kategori_masalah_id')->nullable();
            $t->string('judul')->nullable();
            $t->text('deskripsi')->nullable();
            $t->enum('severity', ['low','medium','high'])->default('low');
            $t->decimal('match_score',5,3)->nullable();
            $t->enum('status', ['suggested','accepted','rejected'])->default('suggested');
            $t->json('rules')->nullable();
            $t->timestamps();
        });
    }

    public function test_flexible_edit_analysis_creates_fallback_recommendations_when_below_threshold()
    {
        // Create kategori
        $kat = KategoriMasalah::create(['nama' => 'Emosi']);

        // Create entry with low sentiment
        $entry = AnalisisEntry::create([
            'summary' => ['full_text' => 'Sedih sekali'],
            'skor_sentimen' => -0.10,
            'review_status' => 'pending_review',
        ]);

        // Create master rekomendasis and link kategori via pivot
        $m1 = MasterRekomendasi::create([
            'judul' => 'Latihan pernapasan',
            'deskripsi' => 'Teknik pernapasan 4-7-8',
            'severity' => 'low',
            'is_active' => true,
        ]);
        $m2 = MasterRekomendasi::create([
            'judul' => 'Jurnal harian',
            'deskripsi' => 'Catatan perasaan tiap malam',
            'severity' => 'low',
            'is_active' => true,
        ]);

        // Attach kategori via pivot
        DB::table('kategori_masalah_master_rekomendasi')->insert([
            ['kategori_masalah_id' => $kat->id, 'master_rekomendasi_id' => $m1->id],
            ['kategori_masalah_id' => $kat->id, 'master_rekomendasi_id' => $m2->id],
        ]);

        // Hit controller endpoint: flexible edit with kategori only (auto suggest)
        // Disable auth middleware and call PATCH endpoint
        $this->withoutMiddleware();
        $resp = $this->patchJson('/guru/analisis/'.$entry->id.'/edit-flex', [
            'kategori_masalah_id' => $kat->id,
        ]);

        $resp->assertStatus(200);

        // Verify recommendations created
        $reks = AnalisisRekomendasi::where('analisis_entry_id', $entry->id)->get();
        $this->assertNotEmpty($reks);
        $this->assertTrue($reks->contains(fn($r) => $r->master_rekomendasi_id === $m1->id));
        $this->assertTrue($reks->contains(fn($r) => $r->master_rekomendasi_id === $m2->id));

        // Check rules persisted with fallback mode
        foreach ($reks as $r) {
            $this->assertIsArray($r->rules);
            $this->assertEquals('fallback', $r->rules['mode']);
            $this->assertArrayHasKey('tolerance', $r->rules);
            $this->assertArrayHasKey('window', $r->rules);
        }
    }
}
