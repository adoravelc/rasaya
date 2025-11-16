import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
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
    const primary = Color(0xFF073763); // navy
    const secondary = Color(0xFFEBDAE3); // soft pink

    final colorScheme = const ColorScheme(
      brightness: Brightness.light,
      primary: primary,
      onPrimary: Colors.white,
      secondary: secondary,
      onSecondary: Colors.black,
      error: Color(0xFFB3261E),
      onError: Colors.white,
      surface: brokenWhite,
      onSurface: Color(0xFF111111),
    );

    final baseText = ThemeData.light().textTheme;
    // Base: Poppins for most text, override titles/headlines with Lora
    final poppins = GoogleFonts.poppinsTextTheme(baseText).apply(
      bodyColor: const Color(0xFF111111),
      displayColor: const Color(0xFF111111),
    );
    final textTheme = poppins.copyWith(
      // Larger title-like styles use Lora
      displayLarge: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 44,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      displayMedium: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 36,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      displaySmall: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 30,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      headlineLarge: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 28,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      headlineMedium: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 24,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      titleLarge: GoogleFonts.lora(
        fontWeight: FontWeight.w700,
        fontSize: 20,
        letterSpacing: -0.2,
        color: const Color(0xFF111111),
      ),
      // Keep other text sizes in Poppins
      titleMedium: GoogleFonts.poppins(
        fontWeight: FontWeight.w600,
        fontSize: 16,
        color: const Color(0xFF111111),
      ),
      bodyLarge: GoogleFonts.poppins(
        fontWeight: FontWeight.w400,
        fontSize: 14,
        height: 1.4,
        color: const Color(0xFF111111),
      ),
      bodyMedium: GoogleFonts.poppins(
        fontWeight: FontWeight.w400,
        fontSize: 13,
        height: 1.5,
        color: const Color(0xFF111111),
      ),
      labelLarge: GoogleFonts.poppins(
        fontWeight: FontWeight.w600,
        fontSize: 13,
        color: Colors.white,
      ),
    );

    final theme = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      textTheme: textTheme,
      scaffoldBackgroundColor: brokenWhite,
      appBarTheme: AppBarTheme(
        backgroundColor: brokenWhite,
        foregroundColor: primary,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: textTheme.titleLarge,
      ),
      pageTransitionsTheme: const PageTransitionsTheme(builders: {
        TargetPlatform.android: ZoomPageTransitionsBuilder(),
        TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        TargetPlatform.windows: ZoomPageTransitionsBuilder(),
        TargetPlatform.linux: ZoomPageTransitionsBuilder(),
        TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
      }),
      cardTheme: CardThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        elevation: 1,
      ),
      inputDecorationTheme: InputDecorationTheme(
        hintStyle: textTheme.bodyMedium?.copyWith(color: Colors.black54),
        border: const OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(12))),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 18),
          elevation: 3,
          shadowColor: primary.withOpacity(0.35),
          textStyle: textTheme.labelLarge,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: secondary,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 18),
          elevation: 3,
          shadowColor: primary.withOpacity(0.35),
          textStyle: textTheme.labelLarge,
        ),
      ),
      chipTheme: ChipThemeData(
        shape: const StadiumBorder(),
        labelStyle: textTheme.bodyMedium?.copyWith(fontSize: 12),
        backgroundColor: secondary.withOpacity(.5),
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
