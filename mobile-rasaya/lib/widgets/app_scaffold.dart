import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/auth_controller.dart';
import '../api/api_client.dart';
// Drawer removed; use bottom navigation everywhere

class AppScaffold extends StatefulWidget {
  const AppScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.bottom,
  });
  final String title;
  final Widget body;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;

  @override
  State<AppScaffold> createState() => _AppScaffoldState();
}

class _AppScaffoldState extends State<AppScaffold>
    with SingleTickerProviderStateMixin {
  late final AnimationController _dropController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 240),
  );
  int _lastTab = 0; // used to highlight when center menu not selected

  int _currentIndex(BuildContext context) {
    String loc = '/home';
    try {
      // Preferred in newer go_router versions
      loc = GoRouterState.of(context).uri.toString();
    } catch (_) {
      try {
        // Fallback for older versions
        final v = GoRouter.of(context).routeInformationProvider.value.location;
        if (v.isNotEmpty) loc = v;
      } catch (_) {
        // keep default '/home'
      }
    }
    if (loc.startsWith('/home')) return 0;
    if (loc.startsWith('/stats')) return 1;
    if (loc.startsWith('/refleksi') || loc.startsWith('/mood'))
      return 2; // input
    if (loc.startsWith('/booking') || loc.startsWith('/my-schedule')) return 3;
    if (loc.startsWith('/profile')) return 4;
    return _lastTab;
  }

  void _onSelect(int index) {
    if (index == 2) {
      _openInputMenu();
      return;
    }
    setState(() => _lastTab = index);
    switch (index) {
      case 0:
        context.go('/home');
        break;
      case 1:
        context.go('/stats');
        break;
      case 3:
        context.go('/booking');
        break;
      case 4:
        context.go('/profile');
        break;
    }
  }

  Future<void> _openInputMenu() async {
    _dropController.forward(from: 0);
    await showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'input-menu',
      barrierColor: Colors.transparent,
      transitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (_, __, ___) {
        final viewPadding = MediaQuery.viewPaddingOf(context);
        return Stack(
          children: [
            // tap anywhere to dismiss
            Positioned.fill(
              child: GestureDetector(onTap: () => Navigator.of(context).pop()),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: (56 + viewPadding.bottom) + 16,
              child: Center(
                child: _InputQuickMenu(
                  onMood: () {
                    Navigator.of(context).pop();
                    context.go('/mood');
                  },
                  onRefleksi: () {
                    Navigator.of(context).pop();
                    context.go('/refleksi');
                  },
                ),
              ),
            ),
          ],
        );
      },
      transitionBuilder: (_, anim, __, child) {
        return FadeTransition(
          opacity: CurvedAnimation(parent: anim, curve: Curves.easeOut),
          child: SlideTransition(
            position: Tween<Offset>(
                    begin: const Offset(0, 0.1), end: Offset.zero)
                .animate(CurvedAnimation(parent: anim, curve: Curves.easeOut)),
            child: child,
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final idx = _currentIndex(context);
    return Scaffold(
      appBar: AppBar(
          title: Text(widget.title),
          actions: [
            const _NotificationBell(),
            ...?widget.actions,
          ],
          bottom: widget.bottom),
      body: widget.body,
      bottomNavigationBar: _RasayaBottomNav(
        selectedIndex: idx,
        onTap: _onSelect,
        controller: _dropController,
      ),
    );
  }
}

// ===== Notification Bell (placeholder logic) =====
final notificationsCountProvider = FutureProvider<int>((ref) async {
  final token = ref.watch(authControllerProvider.select((s) => s.token));
  if (token == null) return 0;
  final api = ref.read(apiClientProvider);
  // Placeholder: attempt GET /notifications if exists, else return 0
  try {
    final res = await api.get('/notifications');
    if (!res.ok) return 0;
    final data = res.data;
    if (data is Map && data['unread'] is int) return data['unread'] as int;
    if (data is List) {
      // assume list of notifications with is_read flag
      final list = data.cast<dynamic>();
      int unread = 0;
      for (final n in list) {
        if (n is Map && (n['is_read'] == false || n['read_at'] == null))
          unread++;
      }
      return unread;
    }
    return 0;
  } catch (_) {
    return 0;
  }
});

class _NotificationBell extends ConsumerWidget {
  const _NotificationBell();
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final countAsync = ref.watch(notificationsCountProvider);
    final badge = countAsync.maybeWhen(data: (c) => c, orElse: () => 0);
    return IconButton(
      tooltip: 'Notifikasi',
      onPressed: () {
        // Future: navigate to notifications page
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Notifikasi coming soon')),
        );
      },
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.notifications_none),
          if (badge > 0)
            Positioned(
              right: -2,
              top: -2,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.redAccent,
                  borderRadius: BorderRadius.circular(10),
                ),
                constraints: const BoxConstraints(minWidth: 18),
                child: Text(
                  badge > 99 ? '99+' : badge.toString(),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _RasayaBottomNav extends StatelessWidget {
  const _RasayaBottomNav({
    required this.selectedIndex,
    required this.onTap,
    required this.controller,
  });
  final int selectedIndex;
  final ValueChanged<int> onTap;
  final AnimationController controller;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    const labels = ['Home', 'Stats', 'Input', 'Booking', 'Profil'];
    return SafeArea(
      top: false,
      child: SizedBox(
        height: 86,
        child: Align(
          alignment: Alignment.center,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: LayoutBuilder(builder: (context, cons) {
                final w = cons.maxWidth;
                const count = 5;
                final itemW = w / count;
                final pillW = (itemW * 0.78).clamp(84.0, 120.0);
                const pillH = 36.0;
                final left =
                    selectedIndex.clamp(0, 4) * itemW + (itemW - pillW) / 2;
                final bar = Container(
                  height: 64,
                  decoration: BoxDecoration(
                    color: cs.primary,
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: const [
                      BoxShadow(
                          color: Color(0x22000000),
                          blurRadius: 12,
                          offset: Offset(0, -2)),
                    ],
                  ),
                );
                final icons = Row(
                  children: List.generate(count, (i) {
                    final active = i == selectedIndex;
                    final icon = [
                      Icons.home,
                      Icons.insights,
                      Icons.menu_book_rounded,
                      Icons.event_available,
                      Icons.person,
                    ][i];
                    return Expanded(
                      child: InkWell(
                        onTap: () => onTap(i),
                        borderRadius: BorderRadius.circular(16),
                        child: SizedBox(
                          height: 64,
                          child: Center(
                            child: active
                                ? const SizedBox
                                    .shrink() // icon rendered inside the pill
                                : Icon(icon,
                                    color: cs.secondary.withOpacity(0.6)),
                          ),
                        ),
                      ),
                    );
                  }),
                );

                final pill = AnimatedPositioned(
                  duration: const Duration(milliseconds: 260),
                  curve: Curves.easeInOut,
                  top: 14,
                  left: left,
                  width: pillW,
                  height: pillH,
                  child: Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(999),
                      boxShadow: const [
                        BoxShadow(
                            color: Color(0x33000000),
                            blurRadius: 10,
                            offset: Offset(0, 6)),
                      ],
                    ),
                    child: Center(
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            [
                              Icons.home,
                              Icons.insights,
                              Icons.menu_book_rounded,
                              Icons.event_available,
                              Icons.person,
                            ][selectedIndex],
                            size: 18,
                            color: cs.primary,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            labels[selectedIndex],
                            style: theme.textTheme.labelLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: cs.primary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );

                return Stack(children: [bar, icons, pill]);
              }),
            ),
          ),
        ),
      ),
    );
  }
}

// Removed old concave-notch painter in favor of pill-style indicator

class _InputQuickMenu extends StatelessWidget {
  const _InputQuickMenu({required this.onRefleksi, required this.onMood});
  final VoidCallback onRefleksi;
  final VoidCallback onMood;
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Material(
      color: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: cs.primary,
          borderRadius: BorderRadius.circular(16),
          boxShadow: const [
            BoxShadow(
                color: Color(0x33000000), blurRadius: 12, offset: Offset(0, 6)),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _pillButton(context,
                label: 'Refleksi', icon: Icons.edit, onTap: onRefleksi),
            const SizedBox(width: 10),
            _pillButton(context,
                label: 'Mood Tracker', icon: Icons.favorite, onTap: onMood),
          ],
        ),
      ),
    );
  }

  Widget _pillButton(BuildContext context,
      {required String label,
      required IconData icon,
      required VoidCallback onTap}) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: cs.secondary,
          borderRadius: BorderRadius.circular(24),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 18, color: Colors.black),
            const SizedBox(width: 6),
            Text(label,
                style:
                    theme.textTheme.labelLarge?.copyWith(color: Colors.black)),
          ],
        ),
      ),
    );
  }
}
