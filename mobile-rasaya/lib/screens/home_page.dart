import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_drawer.dart';
import 'package:intl/intl.dart';

// Helpers
String _fmtTanggal(BuildContext context, String? iso) {
  if (iso == null) return '-';
  try {
    final d = DateTime.parse(iso);
    final l = MaterialLocalizations.of(context);
    return l.formatFullDate(d);
  } catch (_) {
    return iso;
  }
}

// Providers: ambil beberapa item terakhir, mirip History
final recentRefleksiProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final api = ref.read(apiClientProvider);
  final res = await api.get('/input-siswa', query: {'page': 1, 'per_page': 3});
  if (!res.ok) throw Exception(res.errorMessage);
  final m = (res.data as Map).cast<String, dynamic>();
  final list = (m['data'] as List? ?? const []);
  return list
      .map<Map<String, dynamic>>((e) => (e as Map).cast<String, dynamic>())
      .toList();
});

final recentMoodProvider =
    FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final api = ref.read(apiClientProvider);
  final res = await api.getMoodHistory(page: 1, perPage: 5);
  if (!res.ok) throw Exception(res.errorMessage);
  final m = (res.data as Map).cast<String, dynamic>();
  final list = (m['data'] as List? ?? const []);
  return list
      .map<Map<String, dynamic>>((e) => (e as Map).cast<String, dynamic>())
      .toList();
});

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(authControllerProvider);
    final me = state.me ?? {};

    if (state.loading && me.isEmpty) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final name = (me['name'] ?? '-').toString();
    final role = (me['role'] ?? '-').toString();
    final identifier = (me['identifier'] ?? '-').toString();

    // status cepat: fetch daftar terbaru
    final refleksiAsync = ref.watch(recentRefleksiProvider);
    final moodAsync = ref.watch(recentMoodProvider);
    // ambil jadwal saya untuk shortcut: kita ambil dari endpoint /bookings/me (1x di build)
    final futureMyBookings = ref.watch(_myBookingsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
      ),
      drawer: const AppDrawer(),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Salam
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    child: Text(name.isNotEmpty
                        ? name.characters.first.toUpperCase()
                        : '?'),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Halo, $name 👋',
                            style: const TextStyle(
                                fontSize: 18, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 4),
                        Wrap(spacing: 8, children: [
                          Chip(label: Text('Role: $role')),
                          Chip(label: Text('ID: $identifier')),
                        ]),
                        const SizedBox(height: 6),
                        const Text('Semoga harimu menyenangkan!'),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Quick actions
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _QuickAction(
                icon: Icons.edit_note,
                label: 'Tulis Refleksi',
                onTap: () async {
                  final res = await context.push('/refleksi');
                  if (context.mounted && res == true) {
                    // refresh daftar
                    ref.invalidate(recentRefleksiProvider);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Refleksi tersimpan.')),
                    );
                  }
                },
              ),
              _QuickAction(
                icon: Icons.history,
                label: 'Riwayat',
                onTap: () => context.push('/history'),
              ),
              _QuickAction(
                icon: Icons.mood,
                label: 'Mood Tracker',
                onTap: () async {
                  final res = await context.push('/mood');
                  if (context.mounted && res == true) {
                    // refresh daftar
                    ref.invalidate(recentMoodProvider);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Mood tersimpan.')),
                    );
                  }
                },
              ),
            ],
          ),

          const SizedBox(height: 16),

          // Status Cepat - tampil sama seperti di History
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Status Cepat',
                        style: TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),

                    // Shortcut Konseling Terjadwal
                    _UpcomingCounselingSection(
                        futureMyBookings: futureMyBookings),
                    const SizedBox(height: 16),

                    // Refleksi terbaru (beberapa item)
                    const Text('Refleksi Terbaru',
                        style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    refleksiAsync.when(
                      data: (items) {
                        if (items.isEmpty) {
                          return const Text('Belum ada riwayat refleksi.');
                        }
                        return Column(
                          children: items.map((m) {
                            final tanggal = _fmtTanggal(context,
                                (m['tanggal'] ?? m['created_at'])?.toString());
                            final teks = (m['teks'] ?? '').toString();
                            final draft =
                                (m['status_upload']?.toString() ?? '1') == '0';
                            final jenis = (m['meta']?['jenis'] ??
                                    (m['siswa_dilapor_id'] != null
                                        ? 'laporan'
                                        : 'pribadi'))
                                .toString();
                            final kategori =
                                (m['kategoris'] as List?) ?? const [];

                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Card(
                                clipBehavior: Clip.antiAlias,
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(children: [
                                          Text(tanggal,
                                              style: const TextStyle(
                                                  fontWeight: FontWeight.w700)),
                                          const SizedBox(width: 8),
                                          if (draft)
                                            const Chip(
                                              label: Text('Draft',
                                                  style:
                                                      TextStyle(fontSize: 12)),
                                              visualDensity:
                                                  VisualDensity.compact,
                                            ),
                                          const SizedBox(width: 6),
                                          Chip(
                                            label: Text(
                                                jenis == 'laporan'
                                                    ? 'Laporan Teman'
                                                    : 'Pribadi',
                                                style: const TextStyle(
                                                    fontSize: 12)),
                                            visualDensity:
                                                VisualDensity.compact,
                                          ),
                                        ]),
                                        const SizedBox(height: 8),
                                        Text(teks,
                                            maxLines: 4,
                                            overflow: TextOverflow.ellipsis),
                                        if (kategori.isNotEmpty) ...[
                                          const SizedBox(height: 8),
                                          Wrap(
                                            spacing: 6,
                                            runSpacing: -6,
                                            children: kategori.map((k) {
                                              final nama =
                                                  (k['nama'] ?? '').toString();
                                              return Chip(
                                                label: Text(nama,
                                                    style: const TextStyle(
                                                        fontSize: 12)),
                                                visualDensity:
                                                    VisualDensity.compact,
                                              );
                                            }).toList(),
                                          ),
                                        ],
                                      ]),
                                ),
                              ),
                            );
                          }).toList(),
                        );
                      },
                      loading: () => const Padding(
                        padding: EdgeInsets.all(8.0),
                        child: LinearProgressIndicator(minHeight: 2),
                      ),
                      error: (_, __) => const Text('Gagal memuat refleksi.'),
                    ),

                    const SizedBox(height: 16),

                    // Mood terbaru (beberapa item)
                    const Text('Mood Terbaru',
                        style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    moodAsync.when(
                      data: (items) {
                        if (items.isEmpty) {
                          return const Text('Belum ada riwayat mood.');
                        }
                        const emojis = [
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
                        return Column(
                          children: items.map((m) {
                            final tgl = _fmtTanggal(
                                context, (m['tanggal'] ?? '').toString());
                            final sesi =
                                (m['sesi'] ?? '').toString().toUpperCase();
                            final s =
                                int.tryParse((m['skor'] ?? '').toString()) ?? 0;
                            final emoji =
                                (s >= 1 && s <= 10) ? emojis[s - 1] : '•';
                            final adaLampiran = (m['gambar'] != null &&
                                (m['gambar'] as String).isNotEmpty);

                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Card(
                                child: ListTile(
                                  leading: Text(emoji,
                                      style: const TextStyle(fontSize: 24)),
                                  title: Text(tgl),
                                  subtitle: Text('Sesi $sesi'),
                                  trailing: adaLampiran
                                      ? const Icon(Icons.attachment)
                                      : null,
                                ),
                              ),
                            );
                          }).toList(),
                        );
                      },
                      loading: () => const Padding(
                        padding: EdgeInsets.all(8.0),
                        child: LinearProgressIndicator(minHeight: 2),
                      ),
                      error: (_, __) => const Text('Gagal memuat mood.'),
                    ),
                  ]),
            ),
          ),

          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction(
      {required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Ink(
        width: 200,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).dividerColor),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon),
          const SizedBox(width: 10),
          Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
        ]),
      ),
    );
  }
}

