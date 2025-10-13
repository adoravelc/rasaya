import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_rasaya/auth/auth_controller.dart';
import '../api/api_client.dart';

class RefleksiPage extends ConsumerStatefulWidget {
  const RefleksiPage({super.key});

  @override
  ConsumerState<RefleksiPage> createState() => _RefleksiPageState();
}

class _RefleksiPageState extends ConsumerState<RefleksiPage> {
  final _formKey = GlobalKey<FormState>();
  final _teksCtrl = TextEditingController();
  DateTime _tanggal = DateTime.now();
  String _jenis = 'pribadi'; // 'pribadi' | 'laporan'
  int? _laporSiswaId; // muncul hanya saat laporan teman
  bool loading = false;

  // mock upload (belum implementasi file picker)
  String? _mockFilename;

  // cache siswa untuk dropdown (boleh kosong)
  List<Map<String, dynamic>> _siswa = [];
  bool _loadingSiswa = false;

  @override
  void dispose() {
    _teksCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickTanggal() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _tanggal,
      firstDate: DateTime.now().subtract(const Duration(days: 60)),
      lastDate: DateTime.now().add(const Duration(days: 0)),
    );
    if (picked != null) setState(() => _tanggal = picked);
  }

  Future<void> _loadSiswaIfNeeded() async {
    if (_siswa.isNotEmpty || _loadingSiswa) return;
    setState(() => _loadingSiswa = true);
    try {
      final api = ref.read(apiClientProvider);
      // ganti sesuai endpoint kamu; kalau belum ada, tetap aman (empty list)
      final res = await api.get('/siswa?per_page=100'); // contoh
      if (res.ok && res.data is List) {
        _siswa = (res.data as List)
            .cast<Map>()
            .map((e) => {
                  'id': e['id'] as int?,
                  'nama': (e['nama'] ?? e['name'] ?? 'Tanpa Nama').toString(),
                })
            .toList();
      }
    } catch (_) {
      // biarkan kosong kalau gagal
    } finally {
      if (mounted) setState(() => _loadingSiswa = false);
    }
  }

  String _fmtTanggal(DateTime d) {
    // tanpa package intl, gunakan lokal Material
    final loc = MaterialLocalizations.of(context);
    return loc.formatFullDate(d);
  }

  Future<void> _saveDraft() async {
    if (_teksCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Isi refleksi masih kosong.')),
      );
      return;
    }
    // simpan sederhana via shared_prefs (opsional)
    try {
      // ignore: avoid_dynamic_calls
      final sp = await SharedPreferencesAsync.getInstance();
      await sp.setString('refleksi_draft_text', _teksCtrl.text.trim());
      await sp.setString('refleksi_draft_jenis', _jenis);
      await sp.setString('refleksi_draft_tanggal', _tanggal.toIso8601String());
      if (_laporSiswaId != null) {
        await sp.setInt('refleksi_draft_lapor_id', _laporSiswaId!);
      } else {
        await sp.remove('refleksi_draft_lapor_id');
      }
      // filename mock tidak disimpan
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Draft tersimpan.')));
    } catch (_) {
      // kalau package shared_preferences belum ada, fallback notice
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Draft belum diaktifkan (shared_preferences).')));
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => loading = true);
    try {
      final api = ref.read(apiClientProvider);
      final payload = <String, dynamic>{
        'tanggal': _tanggal.toIso8601String().substring(0, 10),
        'teks': _teksCtrl.text.trim(),
        'jenis': _jenis, // backend boleh abaikan jika belum dipakai
        'meta': {
          'src': 'flutter',
          if (_mockFilename != null) 'mock_file': _mockFilename,
          if (_laporSiswaId != null) 'lapor_siswa_id': _laporSiswaId,
        },
      };

      final res = await api.post('/input-siswa', payload);
      if (!mounted) return;
      if (res.ok) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Refleksi terkirim')),
        );
        _teksCtrl.clear();
        context.push('/refleksi/history');
      } else {
        showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Gagal'),
            content: Text(res.errorMessage),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final left = _FormKiri(
      formKey: _formKey,
      tanggal: _tanggal,
      mockFilename: _mockFilename,
      jenis: _jenis,
      onPickTanggal: _pickTanggal,
      onJenisChanged: (v) async {
        setState(() => _jenis = v);
        if (v == 'laporan') {
          await _loadSiswaIfNeeded();
        } else {
          setState(() => _laporSiswaId = null);
        }
      },
      siswaList: _siswa,
      siswaLoading: _loadingSiswa && _jenis == 'laporan',
      laporSiswaId: _laporSiswaId,
      onSiswaChanged: (id) => setState(() => _laporSiswaId = id),
      teksCtrl: _teksCtrl,
      onPickMock: () => setState(() =>
          _mockFilename = 'bukti_${DateTime.now().millisecondsSinceEpoch}.jpg'),
      onResetMock: () => setState(() => _mockFilename = null),
      loading: loading,
      onSaveDraft: _saveDraft,
      onSubmit: _submit,
      fmtTanggal: _fmtTanggal,
    );

    final right = const _PanduanMenulis();

    return Scaffold(
      appBar: AppBar(title: const Text('Refleksi Harian')),
      body: LayoutBuilder(
        builder: (ctx, c) {
          final isWide = c.maxWidth >= 900;
          if (isWide) {
            return Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: left),
                  const SizedBox(width: 16),
                  SizedBox(width: 360, child: right),
                ],
              ),
            );
          }
          // mobile: stack
          return ListView(
            padding: const EdgeInsets.all(12),
            children: [
              left,
              const SizedBox(height: 16),
              right,
            ],
          );
        },
      ),
    );
  }
}

