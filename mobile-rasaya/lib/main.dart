import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'auth/auth_controller.dart';
import 'screens/login_page.dart';
import 'screens/home_page.dart';
import 'screens/refleksi_page.dart';
import 'screens/mood_page.dart';
import 'screens/history_page.dart';

// (opsional) siapkan halaman history sederhana sementara
class RefleksiHistoryPage extends StatelessWidget {
  const RefleksiHistoryPage({super.key});
  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: Text('History Refleksi (todo)')));
}

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
    ref.read(authControllerProvider.notifier).bootstrap();

    _router = GoRouter(
      routes: [
        GoRoute(path: '/', builder: (_, __) => const LoginPage()),
        GoRoute(path: '/home', builder: (_, __) => const HomePage()),
        GoRoute(
            path: '/refleksi', builder: (_, __) => const RefleksiPage()), // NEW
        GoRoute(
            path: '/refleksi/history',
            builder: (_, __) => const RefleksiHistoryPage()),
        GoRoute(
            path: '/mood', builder: (_, __) => const MoodPage()), // NEW (stub)
        GoRoute(path: '/history', builder: (_, __) => const HistoryPage()),
      ],
      redirect: (context, state) {
        final st = ref.read(authControllerProvider);
        final loggedIn = st.token != null && st.me != null;
        final atLogin = state.matchedLocation == '/';
        if (!loggedIn && !atLogin) return '/';
        if (loggedIn && atLogin) return '/home';
        return null;
      },
      refreshListenable: GoRouterRefreshStream(
        ref.read(authControllerProvider.notifier).stream,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Rasaya',
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.indigo),
      routerConfig: _router,
    );
  }
}

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
