import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'auth_controller.dart';
import 'screens/login.dart';
import 'screens/home.dart';

void main() {
  runApp(const ProviderScope(child: App()));
}

class App extends ConsumerStatefulWidget {
  const App({super.key});
  @override
  ConsumerState<App> createState() => _AppState();
}

class _AppState extends ConsumerState<App> {
  @override
  void initState() {
    super.initState();
    // bootstrap sekali saat start
    ref.read(authControllerProvider.notifier).bootstrap();
  }

  @override
  Widget build(BuildContext context) {
    // TONTON state supaya widget re-build saat auth berubah
    final auth = ref.watch(authControllerProvider);
    final loggedIn = auth.token != null && auth.me != null;

    final router = GoRouter(
      routes: [
        GoRoute(path: '/', builder: (_, __) => const LoginPage()),
        GoRoute(path: '/home', builder: (_, __) => const HomePage()),
      ],
      redirect: (context, state) {
        final atLogin = state.matchedLocation == '/';
        if (!loggedIn && !atLogin) return '/';
        if (loggedIn && atLogin) return '/home';
        return null;
      },
    );

    return MaterialApp.router(
      title: 'RASAYA',
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.indigo),
      routerConfig: router,
    );
  }
}