/* =================== Sub-widgets =================== */

class _FormKiri extends StatelessWidget {
  const _FormKiri({
    required this.formKey,
    required this.tanggal,
    required this.jenis,
    required this.onPickTanggal,
    required this.onJenisChanged,
    required this.siswaList,
    required this.siswaLoading,
    required this.laporSiswaId,
    required this.onSiswaChanged,
    required this.teksCtrl,
    required this.mockFilename,
    required this.onPickMock,
    required this.onResetMock,
    required this.loading,
    required this.onSaveDraft,
    required this.onSubmit,
    required this.fmtTanggal,
    super.key,
  });

  final GlobalKey<FormState> formKey;
  final DateTime tanggal;
  final String jenis;
  final VoidCallback onPickTanggal;
  final ValueChanged<String> onJenisChanged;

  final List<Map<String, dynamic>> siswaList;
  final bool siswaLoading;
  final int? laporSiswaId;
  final ValueChanged<int?> onSiswaChanged;

  final TextEditingController teksCtrl;

  final String? mockFilename;
  final VoidCallback onPickMock;
  final VoidCallback onResetMock;

  final bool loading;
  final VoidCallback onSaveDraft;
  final VoidCallback onSubmit;

  final String Function(DateTime) fmtTanggal;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: formKey,
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Form Refleksi',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),

