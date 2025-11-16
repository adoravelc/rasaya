import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';
import 'package:intl/intl.dart';

// Clean rebuild of the Home page to fix corruption and match new design

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

// Providers
final recentRefleksiProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return <Map<String, dynamic>>[];
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
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return <Map<String, dynamic>>[];
  final api = ref.read(apiClientProvider);
  final res = await api.getMoodHistory(page: 1, perPage: 5);
  if (!res.ok) throw Exception(res.errorMessage);
  final m = (res.data as Map).cast<String, dynamic>();
  final list = (m['data'] as List? ?? const []);
  return list
      .map<Map<String, dynamic>>((e) => (e as Map).cast<String, dynamic>())
      .toList();
});

final _refleksiTodayStatusProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return <String, dynamic>{};
  final api = ref.read(apiClientProvider);
  final res = await api.getRefleksiTodayStatus();
  if (!res.ok || res.data is! Map) return <String, dynamic>{};
  return (res.data as Map).cast<String, dynamic>();
});

final _myBookingsProvider = FutureProvider.family
    .autoDispose<Map<String, dynamic>, int>((ref, refreshCounter) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return <String, dynamic>{'data': []};
  final api = ref.read(apiClientProvider);
  final res = await api.getMyBookings();
  if (!res.ok || res.data is! Map) return <String, dynamic>{'data': []};
  return (res.data as Map).cast<String, dynamic>();
});

