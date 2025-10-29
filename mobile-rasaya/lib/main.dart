import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'auth/auth_controller.dart';
import 'screens/login_page.dart';
import 'screens/home_page.dart';
import 'screens/refleksi_page.dart';
import 'screens/mood_page.dart';
import 'screens/history_page.dart';
import 'screens/booking_page.dart';
import 'screens/my_schedule_page.dart';
import 'screens/profile_page.dart';
import 'screens/change_password_page.dart';
import 'screens/stats_page.dart';

class RefleksiHistoryPage extends StatelessWidget {
  const RefleksiHistoryPage({super.key});
  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: Text('History Refleksi (todo)')));
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Set default locale to Indonesian and load date symbols for intl
  Intl.defaultLocale = 'id_ID';
  try {
    await initializeDateFormatting('id_ID');
  } catch (_) {
    // If initialization fails, the app will still run with default locale
  }
  runApp(const ProviderScope(child: App()));
}

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
        GoRoute(path: '/stats', builder: (_, __) => const StatsPage()),
        GoRoute(
            path: '/refleksi',
            builder: (_, state) {
              final jenis = state.uri.queryParameters['jenis'];
              return RefleksiPage(initialJenis: jenis);
            }),
        GoRoute(
            path: '/refleksi/history',
            builder: (_, __) => const RefleksiHistoryPage()),
        GoRoute(
            path: '/mood', builder: (_, __) => const MoodPage()), // NEW (stub)
        GoRoute(path: '/history', builder: (_, __) => const HistoryPage()),
        GoRoute(path: '/booking', builder: (_, __) => const BookingPage()),
        GoRoute(
            path: '/my-schedule', builder: (_, __) => const MySchedulePage()),
        GoRoute(path: '/profile', builder: (_, __) => const ProfilePage()),
        GoRoute(
            path: '/profile/change-password',
            builder: (_, __) => const ChangePasswordPage()),
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
    const brokenWhite = Color(0xFFF7F7F2);
    const primary = Color(0xFF192653); // deep indigo-blue hint
    const secondary = Color(0xFF0F6A49); // green hint

    final colorScheme = const ColorScheme(
      brightness: Brightness.light,
      primary: primary,
      onPrimary: Colors.white,
      secondary: secondary,
      onSecondary: Colors.white,
      error: Color(0xFFB3261E),
      onError: Colors.white,
      surface: brokenWhite,
      onSurface: Color(0xFF1B1B1F),
    );

    final theme = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: brokenWhite,
      appBarTheme: const AppBarTheme(
        backgroundColor: brokenWhite,
        foregroundColor: primary,
        elevation: 0,
        centerTitle: false,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        TargetPlatform.windows: FadeUpwardsPageTransitionsBuilder(),
        TargetPlatform.linux: FadeUpwardsPageTransitionsBuilder(),
        TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
      }),
      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(12))),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: const StadiumBorder(),
        labelStyle: const TextStyle(fontSize: 12),
        backgroundColor: Colors.white.withOpacity(.9),
      ),
    );

    return MaterialApp.router(
      title: 'Rasaya',
      theme: theme,
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

// moved to screens/profile_page.dart
