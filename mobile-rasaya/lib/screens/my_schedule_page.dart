import 'package:flutter/material.dart';
import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../auth/auth_controller.dart';
import 'package:go_router/go_router.dart';
import '../widgets/app_drawer.dart';

class MySchedulePage extends ConsumerStatefulWidget {
  const MySchedulePage({super.key});
  @override
  ConsumerState<MySchedulePage> createState() => _MySchedulePageState();
}

class _MySchedulePageState extends ConsumerState<MySchedulePage> {
  bool _loading = true;
  List<Map<String, dynamic>> _items = [];
  DateTime _nowWita = DateTime.now().toUtc().add(const Duration(hours: 8));
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    _load();
    // update waktu sekarang (WITA) tiap menit agar filter & display tetap akurat
    _ticker = Timer.periodic(const Duration(minutes: 1), (_) {
      setState(() {
        _nowWita = DateTime.now().toUtc().add(const Duration(hours: 8));
      });
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  String _fmtWita(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    try {
      final dt = DateTime.parse(iso);
      final wita = dt.toUtc().add(const Duration(hours: 8));
      // gunakan format jam dengan titik: HH.mm
      return DateFormat('HH.mm', 'id_ID').format(wita);
    } catch (_) {
      return '';
    }
  }

  String _fmtTanggalIndo(DateTime witaDate) {
    // Contoh: Jumat, 17 Oktober 2025
    return DateFormat('EEEE, d MMMM y', 'id_ID').format(witaDate);
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final api = ref.read(apiClientProvider);
    final res = await api.getMyBookings();
    _items = [];
    if (res.ok && res.data is Map && res.data['data'] is List) {
      _items = (res.data['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _cancel(int id) async {
    final reasonController = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Batalkan Booking?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
                'Tindakan ini akan mengosongkan slot dan bisa dipilih orang lain.'),
            const SizedBox(height: 12),
            const Text('Alasan pembatalan (wajib):'),
            const SizedBox(height: 8),
            TextField(
              controller: reasonController,
              maxLines: 3,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                hintText: 'Contoh: Ada keperluan mendadak',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Tutup')),
          FilledButton(
            onPressed: () {
              if (reasonController.text.trim().isEmpty) return;
              Navigator.pop(context, true);
            },
            child: const Text('Ya, Batalkan'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    final api = ref.read(apiClientProvider);
    final res = await api.cancelMyBooking(id, reason: reasonController.text);
    if (!mounted) return;
    if (res.ok) {
      // trigger global refresh marker for bookings
      ref.read(bookingRefreshCounterProvider.notifier).state++;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(children: const [
            Icon(Icons.info_outline, color: Colors.white),
            SizedBox(width: 8),
            Expanded(
                child: Text(
                    'Booking kamu telah dibatalkan. Terima kasih sudah memberi alasan.')),
          ]),
          behavior: SnackBarBehavior.floating,
          backgroundColor: Colors.orange.shade700,
          duration: const Duration(seconds: 3),
        ),
      );
      _load();
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
    // Filter hanya jadwal yang belum berlalu (end_at > sekarang WITA) dan status 'booked'
    final upcoming = _items.where((m) {
      final status = (m['status'] ?? '').toString();
      final endIso = (m['slot']?['end_at'] ?? m['end_at'] ?? '').toString();
      if (endIso.isEmpty) return false;
      try {
        final endWita =
            DateTime.parse(endIso).toUtc().add(const Duration(hours: 8));
        return status == 'booked' && endWita.isAfter(_nowWita);
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
          final ad = DateTime.parse(aStart);
          final bd = DateTime.parse(bStart);
          return ad.compareTo(bd);
        } catch (_) {
          return 0;
        }
      });

    final nowLabel = DateFormat('HH.mm', 'id_ID').format(_nowWita);

    return Scaffold(
      appBar: AppBar(title: const Text('Jadwal Saya')),
      drawer: const AppDrawer(),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: upcoming.isEmpty
                  ? ListView(
                      padding: const EdgeInsets.all(12),
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(4, 4, 4, 8),
                          child: Row(
                            children: [
                              const Icon(Icons.access_time, size: 18),
                              const SizedBox(width: 6),
                              Text('Sekarang (WITA): $nowLabel',
                                  style:
                                      TextStyle(color: Colors.grey.shade700)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 8),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Tidak ada konseling terjadwal',
                                    style:
                                        TextStyle(fontWeight: FontWeight.w700)),
                                const SizedBox(height: 6),
                                Text(
                                    'Kamu belum memiliki jadwal konseling mendatang. Ayo cek ketersediaan slot.',
                                    style:
                                        TextStyle(color: Colors.grey.shade700)),
                                const SizedBox(height: 12),
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: FilledButton.icon(
                                    icon: const Icon(Icons.event_available),
                                    label: const Text('Cari Jadwal'),
                                    onPressed: () =>
                                        GoRouter.of(context).push('/booking'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(12),
                      itemCount: upcoming.length + 1,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (_, i) {
                        if (i == 0) {
                          return Padding(
                            padding: const EdgeInsets.fromLTRB(4, 4, 4, 8),
                            child: Row(
                              children: [
                                const Icon(Icons.access_time, size: 18),
                                const SizedBox(width: 6),
                                Text('Sekarang (WITA): $nowLabel',
                                    style:
                                        TextStyle(color: Colors.grey.shade700)),
                              ],
                            ),
                          );
                        }

                        final m = upcoming[i - 1];
                        final mulaiIso =
                            (m['slot']?['start_at'] ?? m['start_at'] ?? '')
                                .toString();
                        final selesaiIso =
                            (m['slot']?['end_at'] ?? m['end_at'] ?? '')
                                .toString();
                        DateTime? startWita;
                        try {
                          startWita = DateTime.parse(mulaiIso)
                              .toUtc()
                              .add(const Duration(hours: 8));
                        } catch (_) {}
                        final tglLabel = startWita != null
                            ? _fmtTanggalIndo(startWita)
                            : '-';
                        final lokasi =
                            (m['slot']?['lokasi'] ?? m['lokasi'] ?? '')
                                .toString();
                        final notes = (m['slot']?['notes'] ?? m['notes'] ?? '')
                            .toString();
                        final guru = (m['slot']?['guru']?['nama'] ??
                                m['guru']?['nama'] ??
                                m['guru_nama'] ??
                                'Guru BK')
                            .toString();
                        final status = (m['status'] ?? '').toString();
                        final id = (m['id'] as num?)?.toInt();
                        final jam =
                            '${_fmtWita(mulaiIso)} - ${_fmtWita(selesaiIso)} WITA';

                        return Card(
                          child: ListTile(
                            leading: const Icon(Icons.event_available),
                            title: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(tglLabel,
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w600)),
                                const SizedBox(height: 2),
                                Text('Waktu: $jam'),
                              ],
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(guru),
                                if (lokasi.isNotEmpty)
                                  Text('Lokasi: $lokasi',
                                      style: TextStyle(
                                          color: Colors.grey.shade700)),
                                if (notes.isNotEmpty)
                                  Text('Catatan: $notes',
                                      style: TextStyle(
                                          color: Colors.grey.shade700)),
                              ],
                            ),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (status.isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(right: 8.0),
                                    child: Chip(
                                        label: Text(status),
                                        visualDensity: VisualDensity.compact),
                                  ),
                                if (id != null && status == 'booked')
                                  IconButton(
                                    tooltip: 'Batalkan',
                                    onPressed: () => _cancel(id),
                                    icon: const Icon(Icons.close),
                                  ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}
