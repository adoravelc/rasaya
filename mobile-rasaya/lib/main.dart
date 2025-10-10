import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'auth/auth_controller.dart';
import 'screens/login_page.dart';
import 'screens/home_page.dart';

void main() => runApp(const ProviderScope(child: App()));

class App extends ConsumerStatefulWidget {
  const App({super.key});
  @override
  ConsumerState<App> createState() => _AppState();
}

class _AppState extends ConsumerState<App> {
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    // bootstrap token -> /me
    ref.read(authControllerProvider.notifier).bootstrap();

    _router = GoRouter(
      routes: [
        GoRoute(path: '/', builder: (_, __) => const LoginPage()),
        GoRoute(path: '/home', builder: (_, __) => const HomePage()),
      ],
      redirect: (context, state) {
        final st = ref.read(authControllerProvider);
        final loggedIn = st.token != null && st.me != null;
        final atLogin = state.matchedLocation == '/';
        if (!loggedIn && !atLogin) return '/';
        if (loggedIn && atLogin) return '/home';
        return null;
      },
      // biar router tau ada perubahan & evaluasi redirect lagi
      // (cukup rebuild App saat state auth berubah)
      refreshListenable: GoRouterRefreshStream(
        ref.read(authControllerProvider.notifier).stream,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'RASAYA',
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.indigo),
      routerConfig: _router,
    );
  }
}

/// Helper: konversi Stream ke Listenable buat GoRouter.
/// (Sederhana, cukup untuk trigger refresh)
class GoRouterRefreshStream extends ChangeNotifier {
  GoRouterRefreshStream(Stream<dynamic> stream) {
    _sub = stream.asBroadcastStream().listen((_) => notifyListeners());
  }
  late final StreamSubscription<dynamic> _sub;
  @override
  void dispose() {
    _sub.cancel();
    super.dispose();
  }
}