            // Tanggal + Jenis
            Row(children: [
              Expanded(
                child: _FieldGroup(
                  label: 'Tanggal',
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: loading ? null : onPickTanggal,
                    child: InputDecorator(
                      decoration:
                          const InputDecoration(border: OutlineInputBorder()),
                      child: Row(
                        children: [
                          Expanded(child: Text(fmtTanggal(tanggal))),
                          const Icon(Icons.calendar_today, size: 18),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _FieldGroup(
                  label: 'Jenis Refleksi',
                  child: DropdownButtonFormField<String>(
                    value: jenis,
                    items: const [
                      DropdownMenuItem(
                          value: 'pribadi', child: Text('Pribadi')),
                      DropdownMenuItem(
                          value: 'laporan', child: Text('Laporan Teman')),
                    ],
                    onChanged:
                        loading ? null : (v) => onJenisChanged(v ?? 'pribadi'),
                    decoration:
                        const InputDecoration(border: OutlineInputBorder()),
                  ),
                ),
              ),
            ]),

            const SizedBox(height: 12),

            // Pilih siswa (muncul kalau laporan)
            if (jenis == 'laporan') ...[
              _FieldGroup(
                label: 'Pilih Siswa (opsional jika melaporkan teman)',
                helper:
                    'Jika refleksi bersifat pribadi, bagian ini dapat dikosongkan.',
                child: DropdownButtonFormField<int>(
                  value: laporSiswaId,
                  isExpanded: true,
                  items: siswaList
                      .map((e) => DropdownMenuItem<int>(
                            value: e['id'] as int?,
                            child: Text(e['nama'].toString()),
                          ))
                      .toList(),
                  onChanged: loading ? null : onSiswaChanged,
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    hintText: '— Pilih Siswa —',
                  ),
                ),
              ),
              if (siswaLoading)
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: LinearProgressIndicator(minHeight: 2),
                ),
              const SizedBox(height: 12),
            ],

            // Isi refleksi
            _FieldGroup(
              label: 'Isi Refleksi',
              helper:
                  'Saran: tulis jujur dan spesifik. (contoh: apa yang membuat senang/tertekan, bagaimana responsmu, apa yang ingin dilakukan besok)',
              child: TextFormField(
                controller: teksCtrl,
                maxLines: 6,
                minLines: 4,
                decoration: const InputDecoration(
                  hintText:
                      'Ceritakan perasaan, pengalaman, atau hal penting yang kamu alami hari ini...',
                  border: OutlineInputBorder(),
                ),
                validator: (v) {
                  final s = (v ?? '').trim();
                  if (s.isEmpty) return 'Isi refleksi tidak boleh kosong';
                  if (s.length < 5) return 'Minimal 5 karakter';
                  return null;
                },
              ),
            ),

            const SizedBox(height: 12),

            // Upload mock
            _FieldGroup(
              label: 'Unggah Gambar (opsional)',
              helper: 'Gunakan untuk bukti pendukung bila diperlukan.',
              child: InputDecorator(
                decoration: const InputDecoration(border: OutlineInputBorder()),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        mockFilename ?? 'Pilih file... (mockup)',
                        style: TextStyle(
                          color: mockFilename == null ? Colors.grey[600] : null,
                        ),
                      ),
                    ),
                    if (mockFilename != null)
                      IconButton(
                        tooltip: 'Hapus',
                        onPressed: loading ? null : onResetMock,
                        icon: const Icon(Icons.close),
                      ),
                    ElevatedButton.icon(
                      onPressed: loading ? null : onPickMock,
                      icon: const Icon(Icons.upload_file),
                      label: const Text('Pilih'),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Buttons
            Wrap(spacing: 12, runSpacing: 8, children: [
              OutlinedButton.icon(
                onPressed: loading ? null : onSaveDraft,
                icon: const Icon(Icons.save_outlined),
                label: const Text('Simpan Draft'),
              ),
              FilledButton.icon(
                onPressed: loading ? null : onSubmit,
                icon: loading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.send),
                label: const Text('Kirim Refleksi'),
              ),
            ]),
          ]),
        ),
      ),
    );
  }
}

class _PanduanMenulis extends StatelessWidget {
  const _PanduanMenulis();

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Text('Panduan Penulisan',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              SizedBox(height: 12),
              _Bullet('Apa perasaan utama hari ini dan penyebabnya?'),
              _Bullet('Kejadian penting yang kamu alami? (positif/negatif)'),
              _Bullet('Bagaimana kamu merespons situasi tersebut?'),
              _Bullet('Apa yang kamu syukuri dan rencanakan untuk besok?'),
            ]),
      ),
    );
  }
}

class _Bullet extends StatelessWidget {
  const _Bullet(this.text);
  final String text;
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('• $text'),
    );
  }
}

class _FieldGroup extends StatelessWidget {
  const _FieldGroup({
    required this.label,
    required this.child,
    this.helper,
  });

  final String label;
  final Widget child;
  final String? helper;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
      const SizedBox(height: 6),
      child,
      if (helper != null) ...[
        const SizedBox(height: 6),
        Text(helper!, style: TextStyle(color: Colors.grey[700], fontSize: 12)),
      ]
    ]);
  }
}

/* ===== helper mini buat draft tanpa nambah dependency publik =====
   Kalau kamu sudah pakai package shared_preferences, hapus kelas ini
   dan ganti dengan import shared_preferences normal. */

class SharedPreferencesAsync {
  static Future<_DummyPrefs> getInstance() async => _DummyPrefs();
}

class _DummyPrefs {
  final Map<String, Object> _m = {};
  Future<void> setString(String k, String v) async => _m[k] = v;
  Future<void> setInt(String k, int v) async => _m[k] = v;
  Future<void> remove(String k) async => _m.remove(k);
}
