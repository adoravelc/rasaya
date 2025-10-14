import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_rasaya/auth/auth_controller.dart';
import '../api/api_client.dart';

class HistoryPage extends ConsumerStatefulWidget {
  const HistoryPage({super.key});

  @override
  ConsumerState<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends ConsumerState<HistoryPage>
    with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Riwayat'),
        bottom: TabBar(
          controller: _tab,
          tabs: const [
            Tab(text: 'Refleksi'),
            Tab(text: 'Mood'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: const [
          _RefleksiTab(),
          _MoodTab(),
        ],
      ),
    );
  }
}

/* ------------------------ TAB: REFLEKSI ------------------------ */

class _RefleksiTab extends ConsumerStatefulWidget {
  const _RefleksiTab();

  @override
  ConsumerState<_RefleksiTab> createState() => _RefleksiTabState();
}

class _RefleksiTabState extends ConsumerState<_RefleksiTab> {
  bool _loading = true;
  bool _loadingMore = false;
  int _page = 1;
  int _lastPage = 1;
  final List<Map<String, dynamic>> _items = [];

  Future<void> _load({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _loading = true;
        _page = 1;
        _items.clear();
      });
    }
    final api = ref.read(apiClientProvider);
    final res = await api.get('/input-siswa?page=$_page&per_page=10');
    if (res.ok && res.data is Map && res.data['data'] is List) {
      final data = (res.data['data'] as List).cast<Map>();
      _lastPage = (res.data['last_page'] ?? 1) as int;
      _items.addAll(data.cast<Map<String, dynamic>>());
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    if (_page >= _lastPage) return;
    setState(() => _loadingMore = true);
    _page += 1;
    await _load();
    if (mounted) setState(() => _loadingMore = false);
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _fmtTanggal(String? iso) {
    if (iso == null) return '-';
    try {
      final d = DateTime.parse(iso);
      final l = MaterialLocalizations.of(context);
      return l.formatFullDate(d);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _items.length + 1,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (_, i) {
          if (i == _items.length) {
            // footer
            if (_items.isEmpty) {
              return const Center(
                  child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('Belum ada riwayat refleksi.'),
              ));
            }
            if (_page >= _lastPage) {
              return const Center(
                  child: Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: Text('— akhir —', style: TextStyle(color: Colors.grey)),
              ));
            }
            return Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: OutlinedButton(
                  onPressed: _loadingMore ? null : _loadMore,
                  child: _loadingMore
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Muat lagi'),
                ),
              ),
            );
          }

          final m = _items[i];
          final tanggal = _fmtTanggal(m['tanggal']?.toString());
          final teks = (m['teks'] ?? '').toString();
          final draft = (m['status_upload']?.toString() ?? '1') == '0';
          final jenis = (m['meta']?['jenis'] ??
                  (m['siswa_dilapor_id'] != null ? 'laporan' : 'pribadi'))
              .toString();
          final kategori = (m['kategoris'] as List?) ?? const [];

          return Card(
            clipBehavior: Clip.antiAlias,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(tanggal,
                            style:
                                const TextStyle(fontWeight: FontWeight.w700)),
                        const SizedBox(width: 8),
                        if (draft)
                          const Chip(
                            label:
                                Text('Draft', style: TextStyle(fontSize: 12)),
                            visualDensity: VisualDensity.compact,
                          ),
                        const SizedBox(width: 6),
                        Chip(
                          label: Text(
                              jenis == 'laporan' ? 'Laporan Teman' : 'Pribadi',
                              style: const TextStyle(fontSize: 12)),
                          visualDensity: VisualDensity.compact,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      teks,
                      maxLines: 4,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (kategori.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: -6,
                        children: kategori.map((k) {
                          final nama = (k['nama'] ?? '').toString();
                          return Chip(
                            label: Text(nama,
                                style: const TextStyle(fontSize: 12)),
                            visualDensity: VisualDensity.compact,
                          );
                        }).toList(),
                      ),
                    ],
                  ]),
            ),
          );
        },
      ),
    );
  }
}

/* -------------------------- TAB: MOOD -------------------------- */

class _MoodTab extends ConsumerStatefulWidget {
  const _MoodTab();

  @override
  ConsumerState<_MoodTab> createState() => _MoodTabState();
}

class _MoodTabState extends ConsumerState<_MoodTab> {
  bool _loading = true;
  int _page = 1;
  int _lastPage = 1;
  final List<Map<String, dynamic>> _items = [];

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

  Future<void> _load({bool refresh = false}) async {
    if (refresh) {
      setState(() {
        _loading = true;
        _page = 1;
        _items.clear();
      });
    }
    final api = ref.read(apiClientProvider);
    final res = await api.getMoodHistory(page: _page, perPage: 10);
    if (res.ok && res.data is Map && res.data['data'] is List) {
      final data = (res.data['data'] as List).cast<Map>();
      _lastPage = (res.data['last_page'] ?? 1) as int;
      _items.addAll(data.cast<Map<String, dynamic>>());
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadMore() async {
    if (_page >= _lastPage) return;
    _page += 1;
    await _load();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _fmtTanggal(String? iso) {
    if (iso == null) return '-';
    try {
      final d = DateTime.parse(iso);
      final l = MaterialLocalizations.of(context);
      return l.formatFullDate(d);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _items.length + 1,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (_, i) {
          if (i == _items.length) {
            if (_items.isEmpty) {
              return const Center(
                  child: Padding(
                padding: EdgeInsets.all(24),
                child: Text('Belum ada riwayat mood.'),
              ));
            }
            if (_page >= _lastPage) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child:
                      Text('— akhir —', style: TextStyle(color: Colors.grey)),
                ),
              );
            }
            return Center(
              child: OutlinedButton(
                  onPressed: _loadMore, child: const Text('Muat lagi')),
            );
          }

          final m = _items[i];
          final tgl = _fmtTanggal((m['tanggal'] ?? '').toString());
          final sesi = (m['sesi'] ?? '').toString().toUpperCase();
          final s = int.tryParse((m['skor'] ?? '').toString()) ?? 0;
          final emoji = (s >= 1 && s <= 10) ? _emojis[s - 1] : '•';
          final adaLampiran =
              (m['gambar'] != null && (m['gambar'] as String).isNotEmpty);

          return Card(
            child: ListTile(
              leading: Text(emoji, style: const TextStyle(fontSize: 24)),
              title: Text(tgl),
              subtitle: Text('Sesi $sesi'),
              trailing: adaLampiran ? const Icon(Icons.attachment) : null,
            ),
          );
        },
      ),
    );
  }
}
