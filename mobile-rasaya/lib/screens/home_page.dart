import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';
import 'package:intl/intl.dart';

// Clean rebuild of the Home page to fix corruption and match new design

// Helpers
String _fmtTanggal(BuildContext context, String? raw) {
  if (raw == null || raw.isEmpty) return '-';
  try {
    // If backend sends full ISO (e.g. 2025-11-16T16:00:00.000000Z representing midnight WITA),
    // parse as UTC then shift +8h to get the intended local date.
    if (raw.contains('T')) {
      final dt = DateTime.parse(raw);
      final dtWita = dt.isUtc ? dt.toUtc().add(const Duration(hours: 8)) : dt;
      final d = DateTime(dtWita.year, dtWita.month, dtWita.day);
      return DateFormat('EEEE, d MMMM y', 'id_ID').format(d);
    }
    // Plain date string yyyy-MM-dd
    final p = raw.split('-');
    if (p.length == 3) {
      final y = int.parse(p[0]);
      final m = int.parse(p[1]);
      final d = int.parse(p[2]);
      final dt = DateTime(y, m, d);
      return DateFormat('EEEE, d MMMM y', 'id_ID').format(dt);
    }
    // Fallback
    final dt = DateTime.parse(raw);
    return DateFormat('EEEE, d MMMM y', 'id_ID').format(dt);
  } catch (_) {
    return raw;
  }
}

// Mood helpers (color & icon mapping 1..10)
Color _moodColorForScore(int s, {double opacity = 1}) {
  final t = ((s.clamp(1, 10) - 1) / 9.0);
  final hue = 120.0 * t; // 0=red -> 120=green
  final hsv = HSVColor.fromAHSV(1, hue, 0.85, 0.95);
  return hsv.toColor().withOpacity(opacity);
}

