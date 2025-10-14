import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_rasaya/auth/auth_controller.dart';
import '../api/api_client.dart';

class MoodPage extends ConsumerStatefulWidget {
  const MoodPage({super.key});
  @override
  ConsumerState<MoodPage> createState() => _MoodPageState();
}

class _MoodPageState extends ConsumerState<MoodPage> {
  // 10 emojis → index 0..9 => score 1..10
  static const _emojis = [
    '😞',
    '😟',
    '🙁',
    '😕',
    '😐',
    '🙂',
    '😊',
    '😃',
    '😄',
    '🤩'
  ];
  int _selected = 5; // default tengah (index 5 -> score 6)
  bool _loading = false;

  // optional image (mock filename by default)
  File? _imageFile;
  String? _mockFilename;

  // today & history (optional UI ringkas)
  Map<String, dynamic>? _today;
  List<dynamic> _history = [];
  bool _loadingToday = true;
  bool _loadingHistory = true;

  @override
  void initState() {
    super.initState();
    _loadToday();
    _loadHistory();
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

  // NOTE: kalau mau real upload, pasang image_picker & API multipart.
  Future<void> _pickImageMock() async {
    setState(() {
      _imageFile = null;
      _mockFilename = 'lampiran_${DateTime.now().millisecondsSinceEpoch}.jpg';
    });
  }

  Future<void> _removeImage() async {
    setState(() {
      _imageFile = null;
      _mockFilename = null;
    });
  }

  Future<void> _submit() async {
    setState(() => _loading = true);
    final api = ref.read(apiClientProvider);
    final score = _selected + 1; // 1..10
    final res = await api.postMood(score, gambar: _mockFilename);
    setState(() => _loading = false);

    if (!mounted) return;
    if (res.ok) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Mood tersimpan.')));
      await _loadToday();
      await _loadHistory();
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
  }

  @override
  Widget build(BuildContext context) {
    final sesiNow = _today?['sesi_now']?.toString().toUpperCase() ?? '-';
    final itemsToday = ((_today?['items'] as List?) ?? const []);
    final gridCols = MediaQuery.of(context).size.width >= 480 ? 10 : 5;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mood Tracker'),
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
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Picker + attach
          Card(
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text('Sesi sekarang: $sesiNow',
                            style:
                                const TextStyle(fontWeight: FontWeight.w700)),
                        const SizedBox(width: 8),
                        const Text('(pilih emoji yang mewakili perasaanmu)',
                            style: TextStyle(color: Colors.grey)),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // emoji grid (1..10)
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _emojis.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount:
                            gridCols, // 10 di layar lebar, 5 di sempit
                        mainAxisSpacing: 8,
                        crossAxisSpacing: 8,
                        childAspectRatio: 1.2,
                      ),
                      itemBuilder: (_, i) {
                        final selected = _selected == i;
                        return InkWell(
                          onTap: _loading
                              ? null
                              : () => setState(() => _selected = i),
                          borderRadius: BorderRadius.circular(12),
                          child: Ink(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                  color: selected
                                      ? Theme.of(context).colorScheme.primary
                                      : Theme.of(context).dividerColor),
                              color: selected
                                  ? Theme.of(context)
                                      .colorScheme
                                      .primary
                                      .withOpacity(.08)
                                  : null,
                            ),
                            child: Center(
                                child: Text(_emojis[i],
                                    style: const TextStyle(fontSize: 22))),
                          ),
                        );
                      },
                    ),

                    const SizedBox(height: 16),

                    // attach image (opsional, mock)
                    _AttachRow(
                      filename: _mockFilename,
                      onPick: _loading ? null : _pickImageMock,
                      onRemove: (_mockFilename == null || _loading)
                          ? null
                          : _removeImage,
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
                                final emoji =
                                    (s >= 1 && s <= 10) ? _emojis[s - 1] : '•';
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
                                final emoji =
                                    (s >= 1 && s <= 10) ? _emojis[s - 1] : '•';
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