// Mood status for today (to know whether the current session has a mood entry)
final _moodTodayProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return <String, dynamic>{};
  final api = ref.read(apiClientProvider);
  final res = await api.getMoodToday();
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
    final nis = (me['nis'] ?? me['identifier'] ?? '-').toString();
    final kelasLabel = (me['kelas_label'] ?? me['role'] ?? '-').toString();

    final refleksiAsync = ref.watch(recentRefleksiProvider);
    final moodAsync = ref.watch(recentMoodProvider);
    final todayRefleksiStatus = ref.watch(_refleksiTodayStatusProvider);
    final moodToday = ref.watch(_moodTodayProvider);
    final refreshCounter = ref.watch(bookingRefreshCounterProvider);
    final futureMyBookings = ref.watch(_myBookingsProvider(refreshCounter));

    return AppScaffold(
      title: 'Home',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _IdentityHeader(name: name, nis: nis, kelasLabel: kelasLabel),
          const SizedBox(height: 16),
          // 1) Mood shortcut first (if not yet filled for current session)
          moodToday.when(
            data: (m) => _QuickMoodShortcut.fromTodayMap(m, onSaved: () {
              ref.invalidate(_moodTodayProvider);
              ref.invalidate(recentMoodProvider);
            }),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 12),
          // 2) Daily reflection/friend reminder above the big menu (swap order)
          todayRefleksiStatus.when(
            data: (m) {
              final hasSelf = m['has_self_today'] == true;
              final hasFriend = (m['has_friend_report_today'] == true) ||
                  (m['has_friend_today'] == true);
              if (!hasSelf) return const _SelfRefleksiReminder();
              if (hasSelf && !hasFriend) return const _FriendReportReminder();
              return const SizedBox.shrink();
            },
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 16),
          // 3) Big menu grid after reminders (swapped)
          _BigMenuGrid(
            onRefleksiDiri: () async {
              final res = await context.push('/refleksi');
              if (context.mounted && res == true) {
                ref.invalidate(recentRefleksiProvider);
                ref.invalidate(_refleksiTodayStatusProvider);
              }
            },
            onRefleksiTeman: () async {
              final res = await context.push('/refleksi?jenis=laporan');
              if (context.mounted && res == true) {
                ref.invalidate(recentRefleksiProvider);
                ref.invalidate(_refleksiTodayStatusProvider);
              }
            },
            onBooking: () => context.push('/booking'),
            onHistory: () => context.push('/history'),
          ),
          const SizedBox(height: 16),
          const Text('Status Booking Konseling',
              style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          _UpcomingCounselingSection(futureMyBookings: futureMyBookings),
          const SizedBox(height: 16),
          const Text('Refleksi Terbaru',
              style: TextStyle(fontWeight: FontWeight.w700)),
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
                        context, (m['tanggal'] ?? m['created_at'])?.toString());
                    final teks = (m['teks'] ?? '').toString();
                    final draft =
                        (m['status_upload']?.toString() ?? '1') == '0';
                    final jenis = (m['meta']?['jenis'] ??
                            (m['siswa_dilapor_id'] != null
                                ? 'laporan'
                                : 'pribadi'))
                        .toString();
                    final kategori = (m['kategoris'] as List?) ?? const [];

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Card(
                        clipBehavior: Clip.antiAlias,
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(children: [
                                Text(tanggal,
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w700)),
                                const SizedBox(width: 8),
                                if (draft)
                                  const Chip(
                                    label: Text('Draft',
                                        style: TextStyle(fontSize: 12)),
                                    visualDensity: VisualDensity.compact,
                                  ),
                                const SizedBox(width: 6),
                                Chip(
                                  label: Text(
                                      jenis == 'laporan'
                                          ? 'Laporan Teman'
                                          : 'Pribadi',
                                      style: const TextStyle(fontSize: 12)),
                                  visualDensity: VisualDensity.compact,
                                ),
                              ]),
                              const SizedBox(height: 8),
                              Text(teks,
                                  maxLines: 4, overflow: TextOverflow.ellipsis),
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
                            ],
                          ),
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
          const Text('Mood Terbaru',
              style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 150),
            child: moodAsync.when(
              data: (items) {
                if (items.isEmpty) {
                  return const Text('Belum ada riwayat mood.');
                }
                const emojis = [
                  '😓',
                  '😭',
                  '😔',
                  '😟',
                  '😐',
                  '😴',
                  '😊',
                  '😎',
                  '😍',
                  '🤩'
                ];
                return Column(
                  children: items.map((m) {
                    final tgl =
                        _fmtTanggal(context, (m['tanggal'] ?? '').toString());
                    final sesi = (m['sesi'] ?? '').toString().toUpperCase();
                    final s = int.tryParse((m['skor'] ?? '').toString()) ?? 0;
                    final emoji = (s >= 1 && s <= 10) ? emojis[s - 1] : '•';
                    final adaLampiran = (m['gambar'] != null &&
                        (m['gambar'] as String).isNotEmpty);

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Card(
                        child: ListTile(
                          leading:
                              Text(emoji, style: const TextStyle(fontSize: 24)),
                          title: Text(tgl),
                          subtitle: Text('Sesi $sesi'),
                          trailing:
                              adaLampiran ? const Icon(Icons.attachment) : null,
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
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

class _IdentityHeader extends StatelessWidget {
  const _IdentityHeader(
      {required this.name, required this.nis, required this.kelasLabel});
  final String name;
  final String nis;
  final String kelasLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final tt = theme.textTheme;
    return Card(
      elevation: 6,
      shadowColor: cs.primary.withOpacity(0.4),
      clipBehavior: Clip.antiAlias,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 22),
        decoration: BoxDecoration(
          color: cs.primary, // solid navy
        ),
        child: Stack(
          children: [
            // subtle decorative soft pink circles
            Positioned(
              right: -20,
              top: -10,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: cs.secondary.withOpacity(0.20),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Positioned(
              right: 30,
              bottom: -15,
              child: Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: cs.secondary.withOpacity(0.12),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            Row(
              children: [
                Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(
                    color: cs.secondary,
                    shape: BoxShape.circle,
                    boxShadow: const [
                      BoxShadow(
                          color: Color(0x33000000),
                          offset: Offset(0, 4),
                          blurRadius: 10)
                    ],
                  ),
                  child: Center(
                    child: Text(
                      name.isNotEmpty
                          ? name.characters.first.toUpperCase()
                          : '?',
                      style: TextStyle(
                        color: cs.primary,
                        fontWeight: FontWeight.w800,
                        fontSize: 26,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 18),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Halo! 👋',
                          style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: cs.secondary,
                              fontSize: 14)),
                      Text(name,
                          style: tt.headlineSmall?.copyWith(
                            color: cs.secondary,
                          )),
                      const SizedBox(height: 8),
                      Wrap(spacing: 8, runSpacing: -8, children: [
                        _IdentityChip(label: 'NIS: $nis'),
                        _IdentityChip(label: kelasLabel),
                      ]),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Text('🧑‍🎓',
                    style: TextStyle(fontSize: 44, color: cs.secondary)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _IdentityChip extends StatelessWidget {
  const _IdentityChip({required this.label});
  final String label;
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: cs.secondary.withOpacity(0.8), width: 1.4),
        color: cs.primary.withOpacity(0.25),
      ),
      child: Text(label,
          style: TextStyle(
              color: cs.secondary,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.2)),
    );
  }
}

class _BigMenuGrid extends StatelessWidget {
  const _BigMenuGrid({
    required this.onRefleksiDiri,
    required this.onRefleksiTeman,
    required this.onBooking,
    required this.onHistory,
  });
  final VoidCallback onRefleksiDiri;
  final VoidCallback onRefleksiTeman;
  final VoidCallback onBooking;
  final VoidCallback onHistory;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return GridView.count(
      crossAxisCount: 2,
      childAspectRatio: 1.2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        _BigTile(
          label: 'Refleksi Harian',
          emoji: '✍️',
          bg: cs.secondary,
          fg: Colors.black,
          onTap: onRefleksiDiri,
        ),
        _BigTile(
          label: 'Refleksi Teman',
          emoji: '👥',
          bg: cs.primary,
          fg: Colors.white,
          onTap: onRefleksiTeman,
        ),
        _BigTile(
          label: 'Booking Konseling',
          emoji: '📅',
          bg: cs.primary,
          fg: Colors.white,
          onTap: onBooking,
        ),
        _BigTile(
          label: 'Riwayat Input',
          emoji: '🕒',
          bg: cs.secondary,
          fg: Colors.black,
          onTap: onHistory,
        ),
      ],
    );
  }
}

class _BigTile extends StatefulWidget {
  const _BigTile({
    required this.label,
    required this.emoji,
    required this.bg,
    required this.fg,
    required this.onTap,
  });
  final String label;
  final String emoji;
  final Color bg;
  final Color fg;
  final VoidCallback onTap;

  @override
  State<_BigTile> createState() => _BigTileState();
}

class _BigTileState extends State<_BigTile>
    with SingleTickerProviderStateMixin {
  double _scale = 1.0;

  void _press(bool down) {
    setState(() => _scale = down ? 0.98 : 1.0);
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return GestureDetector(
      onTapDown: (_) => _press(true),
      onTapCancel: () => _press(false),
      onTapUp: (_) => _press(false),
      onTap: widget.onTap,
      child: AnimatedScale(
        duration: const Duration(milliseconds: 110),
        curve: Curves.easeOut,
        scale: _scale,
        child: Container(
          decoration: BoxDecoration(
            color: widget.bg,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                  color: cs.primary.withOpacity(0.18),
                  offset: const Offset(0, 6),
                  blurRadius: 14,
                  spreadRadius: 1),
            ],
          ),
          child: Stack(
            children: [
              // decorative soft pink/navy glows
              Positioned(
                right: -10,
                top: -10,
                child: Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                      color: widget.fg.withOpacity(0.08),
                      shape: BoxShape.circle),
                ),
              ),
              Positioned(
                left: -16,
                bottom: -16,
                child: Container(
                  width: 90,
                  height: 90,
                  decoration: BoxDecoration(
                      color: widget.fg.withOpacity(0.06),
                      shape: BoxShape.circle),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(widget.emoji, style: const TextStyle(fontSize: 40)),
                    const SizedBox(height: 12),
                    Text(
                      widget.label,
                      style: TextStyle(
                        color: widget.fg,
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3,
                      ),
                      maxLines: 2,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SelfRefleksiReminder extends ConsumerStatefulWidget {
  const _SelfRefleksiReminder();

  @override
  ConsumerState<_SelfRefleksiReminder> createState() =>
      _SelfRefleksiReminderState();
}

class _SelfRefleksiReminderState extends ConsumerState<_SelfRefleksiReminder>
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
      color: theme.colorScheme.surfaceContainerHighest.withOpacity(0.6),
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
                    'Yuk isi refleksi hari ini ✍️',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Ambil 1-2 menit buat cerita singkat. Kamu hebat sudah sampai sini 💙',
                    style: TextStyle(color: theme.hintColor),
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      elevation: 4,
                      shadowColor: theme.colorScheme.primary.withOpacity(0.5),
                      backgroundColor: theme.colorScheme.primary,
                      foregroundColor: theme.colorScheme.secondary,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16)),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 20, vertical: 14),
                      textStyle: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 14),
                    ),
                    onPressed: () async {
                      final res = await GoRouter.of(context).push('/refleksi');
                      if (context.mounted && res == true) {
                        ref.invalidate(recentRefleksiProvider);
                        ref.invalidate(_refleksiTodayStatusProvider);
                      }
                    },
                    icon: const Icon(Icons.edit),
                    label: const Text('Tulis Refleksi Harian'),
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

class _FriendReportReminder extends ConsumerStatefulWidget {
  const _FriendReportReminder();

  @override
  ConsumerState<_FriendReportReminder> createState() =>
      _FriendReportReminderState();
}

class _FriendReportReminderState extends ConsumerState<_FriendReportReminder>
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
      color: theme.colorScheme.surfaceContainerHighest.withOpacity(0.6),
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
                    'Mau cerita tentang keadaan temanmu nggak? 👥',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Laporkan jika ada teman yang membutuhkan bantuan 💙',
                    style: TextStyle(color: theme.hintColor),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: const [
                      Chip(
                          label: Text('Belum ada laporan teman hari ini'),
                          visualDensity: VisualDensity.compact),
                    ],
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    style: FilledButton.styleFrom(
                      elevation: 4,
                      shadowColor: theme.colorScheme.primary.withOpacity(0.5),
                      backgroundColor: theme.colorScheme.primary,
                      foregroundColor: theme.colorScheme.secondary,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16)),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 20, vertical: 14),
                      textStyle: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 14),
                    ),
                    onPressed: () async {
                      final res = await GoRouter.of(context)
                          .push('/refleksi?jenis=laporan');
                      if (context.mounted && res == true) {
                        ref.invalidate(recentRefleksiProvider);
                        ref.invalidate(_refleksiTodayStatusProvider);
                      }
                    },
                    icon: const Icon(Icons.group),
                    label: const Text('Laporkan Kondisi Teman'),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            ScaleTransition(
              scale: Tween<double>(begin: 0.92, end: 1.08).animate(
                  CurvedAnimation(parent: _c, curve: Curves.easeInOut)),
              child: const Text('🤝', style: TextStyle(fontSize: 42)),
            ),
          ],
        ),
      ),
    );
  }
}

