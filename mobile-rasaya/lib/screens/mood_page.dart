// import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_rasaya/auth/auth_controller.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../widgets/app_scaffold.dart';

class MoodPage extends ConsumerStatefulWidget {
  const MoodPage({super.key});
  @override
  ConsumerState<MoodPage> createState() => _MoodPageState();
}

class _MoodPageState extends ConsumerState<MoodPage>
    with TickerProviderStateMixin {
  // 10 emojis → score 1..10
  static const _emojis = [
    '😞', //1 awful-
    '😟', //2 awful
    '🙁', //3 bad-
    '😕', //4 bad
    '😐', //5 meh
    '🙂', //6 meh+
    '😊', //7 good-
    '😃', //8 good
    '😄', //9 rad-
    '🤩' //10 rad
  ];

  // Main 5 for quick pick: awful, bad, meh, good, rad → map to even scores 2,4,6,8,10
  static const _mainMap = [
    {'label': 'Awful', 'emoji': '😟', 'score': 2},
    {'label': 'Bad', 'emoji': '😕', 'score': 4},
    {'label': 'Meh', 'emoji': '😐', 'score': 6},
    {'label': 'Good', 'emoji': '😃', 'score': 8},
    {'label': 'Rad', 'emoji': '🤩', 'score': 10},
  ];

  int _selectedScore = 6; // default Meh
  bool _loading = false;
  final _catatanCtrl = TextEditingController();

  // optional image (mock filename by default)
  XFile? _imageFile;
  PlatformFile? _webFile;

  // today & history (optional UI ringkas)
  Map<String, dynamic>? _today;
  List<dynamic> _history = [];
  bool _loadingToday = true;
  bool _loadingHistory = true;

  bool _showMore = false;
  late final AnimationController _plusCtrl = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 250));

  @override
  void initState() {
    super.initState();
    _loadToday();
    _loadHistory();
  }

  @override
  void dispose() {
    _catatanCtrl.dispose();
    _plusCtrl.dispose();
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
      context.pop(true); // kembali ke Home dan trigger invalidate
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
    final sesiNow = _today?['sesi_now']?.toString().toUpperCase() ?? '-';
    final itemsToday = ((_today?['items'] as List?) ?? const []);
    final gridCols = MediaQuery.of(context).size.width >= 480 ? 10 : 5;

    String emojiForScore(int s) => (s >= 1 && s <= 10) ? _emojis[s - 1] : '•';

    return AppScaffold(
      title: 'Mood Tracker',
      actions: [
        IconButton(
          tooltip: 'Refresh',
          onPressed: () {
            _loadToday();
            _loadHistory();
          },
          icon: const Icon(Icons.refresh),
        ),
      ],
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Header: playful gradient + quick pick
          Card(
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                          vertical: 18, horizontal: 16),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        gradient: LinearGradient(
                          colors: [
                            Theme.of(context).colorScheme.primary,
                            Theme.of(context).colorScheme.secondary
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                      ),
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Lagi ngerasa gimana?',
                                style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16)),
                            const SizedBox(height: 4),
                            Text('Sesi sekarang: $sesiNow',
                                style: const TextStyle(color: Colors.white70)),
                          ]),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: _mainMap.map((m) {
                        final score = m['score'] as int;
                        final selected = _selectedScore == score;
                        return _EmojiPill(
                          label: m['label'] as String,
                          emoji: m['emoji'] as String,
                          selected: selected,
                          onTap: _loading
                              ? null
                              : () => setState(() => _selectedScore = score),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 12),
                    Align(
                      alignment: Alignment.centerRight,
                      child: ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                            shape: const StadiumBorder()),
                        onPressed: () {
                          setState(() {
                            _showMore = !_showMore;
                            if (_showMore) {
                              _plusCtrl.forward();
                            } else {
                              _plusCtrl.reverse();
                            }
                          });
                        },
                        icon: RotationTransition(
                            turns:
                                Tween(begin: 0.0, end: .25).animate(_plusCtrl),
                            child: const Icon(Icons.add)),
                        label:
                            Text(_showMore ? 'Sembunyikan' : 'Emoji lainnya'),
                      ),
                    ),
                    AnimatedCrossFade(
                      firstChild: const SizedBox.shrink(),
                      secondChild: Padding(
                        padding: const EdgeInsets.only(top: 8.0),
                        child: GridView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: 10,
                          gridDelegate:
                              SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: gridCols,
                            mainAxisSpacing: 8,
                            crossAxisSpacing: 8,
                            childAspectRatio: 1.2,
                          ),
                          itemBuilder: (_, i) {
                            final score = i + 1;
                            final emoji = _emojis[i];
                            final selected = _selectedScore == score;
                            return _EmojiTile(
                              emoji: emoji,
                              selected: selected,
                              onTap: _loading
                                  ? null
                                  : () =>
                                      setState(() => _selectedScore = score),
                            );
                          },
                        ),
                      ),
                      crossFadeState: _showMore
                          ? CrossFadeState.showSecond
                          : CrossFadeState.showFirst,
                      duration: const Duration(milliseconds: 250),
                    ),
                    const SizedBox(height: 16),

                    // attach image (opsional, mock)
                    _AttachRow(
                      filename: _webFile?.name ?? _imageFile?.name,
                      onPick: _loading ? null : _pickImage,
                      onRemove:
                          ((_webFile == null && _imageFile == null) || _loading)
                              ? null
                              : _removeImage,
                    ),
                    const SizedBox(height: 16),

                    // Catatan opsional
                    TextField(
                      controller: _catatanCtrl,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        labelText: 'Catatan (opsional)',
                        hintText: 'Tulis perasaanmu secara singkat...',
                        border: OutlineInputBorder(),
                      ),
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
                                child:
                                    CircularProgressIndicator(strokeWidth: 2))
                            : const Icon(Icons.send),
                        label: const Text('Simpan Mood'),
                      ),
                    ),
                    const SizedBox(height: 6),
                  ]),
            ),
          ),

          const SizedBox(height: 16),

          // Status hari ini
          Card(
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: _loadingToday
                  ? const Center(child: CircularProgressIndicator())
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                          Text('Status Hari Ini (${_today?['tanggal'] ?? '-'})',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          if (itemsToday.isEmpty)
                            const Text('Belum ada data sesi hari ini.')
                          else
                            Column(
                              children: itemsToday.map((e) {
                                final sesi =
                                    (e['sesi'] ?? '').toString().toUpperCase();
                                final skor = (e['skor'] ?? '').toString();
                                // map skor 1..10 balik ke emoji
                                final s = int.tryParse(skor) ?? 0;
                                final emoji = emojiForScore(s);
                                return ListTile(
                                  dense: true,
                                  leading: Text(emoji,
                                      style: const TextStyle(fontSize: 20)),
                                  title: Text('Sesi $sesi'),
                                  subtitle: (e['gambar'] != null &&
                                          (e['gambar'] as String).isNotEmpty)
                                      ? const Text('Ada lampiran')
                                      : null,
                                );
                              }).toList(),
                            ),
                        ]),
            ),
          ),

          const SizedBox(height: 16),

          // Riwayat mini
          Card(
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: _loadingHistory
                  ? const Center(child: CircularProgressIndicator())
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                          const Text('Riwayat Terakhir',
                              style: TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 8),
                          if (_history.isEmpty)
                            const Text('Belum ada riwayat.')
                          else
                            Column(
                              children: _history.map((e) {
                                final tgl = (e['tanggal'] ?? '').toString();
                                final sesi =
                                    (e['sesi'] ?? '').toString().toUpperCase();
                                final s = int.tryParse(
                                        (e['skor'] ?? '').toString()) ??
                                    0;
                                final emoji = emojiForScore(s);
                                return ListTile(
                                  dense: true,
                                  leading: Text(emoji,
                                      style: const TextStyle(fontSize: 20)),
                                  title: Text(tgl),
                                  subtitle: Text('Sesi $sesi'),
                                );
                              }).toList(),
                            ),
                        ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmojiPill extends StatelessWidget {
  const _EmojiPill(
      {required this.label,
      required this.emoji,
      required this.selected,
      required this.onTap});
  final String label;
  final String emoji;
  final bool selected;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected
              ? Theme.of(context).colorScheme.primary.withOpacity(.12)
              : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
              color: selected
                  ? Theme.of(context).colorScheme.primary
                  : Colors.grey.shade300),
        ),
        child: Column(
          children: [
            AnimatedScale(
                scale: selected ? 1.2 : 1.0,
                duration: const Duration(milliseconds: 200),
                child: Text(emoji, style: const TextStyle(fontSize: 22))),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                    color: selected
                        ? Theme.of(context).colorScheme.primary
                        : null)),
          ],
        ),
      ),
    );
  }
}

class _EmojiTile extends StatelessWidget {
  const _EmojiTile(
      {required this.emoji, required this.selected, required this.onTap});
  final String emoji;
  final bool selected;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Ink(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
              color: selected
                  ? Theme.of(context).colorScheme.primary
                  : Theme.of(context).dividerColor),
          color: selected
              ? Theme.of(context).colorScheme.primary.withOpacity(.08)
              : null,
        ),
        child: Center(child: Text(emoji, style: const TextStyle(fontSize: 22))),
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
    return InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Lampiran (opsional)',
        border: OutlineInputBorder(),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              filename ?? 'Tidak ada file terpilih',
              style:
                  TextStyle(color: filename == null ? Colors.grey[600] : null),
            ),
          ),
          if (filename != null)
            IconButton(
              tooltip: 'Hapus',
              onPressed: onRemove,
              icon: const Icon(Icons.close),
            ),
          ElevatedButton.icon(
            onPressed: onPick,
            icon: const Icon(Icons.upload_file),
            label: const Text('Pilih'),
          ),
        ],
      ),
    );
  }
}