// Provider untuk fetch bookings saya (digunakan untuk shortcut di dashboard)
final _myBookingsProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final api = ref.read(apiClientProvider);
  final res = await api.getMyBookings();
  if (!res.ok || res.data is! Map) return <String, dynamic>{'data': []};
  return (res.data as Map).cast<String, dynamic>();
});

class _UpcomingCounselingSection extends ConsumerWidget {
  const _UpcomingCounselingSection({required this.futureMyBookings});
  final AsyncValue<Map<String, dynamic>> futureMyBookings;

  String _fmtWita(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    try {
      final dt = DateTime.parse(iso).toUtc().add(const Duration(hours: 8));
      return DateFormat('HH.mm', 'id_ID').format(dt);
    } catch (_) {
      return '';
    }
  }

  String _fmtTanggal(DateTime dtWita) {
    return DateFormat('EEEE, d MMMM y', 'id_ID').format(dtWita);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return futureMyBookings.when(
      loading: () => const LinearProgressIndicator(minHeight: 2),
      error: (_, __) => const Text('Gagal memuat jadwal konseling.'),
      data: (m) {
        final list = (m['data'] as List? ?? const [])
            .map<Map<String, dynamic>>(
                (e) => (e as Map).cast<String, dynamic>())
            .toList();
        // ambil yang masih 'booked' dan di masa depan (WITA)
        final nowWita = DateTime.now().toUtc().add(const Duration(hours: 8));
        final upcoming = list.where((b) {
          final status = (b['status'] ?? '').toString();
          final endIso = (b['slot']?['end_at'] ?? b['end_at'] ?? '').toString();
          if (status != 'booked' || endIso.isEmpty) return false;
          try {
            final endWita =
                DateTime.parse(endIso).toUtc().add(const Duration(hours: 8));
            return endWita.isAfter(nowWita);
          } catch (_) {
            return false;
          }
        }).toList()
          ..sort((a, b) {
            final aStart =
                (a['slot']?['start_at'] ?? a['start_at'] ?? '').toString();
            final bStart =
                (b['slot']?['start_at'] ?? b['start_at'] ?? '').toString();
            try {
              return DateTime.parse(aStart).compareTo(DateTime.parse(bStart));
            } catch (_) {
              return 0;
            }
          });

        if (upcoming.isEmpty) {
          return Card(
            color: Theme.of(context).colorScheme.surfaceVariant,
            child: ListTile(
              leading: const Icon(Icons.event_busy),
              title: const Text('Tidak ada konseling terjadwal'),
              subtitle:
                  const Text('Ayo cek ketersediaan slot dan lakukan booking.'),
              trailing: FilledButton(
                onPressed: () => GoRouter.of(context).push('/booking'),
                child: const Text('Cari Jadwal'),
              ),
            ),
          );
        }

        final next = upcoming.first;
        final startIso =
            (next['slot']?['start_at'] ?? next['start_at'] ?? '').toString();
        final endIso =
            (next['slot']?['end_at'] ?? next['end_at'] ?? '').toString();
        DateTime? startWita;
        try {
          startWita =
              DateTime.parse(startIso).toUtc().add(const Duration(hours: 8));
        } catch (_) {}
        final dateLabel = startWita != null ? _fmtTanggal(startWita) : '-';
        final timeLabel = '${_fmtWita(startIso)} - ${_fmtWita(endIso)} WITA';
        final lokasi =
            (next['slot']?['lokasi'] ?? next['lokasi'] ?? '').toString();

        return Card(
          color: Theme.of(context).colorScheme.surfaceVariant,
          child: ListTile(
            leading: const Icon(Icons.event_available),
            title: Text(dateLabel,
                style: const TextStyle(fontWeight: FontWeight.w600)),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Waktu: $timeLabel'),
                if (lokasi.isNotEmpty) Text('Lokasi: $lokasi'),
              ],
            ),
            trailing: FilledButton(
              onPressed: () => GoRouter.of(context).push('/my-schedule'),
              child: const Text('Lihat'),
            ),
          ),
        );
      },
    );
  }
}