// =============== Quick Mood Shortcut ===============
class _QuickMoodShortcut extends ConsumerStatefulWidget {
  const _QuickMoodShortcut({required this.sesiNow, required this.hasNow});
  final String sesiNow;
  final bool hasNow;

  // Factory: derive from API map structure
  factory _QuickMoodShortcut.fromTodayMap(Map<String, dynamic> m,
      {required VoidCallback onSaved}) {
    final sesiNow = (m['sesi_now'] ?? '').toString();
    final items = (m['items'] as List?) ?? const [];
    final hasNow = items.any((e) =>
        (e is Map) &&
        ((e['sesi'] ?? '').toString().toLowerCase() == sesiNow.toLowerCase()));
    // If already has mood for this session → return an empty placeholder
    if (hasNow || sesiNow.isEmpty) {
      return _QuickMoodShortcutHidden(onSaved: onSaved);
    }
    return _QuickMoodShortcut._internal(sesiNow: sesiNow, hasNow: hasNow);
  }

  // Private named to use in factory
  const _QuickMoodShortcut._internal(
      {required this.sesiNow, required this.hasNow});

  @override
  ConsumerState<_QuickMoodShortcut> createState() => _QuickMoodShortcutState();
}

// Hidden variant to avoid branching in the caller
class _QuickMoodShortcutHidden extends _QuickMoodShortcut {
  final VoidCallback onSaved;
  const _QuickMoodShortcutHidden({required this.onSaved})
      : super(sesiNow: '', hasNow: true);
  @override
  ConsumerState<_QuickMoodShortcut> createState() => _HiddenState();
}

