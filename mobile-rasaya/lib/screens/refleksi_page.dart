import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/auth_controller.dart';
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
  int? _laporSiswaId; // siswa yang dilaporkan (opsional)
  String? _laporSiswaNama;
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
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _tanggal = picked);
  }

  Future<void> _loadSiswaIfNeeded() async {
    if (_siswa.isNotEmpty || _loadingSiswa) return;
    setState(() => _loadingSiswa = true);
    try {
      final api = ref.read(apiClientProvider);
      // Debug: print request
      print('Loading siswa list...');

      final res = await api.get('/siswas'); // Sesuaikan dengan route di backend
      print('Response: ${res.data}'); // Debug response

      if (res.ok && res.data is Map && res.data['data'] is List) {
        final me = ref.read(authControllerProvider).me ?? {};
        final myUserId = me['id'];
        _siswa = (res.data['data'] as List)
            .cast<Map>()
            .map((e) => {
                  'id': e['user_id'] ?? e['id'],
                  'nama': (e['name'] ?? e['nama'] ?? 'Tanpa Nama').toString(),
                })
            .where((m) => m['id'] != myUserId)
            .toList();

        print('Loaded ${_siswa.length} siswa'); // Debug hasil
      }
    } catch (e) {
      print('Error loading siswa: $e'); // Debug error
    } finally {
      if (mounted) setState(() => _loadingSiswa = false);
    }
  }

  String _fmtTanggal(DateTime d) {
    final loc = MaterialLocalizations.of(context);
    return loc.formatFullDate(d);
  }

  // === SUBMIT KE SERVER ===================================================

  Future<void> _submit({required int statusUpload}) async {
    // validasi minimal teks
    if (!_formKey.currentState!.validate()) return;

    // validasi laporan: harus pilih siswa
    if (_jenis == 'laporan' && _laporSiswaId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih siswa yang ingin dilaporkan.')),
      );
      return;
    }

    setState(() => loading = true);
    try {
      final api = ref.read(apiClientProvider);
      final payload = <String, dynamic>{
        'tanggal': _tanggal.toIso8601String().split('T').first,
        'teks': _teksCtrl.text.trim(),
        'status_upload': statusUpload, // 0=draft, 1=final
        if (_jenis == 'laporan' && _laporSiswaId != null)
          'siswa_dilapor_id': _laporSiswaId,
        'meta': {
          'src': 'flutter',
          if (_mockFilename != null) 'mock_file': _mockFilename,
          'jenis': _jenis,
        },
      };

      final res = await api.post('/input-siswa', payload);

      if (!mounted) return;
      if (res.ok) {
        // reset minimal
        _teksCtrl.clear();
        _laporSiswaId = null;
        _laporSiswaNama = null;

        final msg = statusUpload == 0
            ? 'Draft tersimpan.'
            : 'Refleksi berhasil terkirim.';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(msg)));

        context.go('/home');
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

  // === PICKER SISWA DENGAN SEARCH ========================================

  Future<void> _pickSiswa() async {
    // pastikan data ada
    await _loadSiswaIfNeeded();
    if (!mounted) return;

    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        // state lokal untuk search & filter
        final searchCtrl = TextEditingController();
        List<Map<String, dynamic>> filtered = List.of(_siswa);

        void applyFilter(String q) {
          final qq = q.trim().toLowerCase();
          if (qq.isEmpty) {
            filtered = List.of(_siswa);
          } else {
            filtered = _siswa
                .where((m) =>
                    (m['nama'] ?? '').toString().toLowerCase().contains(qq))
                .toList();
          }
        }

        return DraggableScrollableSheet(
          initialChildSize: 0.85,
          minChildSize: 0.6,
          maxChildSize: 0.95,
          builder: (ctx, scrollController) {
            return StatefulBuilder(builder: (ctx, setBS) {
              return Material(
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(16)),
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    // header
                    Container(
                      height: 48,
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      alignment: Alignment.centerLeft,
                      child: const Text('Pilih Siswa',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.w600)),
                    ),
                    // search box
                    Padding(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 8),
                      child: TextField(
                        controller: searchCtrl,
                        decoration: const InputDecoration(
                          prefixIcon: Icon(Icons.search),
                          hintText: 'Cari nama siswa…',
                          border: OutlineInputBorder(),
                        ),
                        onChanged: (q) => setBS(() {
                          applyFilter(q);
                        }),
                      ),
                    ),
                    if (_loadingSiswa)
                      const LinearProgressIndicator(minHeight: 2),

                    // list hasil
                    Expanded(
                      child: filtered.isEmpty
                          ? const Center(
                              child: Text('Tidak ada hasil'),
                            )
                          : ListView.separated(
                              controller: scrollController,
                              itemCount: filtered.length,
                              separatorBuilder: (_, __) =>
                                  const Divider(height: 1),
                              itemBuilder: (_, i) {
                                final m = filtered[i];
                                return ListTile(
                                  leading: const Icon(Icons.person_outline),
                                  title: Text(m['nama'].toString()),
                                  subtitle: Text('ID: ${m['id']}'),
                                  onTap: () => Navigator.pop(ctx, m),
                                );
                              },
                            ),
                    ),
                  ],
                ),
              );
            });
          },
        );
      },
    );

    if (selected != null) {
      setState(() {
        _laporSiswaId = selected['id'] as int?;
        _laporSiswaNama = selected['nama']?.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final hintText = _jenis == 'laporan'
        ? 'Ceritakan apa yang kamu ketahui tentang keadaan temanmu, '
            'kronologi singkat, dan hal penting yang perlu diketahui guru BK.'
        : 'Ceritakan perasaan, pengalaman, atau hal penting yang kamu alami hari ini…';

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
          setState(() {
            _laporSiswaId = null;
            _laporSiswaNama = null;
          });
        }
      },
      // gunakan field pilih siswa dengan search (custom picker)
      siswaPickerLabel: _laporSiswaNama ?? '— Pilih Siswa —',
      onTapPilihSiswa: _jenis == 'laporan' ? _pickSiswa : null,
      siswaLoading: _loadingSiswa && _jenis == 'laporan',

      teksCtrl: _teksCtrl,
      isiHint: hintText,

      onPickMock: () => setState(() =>
          _mockFilename = 'bukti_${DateTime.now().millisecondsSinceEpoch}.jpg'),
      onResetMock: () => setState(() => _mockFilename = null),
      loading: loading,

      // tombol
      onSaveDraft: () => _submit(statusUpload: 0),
      onSubmit: () => _submit(statusUpload: 1),

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
    required this.siswaPickerLabel,
    required this.onTapPilihSiswa,
    required this.siswaLoading,
    required this.teksCtrl,
    required this.isiHint,
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

  // picker siswa (dengan search)
  final String siswaPickerLabel;
  final VoidCallback? onTapPilihSiswa;
  final bool siswaLoading;

  final TextEditingController teksCtrl;
  final String isiHint;

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
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
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
                        decoration: const InputDecoration(
                          border: OutlineInputBorder(),
                        ),
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
                      onChanged: loading
                          ? null
                          : (v) => onJenisChanged(v ?? 'pribadi'),
                      decoration:
                          const InputDecoration(border: OutlineInputBorder()),
                    ),
                  ),
                ),
              ]),

              const SizedBox(height: 12),

              // Pilih siswa (hanya saat laporan) — via picker search
              if (jenis == 'laporan') ...[
                _FieldGroup(
                  label: 'Pilih Siswa (opsional jika melaporkan teman)',
                  helper:
                      'Jika refleksi bersifat pribadi, bagian ini dikosongkan.',
                  child: InkWell(
                    onTap: loading ? null : onTapPilihSiswa,
                    borderRadius: BorderRadius.circular(8),
                    child: InputDecorator(
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        hintText: '— Pilih Siswa —',
                      ),
                      child: Row(
                        children: [
                          Expanded(child: Text(siswaPickerLabel)),
                          const Icon(Icons.arrow_drop_down),
                        ],
                      ),
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
                  decoration: InputDecoration(
                    hintText: isiHint,
                    border: const OutlineInputBorder(),
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
                  decoration:
                      const InputDecoration(border: OutlineInputBorder()),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          mockFilename ?? 'Pilih file... (mockup)',
                          style: TextStyle(
                            color:
                                mockFilename == null ? Colors.grey[600] : null,
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
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.send),
                  label: const Text('Kirim Refleksi'),
                ),
              ]),
            ],
          ),
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
          ],
        ),
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
