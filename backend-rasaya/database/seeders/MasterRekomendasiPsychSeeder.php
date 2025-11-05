<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterRekomendasi;

class MasterRekomendasiPsychSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // Kesehatan Mental & Emosi — Stres Akademik (SAKD)
            ['kode'=>'SAKD_01','judul'=>'Konseling individu berbasis manajemen stres','deskripsi'=>'Membantu siswa mengenali pemicu stres akademik dan melatih teknik regulasi emosi.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','ujian','nilai','deadline','pusing'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'SAKD_02','judul'=>'Pelatihan manajemen waktu & belajar','deskripsi'=>'Mengajarkan teknik time blocking, Pomodoro, dan strategi belajar efektif.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','ujian','deadline','remed'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'SAKD_03','judul'=>'Koordinasi guru mapel untuk beban tugas','deskripsi'=>'Menyelaraskan jadwal/penugasan agar realistis dan tidak menumpuk.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','deadline','kelas','mapel'],'topik'=>'Kesehatan Mental & Emosi']],

            // Kecemasan Sosial (KSOS)
            ['kode'=>'KSOS_01','judul'=>'Latihan role-play & public speaking','deskripsi'=>'Simulasi aman untuk meningkatkan paparan dan kepercayaan diri tampil.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['takut','malu','gugup','presentasi','diam'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'KSOS_02','judul'=>'Kelompok dukungan sebaya','deskripsi'=>'Fasilitasi dukungan teman agar rasa aman sosial meningkat.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['takut','malu','teman','grup'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'KSOS_03','judul'=>'Umpan balik positif bertahap dari guru','deskripsi'=>'Penguatan positif terstruktur untuk membangun kepercayaan diri.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['gugup','pujian','dukungan'],'topik'=>'Kesehatan Mental & Emosi']],

            // Depresi Ringan (DPRN)
            ['kode'=>'DPRN_01','judul'=>'Skrining awal & konseling suportif','deskripsi'=>'Deteksi dini gejala dan dukungan emosional terarah.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['sedih','kosong','nangis','menarik diri'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'DPRN_02','judul'=>'Rutinitas harian sehat','deskripsi'=>'Menstabilkan tidur, makan, dan aktivitas fisik ringan untuk perbaikan mood.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tidur','lelah','makan'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'DPRN_03','judul'=>'Rujukan profesional bila gejala menetap','deskripsi'=>'Pastikan akses ke psikolog/konselor jika gejala berlanjut.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['menarik diri','nangis','turun minat'],'topik'=>'Kesehatan Mental & Emosi']],

            // Gangguan Tidur (GTDR)
            ['kode'=>'GTDR_01','judul'=>'Edukasi sleep hygiene & batas layar','deskripsi'=>'Kurangi paparan layar malam dan terapkan kebiasaan tidur sehat.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['begadang','insomnia','ngantuk','layar'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'GTDR_02','judul'=>'Rutinitas relaksasi sebelum tidur','deskripsi'=>'Latihan relaksasi ringan untuk menurunkan aktivasi sebelum tidur.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['relaksasi','napas','tenang'],'topik'=>'Kesehatan Mental & Emosi']],
            ['kode'=>'GTDR_03','judul'=>'Penjadwalan tugas yang realistis','deskripsi'=>'Mengatur beban akademik agar tidak menekan di malam hari.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','malam','deadline'],'topik'=>'Kesehatan Mental & Emosi']],

            // Bullying Tatap Muka (BTMK)
            ['kode'=>'BTMK_01','judul'=>'Program anti-bullying berbasis sekolah (Roots)','deskripsi'=>'Bangun agen perubahan sebaya untuk mengubah norma kelas/sekolah.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['bully','ejek','eksklusi','dorong'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'BTMK_02','judul'=>'Mediasi & konsekuensi restoratif','deskripsi'=>'Pulihkan relasi dan tanggung jawab melalui pendekatan restoratif.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['berantem','konflik','damai'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'BTMK_03','judul'=>'Sistem pelaporan aman','deskripsi'=>'Sediakan kanal aman dan mudah diakses untuk pelaporan.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['lapor','aman','pengaduan'],'topik'=>'Sosial & Pergaulan']],

            // Cyberbullying (CBUL)
            ['kode'=>'CBUL_01','judul'=>'Literasi digital & etika online','deskripsi'=>'Edukasi perilaku aman dan etika bermedia sosial.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['sebar','hina','akun palsu','dm'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'CBUL_02','judul'=>'Prosedur takedown & bukti digital','deskripsi'=>'Tindak lanjut cepat: dokumentasi dan takedown konten.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['bukti','screenshot','takedown'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'CBUL_03','judul'=>'Keterlibatan orang tua terarah','deskripsi'=>'Selaraskan dukungan rumah-sekolah menghadapi kasus cyberbullying.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['orang tua','lapor','medsos'],'topik'=>'Sosial & Pergaulan']],

            // Tekanan Teman Sebaya (TTSP)
            ['kode'=>'TTSP_01','judul'=>'Pelatihan asertif & penolakan sehat','deskripsi'=>'Latih bahasa menolak dengan aman tanpa konflik.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tekanan','ikut-ikutan','nolak'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'TTSP_02','judul'=>'Peer leadership positif','deskripsi'=>'Angkat teladan siswa sebagai pemimpin prososial.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['teladan','leader','peer'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'TTSP_03','judul'=>'Kampanye norma prososial','deskripsi'=>'Geser standar kelompok menuju perilaku prososial.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['kampanye','norma','positif'],'topik'=>'Sosial & Pergaulan']],

            // Kesepian / Isolasi (KISO)
            ['kode'=>'KISO_01','judul'=>'Mentoring sebaya (buddy system)','deskripsi'=>'Pasangkan teman pendamping untuk rasa aman awal.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['kesepian','sendirian','dukungan'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'KISO_02','judul'=>'Kegiatan ko-kurikuler inklusif','deskripsi'=>'Fasilitasi kegiatan sesuai minat untuk membangun relasi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['minat','ekstrakurikuler','komunitas'],'topik'=>'Sosial & Pergaulan']],
            ['kode'=>'KISO_03','judul'=>'Pelatihan empati di kelas','deskripsi'=>'Bangun kepekaan sosial antar siswa.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['empati','peduli','teman'],'topik'=>'Sosial & Pergaulan']],

            // Konflik Orang Tua / Broken Home (KOTH)
            ['kode'=>'KOTH_01','judul'=>'Konseling keluarga terarah','deskripsi'=>'Perbaiki komunikasi keluarga dan batas yang sehat.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['ribut','pisah','takut'],'topik'=>'Keluarga & Pola Asuh']],
            ['kode'=>'KOTH_02','judul'=>'Pertemuan wali kelas-orang tua','deskripsi'=>'Selaraskan dukungan belajar di rumah.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['wali','orang tua','komunikasi'],'topik'=>'Keluarga & Pola Asuh']],
            ['kode'=>'KOTH_03','judul'=>'Rujukan psikolog bila ada risiko','deskripsi'=>'Tangani dampak emosional serius melalui profesional.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['trauma','psikolog','takut'],'topik'=>'Keluarga & Pola Asuh']],

            // Tekanan Prestasi Keluarga (TPKL)
            ['kode'=>'TPKL_01','judul'=>'Edukasi parenting & komunikasi suportif','deskripsi'=>'Geser pola asuh ke dukungan, bukan tekanan.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['dituntut','ranking','dibandingkan'],'topik'=>'Keluarga & Pola Asuh']],
            ['kode'=>'TPKL_02','judul'=>'Kontrak belajar realistis','deskripsi'=>'Target bertahap yang bisa dicapai.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['target','realistis','kontrak'],'topik'=>'Keluarga & Pola Asuh']],
            ['kode'=>'TPKL_03','judul'=>'Konseling motivasi berorientasi proses','deskripsi'=>'Pulihkan motivasi intrinsik dan fokus proses.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['motivasi','proses','belajar'],'topik'=>'Keluarga & Pola Asuh']],

            // Motivasi Belajar Rendah (MBRD)
            ['kode'=>'MBRD_01','judul'=>'Konseling motivasi (goal setting)','deskripsi'=>'Tetapkan tujuan pribadi yang bermakna dan terukur.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['males','nggak minat','capek'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'MBRD_02','judul'=>'Strategi pembelajaran aktif','deskripsi'=>'Metode aktif untuk meningkatkan partisipasi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['aktif','diskusi','partisipasi'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'MBRD_03','judul'=>'Sistem penguatan positif','deskripsi'=>'Penguatan progres kecil dan konsisten.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['apresiasi','reward','progres'],'topik'=>'Akademis & Disiplin']],

            // Prokrastinasi Tugas (PRTG)
            ['kode'=>'PRTG_01','judul'=>'Pelatihan manajemen waktu (Pomodoro)','deskripsi'=>'Teknik jeda-terstruktur untuk mulai mengerjakan.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['nunda','deadline','perfeksionis'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'PRTG_02','judul'=>'Segmentasi tugas kecil','deskripsi'=>'Pecah tugas besar menjadi langkah-langkah kecil.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','pecah','bagian'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'PRTG_03','judul'=>'Pengingat & monitoring wali kelas','deskripsi'=>'Pengingat periodik dan cek-progres.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['pengingat','monitor','wali'],'topik'=>'Akademis & Disiplin']],

            // Ketidakhadiran / Bolos (KBLN)
            ['kode'=>'KBLN_01','judul'=>'Home visit & koordinasi orang tua','deskripsi'=>'Memahami akar masalah kehadiran dari sisi rumah.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['bolos','telat','cabut'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'KBLN_02','judul'=>'Disiplin positif berbasis pemulihan','deskripsi'=>'Fokus pada pemulihan dan tanggung jawab, bukan hukuman.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['disiplin','positif','pemulihan'],'topik'=>'Akademis & Disiplin']],
            ['kode'=>'KBLN_03','judul'=>'Kontrak kehadiran bertahap','deskripsi'=>'Target kehadiran progresif dan terukur.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['hadir','kontrak','target'],'topik'=>'Akademis & Disiplin']],

            // Kurang Aktivitas Fisik (KAFK)
            ['kode'=>'KAFK_01','judul'=>'Program olahraga ringan rutin','deskripsi'=>'Sediakan opsi aktivitas mudah dan konsisten.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['olahraga','jalan','senam'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],
            ['kode'=>'KAFK_02','judul'=>'Klub fisik rekreatif','deskripsi'=>'Tarik minat melalui kegiatan seru dan sosial.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['klub','rekreatif','komunitas'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],
            ['kode'=>'KAFK_03','judul'=>'Edukasi manfaat aktivitas untuk mood & fokus','deskripsi'=>'Hubungkan gerak dengan performa belajar.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['manfaat','fokus','mood'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],

            // Pola Tidur & Gizi Buruk (PTGB)
            ['kode'=>'PTGB_01','judul'=>'Sarapan bersama di sekolah (pilot)','deskripsi'=>'Dorong kebiasaan pagi sehat di sekolah.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['sarapan','lemas','kurang tidur'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],
            ['kode'=>'PTGB_02','judul'=>'Edukasi gizi & tidur sehat','deskripsi'=>'Tingkatkan literasi kesehatan terkait gizi & tidur.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['gizi','tidur','sehat'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],
            ['kode'=>'PTGB_03','judul'=>'Penjadwalan ulang tugas berat','deskripsi'=>'Kurangi beban larut malam yang mengganggu tidur.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['tugas','malam','jadwal'],'topik'=>'Kesehatan Fisik & Gaya Hidup']],

            // Konflik Percintaan (KPCR)
            ['kode'=>'KPCR_01','judul'=>'Konseling emosi & komunikasi','deskripsi'=>'Latih keterampilan berbicara tanpa menyakiti.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['cemburu','curiga','berantem'],'topik'=>'Relasi & Percintaan']],
            ['kode'=>'KPCR_02','judul'=>'Batasan sehat dalam relasi','deskripsi'=>'Tetapkan ekspektasi realistis dan batas yang aman.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['batas','posesif','privasi'],'topik'=>'Relasi & Percintaan']],
            ['kode'=>'KPCR_03','judul'=>'Dukungan sebaya terarah','deskripsi'=>'Sediakan ruang curhat aman dan terarah.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['dukungan','curhat','teman'],'topik'=>'Relasi & Percintaan']],

            // Putus & Kehilangan (PTKH)
            ['kode'=>'PTKH_01','judul'=>'Konseling dukacita remaja','deskripsi'=>'Validasi emosi dan fasilitasi proses pemulihan.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['putus','sedih','menarik diri'],'topik'=>'Relasi & Percintaan']],
            ['kode'=>'PTKH_02','judul'=>'Aktivitas pemulihan diri','deskripsi'=>'Journaling/olahraga ringan sebagai katarsis sehat.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['journaling','olahraga','pulih'],'topik'=>'Relasi & Percintaan']],
            ['kode'=>'PTKH_03','judul'=>'Monitoring wali kelas','deskripsi'=>'Pantau keberlanjutan belajar pasca-krisis.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['monitoring','wali','kelas'],'topik'=>'Relasi & Percintaan']],

            // Kebingungan Jurusan/Karier (KJUR)
            ['kode'=>'KJUR_01','judul'=>'Tes minat-bakat & interpretasi','deskripsi'=>'Pemetaan objektif profil diri untuk arah karier.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['minat','bakat','karier'],'topik'=>'Karier & Masa Depan']],
            ['kode'=>'KJUR_02','judul'=>'Pameran jurusan/profesi','deskripsi'=>'Perluas wawasan pilihan studi/profesi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['jurusan','profesi','info'],'topik'=>'Karier & Masa Depan']],
            ['kode'=>'KJUR_03','judul'=>'Konseling karier berjenjang','deskripsi'=>'Rencana langkah realistis pasca-SMA.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['rencana','kuliah','kerja'],'topik'=>'Karier & Masa Depan']],

            // Hambatan Ekonomi (HEKO)
            ['kode'=>'HEKO_01','judul'=>'Klinik beasiswa & literasi finansial','deskripsi'=>'Bantu strategi daftar beasiswa dan perencanaan keuangan.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['beasiswa','biaya','spp'],'topik'=>'Karier & Masa Depan']],
            ['kode'=>'HEKO_02','judul'=>'Kemitraan beasiswa lokal','deskripsi'=>'Hubungkan sumber dukungan setempat.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['kemitraan','donatur','lokal'],'topik'=>'Karier & Masa Depan']],
            ['kode'=>'HEKO_03','judul'=>'Skema kerja paruh waktu ramah sekolah','deskripsi'=>'Seimbangkan kerja-belajar dengan aman.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['paruh waktu','kerja','jadwal'],'topik'=>'Karier & Masa Depan']],

            // Pelanggaran Tata Tertib (PTTB)
            ['kode'=>'PTTB_01','judul'=>'Disiplin positif & restoratif','deskripsi'=>'Fokus pemulihan dan tanggung jawab, bukan hukuman.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['poin','seragam','hp','sepatu'],'topik'=>'Disiplin & Tata Tertib']],
            ['kode'=>'PTTB_02','judul'=>'Edukasi aturan beserta alasannya','deskripsi'=>'Memahamkan makna aturan agar internalisasi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['aturan','alasan','edukasi'],'topik'=>'Disiplin & Tata Tertib']],
            ['kode'=>'PTTB_03','judul'=>'Keterlibatan orang tua bila berulang','deskripsi'=>'Dukungan keluarga untuk konsistensi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['orang tua','ulang','dukungan'],'topik'=>'Disiplin & Tata Tertib']],

            // Manajemen Waktu Buruk (MWBK)
            ['kode'=>'MWBK_01','judul'=>'Workshop perencanaan mingguan','deskripsi'=>'Ajari teknik prioritisasi dan perencanaan minggu.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['prioritas','jadwal','planning'],'topik'=>'Disiplin & Tata Tertib']],
            ['kode'=>'MWBK_02','judul'=>'Kalender bersama kelas','deskripsi'=>'Transparansi jadwal kolektif untuk mengurangi bentrok.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['kalender','kelas','bentrok'],'topik'=>'Disiplin & Tata Tertib']],
            ['kode'=>'MWBK_03','judul'=>'Cek-in berkala wali kelas','deskripsi'=>'Menjaga akuntabilitas progres siswa.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['cek-in','monitor','wali'],'topik'=>'Disiplin & Tata Tertib']],

            // Overuse Media Sosial (OMSO)
            ['kode'=>'OMSO_01','judul'=>'Detoks digital bertahap','deskripsi'=>'Turunkan paparan adiktif secara aman dan bertahap.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['scroll','fomo','bandingin','kacau tidur'],'topik'=>'Digital Wellbeing']],
            ['kode'=>'OMSO_02','judul'=>'Edukasi literasi media','deskripsi'=>'Kritis terhadap konten dan algoritma media sosial.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['literasi','media','konten'],'topik'=>'Digital Wellbeing']],
            ['kode'=>'OMSO_03','judul'=>'Tantangan kelas tanpa gawai tertentu','deskripsi'=>'Ubah norma kelas soal gawai pada momen tertentu.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['gawai','tantangan','kelas'],'topik'=>'Digital Wellbeing']],

            // Game Berlebihan (GBRL)
            ['kode'=>'GBRL_01','judul'=>'Kontrak waktu bermain sehat','deskripsi'=>'Tetapkan batas jelas dan disepakati bersama.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['game','rank','subuh','marah'],'topik'=>'Digital Wellbeing']],
            ['kode'=>'GBRL_02','judul'=>'Aktivitas pengganti yang memuaskan','deskripsi'=>'Ganti reward dopamin tinggi dengan kegiatan alternatif.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['pengganti','aktivitas','reward'],'topik'=>'Digital Wellbeing']],
            ['kode'=>'GBRL_03','judul'=>'Keterlibatan orang tua konsisten','deskripsi'=>'Satukan aturan rumah-sekolah untuk konsistensi.','severity'=>'low','is_active'=>true,'rules'=>['min_neg_score'=>-0.05,'any_keywords'=>['orang tua','atur','konsisten'],'topik'=>'Digital Wellbeing']],

            // Kekerasan Fisik/Verbal oleh Dewasa (KFVD)
            ['kode'=>'KFVD_01','judul'=>'Kode etik & pelatihan disiplin positif','deskripsi'=>'Ubah praktik hukuman menjadi pendekatan edukatif.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['dibentak','ditampar','dipermalukan'],'topik'=>'Keamanan & Keselamatan']],
            ['kode'=>'KFVD_02','judul'=>'Mekanisme aduan aman','deskripsi'=>'Sediakan kanal aman untuk suara siswa.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['aduan','aman','lapor'],'topik'=>'Keamanan & Keselamatan']],
            ['kode'=>'KFVD_03','judul'=>'Pendampingan korban oleh BK','deskripsi'=>'Pulihkan rasa aman melalui pendampingan intensif.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['korban','bk','aman'],'topik'=>'Keamanan & Keselamatan']],

            // Perundungan Berbasis Gender (PBGD)
            ['kode'=>'PBGD_01','judul'=>'Aturan anti-GBV & sosialisasi','deskripsi'=>'Ciptakan standar nol toleransi pada GBV.','severity'=>'high','is_active'=>true,'rules'=>['min_neg_score'=>-0.15,'any_keywords'=>['gender','pelecehan','catcalling'],'topik'=>'Keamanan & Keselamatan']],
            ['kode'=>'PBGD_02','judul'=>'Edukasi kesetaraan & hormat','deskripsi'=>'Tingkatkan pemahaman nilai hormat dan kesetaraan.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['kesetaraan','hormat','edukasi'],'topik'=>'Keamanan & Keselamatan']],
            ['kode'=>'PBGD_03','judul'=>'Intervensi saksi (bystander)','deskripsi'=>'Aktifkan peran saksi untuk mencegah.','severity'=>'medium','is_active'=>true,'rules'=>['min_neg_score'=>-0.10,'any_keywords'=>['saksi','bystander','intervensi'],'topik'=>'Keamanan & Keselamatan']],
        ];

        foreach ($data as $row) {
            MasterRekomendasi::updateOrCreate(
                ['kode' => $row['kode']],
                [
                    'judul'     => $row['judul'],
                    'deskripsi' => $row['deskripsi'] ?: null,
                    'severity'  => $row['severity'] ?? 'low',
                    'is_active' => $row['is_active'] ?? true,
                    'rules'     => $row['rules'],
                ]
            );
        }
    }
}
