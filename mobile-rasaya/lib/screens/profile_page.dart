import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});
  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authControllerProvider).me ?? {};
    final name = (me['name'] ?? me['nama'] ?? '-').toString();
    final email = (me['email'] ?? '-').toString();
    final nis = (me['nis'] ?? me['identifier'] ?? '-').toString();

    return AppScaffold(
      title: 'Profil',
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: _HeaderCard(name: name, email: email),
          ),
          SliverToBoxAdapter(child: const SizedBox(height: 12)),
          SliverToBoxAdapter(
            child: _InfoList(
              nis: nis,
              email: email,
            ),
          ),
          SliverToBoxAdapter(child: const SizedBox(height: 12)),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: FilledButton.icon(
                onPressed: () => context.push('/profile/change-password'),
                icon: const Icon(Icons.lock_reset),
                label: const Text('Ubah Password'),
              ),
            ),
          ),
          SliverToBoxAdapter(child: const SizedBox(height: 12)),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: OutlinedButton.icon(
                onPressed: () => context.push('/history'),
                icon: const Icon(Icons.history),
                label: const Text('Lihat History Input'),
              ),
            ),
          ),
          SliverToBoxAdapter(child: const SizedBox(height: 12)),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red.shade700,
                  side: BorderSide(color: Colors.red.shade300),
                ),
                onPressed: () async {
                  final confirm = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Keluar dari akun?'),
                      content: const Text(
                          'Kamu akan keluar dari aplikasi dan perlu login kembali.'),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(ctx, false),
                          child: const Text('Batal'),
                        ),
                        FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.red.shade600,
                          ),
                          onPressed: () => Navigator.pop(ctx, true),
                          child: const Text('Logout'),
                        ),
                      ],
                    ),
                  );
                  if (confirm == true) {
                    await ref.read(authControllerProvider.notifier).logout();
                    if (context.mounted) {
                      GoRouter.of(context).go('/');
                    }
                  }
                },
                icon: const Icon(Icons.logout),
                label: const Text('Logout'),
              ),
            ),
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.name, required this.email});
  final String name;
  final String email;
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: LinearGradient(
          colors: [cs.primary, cs.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        children: [
          const SizedBox(height: 24),
          const CircleAvatar(
              radius: 36,
              backgroundColor: Colors.white,
              child: Icon(Icons.person, size: 40, color: Colors.black54)),
          const SizedBox(height: 12),
          Text(name,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
                fontSize: 18,
              )),
          const SizedBox(height: 4),
          if (email.isNotEmpty)
            Text(email,
                style: const TextStyle(color: Colors.white70, fontSize: 12)),
        ],
      ),
    );
  }
}

class _InfoList extends StatelessWidget {
  const _InfoList({required this.nis, required this.email});
  final String nis;
  final String email;
  @override
  Widget build(BuildContext context) {
    final iconColor = Theme.of(context).colorScheme.primary;
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        children: [
          _InfoTile(
            icon: Icons.badge_outlined,
            label: 'NIS',
            value: nis,
            iconColor: iconColor,
          ),
          const Divider(height: 1),
          _InfoTile(
            icon: Icons.email_outlined,
            label: 'Email',
            value: email,
            iconColor: iconColor,
          ),
        ],
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile(
      {required this.icon,
      required this.label,
      required this.value,
      required this.iconColor});
  final IconData icon;
  final String label;
  final String value;
  final Color iconColor;
  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: iconColor),
      title: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(value.isEmpty ? '-' : value),
    );
  }
}