class _HiddenState extends ConsumerState<_QuickMoodShortcut> {
  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}

class _QuickMoodShortcutState extends ConsumerState<_QuickMoodShortcut> {
  bool _saving = false;
  // Left → right should go from lowest to highest score
  static const _mainQuick = [
    {'label': 'Awful', 'emoji': '😓', 'score': 1},
    {'label': 'Bad', 'emoji': '😔', 'score': 3},
    {'label': 'Meh', 'emoji': '😐', 'score': 5},
    {'label': 'Good', 'emoji': '😊', 'score': 7},
    {'label': 'Rad', 'emoji': '🤩', 'score': 10},
  ];

  Future<void> _save(int score) async {
    if (_saving) return;
    setState(() => _saving = true);
    final api = ref.read(apiClientProvider);
    final res = await api.postMood(score);
    setState(() => _saving = false);
    if (!mounted) return;
    if (res.ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Mood tersimpan. Terima kasih!')),
      );
      // Invalidate providers from parent by reading and invalidating
      ref.invalidate(_moodTodayProvider);
      ref.invalidate(recentMoodProvider);
    } else {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Gagal menyimpan mood'),
          content: Text(res.errorMessage),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Bagaimana perasaanmu sesi ini?',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: _mainQuick.map((m) {
                final score = m['score'] as int;
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        backgroundColor: cs.secondary,
                        foregroundColor: Colors.black,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      onPressed: _saving ? null : () => _save(score),
                      child: Text(m['emoji'] as String,
                          style: const TextStyle(fontSize: 20)),
                    ),
                  ),
                );
              }).toList(),
            ),
            if (_saving) ...[
              const SizedBox(height: 8),
              const LinearProgressIndicator(minHeight: 2),
            ],
          ],
        ),
      ),
    );
  }
}

// Removed unused quick mood widgets and duplicate providers

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
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
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
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
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
