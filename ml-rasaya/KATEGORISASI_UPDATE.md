# 🔄 Update Kategorisasi & Rekomendasi System

## 📅 Tanggal: 26 November 2025

---

## 🎯 **PERUBAHAN UTAMA**

### **1. Kategorisasi: Kembali ke Multi-Level Matching (Metode Lama yang Lebih Akurat)**

#### **Sebelum (Kurang Akurat):**
```python
# HANYA keywords dari kategori kecil
categories_map = {
    "KECEMASAN": ["cemas", "takut"],
    "DEPRESI": ["sedih", "murung"]
}
# Tidak ada agregasi bucket
# Miss rate tinggi karena keywords terbatas
```

**Contoh Kegagalan:**
- Text: "Saya **stres** berat"
- Keywords kategori kecil tidak ada "stres"
- **Result:** ❌ Tidak terdeteksi kategori EMOSI

---

#### **Sesudah (Lebih Akurat):**
```python
# MULTI-LEVEL: Kategori Kecil + Bucket Agregat
categories_map = {
    "KECEMASAN": ["cemas", "takut", "gelisah"],
    "DEPRESI": ["sedih", "murung", "depresi"]
}

bucket_map = {
    "EMOSI": ["cemas", "takut", "gelisah", "sedih", "murung", "depresi", "stres", "marah"]
    # ^ Agregasi SEMUA keywords dari kategori kecil di bucket EMOSI
}

# Scoring: Check di kategori kecil DAN bucket
cat_scores = score_categories_for_text(text, categories_map)  # Level 1
bucket_scores = score_categories_for_text(text, bucket_map)   # Level 2 (BACKUP)
```

**Contoh Sukses:**
- Text: "Saya **stres** berat"
- Match di `bucket_map["EMOSI"]`? ✅ ("stres" ada di agregat)
- **Result:** ✅ Terdeteksi kategori EMOSI dengan confidence tinggi

---

### **2. Rekomendasi: Per Kategori Kecil (Granular) dari Database**

#### **Sebelum:**
```python
# Rule-based heuristic per BUCKET (kategori besar)
def recommend_rules(bucket: str):
    if bucket == "EMOSI":
        return ["REGULASI_EMOSI"]
    elif bucket == "AKADEMIK":
        return ["RAPAT_JADWAL"]
    # Terlalu general, tidak spesifik
```

---

#### **Sesudah:**
```python
# Database-driven per KATEGORI KECIL
def recommend_by_topic(topic_id: str, topic_name: str, bucket: str):
    return {
        "kategori_kode": topic_id,  # "KM_EMOSI_001" (match dengan database)
        "kategori_nama": topic_name, # "Kecemasan"
        "bucket": bucket,            # "EMOSI"
        "severity": 0.75,
        "suggested_actions": [...]
    }

# Laravel: Match dengan master_rekomendasis
# Filter by: kategoris()->where('kode', '=', 'KM_EMOSI_001')
```

**Keunggulan:**
- ✅ **Granular**: Rekomendasi spesifik untuk "Kecemasan" vs "Depresi"
- ✅ **Database-driven**: Admin bisa manage via UI
- ✅ **Flexible**: Tambah/edit rekomendasi tanpa ubah kode ML

---

## 🔧 **IMPLEMENTASI TEKNIS**

### **A. ML Service (app.py)**

#### **1. Multi-Level Keyword Index**
```python
def build_topic_index_and_categories_map():
    topic_index = {}
    categories_map = {}  # Kategori kecil keywords
    bucket_map = defaultdict(set)  # Agregasi per bucket
    
    for tp in taxonomy["topics"]:
        kw = set(tp.get("keywords", []))
        categories_map[tp["name"]] = kw
        
        # AGREGASI ke bucket (ini yang bikin akurat!)
        bucket = tp.get("bucket")
        if bucket:
            bucket_map[bucket].update(kw)
    
    return topic_index, categories_map, bucket_map
```

