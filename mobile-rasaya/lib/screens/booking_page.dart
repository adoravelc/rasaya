import 'package:flutter/material.dart';
import 'dart:math' as math;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_drawer.dart';
import 'package:go_router/go_router.dart';

class BookingPage extends ConsumerStatefulWidget {
  const BookingPage({super.key});
  @override
  ConsumerState<BookingPage> createState() => _BookingPageState();
}

class _BookingPageState extends ConsumerState<BookingPage> {
  bool _loading = true;
  DateTime _currentMonth =
      DateTime(DateTime.now().year, DateTime.now().month, 1);
  DateTime? _selectedDate;
  Map<String, List<Map<String, dynamic>>> _slotsByDate = {};

  @override
  void initState() {
    super.initState();
    _selectedDate = DateTime.now();
    _loadMonth();
  }

  Future<void> _jumpToNextAvailableDate() async {
    // Pastikan ada tanggal referensi
    if (_selectedDate == null) {
      setState(() => _selectedDate = DateTime.now());
    }

    final fmt = DateFormat('yyyy-MM-dd');
    final selected = _selectedDate ?? DateTime.now();

    // Pastikan bulan aktif selaras dengan tanggal terpilih
    if (!(selected.year == _currentMonth.year &&
        selected.month == _currentMonth.month)) {
      setState(() {
        _currentMonth = DateTime(selected.year, selected.month, 1);
      });
      await _loadMonth();
    }

    // 1) Cari di sisa hari pada bulan saat ini
    final lastDayThisMonth =
        DateTime(_currentMonth.year, _currentMonth.month + 1, 0).day;
    for (int d = selected.day + 1; d <= lastDayThisMonth; d++) {
      final dt = DateTime(_currentMonth.year, _currentMonth.month, d);
      final key = fmt.format(dt);
      final slots = _slotsByDate[key] ?? const [];
      if (slots.isNotEmpty) {
        if (mounted) setState(() => _selectedDate = dt);
        return;
      }
    }

    // 2) Jika tidak ada, iterasi sampai 12 bulan ke depan
    DateTime probeMonth =
        DateTime(_currentMonth.year, _currentMonth.month + 1, 1);
    for (int i = 0; i < 12; i++) {
      setState(() => _currentMonth = probeMonth);
      await _loadMonth();
      final lastDay = DateTime(probeMonth.year, probeMonth.month + 1, 0).day;
      for (int d = 1; d <= lastDay; d++) {
        final dt = DateTime(probeMonth.year, probeMonth.month, d);
        final key = fmt.format(dt);
        final slots = _slotsByDate[key] ?? const [];
        if (slots.isNotEmpty) {
          if (mounted) setState(() => _selectedDate = dt);
          return;
        }
      }
      probeMonth = DateTime(probeMonth.year, probeMonth.month + 1, 1);
    }

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text(
            'Tidak ada jadwal tersedia pada bulan-bulan berikutnya.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _loadMonth() async {
    setState(() => _loading = true);
    final api = ref.read(apiClientProvider);
    final firstDay = DateTime(_currentMonth.year, _currentMonth.month, 1);
    final lastDay = DateTime(_currentMonth.year, _currentMonth.month + 1, 0);
    final res =
        await api.getAvailableSlots(from: firstDay, to: lastDay, perPage: 500);
    final map = <String, List<Map<String, dynamic>>>{};
    if (res.ok && res.data is Map && res.data['data'] is List) {
      for (final e in (res.data['data'] as List)) {
        final m = Map<String, dynamic>.from(e as Map);
        final key =
            (m['tanggal'] ?? (m['start_at']?.toString().substring(0, 10) ?? ''))
                .toString();
        if (key.isEmpty) continue;
        (map[key] ??= []).add(m);
      }
    }
    _slotsByDate = map;
    if (mounted) setState(() => _loading = false);
  }

  void _prevMonth() {
    setState(() {
      _currentMonth = DateTime(_currentMonth.year, _currentMonth.month - 1, 1);
    });
    _loadMonth();
  }

  void _nextMonth() {
    setState(() {
      _currentMonth = DateTime(_currentMonth.year, _currentMonth.month + 1, 1);
    });
    _loadMonth();
  }

  String _fmtWita(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    try {
      if (iso.length >= 16) return iso.substring(11, 16);
    } catch (_) {}
    try {
      final dt = DateTime.parse(iso);
      final wita = dt.toUtc().add(const Duration(hours: 8));
      return DateFormat('HH:mm').format(wita);
    } catch (_) {
      return iso;
    }
  }

  Future<void> _book(Map<String, dynamic> slot) async {
    final api = ref.read(apiClientProvider);
    final id = (slot['id'] as num?)?.toInt();
    if (id == null) return;

    final start = _fmtWita(slot['start_at']);
    final end = _fmtWita(slot['end_at']);
    final guru =
        (slot['guru']?['nama'] ?? slot['guru_nama'] ?? 'Guru BK').toString();
    final lokasi = (slot['lokasi'] ?? '-').toString();
    final notes = (slot['notes'] ?? '').toString();

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
            left: 16,
            right: 16,
            top: 16,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Konfirmasi Booking',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
              const SizedBox(height: 8),
              Text('Guru: $guru'),
              Text('Waktu: $start - $end'),
              Text('Lokasi: $lokasi'),
              if (notes.isNotEmpty) Text('Catatan: $notes'),
              const SizedBox(height: 12),
              Row(
                children: [
                  TextButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      child: const Text('Batal')),
                  const Spacer(),
                  FilledButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    child: const Text('Booking'),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );

    if (ok != true) return;
    final res = await api.bookSlot(id);
    if (!mounted) return;
    if (res.ok) {
      // trigger global refresh marker for bookings
      ref.read(bookingRefreshCounterProvider.notifier).state++;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: const [
              Icon(Icons.check_circle, color: Colors.white),
              SizedBox(width: 8),
              Expanded(
                  child: Text(
                      'Slot kamu berhasil dibooking. Sampai jumpa di sesi konseling!')),
            ],
          ),
          behavior: SnackBarBehavior.floating,
          backgroundColor: Colors.green.shade600,
          duration: const Duration(seconds: 3),
        ),
      );
      if (Navigator.canPop(context)) {
        Navigator.pop(context);
      }
      // setelah booking, arahkan ke Home agar status cepat ter-refresh
      GoRouter.of(context).go('/home');
    } else {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
            title: const Text('Gagal'), content: Text(res.errorMessage)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final monthTitle = DateFormat.yMMMM().format(_currentMonth);
    return Scaffold(
      appBar: AppBar(title: const Text('Booking Konseling')),
      drawer: const AppDrawer(),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : LayoutBuilder(
              builder: (context, c) {
                final isWide = c.maxWidth >= 900;
                final calendar = _MiniCalendar(
                  month: _currentMonth,
                  monthTitle: monthTitle,
                  slotsByDate: _slotsByDate,
                  selected: _selectedDate,
                  onPrev: _prevMonth,
                  onNext: _nextMonth,
                  onSelect: (d) => setState(() => _selectedDate = d),
                );

                final times = _TimesPane(
                  date: _selectedDate,
                  slots: _selectedDate == null
                      ? const []
                      : (_slotsByDate[DateFormat('yyyy-MM-dd')
                              .format(_selectedDate!)] ??
                          const []),
                  onTap: _book,
                  onJumpNext: _jumpToNextAvailableDate,
                );

                if (isWide) {
                  return Row(
                    children: [
                      SizedBox(width: 360, child: calendar),
                      const VerticalDivider(width: 1),
                      Expanded(child: times),
                    ],
                  );
                }
                final calHeight =
                    math.max(320.0, math.min(480.0, c.maxHeight * 0.5));
                return Column(
                  children: [
                    SizedBox(height: calHeight, child: calendar),
                    const Divider(height: 1),
                    Expanded(child: times),
                  ],
                );
              },
            ),
    );
  }
}

