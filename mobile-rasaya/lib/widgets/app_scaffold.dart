import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
// Drawer removed; use bottom navigation everywhere

class AppScaffold extends StatelessWidget {
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
    if (loc.startsWith('/refleksi')) return 2; // center plus
    if (loc.startsWith('/booking') || loc.startsWith('/my-schedule')) return 3;
    if (loc.startsWith('/profile')) return 4;
    return 0;
  }

  void _onSelect(BuildContext context, int index) {
    switch (index) {
      case 0:
        context.go('/home');
        break;
      case 1:
        context.go('/stats');
        break;
      case 2:
        context.go('/refleksi');
        break;
      case 3:
        context.go('/booking');
        break;
      case 4:
        context.go('/profile');
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: Text(title), actions: actions, bottom: bottom),
      body: body,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex(context),
        onDestinationSelected: (i) => _onSelect(context, i),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.insights_outlined),
              selectedIcon: Icon(Icons.insights),
              label: 'Stats'),
          NavigationDestination(
              icon: Icon(Icons.add_circle_outline),
              selectedIcon: Icon(Icons.add_circle),
              label: 'Refleksi'),
          NavigationDestination(
              icon: Icon(Icons.event_available_outlined),
              selectedIcon: Icon(Icons.event_available),
              label: 'Booking'),
          NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Profil'),
        ],
        indicatorColor: cs.primary.withOpacity(.12),
      ),
    );
  }
}
