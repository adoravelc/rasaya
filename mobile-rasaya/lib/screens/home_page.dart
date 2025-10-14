import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  Future<void> _doLogout(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Keluar dari aplikasi?'),
        content: const Text('Anda yakin ingin logout dari aplikasi?'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal')),
          FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Logout')),
        ],
      ),
    );
    if (ok == true) {
      await ref.read(authControllerProvider.notifier).logout();
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Berhasil logout')));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(authControllerProvider);
    final me = state.me ?? {};

    if (state.loading && me.isEmpty) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final name = (me['name'] ?? '-').toString();
    final role = (me['role'] ?? '-').toString();
    final identifier = (me['identifier'] ?? '-').toString();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Logout',
            icon: const Icon(Icons.logout),
            onPressed: () => _doLogout(context, ref),
          )
        ],
      ),
      body: LayoutBuilder(
        builder: (ctx, c) {
          final isWide = c.maxWidth >= 900;
          final content = ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Greeting + profile mini
              Card(
                clipBehavior: Clip.antiAlias,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CircleAvatar(
                        radius: 28,
                        child: Text(name.isNotEmpty
                            ? name.characters.first.toUpperCase()
                            : '?'),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Halo, $name 👋',
                                  style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700)),
                              const SizedBox(height: 4),
                              Row(children: [
                                Chip(label: Text('Role: $role')),
                                const SizedBox(width: 8),
                                Chip(label: Text('ID: $identifier')),
                              ]),
                              const SizedBox(height: 6),
                              const Text('Semoga harimu menyenangkan!'),
                            ]),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Quick actions
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _QuickAction(
                    icon: Icons.edit_note,
                    label: 'Tulis Refleksi',
                    onTap: () => context.push('/refleksi'),
                  ),
                  _QuickAction(
                    icon: Icons.history,
                    label: 'Riwayat',
                    onTap: () => context.push('/history'),
                  ),
                  _QuickAction(
                    icon: Icons.mood,
                    label: 'Mood Tracker',
                    onTap: () => context.push('/mood'),
                  ),
                ],
              ),

              const SizedBox(height: 16),

              // Card placeholder untuk info ringkas / notifikasi
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Text('Status Cepat',
                            style: TextStyle(fontWeight: FontWeight.w700)),
                        SizedBox(height: 8),
                        Text('• Refleksi hari ini: —'),
                        Text('• Mood pagi/sore: —'),
                      ]),
                ),
              ),

              const SizedBox(height: 24),

              // Tombol logout besar
              Align(
                alignment: Alignment.centerLeft,
                child: SizedBox(
                  width: 220,
                  child: FilledButton.icon(
                    icon: const Icon(Icons.logout),
                    label: const Text('Logout'),
                    onPressed: () => _doLogout(context, ref),
                  ),
                ),
              ),

              const SizedBox(height: 12),
              const Text(
                  'Tips: ganti baseUrl di ApiClient sesuai target (emulator/device).',
                  style: TextStyle(color: Colors.grey)),
            ],
          );

          if (isWide) {
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 7, child: content),
                const SizedBox(width: 12),
                // Sidebar kanan kecil (opsional)
                Expanded(
                  flex: 3,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: const [
                                Text('Pengumuman',
                                    style:
                                        TextStyle(fontWeight: FontWeight.w700)),
                                SizedBox(height: 8),
                                Text('Belum ada pengumuman.'),
                              ]),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }
          return content;
        },
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction(
      {required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Ink(
        width: 200,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).dividerColor),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon),
            const SizedBox(width: 10),
            Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
          ],
        ),
      ),
    );
  }
}
