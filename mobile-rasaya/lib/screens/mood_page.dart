// import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_rasaya/auth/auth_controller.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../widgets/app_scaffold.dart';
import 'package:intl/intl.dart';

class MoodPage extends ConsumerStatefulWidget {
  const MoodPage({super.key});
  @override
  ConsumerState<MoodPage> createState() => _MoodPageState();
}

class _MoodPageState extends ConsumerState<MoodPage>
    with TickerProviderStateMixin {
  // Helper: Map score (1..10) → color (red → yellow → green)
  Color _colorForScore(int s, {double opacity = 1}) {
    final t = ((s.clamp(1, 10) - 1) / 9.0);
    final hue = 120.0 * t; // 0=red → 120=green, mid≈60=yellow
    final hsv = HSVColor.fromAHSV(1, hue, 0.85, 0.95);
    return hsv.toColor().withOpacity(opacity);
  }

  // Helper: Choose face icon by band (very bad → very good)
  IconData _iconForScore(int s, {bool filled = false}) {
    final b = s.clamp(1, 10);
    if (b <= 2) {
      return filled
          ? Icons.sentiment_very_dissatisfied
          : Icons.sentiment_very_dissatisfied_outlined;
    } else if (b <= 4) {
      return filled
          ? Icons.sentiment_dissatisfied
          : Icons.sentiment_dissatisfied_outlined;
    } else if (b <= 6) {
      return filled
          ? Icons.sentiment_neutral
          : Icons.sentiment_neutral_outlined;
    } else if (b <= 8) {
      return filled
          ? Icons.sentiment_satisfied
          : Icons.sentiment_satisfied_outlined;
    } else {
      return filled
          ? Icons.sentiment_very_satisfied
          : Icons.sentiment_very_satisfied_outlined;
    }
  }

  int _selectedScore = 5; // default Meh
  bool _loading = false;
  final _catatanCtrl = TextEditingController();
  bool _showAll = false; // show second set of 5 on demand

  // optional image (mock filename by default)
  XFile? _imageFile;
  PlatformFile? _webFile;

  // today & history (optional UI ringkas)
  Map<String, dynamic>? _today;
  List<dynamic> _history = [];
  bool _loadingToday = true;
  bool _loadingHistory = true;

  // removed: old expand/collapse for extra emojis

  String _fmtTanggalIndo(String? raw) {
    if (raw == null || raw.isEmpty) return '-';
    try {
      if (raw.contains('T')) {
        final dt = DateTime.parse(raw);
        final dtWita = dt.isUtc ? dt.toUtc().add(const Duration(hours: 8)) : dt;
        final d = DateTime(dtWita.year, dtWita.month, dtWita.day);
        return DateFormat('EEEE, d MMMM y', 'id_ID').format(d);
      }
      final p = raw.split('-');
      if (p.length == 3) {
        final y = int.parse(p[0]);
        final m = int.parse(p[1]);
        final day = int.parse(p[2]);
        final dt = DateTime(y, m, day);
        return DateFormat('EEEE, d MMMM y', 'id_ID').format(dt);
      }
      final dt = DateTime.parse(raw);
      return DateFormat('EEEE, d MMMM y', 'id_ID').format(dt);
    } catch (_) {
      return raw;
    }
  }

  String _fmtSesiLabel(String sesi) {
    if (sesi.isEmpty) return '';
    final lower = sesi.toLowerCase();
    final cap = lower[0].toUpperCase() + lower.substring(1);
    return 'Sesi $cap';
  }

  @override
  void initState() {
    super.initState();
    _loadToday();
    _loadHistory();
  }

  @override
  void dispose() {
    _catatanCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadToday() async {
    setState(() => _loadingToday = true);
    final api = ref.read(apiClientProvider);
    final res = await api.getMoodToday();
    if (res.ok && res.data is Map) _today = res.data as Map<String, dynamic>;
    if (mounted) setState(() => _loadingToday = false);
  }

  Future<void> _loadHistory() async {
    setState(() => _loadingHistory = true);
    final api = ref.read(apiClientProvider);
    final res = await api.getMoodHistory(perPage: 10);
    if (res.ok && res.data is Map && res.data['data'] is List) {
      _history = (res.data['data'] as List);
    }
    if (mounted) setState(() => _loadingHistory = false);
  }

  Future<void> _pickImage() async {
    if (kIsWeb) {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.image,
        withData: true, // penting untuk dapat bytes
      );
      if (result != null && result.files.isNotEmpty) {
        setState(() {
          _webFile = result.files.first;
          _imageFile = null;
        });
      }
    } else {
      final picker = ImagePicker();
      final x =
          await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
      if (x != null) {
        setState(() {
          _imageFile = x;
          _webFile = null;
        });
      }
    }
  }

  Future<void> _removeImage() async {
    setState(() {
      _imageFile = null;
      _webFile = null;
    });
  }

  Future<void> _submit() async {
    setState(() => _loading = true);
    final api = ref.read(apiClientProvider);
    final score = _selectedScore.clamp(1, 10);
    final catatan = _catatanCtrl.text.trim();

    final res = await api.postMood(
      score,
      gambarFile: _imageFile,
      webBytes: _webFile?.bytes,
      webFilename: _webFile?.name,
      catatan: catatan.isEmpty ? null : catatan,
    );
    setState(() => _loading = false);

    if (res.ok) {
      if (!mounted) return;
      final router = GoRouter.of(context);
      if (router.canPop()) {
        context.pop(true); // kembali dan trigger invalidate di Home
      } else {
        router.go(
            '/'); // fallback kalau tidak ada route untuk di-pop (web, direct route)
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
  }

  @override
  Widget build(BuildContext context) {
    // Colors: use navbar navy (primary) and pink (secondary)
    final cs = Theme.of(context).colorScheme;
    final Color navy = cs.primary;
    final Color pink = cs.secondary;
    final me = ref.watch(authControllerProvider).me ?? {};
    final fullName = (me['name'] ?? '').toString().trim();
    final firstName =
        fullName.isEmpty ? '' : fullName.split(RegExp(r'\s+')).first;
    final prompt =
        'Bagaimana perasaanmu saat ini${firstName.isNotEmpty ? ' $firstName' : ''}?';
    final sesiNow = _today?['sesi_now']?.toString().toUpperCase() ?? '-';
    final itemsToday = ((_today?['items'] as List?) ?? const []);
    const gridCols = 5; // selalu 5 kolom agar tidak berdempetan
    String _labelForScore(int s) {
      switch (s) {
        case 1:
          return 'sangat buruk';
        case 2:
          return 'buruk';
        case 3:
          return 'kurang baik';
        case 4:
          return 'agak kurang';
        case 5:
          return 'biasa aja';
        case 6:
          return 'lumayan';
        case 7:
          return 'baik';
        case 8:
          return 'sangat baik';
        case 9:
          return 'bagus banget';
        case 10:
          return 'mantap';
        default:
          return '$s';
      }
    }

    return AppScaffold(
      title: 'Pelacak Mood',
      actions: [
        IconButton(
          tooltip: 'Muat ulang',
          onPressed: () {
            _loadToday();
            _loadHistory();
          },
          icon: const Icon(Icons.refresh),
        ),
      ],
      body: Container(
        color: navy,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Header text (no box), Lora + pink
            Center(
              child: Text(
                prompt,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontFamily: 'Lora',
                  fontWeight: FontWeight.w800,
                  fontSize: 28,
                  color: pink,
                ),
              ),
            ),
            const SizedBox(height: 8),
            Center(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: pink.withOpacity(0.8), width: 1.4),
                  color: navy.withOpacity(0.25),
                ),
                child: Text(
                  'Sesi sekarang: $sesiNow',
                  style: TextStyle(
                    color: pink,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.2,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Grid of 5, expand to 10 with +
            AnimatedSize(
              duration: const Duration(milliseconds: 200),
              curve: Curves.easeInOut,
              child: GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _showAll ? 10 : 5,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: gridCols,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 0.9,
                ),
                itemBuilder: (_, i) {
                  const collapsed = [1, 3, 5, 8, 10];
                  final score = _showAll ? (i + 1) : collapsed[i];
                  final selected = _selectedScore == score;
                  return _MoodIconTile(
                    score: score,
                    color: _colorForScore(score),
                    icon: _iconForScore(score, filled: false),
                    selected: selected,
                    label: _labelForScore(score),
                    onTap: _loading
                        ? null
                        : () => setState(() => _selectedScore = score),
                  );
                },
              ),
            ),
            const SizedBox(height: 8),
            Center(
              child: IconButton(
                onPressed: () => setState(() => _showAll = !_showAll),
                icon: Icon(_showAll ? Icons.remove : Icons.add, color: pink),
                style: IconButton.styleFrom(
                  backgroundColor: pink.withOpacity(0.08),
                  side: BorderSide(color: pink.withOpacity(0.35)),
                ),
              ),
            ),
            const SizedBox(height: 8),

            // attach image (opsional)
            _AttachRow(
              filename: _webFile?.name ?? _imageFile?.name,
              onPick: _loading ? null : _pickImage,
              onRemove: ((_webFile == null && _imageFile == null) || _loading)
                  ? null
                  : _removeImage,
            ),
            const SizedBox(height: 16),

            // Catatan opsional
            TextField(
              controller: _catatanCtrl,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: 'Tambahkan Catatan',
                hintText: 'Tulis perasaanmu secara singkat...',
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderSide: BorderSide(color: navy.withOpacity(0.15)),
                  borderRadius: BorderRadius.circular(12),
                ),
                enabledBorder: OutlineInputBorder(
                  borderSide: BorderSide(color: navy.withOpacity(0.15)),
                  borderRadius: BorderRadius.circular(12),
                ),
                focusedBorder: OutlineInputBorder(
                  borderSide:
                      BorderSide(color: navy.withOpacity(0.35), width: 1.5),
                  borderRadius: BorderRadius.circular(12),
                ),
                labelStyle: TextStyle(color: navy),
                hintStyle: TextStyle(color: navy.withOpacity(0.7)),
              ),
              style: TextStyle(color: navy),
            ),

            const SizedBox(height: 16),

            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _loading ? null : _submit,
                icon: _loading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2))
                    : Icon(Icons.send, color: navy),
                label: Text('Simpan',
                    style: TextStyle(color: navy, fontWeight: FontWeight.w700)),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: navy,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                ).copyWith(
                  overlayColor: WidgetStatePropertyAll(pink.withOpacity(0.08)),
                  shadowColor: WidgetStatePropertyAll(pink.withOpacity(0.12)),
                ),
              ),
            ),
            const SizedBox(height: 6),
            const SizedBox(height: 16),
            // Divider spacing
            const SizedBox(height: 8),
            // The rest sections follow

            const SizedBox(height: 16),

            // Status hari ini
            Card(
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: _loadingToday
                    ? const Center(child: CircularProgressIndicator())
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                            Text(
                                'Status Hari Ini (${_fmtTanggalIndo(_today?['tanggal']?.toString())})',
                                style: TextStyle(
                                    fontWeight: FontWeight.w700, color: navy)),
                            const SizedBox(height: 8),
                            if (itemsToday.isEmpty)
                              Text('Belum ada data sesi hari ini.',
                                  style:
                                      TextStyle(color: navy.withOpacity(0.8)))
                            else
                              Column(
                                children: itemsToday.map((e) {
                                  final sesi = (e['sesi'] ?? '')
                                      .toString()
                                      .toUpperCase();
                                  final skor = (e['skor'] ?? '').toString();
                                  // map skor 1..10 balik ke emoji
                                  final s = int.tryParse(skor) ?? 0;
                                  return ListTile(
                                    dense: true,
                                    leading: Icon(
                                      _iconForScore(s, filled: true),
                                      color: _colorForScore(s),
                                    ),
                                    title: Text(_fmtSesiLabel(sesi),
                                        style: TextStyle(color: navy)),
                                    subtitle: (e['gambar'] != null &&
                                            (e['gambar'] as String).isNotEmpty)
                                        ? Text('Ada lampiran',
                                            style: TextStyle(
                                                color: navy.withOpacity(0.75)))
                                        : null,
                                  );
                                }).toList(),
                              ),
                          ]),
              ),
            ),

            const SizedBox(height: 16),

            // Riwayat mini (playful cards)
            Card(
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: _loadingHistory
                    ? const Center(child: CircularProgressIndicator())
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                            Text('Riwayat Terakhir',
                                style: TextStyle(
                                    fontWeight: FontWeight.w700, color: navy)),
                            const SizedBox(height: 8),
                            if (_history.isEmpty)
                              Text('Belum ada riwayat.',
                                  style:
                                      TextStyle(color: navy.withOpacity(0.8)))
                            else
                              Column(
                                children: _history.map((e) {
                                  final tgl = (e['tanggal'] ?? '').toString();
                                  final sesiRaw = (e['sesi'] ?? '')
                                      .toString()
                                      .toUpperCase();
                                  final s = int.tryParse(
                                          (e['skor'] ?? '').toString()) ??
                                      0;
                                  final dateLabel = _fmtTanggalIndo(tgl);
                                  final sesiLabel = _fmtSesiLabel(sesiRaw);
                                  final adaLampiran = (e['gambar'] != null &&
                                      (e['gambar'] as String).isNotEmpty);

                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: _MoodHistoryCard(
                                      score: s,
                                      title: '$dateLabel ($sesiLabel)',
                                      hasAttachment: adaLampiran,
                                    ),
                                  );
                                }).toList(),
                              ),
                          ]),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MoodIconTile extends StatelessWidget {
  const _MoodIconTile({
    required this.score,
    required this.color,
    required this.icon,
    required this.selected,
    required this.onTap,
    required this.label,
  });
  final int score;
  final Color color;
  final IconData icon;
  final bool selected;
  final VoidCallback? onTap;
  final String label;

  @override
  Widget build(BuildContext context) {
    final borderColor = selected ? color : color.withOpacity(0.25);
    const dur = Duration(milliseconds: 220);
    return AnimatedScale(
      duration: dur,
      curve: Curves.easeOut,
      scale: selected ? 1.05 : 1.0,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: AnimatedContainer(
          duration: dur,
          curve: Curves.easeInOut,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: borderColor, width: selected ? 2.0 : 1.2),
            color: Colors.white,
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: color.withOpacity(0.55),
                      blurRadius: 18,
                      spreadRadius: 1,
                    ),
                  ]
                : [
                    BoxShadow(
                      color: color.withOpacity(0.0),
                      blurRadius: 0,
                      spreadRadius: 0,
                    ),
                  ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: color, size: 28),
              const SizedBox(height: 6),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AttachRow extends StatelessWidget {
  const _AttachRow({this.filename, this.onPick, this.onRemove});
  final String? filename;
  final VoidCallback? onPick;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: cs.primary.withOpacity(0.12)),
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: cs.primary.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              filename ?? 'Tambahkan Foto',
              style: TextStyle(
                color: filename == null ? Colors.grey[600] : null,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          if (filename != null)
            IconButton(
              tooltip: 'Hapus',
              onPressed: onRemove,
              icon: const Icon(Icons.close),
            ),
          FilledButton.icon(
            onPressed: onPick,
            icon: const Icon(Icons.upload_file),
            label: const Text('Pilih'),
          ),
        ],
      ),
    );
  }
}