IconData _moodIconForScore(int s, {bool filled = false}) {
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
    return filled ? Icons.sentiment_neutral : Icons.sentiment_neutral_outlined;
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

// Providers (restored)
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
    final needsPwdUpdate = me['needs_password_update'] == true;

    final refleksiAsync = ref.watch(recentRefleksiProvider);
    final moodAsync = ref.watch(recentMoodProvider);
    final todayRefleksiStatus = ref.watch(_refleksiTodayStatusProvider);
    final moodToday = ref.watch(_moodTodayProvider);
    final showMoodShortcut = moodToday.maybeWhen(
      data: (m) {
        final sesiNow = (m['sesi_now'] ?? '').toString();
        if (sesiNow.isEmpty) return false;
        final items = (m['items'] as List?) ?? const [];
        final hasNow = items.any((e) =>
            (e is Map) &&
            ((e['sesi'] ?? '').toString().toLowerCase() ==
                sesiNow.toLowerCase()));
        return !hasNow;
      },
      orElse: () => false,
    );
    final refreshCounter = ref.watch(bookingRefreshCounterProvider);
    final futureMyBookings = ref.watch(_myBookingsProvider(refreshCounter));

    return AppScaffold(
      title: 'Beranda',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _IdentityHeader(name: name, nis: nis, kelasLabel: kelasLabel),
          const SizedBox(height: 12),
          if (showMoodShortcut)
            moodToday.when(
              data: (m) => _QuickMoodShortcut.fromTodayMap(m, onSaved: () {
                ref.invalidate(_moodTodayProvider);
                ref.invalidate(recentMoodProvider);
              }),
              loading: () => const SizedBox.shrink(),
              error: (_, __) => const SizedBox.shrink(),
            ),
          if (showMoodShortcut) const SizedBox(height: 12),
          if (needsPwdUpdate) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Card(
                color: Colors.amber.shade100,
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.warning_rounded, color: Colors.black87),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Segera ubah password anda',
                                style: TextStyle(fontWeight: FontWeight.w600)),
                            const SizedBox(height: 4),
                            const Text(
                                'Gunakan Token password sebagai Password saat ini lalu tetapkan password baru yang mudah diingat.',
                                style: TextStyle(fontSize: 13)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
          todayRefleksiStatus.when(
            data: (m) {
              final hasSelf = m['has_self_today'] == true;
              final hasFriend = (m['has_friend_report_today'] == true) ||
                  (m['has_friend_today'] == true);
              final whiteVariant = !showMoodShortcut;
              if (!hasSelf) return _HeroPromoCard(whiteVariant: whiteVariant);
              if (hasSelf && !hasFriend) {
                return _FriendReportHeroCard(whiteVariant: whiteVariant);
              }
              return const SizedBox.shrink();
            },
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
          const SizedBox(height: 12),
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
                return Column(
                  children: items.map((m) {
                    final tgl =
                        _fmtTanggal(context, (m['tanggal'] ?? '').toString());
                    final sesi = (m['sesi'] ?? '').toString().toUpperCase();
                    final s = int.tryParse((m['skor'] ?? '').toString()) ?? 0;
                    final adaLampiran = (m['gambar'] != null &&
                        (m['gambar'] as String).isNotEmpty);

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Card(
                        child: ListTile(
                          leading: Icon(
                            _moodIconForScore(s, filled: true),
                            color: _moodColorForScore(s),
                            size: 24,
                          ),
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
    return Container(
      decoration: BoxDecoration(
        color: cs.primary,
        borderRadius: const BorderRadius.only(
          bottomLeft: Radius.circular(42),
          bottomRight: Radius.circular(42),
        ),
        boxShadow: [
          BoxShadow(
              color: cs.primary.withOpacity(0.30),
              blurRadius: 24,
              offset: const Offset(0, 10)),
        ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 34),
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
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Hai 👋',
                      style: TextStyle(
                        fontFamily: 'Lora',
                        fontWeight: FontWeight.w600,
                        color: cs.secondary,
                        fontSize: 22,
                        letterSpacing: 0.2,
                      ),
                    ),
                    Text(
                      name,
                      style: tt.headlineSmall?.copyWith(
                        fontFamily: 'Lora',
                        color: cs.secondary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 14,
                      runSpacing: -8,
                      children: [
                        _IdentityChip(label: 'NIS: $nis'),
                        _IdentityChip(label: kelasLabel),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
            ],
          ),
        ],
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
      childAspectRatio: 1.35,
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        _SmallTile(
          label: 'Refleksi',
          icon: Icons.edit_note_rounded,
          fg: cs.primary,
          accent: cs.secondary,
          onTap: onRefleksiDiri,
        ),
        _SmallTile(
          label: 'Cerita Teman',
          icon: Icons.group_rounded,
          fg: cs.primary,
          accent: cs.primary,
          onTap: onRefleksiTeman,
        ),
        _SmallTile(
          label: 'Konseling',
          icon: Icons.event_available_rounded,
          fg: cs.primary,
          accent: cs.primary,
          onTap: onBooking,
        ),
        _SmallTile(
          label: 'Riwayat',
          icon: Icons.history_rounded,
          fg: cs.primary,
          accent: cs.secondary,
          onTap: onHistory,
        ),
      ],
    );
  }
}

class _SmallTile extends StatefulWidget {
  _SmallTile({
    required this.label,
    required this.icon,
    required this.fg,
    required this.accent,
    required this.onTap,
  });
  final String label;
  final IconData icon;
  final Color fg;
  final Color accent;
  final VoidCallback onTap;

  @override
  State<_SmallTile> createState() => _SmallTileState();
}

class _SmallTileState extends State<_SmallTile>
    with SingleTickerProviderStateMixin {
  double _scale = 1.0;

  void _press(bool down) => setState(() => _scale = down ? 0.98 : 1.0);

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final cardBg = Theme.of(context).cardColor;
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
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                cardBg,
                Color.alphaBlend(widget.accent.withOpacity(0.04), cardBg),
              ],
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: cs.primary.withOpacity(0.08),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
            border: Border.all(color: cs.primary.withOpacity(0.06)),
          ),
          clipBehavior: Clip.antiAlias,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        widget.accent.withOpacity(0.35),
                        widget.accent.withOpacity(0.15),
                      ],
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: widget.accent.withOpacity(0.25),
                        blurRadius: 16,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Icon(widget.icon, color: widget.fg, size: 28),
                ),
                const SizedBox(height: 10),
                Text(
                  widget.label,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: widget.fg,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                    letterSpacing: 0.2,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _HeroPromoCard extends ConsumerWidget {
  const _HeroPromoCard({this.whiteVariant = false});
  final bool whiteVariant;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final gradient = whiteVariant
        ? LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white,
              Color.alphaBlend(cs.secondary.withOpacity(0.08), Colors.white),
            ],
          )
        : LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              cs.primary.withOpacity(0.95),
              Color.alphaBlend(cs.secondary.withOpacity(0.35), cs.primary),
            ],
          );
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: gradient,
        boxShadow: [
          BoxShadow(
            color: cs.primary.withOpacity(whiteVariant ? 0.12 : 0.25),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Yuk refleksi hari ini!',
                  style: TextStyle(
                    color: whiteVariant ? cs.primary : cs.secondary,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Catat perasaanmu sebentar aja. Bantu kamu lebih kenal dirimu sendiri 💙',
                  style: TextStyle(
                    color: whiteVariant
                        ? cs.primary.withOpacity(0.75)
                        : cs.secondary.withOpacity(0.85),
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: whiteVariant ? cs.primary : Colors.white,
                    foregroundColor: whiteVariant ? cs.secondary : cs.primary,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 18, vertical: 12),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () async {
                    final res = await GoRouter.of(context).push('/refleksi');
                    if (context.mounted && res == true) {
                      ref.invalidate(recentRefleksiProvider);
                      ref.invalidate(_refleksiTodayStatusProvider);
                    }
                  },
                  child: const Text('Mulai'),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 86,
            height: 86,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: whiteVariant
                  ? cs.primary.withOpacity(0.08)
                  : cs.secondary.withOpacity(0.25),
            ),
            child: Icon(Icons.auto_awesome_rounded,
                color: whiteVariant ? cs.primary : cs.secondary, size: 42),
          ),
        ],
      ),
    );
  }
}

