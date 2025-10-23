import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';
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

// Status refleksi hari ini (self/friend) untuk reminder
final _refleksiTodayStatusProvider =
    FutureProvider<Map<String, dynamic>>((ref) async {
  final api = ref.read(apiClientProvider);
  final res = await api.getRefleksiTodayStatus();
  if (!res.ok || res.data is! Map) return <String, dynamic>{};
  return (res.data as Map).cast<String, dynamic>();
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
    final todayRefleksiStatus = ref.watch(_refleksiTodayStatusProvider);
    // ambil jadwal saya untuk shortcut, tergantung pada refresh counter
    final refreshCounter = ref.watch(bookingRefreshCounterProvider);
    final futureMyBookings = ref.watch(_myBookingsProvider(refreshCounter));

    return AppScaffold(
      title: 'Home',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Salam header dengan gradient (now placed above mood prompt)
          Card(
            clipBehavior: Clip.antiAlias,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Theme.of(context).colorScheme.primary,
                    Theme.of(context).colorScheme.secondary
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: Colors.white,
                    child: Text(
                      name.isNotEmpty
                          ? name.characters.first.toUpperCase()
                          : '?',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Halo, $name 👋',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            )),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          runSpacing: -8,
                          children: [
                            Chip(
                              label: Text(
                                'Role: $role',
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                              ),
                              backgroundColor: Colors.white,
                              visualDensity: VisualDensity.compact,
                            ),
                            Chip(
                              label: Text(
                                'ID: $identifier',
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                              ),
                              backgroundColor: Colors.white,
                              visualDensity: VisualDensity.compact,
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        const Text('Semoga harimu menyenangkan!',
                            style: TextStyle(color: Colors.white70)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          // Quick Mood Prompt (show only if not filled for current session)
          _QuickMoodPrompt(),
          const SizedBox(height: 12),

          // Fun reminder to do self-reflection (if not yet today)
          todayRefleksiStatus.when(
            data: (m) => (m['has_self_today'] == true)
                ? const SizedBox.shrink()
                : const _SelfRefleksiReminder(),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 12),

          // Quick actions (horizontal chips)
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(children: [
              _QuickChip(
                icon: Icons.edit_note,
                label: 'Tulis Refleksi',
                onTap: () async {
                  final res = await context.push('/refleksi');
                  if (context.mounted && res == true) {
                    ref.invalidate(recentRefleksiProvider);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Refleksi tersimpan.')),
                    );
                  }
                },
              ),
              const SizedBox(width: 8),
              _QuickChip(
                icon: Icons.history,
                label: 'Riwayat',
                onTap: () => context.push('/history'),
              ),
              const SizedBox(width: 8),
              _QuickChip(
                icon: Icons.mood,
                label: 'Mood Tracker',
                onTap: () async {
                  final res = await context.push('/mood');
                  if (context.mounted && res == true) {
                    ref.invalidate(recentMoodProvider);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Mood tersimpan.')),
                    );
                  }
                },
              ),
            ]),
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
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 150),
                      child: refleksiAsync.when(
                        data: (items) {
                          if (items.isEmpty) {
                            return const Text('Belum ada riwayat refleksi.');
                          }
                          return Column(
                            children: items.map((m) {
                              final tanggal = _fmtTanggal(
                                  context,
                                  (m['tanggal'] ?? m['created_at'])
                                      ?.toString());
                              final teks = (m['teks'] ?? '').toString();
                              final draft =
                                  (m['status_upload']?.toString() ?? '1') ==
                                      '0';
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
                                                    fontWeight:
                                                        FontWeight.w700)),
                                            const SizedBox(width: 8),
                                            if (draft)
                                              const Chip(
                                                label: Text('Draft',
                                                    style: TextStyle(
                                                        fontSize: 12)),
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
                                                final nama = (k['nama'] ?? '')
                                                    .toString();
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
                    ),

                    const SizedBox(height: 16),

                    // Mood terbaru (beberapa item)
                    const Text('Mood Terbaru',
                        style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 150),
                      child: moodAsync.when(
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
                                  int.tryParse((m['skor'] ?? '').toString()) ??
                                      0;
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

class _QuickMoodPrompt extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_moodTodayProvider);
    return async.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (m) {
        final sesiNow = (m['sesi_now'] ?? '').toString().toUpperCase();
        final items = (m['items'] as List? ?? const []);
        final filled = items
            .any((e) => (e['sesi'] ?? '').toString().toUpperCase() == sesiNow);
        if (sesiNow.isEmpty || filled) return const SizedBox.shrink();
        final theme = Theme.of(context);
        return Card(
          clipBehavior: Clip.antiAlias,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('How are you?',
                    style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: const [
                    _MoodQuickEmoji(label: 'rad', emoji: '🤩'),
                    _MoodQuickEmoji(label: 'good', emoji: '😃'),
                    _MoodQuickEmoji(label: 'meh', emoji: '😐'),
                    _MoodQuickEmoji(label: 'bad', emoji: '😕'),
                    _MoodQuickEmoji(label: 'awful', emoji: '😟'),
                  ],
                ),
                const SizedBox(height: 6),
                Text('Sesi sekarang: $sesiNow',
                    style: TextStyle(color: theme.hintColor)),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _SelfRefleksiReminder extends StatefulWidget {
  const _SelfRefleksiReminder({Key? key}) : super(key: key);

  @override
  State<_SelfRefleksiReminder> createState() => _SelfRefleksiReminderState();
}

class _SelfRefleksiReminderState extends State<_SelfRefleksiReminder>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      color: theme.colorScheme.surfaceVariant.withOpacity(0.6),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Yuk, isi refleksi dirimu hari ini ✍️',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Cuma 1 menit. Biar konselormu tau kabarmu 💛',
                    style: TextStyle(color: theme.hintColor),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: const [
                      Chip(
                          label: Text('Belum ada refleksi diri hari ini'),
                          visualDensity: VisualDensity.compact),
                    ],
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: () => GoRouter.of(context).push('/refleksi'),
                    icon: const Icon(Icons.edit),
                    label: const Text('Refleksi Diri Sekarang'),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            ScaleTransition(
              scale: Tween<double>(begin: 0.92, end: 1.08).animate(
                  CurvedAnimation(parent: _c, curve: Curves.easeInOut)),
              child: const Text('📝', style: TextStyle(fontSize: 42)),
            ),
          ],
        ),
      ),
    );
  }
}

final _moodTodayProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final api = ref.read(apiClientProvider);
  final res = await api.getMoodToday();
  if (!res.ok || res.data is! Map) return <String, dynamic>{};
  return (res.data as Map).cast<String, dynamic>();
});

class _MoodQuickEmoji extends StatelessWidget {
  const _MoodQuickEmoji({required this.label, required this.emoji});
  final String label;
  final String emoji;
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => GoRouter.of(context).push('/mood'),
      child: Column(
        children: [
          AnimatedScale(
            scale: 1.0,
            duration: const Duration(milliseconds: 150),
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(22),
                boxShadow: const [
                  BoxShadow(
                      color: Color(0x14000000),
                      offset: Offset(0, 1),
                      blurRadius: 3),
                ],
              ),
              child: Center(
                  child: Text(emoji, style: const TextStyle(fontSize: 22))),
            ),
          ),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(fontSize: 12, color: Colors.grey)),
        ],
      ),
    );
  }
}

class _QuickChip extends StatelessWidget {
  const _QuickChip(
      {required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8.0),
      child: ActionChip(
        avatar: Icon(icon, size: 18),
        label: Text(label),
        onPressed: onTap,
        shape: const StadiumBorder(),
        backgroundColor: Theme.of(context).chipTheme.backgroundColor,
        side: BorderSide(color: Theme.of(context).dividerColor),
      ),
    );
  }
}

// Provider untuk fetch bookings saya (digunakan untuk shortcut di dashboard)
final _myBookingsProvider = FutureProvider.family<Map<String, dynamic>, int>(
    (ref, refreshCounter) async {
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
