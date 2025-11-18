import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';

class RefleksiPage extends ConsumerStatefulWidget {
  final String? initialJenis;

  const RefleksiPage({super.key, this.initialJenis});

  @override
  ConsumerState<RefleksiPage> createState() => _RefleksiPageState();
}

class _RefleksiPageState extends ConsumerState<RefleksiPage> {
  final _formKey = GlobalKey<FormState>();
  final _teksCtrl = TextEditingController();
  DateTime _tanggal = DateTime.now();
  String _jenis = 'pribadi'; // 'pribadi' | 'laporan'
  int? _laporSiswaKelasId; // siswa yang dilaporkan (opsional)
  String? _laporSiswaNama;
  bool loading = false;

  // real upload
  XFile? _pickedFile; // Android/iOS
  Uint8List? _webBytes; // Web
  String? _webFilename; // Web

  // cache siswa untuk dropdown (boleh kosong)
  List<Map<String, dynamic>> _siswa = [];
  bool _loadingSiswa = false;

  @override
  void initState() {
    super.initState();
    // Set jenis dari parameter jika ada
    if (widget.initialJenis == 'laporan') {
      _jenis = 'laporan';
    }
  }

  @override
  void dispose() {
    _teksCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickTanggal() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _tanggal,
      firstDate: DateTime.now(), // tidak bisa pilih tanggal lalu
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _tanggal = picked);
  }

  Future<void> _loadSiswaIfNeeded() async {
    if (_siswa.isNotEmpty || _loadingSiswa) return;
    setState(() => _loadingSiswa = true);
    try {
      final api = ref.read(apiClientProvider);
      final res = await api.get(
          '/siswa-list'); // returns {id(user), nama, siswa_kelas_id?, kelas_label?}
      if (res.ok && res.data is Map && res.data['data'] is List) {
        final me = ref.read(authControllerProvider).me ?? {};
        final myUserId = me['id'];
        final list = (res.data['data'] as List).cast<Map>();
        _siswa = list
            .map((e) => {
                  // id yang akan dikirim ke server: pakai siswa_kelas_id jika ada
                  'id': e['siswa_kelas_id'] ?? e['id'],
                  'nama': (e['nama'] ?? e['siswa_nama'] ?? '').toString(),
                  'user_id': e['user_id'] ?? e['siswa_user_id'] ?? e['id'],
                  'kelas': (e['kelas_label'] ?? '').toString(),
                })
            .where((m) => (m['user_id'] ?? -1) != myUserId)
            .toList();
        // Sortir: per kelas lalu nama
        _siswa.sort((a, b) {
          final ka = (a['kelas'] ?? '') as String;
          final kb = (b['kelas'] ?? '') as String;
          final cmpK = ka.toUpperCase().compareTo(kb.toUpperCase());
          if (cmpK != 0) return cmpK;
          final na = (a['nama'] ?? '') as String;
          final nb = (b['nama'] ?? '') as String;
          return na.toUpperCase().compareTo(nb.toUpperCase());
        });
      } else {
        debugPrint('GET /siswa-list gagal: ${res.errorMessage}');
      }
    } catch (e) {
      debugPrint('load siswa error: $e');
    } finally {
      if (mounted) setState(() => _loadingSiswa = false);
    }
  }

  Future<void> _pickFile() async {
    if (kIsWeb) {
      final res = await FilePicker.platform.pickFiles(type: FileType.image);
      if (res != null && res.files.isNotEmpty) {
        final f = res.files.first;
        setState(() {
          _webBytes = f.bytes;
          _webFilename = f.name;
          _pickedFile = null; // ensure only one source used
        });
      }
      return;
    }
    final picker = ImagePicker();
    final x =
        await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (x != null) {
      setState(() {
        _pickedFile = x;
        _webBytes = null;
        _webFilename = null;
      });
    }
  }

  void _resetFile() {
    setState(() {
      _pickedFile = null;
      _webBytes = null;
      _webFilename = null;
    });
  }

  String _fmtTanggal(DateTime d) {
    final loc = MaterialLocalizations.of(context);
    return loc.formatFullDate(d);
  }

  // === SUBMIT KE SERVER ===================================================

  Future<void> _submit({required int statusUpload}) async {
    if (!_formKey.currentState!.validate()) return;

    if (_jenis == 'laporan' && _laporSiswaKelasId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih siswa yang ingin dilaporkan.')),
      );
      return;
    }

    setState(() => loading = true);
    try {
      final api = ref.read(apiClientProvider);
      final fields = <String, dynamic>{
        'tanggal': _tanggal.toIso8601String().split('T').first,
        'teks': _teksCtrl.text.trim(),
        'status_upload': statusUpload, // 0=draft, 1=final
        if (_jenis == 'laporan' && _laporSiswaKelasId != null)
          'siswa_dilapor_kelas_id': _laporSiswaKelasId,
        // Kirim meta sebagai nested fields agar diterima sebagai array oleh Laravel
        'meta[src]': 'flutter',
        'meta[jenis]': _jenis,
      };

      final res = await api.postMultipartFlexible(
        '/input-siswa',
        fields: fields,
        xfile: kIsWeb ? null : _pickedFile,
        bytes: kIsWeb ? _webBytes : null,
        filename: kIsWeb ? _webFilename : null,
        fileField: 'gambar',
      );

      if (!mounted) return;
      if (res.ok) {
        _teksCtrl.clear();
        _laporSiswaKelasId = null;
        _laporSiswaNama = null;
        _resetFile();

        final msg = statusUpload == 0
            ? 'Draft tersimpan.'
            : 'Refleksi berhasil terkirim.';
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(msg)));
        final router = GoRouter.of(context);
        if (router.canPop()) {
          context.pop(true);
        } else {
          router.go('/');
        }
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
            filtered = _siswa.where((m) {
              final nm = (m['nama'] ?? '').toString().toLowerCase();
              final kl = (m['kelas'] ?? '').toString().toLowerCase();
              return nm.contains(qq) || kl.contains(qq);
            }).toList();
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
                                  subtitle: Text(
                                    (m['kelas'] as String).isNotEmpty
                                        ? m['kelas'] as String
                                        : '—',
                                  ),
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
        _laporSiswaKelasId = selected['id'] as int?;
        _laporSiswaNama = selected['nama']?.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final hintText = _jenis == 'laporan'
        ? 'Mau ngasih heads-up soal temanmu? Ceritain singkat apa yang terjadi, kronologi, dan hal penting yang perlu guru tau biar bisa bantu dengan tepat.'
        : 'Cerita dikit tentang harimu yuk—lagi ngerasa apa? Ada momen yang bikin kamu ke-trigger, seneng, atau capek? Tulis santai aja ✍️';

    final displayFilename = _pickedFile?.name ?? _webFilename;
    final left = _FormKiri(
      formKey: _formKey,
      tanggal: _tanggal,
      pickedFilename: displayFilename,
      jenis: _jenis,
      onPickTanggal: _pickTanggal,
      onJenisChanged: (v) async {
        setState(() => _jenis = v);
        if (v == 'laporan') {
          await _loadSiswaIfNeeded();
        } else {
          setState(() {
            _laporSiswaKelasId = null;
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

      onPickFile: _pickFile,
      onResetFile: _resetFile,
      loading: loading,

      // tombol
      onSaveDraft: () => _submit(statusUpload: 0),
      onSubmit: () => _submit(statusUpload: 1),

      fmtTanggal: _fmtTanggal,
    );

    final right = const _PanduanMenulis();

    // Tentukan title berdasarkan jenis
    final pageTitle = _jenis == 'laporan' ? 'Lapor Teman' : 'Refleksi Harian';

    return AppScaffold(
      title: pageTitle,
      body: SafeArea(
        child: LayoutBuilder(
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
    required this.pickedFilename,
    required this.onPickFile,
    required this.onResetFile,
    required this.loading,
    required this.onSaveDraft,
    required this.onSubmit,
    required this.fmtTanggal,
    Key? key,
  }) : super(key: key);

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

  final String? pickedFilename;
  final VoidCallback onPickFile;
  final VoidCallback onResetFile;

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
                      initialValue: jenis,
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

              // Upload gambar (opsional)
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
                          pickedFilename ?? 'Pilih file…',
                          style: TextStyle(
                            color: pickedFilename == null
                                ? Colors.grey[600]
                                : null,
                          ),
                        ),
                      ),
                      if (pickedFilename != null)
                        IconButton(
                          tooltip: 'Hapus',
                          onPressed: loading ? null : onResetFile,
                          icon: const Icon(Icons.close),
                        ),
                      ElevatedButton.icon(
                        onPressed: loading ? null : onPickFile,
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