class _FriendReportHeroCard extends ConsumerWidget {
  const _FriendReportHeroCard({this.whiteVariant = false});
  final bool whiteVariant;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = Theme.of(context).colorScheme;
    final gradient = whiteVariant
        ? LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white,
              Color.alphaBlend(cs.secondary.withOpacity(0.08), Colors.white),
            ],
          )
        : LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              cs.primary.withOpacity(0.95),
              Color.alphaBlend(cs.secondary.withOpacity(0.35), cs.primary),
            ],
          );
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: gradient,
        boxShadow: [
          BoxShadow(
            color: cs.primary.withOpacity(whiteVariant ? 0.12 : 0.25),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Apakah kamu ingin menceritakan kondisi temanmu hari ini?',
                  style: TextStyle(
                    color: whiteVariant ? cs.primary : cs.secondary,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Jika ada teman yang perlu dukungan, kamu bisa berbagi cerita dengan aman di sini 💙',
                  style: TextStyle(
                    color: whiteVariant
                        ? cs.primary.withOpacity(0.75)
                        : cs.secondary.withOpacity(0.85),
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: whiteVariant ? cs.primary : Colors.white,
                    foregroundColor: whiteVariant ? cs.secondary : cs.primary,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 18, vertical: 12),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () async {
                    final res = await GoRouter.of(context)
                        .push('/refleksi?jenis=laporan');
                    if (context.mounted && res == true) {
                      ref.invalidate(recentRefleksiProvider);
                      ref.invalidate(_refleksiTodayStatusProvider);
                    }
                  },
                  child: const Text('Cerita tentang Teman'),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 86,
            height: 86,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: whiteVariant
                  ? cs.primary.withOpacity(0.08)
                  : cs.secondary.withOpacity(0.25),
            ),
            child: Icon(Icons.group_rounded,
                color: whiteVariant ? cs.primary : cs.secondary, size: 42),
          ),
        ],
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
  int? _selectedScore;

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

  void _selectAndSave(int score) {
    if (_saving) return;
    setState(() => _selectedScore = score);
    _save(score);
  }

  @override
  Widget build(BuildContext context) {
    // Build greeting with first name (kata pertama)
    final me = ref.watch(authControllerProvider).me ?? {};
    final fullName = (me['name'] ?? '').toString().trim();
    final firstName =
        fullName.isEmpty ? '' : fullName.split(RegExp(r'\s+')).first;
    final prompt =
        'Bagaimana perasaanmu saat ini${firstName.isNotEmpty ? ' $firstName' : ''}?';

    final cs = Theme.of(context).colorScheme;
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Colors.white,
            Color.alphaBlend(cs.secondary.withOpacity(0.08), Colors.white),
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: cs.primary.withOpacity(0.12),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
        border: Border.all(color: cs.primary.withOpacity(0.06)),
      ),
      constraints: const BoxConstraints(minHeight: 150),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              prompt,
              style: TextStyle(
                color: cs.primary,
                fontWeight: FontWeight.w800,
                fontSize: 16,
              ),
            ),
            const SizedBox(height: 10),
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 5,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 0.9,
              children: [1, 3, 5, 8, 10]
                  .map((score) => _MoodQuickTileWrapper(score: score))
                  .toList(),
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

// (Old _MoodOptionButton removed)

// Quick mood tile with label, matching Mood page style
class _MoodQuickTileWrapper extends ConsumerWidget {
  const _MoodQuickTileWrapper({required this.score});
  final int score;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Access parent state to manage selection and save
    final state = context.findAncestorStateOfType<_QuickMoodShortcutState>();
    final selected = state?._selectedScore == score;
    final color = _moodColorForScore(score);
    return _MoodQuickTile(
      score: score,
      color: color,
      icon: _moodIconForScore(score, filled: false),
      label: state?._labelForScore(score) ?? '$score',
      selected: selected == true,
      onTap: (state == null)
          ? null
          : () {
              if (state._saving) return;
              state._selectAndSave(score);
            },
    );
  }
}

class _MoodQuickTile extends StatelessWidget {
  const _MoodQuickTile({
    required this.score,
    required this.color,
    required this.icon,
    required this.label,
    required this.selected,
    this.onTap,
  });
  final int score;
  final Color color;
  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback? onTap;

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