class _MiniCalendar extends StatelessWidget {
  const _MiniCalendar({
    required this.month,
    required this.monthTitle,
    required this.slotsByDate,
    required this.selected,
    required this.onPrev,
    required this.onNext,
    required this.onSelect,
  });
  final DateTime month;
  final String monthTitle;
  final Map<String, List<Map<String, dynamic>>> slotsByDate;
  final DateTime? selected;
  final VoidCallback onPrev;
  final VoidCallback onNext;
  final void Function(DateTime) onSelect;

  @override
  Widget build(BuildContext context) {
    final firstDay = DateTime(month.year, month.month, 1);
    final lastDay = DateTime(month.year, month.month + 1, 0);
    final totalDays = lastDay.day;
    final startWeekday = firstDay.weekday % 7;
    final cells = startWeekday + totalDays;
    final rows = (cells / 7).ceil();
    int day = 1;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              IconButton(
                  onPressed: onPrev, icon: const Icon(Icons.chevron_left)),
              Expanded(
                child: Center(
                  child: Text(
                    monthTitle,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
              IconButton(
                  onPressed: onNext, icon: const Icon(Icons.chevron_right)),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: const [
              _Dow('M'),
              _Dow('S'),
              _Dow('S'),
              _Dow('R'),
              _Dow('K'),
              _Dow('J'),
              _Dow('S'),
            ],
          ),
        ),
        const SizedBox(height: 6),
        // Jadikan grid bisa discroll pada ruang sempit agar tidak overflow
        Expanded(
          child: SingleChildScrollView(
            child: GridView.builder(
              padding: const EdgeInsets.all(16),
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 7,
                crossAxisSpacing: 6,
                mainAxisSpacing: 6,
              ),
              itemCount: rows * 7,
              itemBuilder: (context, index) {
                if (index < startWeekday || day > totalDays) {
                  return const SizedBox.shrink();
                }
                final date = DateTime(month.year, month.month, day);
                final key = DateFormat('yyyy-MM-dd').format(date);
                final count = slotsByDate[key]?.length ?? 0;
                final sel =
                    selected != null && DateUtils.isSameDay(selected!, date);
                final isToday = DateUtils.isSameDay(date, DateTime.now());
                day++;
                return InkWell(
                  borderRadius: BorderRadius.circular(8),
                  onTap: () => onSelect(date),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      color: sel ? Colors.indigo.withOpacity(0.12) : null,
                      border: Border.all(
                        color: sel ? Colors.indigo : Colors.grey.shade300,
                      ),
                    ),
                    padding: const EdgeInsets.all(8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              '${date.day}',
                              style: TextStyle(
                                fontWeight: FontWeight.w700,
                                color: sel
                                    ? Colors.indigo
                                    : (isToday ? Colors.indigo : null),
                              ),
                            ),
                            const Spacer(),
                            if (count > 0)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.green.shade100,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text('$count',
                                    style: const TextStyle(fontSize: 12)),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

class _TimesPane extends StatelessWidget {
  const _TimesPane(
      {required this.date,
      required this.slots,
      required this.onTap,
      required this.onJumpNext});
  final DateTime? date;
  final List<Map<String, dynamic>> slots;
  final void Function(Map<String, dynamic>) onTap;
  final VoidCallback onJumpNext;

  // Format jam persis seperti di DB (tanpa konversi zona waktu)
  String _fmt(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    // Ambil HH:mm langsung dari ISO 8601: yyyy-MM-ddTHH:mm:ss+zz:zz
    if (iso.length >= 16) {
      final hhmm = iso.substring(11, 16);
      // Validasi sederhana
      final ok = RegExp(r'^\d{2}:\d{2}$').hasMatch(hhmm);
      if (ok) return hhmm;
    }
    // Fallback: parse dan tampilkan ke WITA
    try {
      final dt = DateTime.parse(iso);
      final wita = dt.toUtc().add(const Duration(hours: 8));
      return DateFormat('HH:mm').format(wita);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    final labelDate =
        date == null ? '-' : DateFormat.yMMMMEEEEd().format(date!);
    // Tidak tampilkan label WIB/GMT sesuai permintaan

    final sorted = [...slots];
    sorted.sort((a, b) => (a['start_at'] ?? '')
        .toString()
        .compareTo((b['start_at'] ?? '').toString()));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Select an appointment time',
                        style: TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 16)),
                    const SizedBox(height: 4),
                    Text(labelDate),
                  ],
                ),
              ),
              const SizedBox.shrink(),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: slots.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Tidak ada slot pada tanggal ini',
                          style: TextStyle(color: Colors.grey.shade600)),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: onJumpNext,
                        icon: const Icon(Icons.skip_next),
                        label:
                            const Text('Loncat ke tanggal tersedia berikutnya'),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding:
                      const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
                  itemCount: sorted.length,
                  itemBuilder: (_, i) {
                    final s = sorted[i];
                    final jam = _fmt(s['start_at']);
                    final guru =
                        (s['guru']?['nama'] ?? s['guru_nama'] ?? 'Guru BK')
                            .toString();
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                              vertical: 16, horizontal: 20),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(28)),
                        ),
                        onPressed: () => onTap(s),
                        child: Row(
                          children: [
                            Text(jam,
                                style: const TextStyle(
                                    fontWeight: FontWeight.w600)),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(guru,
                                  overflow: TextOverflow.ellipsis,
                                  style:
                                      TextStyle(color: Colors.grey.shade700)),
                            ),
                            const Icon(Icons.chevron_right),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        )
      ],
    );
  }
}

class _Dow extends StatelessWidget {
  const _Dow(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Expanded(
        child: Center(
            child: Text(text,
                style: const TextStyle(fontWeight: FontWeight.w600))),
      );
}

// Drawer moved to widgets/app_drawer.dart
