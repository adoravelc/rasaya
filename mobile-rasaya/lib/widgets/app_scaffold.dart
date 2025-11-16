import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
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
          actions: widget.actions,
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
    final alignX = [-0.8, -0.4, 0.0, 0.4, 0.8][selectedIndex.clamp(0, 4)];

    return Container(
      decoration: BoxDecoration(
        color: cs.primary,
        boxShadow: const [
          BoxShadow(
              color: Color(0x22000000), blurRadius: 12, offset: Offset(0, -4)),
        ],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 74,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              // Water drop indicator (simple white drop icon)
              Positioned.fill(
                top: -16,
                child: AnimatedAlign(
                  alignment: Alignment(alignX, -1.0),
                  duration: const Duration(milliseconds: 260),
                  curve: Curves.easeInOut,
                  child: const Icon(Icons.water_drop,
                      color: Colors.white, size: 28),
                ),
              ),
              Align(
                alignment: Alignment.center,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: Row(
                    children: List.generate(5, (i) {
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
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(icon,
                                    color: active
                                        ? cs.secondary
                                        : cs.secondary.withOpacity(0.6)),
                                const SizedBox(height: 4),
                                Text(labels[i],
                                    style:
                                        theme.textTheme.labelMedium?.copyWith(
                                      fontWeight: FontWeight.w600,
                                      color: active
                                          ? cs.secondary
                                          : cs.secondary.withOpacity(0.6),
                                    )),
                              ],
                            ),
                          ),
                        ),
                      );
                    }),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

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
