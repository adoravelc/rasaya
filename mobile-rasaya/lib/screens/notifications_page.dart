import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';
import 'package:intl/intl.dart';

// Provider untuk fetch notifications
final notificationsProvider =
    FutureProvider.autoDispose<List<NotificationItem>>((ref) async {
  final api = ref.watch(apiClientProvider);
  try {
    final res = await api.get('/notifications');
    if (res.ok && res.data is List) {
      return (res.data as List)
          .map((n) => NotificationItem.fromJson(n))
          .toList();
    }
    return [];
  } catch (e) {
    return [];
  }
});

class NotificationItem {
  final int id;
  final String title;
  final String message;
  final String? type;
  final String? link;
  final bool isRead;
  final DateTime createdAt;

  NotificationItem({
    required this.id,
    required this.title,
    required this.message,
    this.type,
    this.link,
    required this.isRead,
    required this.createdAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    return NotificationItem(
      id: json['id'] ?? 0,
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      type: json['type'],
      link: json['link'],
      isRead: json['is_read'] == 1 || json['is_read'] == true,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }
}

class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});
  Future<void> _markAllAsRead(WidgetRef ref) async {
    try {
      final api = ref.read(apiClientProvider);
      await api.post('/notifications/read-all', {});
      ref.invalidate(notificationsProvider);
      ref.invalidate(notificationsCountProvider);
    } catch (e) {
      // Ignore error
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsAsync = ref.watch(notificationsProvider);
    final unreadAsync = ref.watch(notificationsCountProvider);

    return AppScaffold(
      title: 'Notifikasi',
      actions: [
        // Only show when there are unread notifications
        unreadAsync.maybeWhen(
          data: (count) => count > 0
              ? TextButton.icon(
                  onPressed: () async {
                    await _markAllAsRead(ref);
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                            content:
                                Text('Semua notifikasi ditandai sudah dibaca')),
                      );
                    }
                  },
                  icon:
                      const Icon(Icons.done_all, size: 18, color: Colors.white),
                  label: const Text(
                    'Baca Semua',
                    style: TextStyle(color: Colors.white, fontSize: 13),
                  ),
                )
              : const SizedBox.shrink(),
          orElse: () => TextButton.icon(
            onPressed: null,
            icon: const Icon(Icons.hourglass_empty,
                size: 18, color: Colors.white),
            label: const Text(
              'Memuat…',
              style: TextStyle(color: Colors.white, fontSize: 13),
            ),
          ),
        ),
      ],
      body: notificationsAsync.when(
        data: (notifications) {
          if (notifications.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.notifications_none_outlined,
                    size: 80,
                    color: Colors.grey[300],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Tidak ada notifikasi',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey[600],
                    ),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(notificationsProvider);
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: notifications.length,
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final notif = notifications[index];
                return _NotificationCard(notif: notif);
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.error_outline,
                size: 60,
                color: Colors.red,
              ),
              const SizedBox(height: 16),
              Text(
                'Gagal memuat notifikasi',
                style: TextStyle(color: Colors.grey[700]),
              ),
              const SizedBox(height: 8),
              ElevatedButton.icon(
                onPressed: () => ref.invalidate(notificationsProvider),
                icon: const Icon(Icons.refresh),
                label: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotificationCard extends ConsumerWidget {
  final NotificationItem notif;

  const _NotificationCard({required this.notif});

  IconData _getIconForType(String? type) {
    if (type == null) return Icons.info_outline;
    if (type.contains('konseling') || type.contains('booking')) {
      return Icons.calendar_today;
    }
    if (type.contains('mood') || type.contains('emosi')) {
      return Icons.emoji_emotions_outlined;
    }
    if (type.contains('refleksi')) {
      return Icons.edit_note;
    }
    return Icons.notifications_outlined;
  }

  Color _getColorForType(String? type) {
    if (type == null) return Colors.blue;
    if (type.contains('konseling') || type.contains('booking')) {
      return const Color(0xFFEC4899); // Pink
    }
    if (type.contains('mood') || type.contains('emosi')) {
      return const Color(0xFFF59E0B); // Orange
    }
    if (type.contains('refleksi')) {
      return const Color(0xFF8B5CF6); // Purple
    }
    return Colors.blue;
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Baru saja';
    } else if (difference.inHours < 1) {
      return '${difference.inMinutes} menit lalu';
    } else if (difference.inDays < 1) {
      return '${difference.inHours} jam lalu';
    } else if (difference.inDays < 7) {
      return '${difference.inDays} hari lalu';
    } else {
      return DateFormat('dd MMM yyyy', 'id_ID').format(dateTime);
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final iconColor = _getColorForType(notif.type);
    final icon = _getIconForType(notif.type);

    return Card(
      elevation: notif.isRead ? 0 : 2,
      color: notif.isRead ? Colors.white : Colors.blue.shade50,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: notif.isRead ? Colors.grey.shade200 : Colors.blue.shade200,
          width: 1,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: notif.link != null && notif.link!.isNotEmpty
            ? () async {
                // Mark as read
                try {
                  final api = ref.read(apiClientProvider);
                  await api.post('/notifications/${notif.id}/read', {});
                  ref.invalidate(notificationsProvider);
                  ref.invalidate(notificationsCountProvider);
                } catch (e) {
                  // Ignore error
                }

                // Navigate if link is provided
                if (context.mounted) {
                  // Simple navigation based on link
                  if (notif.link!.contains('booking') ||
                      notif.link!.contains('konseling')) {
                    context.go('/booking');
                  }
                }
              }
            : () async {
                // Just mark as read
                try {
                  final api = ref.read(apiClientProvider);
                  await api.post('/notifications/${notif.id}/read', {});
                  ref.invalidate(notificationsProvider);
                  ref.invalidate(notificationsCountProvider);
                } catch (e) {
                  // Ignore error
                }
              },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: iconColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  icon,
                  color: iconColor,
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              // Content
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            notif.title,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: notif.isRead
                                  ? FontWeight.w500
                                  : FontWeight.bold,
                              color: Colors.grey[900],
                            ),
                          ),
                        ),
                        if (!notif.isRead)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Colors.blue,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      notif.message,
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.grey[700],
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _formatTime(notif.createdAt),
                      style: TextStyle(
                        fontSize: 11,
                        color: Colors.grey[500],
                      ),
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