class _MoodHistoryCard extends StatelessWidget {
  const _MoodHistoryCard({
    required this.score,
    required this.title,
    this.hasAttachment = false,
  });
  final int score;
  final String title;
  final bool hasAttachment;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    Color _colorForScoreLocal(int s) {
      final t = ((s.clamp(1, 10) - 1) / 9.0);
      final hue = 120.0 * t;
      return HSVColor.fromAHSV(1, hue, 0.85, 0.95).toColor();
    }

    IconData _iconForScoreLocal(int s) {
      if (s <= 2) return Icons.sentiment_very_dissatisfied;
      if (s <= 4) return Icons.sentiment_dissatisfied;
      if (s <= 6) return Icons.sentiment_neutral;
      if (s <= 8) return Icons.sentiment_satisfied;
      return Icons.sentiment_very_satisfied;
    }

    final color = _colorForScoreLocal(score);
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Colors.white,
            Color.alphaBlend(cs.primary.withOpacity(0.04), Colors.white),
          ],
        ),
        border: Border.all(color: cs.primary.withOpacity(0.08)),
        boxShadow: [
          BoxShadow(
            color: cs.primary.withOpacity(0.06),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Color.alphaBlend(color.withOpacity(0.15), Colors.white),
            ),
            child: Center(
              child: Icon(
                _iconForScoreLocal(score),
                color: color,
                size: 22,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          if (hasAttachment)
            const Padding(
              padding: EdgeInsets.only(left: 8.0),
              child: Icon(Icons.attachment),
            ),
        ],
      ),
    );
  }
}