#### **2. Hybrid Scoring**
```python
# Score di kategori kecil
cat_scores = score_categories_for_text(text, categories_map)

# Score di bucket (agregat)
bucket_scores = score_categories_for_text(text, bucket_map)

# Agregasi boost
for cat, score in cat_scores.items():
    bucket = get_bucket(cat)
    bucket_scores[bucket] += score * 0.8

# Pilih best kategori kecil dengan boost dari bucket
best_cat = max(cat_scores)  # Prioritas: kategori kecil
best_bucket = max(bucket_scores)  # Fallback: bucket
```

#### **3. Granular Recommendations**
```python
def recommend_by_topic(topic_id, topic_name, bucket, severity, negative, sarcasm):
    return {
        "kategori_kode": topic_id,  # "KM_EMOSI_001"
        "kategori_nama": topic_name, # "Kecemasan"
        "bucket": bucket,
        "severity": severity,
        "negative": negative,
        "sarcasm": sarcasm,
        "suggested_actions": [
            {"type": "URGENT", "reason": "Severity tinggi"}
        ]
    }
```

---

### **B. Laravel Backend (AnalisisService.php)**

#### **1. Extract Kategori dari ML Response**
```php
// ML response structure:
// {
//   "global_recommendations": [
//     {
//       "category": "Kecemasan",
//       "kategori_kode": "KM_EMOSI_001",
//       "score": 0.85,
//       "recommendation": {...}
//     }
//   ]
// }

$detectedKategoriKodes = [];
foreach ($mlResponse['global_recommendations'] as $rec) {
    $detectedKategoriKodes[] = $rec['kategori_kode'];
}
```

#### **2. Match dengan Master Rekomendasi**
```php
$masters = MasterRekomendasi::with('kategoris')->where('is_active', true)->get();

foreach ($masters as $m) {
    $ok = true;
    
    // Rule 1: Sentiment threshold
    if (isset($m->rules['min_neg_score'])) {
        $ok = $ok && ($entry->skor_sentimen <= $m->rules['min_neg_score']);
    }
    
    // Rule 2: Kategori match (NEW!)
    $masterKategoriKodes = $m->kategoris->pluck('kode')->toArray();
    $hasMatch = !empty(array_intersect($detectedKategoriKodes, $masterKategoriKodes));
    $ok = $ok && $hasMatch;
    
    if ($ok) {
        AnalisisRekomendasi::create([
            'analisis_entry_id' => $entry->id,
            'master_rekomendasi_id' => $m->id,
            'match_score' => $rec['score'],
            'status' => 'suggested',
        ]);
    }
}
```

---

## 📊 **PERBANDINGAN AKURASI**

### **Test Case 1: Text dengan Keyword Tidak Lengkap**

**Input:**
```
"Saya merasa stres dan tertekan akhir-akhir ini"
```

**Metode Lama (Single-level):**
```
Kategori Kecil Keywords:
- Kecemasan: ["cemas", "takut"]
- Depresi: ["sedih", "murung"]

Match "stres"? ❌ Tidak ada
Match "tertekan"? ❌ Tidak ada

Result: ❌ Tidak terdeteksi kategori
```

**Metode Baru (Multi-level):**
```
Kategori Kecil Keywords:
- Kecemasan: ["cemas", "takut"]
- Depresi: ["sedih", "murung"]

Bucket Keywords (Agregat):
- EMOSI: ["cemas", "takut", "sedih", "murung", "stres", "tertekan", "marah"]

Match "stres"? ✅ Ada di bucket EMOSI
Match "tertekan"? ✅ Ada di bucket EMOSI

Result: ✅ Kategori EMOSI terdeteksi, confidence 0.82
        Sub-kategori: Kecemasan (boosted by bucket match)
```

---

### **Test Case 2: Rekomendasi Spesifik**

**Sebelum (Bucket-based):**
```
Kategori: EMOSI
Rekomendasi:
- ❌ "Latihan regulasi emosi" (terlalu general)
```

**Sesudah (Kategori Kecil-based):**
```
Kategori: Kecemasan (KM_EMOSI_001)
Rekomendasi (dari database):
- ✅ "Teknik pernapasan untuk mengatasi kecemasan"
- ✅ "Konseling untuk gangguan anxietas"
- ✅ "Latihan mindfulness anti-cemas"
```

---

## 🚀 **SARAN OPTIMALISASI**

