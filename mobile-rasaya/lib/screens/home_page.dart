import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/auth_controller.dart';
import 'package:go_router/go_router.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  Future<void> _doLogout(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Keluar?'),
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
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(authControllerProvider);
    final me = state.me ?? {};

    if (state.loading && me.isEmpty) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Logout',
            icon: const Icon(Icons.logout),
            onPressed: () => _doLogout(context, ref),
          ),
        ],
      ),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Profil (from /api/me):',
              style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          SelectableText(me.isEmpty ? '—' : me.toString()),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Halo, ${me['name'] ?? '-'}'),
                    Text('Role: ${me['role'] ?? '-'}'),
                    Text('Identifier: ${me['identifier'] ?? '-'}'),
                    Text('Email: ${me['email'] ?? '-'}'),
                  ]),
            ),
          ),
          const SizedBox(height: 24),

          // ===== Navigasi Refleksi (NEW) =====
          Row(children: [
            SizedBox(
              width: 220,
              child: FilledButton.icon(
                icon: const Icon(Icons.edit_note),
                label: const Text('Tulis Refleksi Hari Ini'),
                onPressed: () => context.push('/refleksi'),
              ),
            ),
            const SizedBox(width: 12),
            OutlinedButton.icon(
              icon: const Icon(Icons.history),
              label: const Text('Riwayat Refleksi'),
              onPressed: () => context.push('/refleksi/history'),
            ),
          ]),
          const SizedBox(height: 24),
          // ===================================

          // Tombol logout besar (tetap)
          SizedBox(
            width: 200,
            child: FilledButton.icon(
              icon: const Icon(Icons.logout),
              label: const Text('Logout'),
              onPressed: () => _doLogout(context, ref),
            ),
          ),
          const SizedBox(height: 12),
          const Text(
              'Tips: ganti baseUrl di ApiClient sesuai target (emulator/device).'),
        ]),
      ),
    );
  }
}
