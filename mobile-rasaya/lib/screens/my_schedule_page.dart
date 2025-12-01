import 'package:flutter/material.dart';
import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../auth/auth_controller.dart';
import 'package:go_router/go_router.dart';
import '../widgets/app_scaffold.dart';

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
    // Filter untuk jadwal aktif (booked) yang belum berlalu
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

    // Filter untuk riwayat booking (canceled, completed, no_show)
    final history = _items.where((m) {
      final status = (m['status'] ?? '').toString();
      return ['canceled', 'completed', 'no_show'].contains(status);
    }).toList()
      ..sort((a, b) {
        // Sort by created_at descending (terbaru dulu)
        final aCreated = (a['created_at'] ?? '').toString();
        final bCreated = (b['created_at'] ?? '').toString();
        try {
          final ad = DateTime.parse(aCreated);
          final bd = DateTime.parse(bCreated);
          return bd.compareTo(ad); // descending
        } catch (_) {
          return 0;
        }
      });

    final nowLabel = DateFormat('HH.mm', 'id_ID').format(_nowWita);

    return AppScaffold(
      title: 'Jadwal Saya',
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: upcoming.isEmpty && history.isEmpty
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
                      itemCount: 1 + // header waktu
                          (upcoming.isNotEmpty
                              ? upcoming.length + 1
                              : 0) + // upcoming + section header
                          (history.isNotEmpty
                              ? history.length + 1
                              : 0), // history + section header
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (_, i) {
                        // Index 0: Current time display
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

                        int idx = i - 1;

                        // Upcoming bookings section
                        if (upcoming.isNotEmpty) {
                          if (idx == 0) {
                            return Padding(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 4, vertical: 8),
                              child: Text(
                                '📅 Jadwal Mendatang',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.blue.shade800,
                                ),
                              ),
                            );
                          }
                          if (idx <= upcoming.length) {
                            final m = upcoming[idx - 1];
                            return _buildBookingCard(context, m,
                                isUpcoming: true);
                          }
                          idx -= (upcoming.length + 1);
                        }

                        // History section
                        if (history.isNotEmpty) {
                          if (idx == 0) {
                            return Padding(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 4, vertical: 8),
                              child: Text(
                                '📋 Riwayat Booking',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.grey.shade700,
                                ),
                              ),
                            );
                          }
                          if (idx <= history.length) {
                            final m = history[idx - 1];
                            return _buildBookingCard(context, m,
                                isUpcoming: false);
                          }
                        }

                        return const SizedBox.shrink();
                      },
                    ),
            ),
    );
  }

  Widget _buildBookingCard(BuildContext context, Map<String, dynamic> m,
      {required bool isUpcoming}) {
    final mulaiIso = (m['slot']?['start_at'] ?? m['start_at'] ?? '').toString();
    final selesaiIso = (m['slot']?['end_at'] ?? m['end_at'] ?? '').toString();
    DateTime? startWita;
    try {
      startWita =
          DateTime.parse(mulaiIso).toUtc().add(const Duration(hours: 8));
    } catch (_) {}
    final tglLabel = startWita != null ? _fmtTanggalIndo(startWita) : '-';
    final lokasi = (m['slot']?['lokasi'] ?? m['lokasi'] ?? '').toString();
    final notes = (m['slot']?['notes'] ?? m['notes'] ?? '').toString();
    final guru = (m['slot']?['guru']?['user']?['name'] ??
            m['slot']?['guru']?['nama'] ??
            m['guru']?['nama'] ??
            m['guru_nama'] ??
            'Guru BK')
        .toString();
    final status = (m['status'] ?? '').toString();
    final id = (m['id'] as num?)?.toInt();
    final jam = '${_fmtWita(mulaiIso)} - ${_fmtWita(selesaiIso)} WITA';
    final cancelReason = (m['cancel_reason'] ?? '').toString();

    // Determine who canceled
    String? canceledBy;
    if (status == 'canceled') {
      final canceledByData = m['canceled_by'];
      if (canceledByData != null && canceledByData is Map) {
        // Dibatalkan oleh user tertentu (biasanya Guru BK)
        final canceledByName = (canceledByData['name'] ?? 'Guru BK').toString();
        canceledBy = canceledByName;
      } else {
        // Dibatalkan oleh siswa sendiri
        canceledBy = 'Anda';
      }
    }

    // Status badge color
    Color statusColor;
    IconData statusIcon;
    switch (status) {
      case 'booked':
        statusColor = Colors.green;
        statusIcon = Icons.check_circle;
        break;
      case 'canceled':
        statusColor = Colors.red;
        statusIcon = Icons.cancel;
        break;
      case 'completed':
        statusColor = Colors.blue;
        statusIcon = Icons.check_circle_outline;
        break;
      case 'no_show':
        statusColor = Colors.grey;
        statusIcon = Icons.event_busy;
        break;
      default:
        statusColor = Colors.grey;
        statusIcon = Icons.info;
    }

    return Card(
      color: status == 'canceled' ? Colors.red.shade50 : null,
      child: ListTile(
        leading: Icon(
          isUpcoming ? Icons.event_available : statusIcon,
          color: isUpcoming ? null : statusColor,
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(tglLabel, style: const TextStyle(fontWeight: FontWeight.w600)),
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
                  style: TextStyle(color: Colors.grey.shade700)),
            if (notes.isNotEmpty)
              Text('Catatan: $notes',
                  style: TextStyle(color: Colors.grey.shade700)),
            if (status == 'canceled') ...[
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red.shade100,
                  borderRadius: BorderRadius.circular(4),
                  border: Border.all(color: Colors.red.shade300),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.info_outline,
                            size: 16, color: Colors.red.shade800),
                        const SizedBox(width: 4),
                        Text(
                          'Dibatalkan oleh: ${canceledBy ?? "Unknown"}',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.red.shade800,
                          ),
                        ),
                      ],
                    ),
                    if (cancelReason.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        'Alasan: $cancelReason',
                        style: TextStyle(color: Colors.red.shade900),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (status.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(right: 8.0),
                child: Chip(
                  label: Text(
                    status == 'booked'
                        ? 'Booked'
                        : status == 'canceled'
                            ? 'Canceled'
                            : status == 'completed'
                                ? 'Completed'
                                : status == 'no_show'
                                    ? 'No Show'
                                    : status,
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
                  backgroundColor: statusColor,
                  visualDensity: VisualDensity.compact,
                ),
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
  }
}