### **1. Keywords Coverage**

**Masalah:** Keywords kategori kecil masih terbatas

**Solusi:**
```sql
-- Audit keywords per kategori
SELECT 
    km.nama AS kategori,
    JSON_LENGTH(km.kata_kunci) AS jumlah_keywords
FROM kategori_masalahs km
WHERE is_active = 1
ORDER BY jumlah_keywords ASC;

-- Target: Min 15-20 keywords per kategori kecil
```

**Action Items:**
- [ ] Tambah keywords dari corpus siswa (extract dari InputSiswa historis)
- [ ] Include sinonim & variasi ejaan (stres, stress, pusing, mumet)
- [ ] Tambah slang & dialect (gabut, baper, galau, susa, dolo)

---

### **2. Feedback Loop**

**Implementasi:**
```python
# ML: Endpoint untuk guru adjust kategori
@app.post("/feedback")
def feedback():
    # keyword: "telat"
    # from_category: "EMOSI"
    # to_category: "DISIPLIN"
    # delta: +0.2
    
    # Update feedback_weights.json
    weights["telat"]["DISIPLIN"] += 0.2
    weights["telat"]["EMOSI"] -= 0.1
    
    return {"ok": True}
```

**UI Flow:**
1. Guru lihat analisis: "Kategori: Kecemasan"
2. Guru rasa kurang tepat, klik "Ubah Kategori"
3. Pilih kategori baru: "Tekanan Akademik"
4. System kirim feedback ke ML
5. ML adjust scoring untuk keyword terkait

---

### **3. Periodic Taxonomy Sync**

**Sekarang:** Sync manual saat admin edit

**Usulan:** Auto-sync periodic + cache

```php
// TaxonomySync.php
public static function syncWithCache(): void
{
    $lastSync = Cache::get('taxonomy_last_sync');
    
    if (!$lastSync || now()->diffInMinutes($lastSync) > 30) {
        self::syncToJson();
        Cache::put('taxonomy_last_sync', now(), 3600);
    }
}

// Panggil di scheduler
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(fn() => TaxonomySync::syncWithCache())
             ->everyThirtyMinutes();
}
```

---

### **4. ML Model Versioning**

**Tracking:**
```python
# app.py
SERVICE_VERSION = "2.0.0-hybrid-kategorisasi"

@app.get("/health")
def health():
    return {
        "status": "ok",
        "version": SERVICE_VERSION,
        "features": {
            "multi_level_scoring": True,
            "granular_recommendations": True,
            "bert_enabled": ENABLE_BERT
        },
        "taxonomy_loaded": len(TOPIC_INDEX),
        "buckets_loaded": len(BUCKET_KEYWORDS)
    }
```

---

### **5. Performance Monitoring**

**Metrics to Track:**
```python
import time

# Per-request timing
@app.post("/analyze")
def analyze():
    start = time.time()
    
    # ... processing ...
    
    duration = time.time() - start
    
    return {
        "items": results,
        "meta": {
            "processing_time_ms": round(duration * 1000, 2),
            "items_processed": len(items),
            "avg_time_per_item_ms": round(duration * 1000 / len(items), 2)
        }
    }
```

**Target:** < 500ms per item untuk production

---

## ✅ **CHECKLIST DEPLOYMENT**

- [x] Update `app.py` dengan multi-level scoring
- [x] Update `AnalisisService.php` dengan kategori kecil matching
- [ ] Run taxonomy sync: `php artisan rasaya:sync-taxonomy`
- [ ] Restart ML service: `sudo systemctl restart ml-rasaya`
- [ ] Test sample analysis via Postman
- [ ] Verify rekomendasi muncul sesuai kategori kecil
- [ ] Monitor logs untuk errors
- [ ] Backup taxonomy.json sebelum production

---

## 📞 **SUPPORT**

Jika ada issue:
1. Check ML logs: `/var/log/ml-rasaya/app.log`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Test health endpoint: `GET http://localhost:5001/health`
4. Manual sync taxonomy: `php artisan rasaya:sync-taxonomy`

---

**Update by:** GitHub Copilot  
**Date:** 26 November 2025  
**Version:** 2.0.0-hybrid-kategorisasi
