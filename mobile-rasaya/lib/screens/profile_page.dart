import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';
import '../utils/external_navigation.dart';
import '../utils/guest_logout_url.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});
  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  Future<void> _showChangeEmailDialog(BuildContext context) async {
    final me = ref.read(authControllerProvider).me ?? {};
    final currentEmail = (me['email'] ?? '').toString();
    final controller = TextEditingController(text: currentEmail);
    final formKey = GlobalKey<FormState>();
    final emailRegex = RegExp(r'^\S+@\S+\.[\w\-]{2,}$');

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ubah Email'),
        content: Form(
          key: formKey,
          child: TextFormField(
            controller: controller,
            decoration: const InputDecoration(labelText: 'Email'),
            keyboardType: TextInputType.emailAddress,
            validator: (v) {
              final val = (v ?? '').trim();
              if (val.isEmpty) return 'Email wajib diisi';
              if (!emailRegex.hasMatch(val)) return 'Format email tidak valid';
              return null;
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () async {
              if (formKey.currentState?.validate() != true) return;
              Navigator.pop(ctx, true);
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    final newEmail = controller.text.trim();
    final api = ref.read(apiClientProvider);
    final res = await api.changeEmail(newEmail);
    if (!mounted) return;
    if (res.ok) {
      // refresh profile
      await ref.read(authControllerProvider.notifier).bootstrap();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Email berhasil diperbarui')),
      );
      setState(() {});
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(res.errorMessage.isNotEmpty
                ? res.errorMessage
                : 'Gagal memperbarui email')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authControllerProvider);
    final me = authState.me ?? {};
    final isGuestMode = authState.isGuestMode;
    final name = (me['name'] ?? me['nama'] ?? '-').toString();
    final email = (me['email'] ?? '-').toString();
    final nis = (me['nis'] ?? me['identifier'] ?? '-').toString();
    final kelasLabel = (me['kelas_label'] ?? me['role'] ?? '').toString();

    return AppScaffold(
      title: 'Profil',
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: _IdentityHeaderProfile(
              name: name,
              nis: nis,
              kelasLabel: kelasLabel,
            ),
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
            child: _ActionGrid(
              isGuestMode: isGuestMode,
              onChangePassword: () => context.push('/profile/change-password'),
              onChangeEmail: () => _showChangeEmailDialog(context),
              onHistory: () => context.push('/history'),
              onSchedule: () => context.push('/my-schedule'),
              onLogout: () async {
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

                  if (context.mounted) {
                    GoRouter.of(context).go('/');
                  }
                }
              },
            ),
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    );
  }
}

class _IdentityHeaderProfile extends StatelessWidget {
  const _IdentityHeaderProfile(
      {required this.name, required this.nis, required this.kelasLabel});
  final String name;
  final String nis;
  final String kelasLabel;
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final tt = theme.textTheme;
    return Card(
      elevation: 6,
      shadowColor: cs.primary.withOpacity(0.4),
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.all(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 22),
        decoration: BoxDecoration(color: cs.primary),
        child: Stack(children: [
          Positioned(
            right: -20,
            top: -10,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                color: cs.secondary.withOpacity(0.20),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            right: 30,
            bottom: -15,
            child: Container(
              width: 70,
              height: 70,
              decoration: BoxDecoration(
                color: cs.secondary.withOpacity(0.12),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Row(children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Profil',
                      style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: cs.secondary,
                          fontSize: 16)),
                  Text(name,
                      style: tt.headlineSmall?.copyWith(color: cs.secondary)),
                  const SizedBox(height: 10),
                  Wrap(spacing: 10, runSpacing: -8, children: [
                    _IdentityChip(label: 'NIS: $nis'),
                    if (kelasLabel.isNotEmpty) _IdentityChip(label: kelasLabel),
                  ]),
                ],
              ),
            ),
          ])
        ]),
      ),
    );
  }
}

class _IdentityChip extends StatelessWidget {
  const _IdentityChip({required this.label});
  final String label;
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: cs.secondary.withOpacity(0.8), width: 1.4),
        color: cs.primary.withOpacity(0.25),
      ),
      child: Text(label,
          style: TextStyle(
              color: cs.secondary,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.2)),
    );
  }
}

class _InfoList extends StatelessWidget {
  const _InfoList({required this.nis, required this.email});
  final String nis;
  final String email;
  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final iconColor = cs.primary;
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

class _ActionGrid extends StatelessWidget {
  const _ActionGrid({
    required this.isGuestMode,
    required this.onChangePassword,
    required this.onChangeEmail,
    required this.onHistory,
    required this.onSchedule,
    required this.onLogout,
  });
  final bool isGuestMode;
  final VoidCallback onChangePassword;
  final VoidCallback onChangeEmail;
  final VoidCallback onHistory;
  final VoidCallback onSchedule;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          GridView.count(
            crossAxisCount: 2,
            childAspectRatio: 2.8,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: [
              if (!isGuestMode)
                _SmallActionButton(
                  label: 'Ubah Password',
                  icon: Icons.lock_reset,
                  fg: cs.primary,
                  onTap: onChangePassword,
                ),
              if (!isGuestMode)
                _SmallActionButton(
                  label: 'Ubah Email',
                  icon: Icons.alternate_email,
                  fg: cs.primary,
                  onTap: onChangeEmail,
                ),
              _SmallActionButton(
                label: 'Riwayat Input',
                icon: Icons.history,
                fg: cs.primary,
                onTap: onHistory,
              ),
              _SmallActionButton(
                label: 'Jadwal Saya',
                icon: Icons.event_available,
                fg: cs.primary,
                onTap: onSchedule,
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: _SmallActionButton(
              label: 'Logout',
              icon: Icons.logout,
              fg: Colors.red,
              bg: Colors.white,
              border: Border.all(color: Colors.red.shade300),
              onTap: onLogout,
            ),
          ),
        ],
      ),
    );
  }
}

class _SmallActionButton extends StatefulWidget {
  const _SmallActionButton({
    required this.label,
    required this.icon,
    required this.fg,
    this.bg,
    this.border,
    required this.onTap,
  });
  final String label;
  final IconData icon;
  final Color fg;
  final Color? bg;
  final BoxBorder? border;
  final VoidCallback onTap;

  @override
  State<_SmallActionButton> createState() => _SmallActionButtonState();
}

class _SmallActionButtonState extends State<_SmallActionButton>
    with SingleTickerProviderStateMixin {
  double _scale = 1.0;

  void _press(bool down) => setState(() => _scale = down ? 0.98 : 1.0);

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final cardBg = widget.bg ?? Theme.of(context).cardColor;
    return GestureDetector(
      onTapDown: (_) => _press(true),
      onTapCancel: () => _press(false),
      onTapUp: (_) => _press(false),
      onTap: widget.onTap,
      child: AnimatedScale(
        duration: const Duration(milliseconds: 110),
        curve: Curves.easeOut,
        scale: _scale,
        child: Container(
          decoration: BoxDecoration(
            color: cardBg,
            border: widget.border,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: cs.primary.withOpacity(0.10),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            children: [
              Icon(widget.icon, color: widget.fg),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  widget.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: widget.fg,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
