import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fl_chart/fl_chart.dart';
import '../widgets/app_scaffold.dart';
import '../auth/auth_controller.dart';
import 'package:intl/intl.dart';

// Mood helpers (same mapping as Home/Mood pages)
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

class StatsPage extends ConsumerStatefulWidget {
  const StatsPage({super.key});
  @override
  ConsumerState<StatsPage> createState() => _StatsPageState();
}

class _StatsPageState extends ConsumerState<StatsPage> {
  String _period = 'mingguan';
  // Selected month for 'bulanan' view (first day of month)
  DateTime _selectedMonth =
      DateTime(DateTime.now().year, DateTime.now().month, 1);

  @override
  Widget build(BuildContext context) {
    final query = _buildQueryFor(_period);
    final async = ref.watch(_moodStatsProvider(query));
    final cs = Theme.of(context).colorScheme;

    return AppScaffold(
      title: 'Statistik Emosi',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: const Text('Mingguan'),
                selected: _period == 'mingguan',
                onSelected: (_) => setState(() => _period = 'mingguan'),
              ),
              ChoiceChip(
                label: const Text('Bulanan'),
                selected: _period == 'bulanan',
                onSelected: (_) => setState(() => _period = 'bulanan'),
              ),
            ],
          ),
          if (_period == 'bulanan') ...[
            const SizedBox(height: 12),
            // Month selector styled similar to Home cards
            Builder(builder: (context) {
              final cs = Theme.of(context).colorScheme;
              final now = DateTime.now();
              final months = List.generate(
                  12, (i) => DateTime(now.year, now.month - i, 1));
              return Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      Colors.white,
                      Color.alphaBlend(
                          cs.secondary.withOpacity(0.06), Colors.white),
                    ],
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: cs.primary.withOpacity(0.10),
                      blurRadius: 16,
                      offset: const Offset(0, 8),
                    ),
                  ],
                  border: Border.all(color: cs.primary.withOpacity(0.06)),
                ),
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<DateTime>(
                    value: _selectedMonth,
                    icon: const Icon(Icons.keyboard_arrow_down_rounded),
                    items: [
                      for (final m in months)
                        DropdownMenuItem<DateTime>(
                          value: m,
                          child: Text(DateFormat('MMMM y', 'id_ID').format(m)),
                        )
                    ],
                    onChanged: (v) {
                      if (v == null) return;
                      setState(() {
                        _selectedMonth = DateTime(v.year, v.month, 1);
                      });
                    },
                  ),
                ),
              );
            }),
          ],
          const SizedBox(height: 16),
          async.when(
            loading: () => const Card(
              child: SizedBox(
                  height: 260,
                  child: Center(child: CircularProgressIndicator())),
            ),
            error: (e, _) => Card(
              child: SizedBox(
                height: 120,
                child: Center(
                  child: Text('Gagal memuat data: $e'),
                ),
              ),
            ),
            data: (data) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(24),
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          Colors.white,
                          Color.alphaBlend(
                              cs.secondary.withOpacity(0.08), Colors.white),
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
                    child: Padding(
                      padding: const EdgeInsets.all(12.0),
                      child: SizedBox(
                        height: 260,
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 500),
                          switchInCurve: Curves.easeOutCubic,
                          switchOutCurve: Curves.easeInCubic,
                          child: _MoodLineChart(
                            key: ValueKey(
                                '$_period-${data.xLabels.length}-${data.count}'),
                            data: data,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Center(
                    child: Text(
                      'Top Emoji Paling Sering',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                        color: cs.primary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  _TopEmojis(freq: data.freq),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  _StatsQuery _buildQueryFor(String period) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    if (period == 'bulanan') {
      final first = DateTime(_selectedMonth.year, _selectedMonth.month, 1);
      final last = DateTime(_selectedMonth.year, _selectedMonth.month + 1, 0);
      return _StatsQuery(
          from: first, to: last, granularity: _Granularity.daily);
    }
    // default: mingguan (Senin -> Minggu)
    final monday = today.subtract(Duration(days: today.weekday - 1));
    final sunday = monday.add(const Duration(days: 6));
    final from = DateTime(monday.year, monday.month, monday.day);
    final to = DateTime(sunday.year, sunday.month, sunday.day);
    return _StatsQuery(from: from, to: to, granularity: _Granularity.daily);
  }
}

enum _Granularity { daily }

class _StatsQuery {
  final DateTime from;
  final DateTime to;
  final _Granularity granularity;
  const _StatsQuery(
      {required this.from, required this.to, required this.granularity});

  // Ensure Riverpod family sees equivalent params as equal (prevents refetch loops)
  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is _StatsQuery &&
        other.granularity == granularity &&
        _dateKey(other.from) == _dateKey(from) &&
        _dateKey(other.to) == _dateKey(to);
  }

  @override
  int get hashCode => Object.hash(granularity, _dateKey(from), _dateKey(to));

  static String _dateKey(DateTime d) =>
      DateFormat('yyyy-MM-dd').format(DateTime(d.year, d.month, d.day));
}

class _MoodStatsData {
  final List<_StatsPoint> points; // x index + y (avg score)
  final int count; // total entries in range
  final List<String> xLabels;
  final Map<int, int> freq; // score -> count
  const _MoodStatsData(
      {required this.points,
      required this.count,
      required this.xLabels,
      required this.freq});
}

class _StatsPoint {
  final int xIndex; // index in labels
  final double y; // avg score (1..10)
  const _StatsPoint(this.xIndex, this.y);
}

final _moodStatsProvider =
    FutureProvider.family<_MoodStatsData, _StatsQuery>((ref, q) async {
  final api = ref.read(apiClientProvider);
  String fromStr = DateFormat('yyyy-MM-dd').format(q.from);
  String toStr = DateFormat('yyyy-MM-dd').format(q.to);
  final res = await api.getMoodHistory(
    page: 1,
    perPage: 1000,
    tanggalFrom: fromStr,
    tanggalTo: toStr,
  );
  if (!res.ok || res.data is! Map || (res.data['data'] is! List)) {
    return const _MoodStatsData(points: [], count: 0, xLabels: [], freq: {});
  }
  final list = (res.data['data'] as List).cast<Map>();
  // frequency of all scores in range (1..10)
  final freq = <int, int>{};
  for (final raw in list) {
    final m = raw.cast<String, dynamic>();
    final s = int.tryParse((m['skor'] ?? '').toString());
    if (s != null) freq[s] = (freq[s] ?? 0) + 1;
  }
  // Build buckets
  if (q.granularity == _Granularity.daily) {
    // map yyyy-MM-dd -> [scores]
    final byDay = <String, List<int>>{};
    DateTime _normalize(String raw) {
      if (raw.contains('T')) {
        final dt = DateTime.parse(raw);
        final dtWita = dt.isUtc ? dt.toUtc().add(const Duration(hours: 8)) : dt;
        return DateTime(dtWita.year, dtWita.month, dtWita.day);
      }
      final p = raw.split('-');
      if (p.length == 3) {
        return DateTime(int.parse(p[0]), int.parse(p[1]), int.parse(p[2]));
      }
      return DateTime.parse(raw);
    }

    for (final raw in list) {
      final m = raw.cast<String, dynamic>();
      final tglRaw = (m['tanggal'] ?? '').toString();
      final s = int.tryParse((m['skor'] ?? '').toString());
      if (tglRaw.isEmpty || s == null) continue;
      final dt = _normalize(tglRaw);
      final dayKey = DateFormat('yyyy-MM-dd').format(dt);
      (byDay[dayKey] ??= []).add(s);
    }
    // labels: from..to each day
    final labels = <String>[];
    final points = <_StatsPoint>[];
    final days = q.to.difference(q.from).inDays + 1;
    final totalCount = list.length;
    for (int i = 0; i < days; i++) {
      final d = DateTime(q.from.year, q.from.month, q.from.day)
          .add(Duration(days: i));
      final key = DateFormat('yyyy-MM-dd').format(d);
      final scores = byDay[key];
      // Label style: if the window is >= 28 days (monthly), show 'd/M', else weekday name
      final isMonthlyWindow = days >= 28;
      labels.add(isMonthlyWindow
          ? DateFormat('d/M').format(d)
          : DateFormat('EEE', 'id_ID').format(d));
      if (scores != null && scores.isNotEmpty) {
        final avg = scores.reduce((a, b) => a + b) / scores.length;
        points.add(_StatsPoint(i, avg.toDouble()));
      }
    }
    return _MoodStatsData(
        points: points, count: totalCount, xLabels: labels, freq: freq);
  }
  // Fallback (should not be reached since only 'daily' is used)
  return const _MoodStatsData(points: [], count: 0, xLabels: [], freq: {});
});

class _MoodLineChart extends StatelessWidget {
  const _MoodLineChart({super.key, required this.data});
  final _MoodStatsData data;

  @override
  Widget build(BuildContext context) {
    // final cs = Theme.of(context).colorScheme;
    // Convert to FlSpots
    final spots =
        data.points.map((p) => FlSpot(p.xIndex.toDouble(), p.y)).toList();
    final double maxX =
        data.xLabels.isEmpty ? 0.0 : (data.xLabels.length - 1).toDouble();

    Color colorFor(double y) {
      final s = y.round().clamp(1, 10);
      if (s <= 2) return Colors.red.shade500;
      if (s <= 4) return Colors.deepOrange.shade500;
      if (s <= 6) return Colors.amber.shade600;
      if (s <= 8) return Colors.lightGreen.shade600;
      return Colors.green.shade600;
    }

    // Build colored segments so the line color follows mood score
    final List<LineChartBarData> segments = [];
    if (spots.length >= 2) {
      List<FlSpot> current = [spots[0]];
      Color currentColor = colorFor(spots[0].y);
      for (int i = 1; i < spots.length; i++) {
        final c = colorFor(spots[i].y);
        if (c.value == currentColor.value) {
          current.add(spots[i]);
        } else {
          if (current.length >= 2) {
            segments.add(LineChartBarData(
              spots: List.of(current),
              isCurved: true,
              color: currentColor,
              barWidth: 3.0,
              dotData: FlDotData(show: true),
              belowBarData: BarAreaData(
                show: true,
                color: currentColor.withOpacity(.12),
              ),
            ));
          }
          // start a new segment, including the prev point to maintain continuity
          current = [spots[i - 1], spots[i]];
          currentColor = c;
        }
      }
      if (current.length >= 2) {
        segments.add(LineChartBarData(
          spots: List.of(current),
          isCurved: true,
          color: currentColor,
          barWidth: 3.0,
          dotData: FlDotData(show: true),
          belowBarData: BarAreaData(
            show: true,
            color: currentColor.withOpacity(.12),
          ),
        ));
      }
    } else if (spots.isNotEmpty) {
      final c = colorFor(spots.first.y);
      segments.add(LineChartBarData(
        spots: spots,
        isCurved: true,
        color: c,
        barWidth: 3.0,
        dotData: FlDotData(show: true),
        belowBarData: BarAreaData(show: true, color: c.withOpacity(.12)),
      ));
    }
    return LineChart(
      LineChartData(
        minY: 1.0,
        maxY: 10.0,
        minX: 0.0,
        maxX: maxX,
        gridData: const FlGridData(show: false),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 36.0,
              interval: 1.0,
              getTitlesWidget: (v, meta) {
                // Show emojis at 2,4,6,8,10 for a clean scale
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
                final i = v.round();
                if (i % 2 != 0 || i < 1 || i > 10) {
                  return const SizedBox.shrink();
                }
                return Text(emojis[i - 1],
                    style: const TextStyle(fontSize: 12));
              },
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: (data.xLabels.length / 6).clamp(1, 6).toDouble(),
              getTitlesWidget: (v, meta) {
                final idx = v.round();
                if (idx < 0 || idx >= data.xLabels.length) {
                  return const SizedBox.shrink();
                }
                return Padding(
                  padding: const EdgeInsets.only(top: 4.0),
                  child: Text(
                    data.xLabels[idx],
                    style: const TextStyle(fontSize: 10),
                  ),
                );
              },
            ),
          ),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData:
            FlBorderData(show: true, border: Border.all(color: Colors.black12)),
        lineTouchData: LineTouchData(
          enabled: true,
          touchTooltipData: LineTouchTooltipData(
            getTooltipItems: (touchedSpots) {
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
              String emojiFor(double y) {
                int s = y.round().clamp(1, 10);
                return emojis[s - 1];
              }

              return touchedSpots.map((ts) {
                final idx = ts.x.round();
                final label = (idx >= 0 && idx < data.xLabels.length)
                    ? data.xLabels[idx]
                    : '';
                return LineTooltipItem(
                  '$label\nMood: ${emojiFor(ts.y)}',
                  const TextStyle(color: Colors.white),
                );
              }).toList();
            },
          ),
        ),
        lineBarsData: segments,
      ),
    );
  }
}

class _TopEmojis extends StatelessWidget {
  const _TopEmojis({required this.freq});
  final Map<int, int> freq;

  @override
  Widget build(BuildContext context) {
    if (freq.isEmpty) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(12.0),
          child: Text('Belum ada data.'),
        ),
      );
    }
    final entries = freq.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));
    final top = entries.take(5).toList();
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
      child: Padding(
        padding: const EdgeInsets.all(12.0),
        child: Wrap(
          spacing: 10,
          runSpacing: 10,
          children: top.map((e) {
            final score = e.key.clamp(1, 10);
            final color = _moodColorForScore(score);
            final icon = _moodIconForScore(score, filled: true);
            return Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: const [
                  BoxShadow(
                      color: Colors.black12,
                      blurRadius: 8,
                      offset: Offset(0, 4)),
                ],
                border: Border.all(color: Colors.black12, width: 0.5),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, color: color, size: 28),
                  const SizedBox(width: 6),
                  Text('×${e.value}',
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                ],
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}
