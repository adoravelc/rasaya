import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth_controller.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(authControllerProvider);
    final me = state.me ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
            },
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
          const SizedBox(height: 12),
          const Text(
              'Tips: ganti baseUrl di ApiClient sesuai target (emulator/device).'),
        ]),
      ),
    );
  }
}
