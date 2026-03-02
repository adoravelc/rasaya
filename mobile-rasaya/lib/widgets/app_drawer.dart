import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../utils/external_navigation.dart';
import '../utils/guest_logout_url.dart';

class AppDrawer extends ConsumerWidget {
  const AppDrawer({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(authControllerProvider).me;
    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            UserAccountsDrawerHeader(
              accountName:
                  Text((me?['nama'] ?? me?['name'] ?? 'Siswa').toString()),
              accountEmail: Text((me?['email'] ?? '-').toString()),
              currentAccountPicture:
                  const CircleAvatar(child: Icon(Icons.person)),
            ),
            _Item(
                icon: Icons.home,
                label: 'Home',
                onTap: () => context.go('/home')),
            _Item(
                icon: Icons.calendar_month,
                label: 'Booking Konseling',
                onTap: () => context.go('/booking')),
            _Item(
                icon: Icons.schedule,
                label: 'Jadwal Saya',
                onTap: () => context.go('/my-schedule')),
            _Item(
                icon: Icons.history,
                label: 'Riwayat',
                onTap: () => context.go('/history')),
            _Item(
                icon: Icons.edit_note,
                label: 'Tulis Refleksi',
                onTap: () => context.go('/refleksi')),
            _Item(
                icon: Icons.mood,
                label: 'Mood Tracker',
                onTap: () => context.go('/mood')),
            const Spacer(),
            const Divider(),
            _Item(
                icon: Icons.person,
                label: 'Profil',
                onTap: () => context.go('/profile')),
            _Item(
              icon: Icons.logout,
              label: 'Logout',
              onTap: () async {
                final auth = ref.read(authControllerProvider);
                final guestHomeUrl = auth.guestHomeUrl;
                final isGuestMode = auth.isGuestMode;

                await ref.read(authControllerProvider.notifier).logout();
                if (isGuestMode &&
                    guestHomeUrl != null &&
                    guestHomeUrl.isNotEmpty) {
                  final target = buildGuestResetUrl(
                    guestHomeUrl: guestHomeUrl,
                    flutterOrigin: Uri.base.origin,
                  );
                  await openExternalUrl(target);
                  return;
                }

                if (context.mounted) context.go('/');
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _Item extends StatelessWidget {
  const _Item({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label),
      onTap: onTap,
    );
  }
}
