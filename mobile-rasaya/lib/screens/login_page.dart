import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../auth/auth_controller.dart';
import '../utils/external_navigation.dart';

class LoginPage extends ConsumerStatefulWidget {
  final bool isGuestContext;
  final bool autoGuestLogin;
  final String? returnHomeUrl;

  const LoginPage({
    super.key,
    this.isGuestContext = false,
    this.autoGuestLogin = false,
    this.returnHomeUrl,
  });

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _idCtrl = TextEditingController();
  final _pwCtrl = TextEditingController();
  bool _obscure = true;
  bool _remember = false;
  bool _guestAutoLoginTriggered = false;
  late final bool _isGuestContext;
  late final bool _autoGuestLogin;
  late final String? _returnHomeUrl;

  @override
  void initState() {
    super.initState();
    final qp = Uri.base.queryParameters;
    _isGuestContext = widget.isGuestContext || qp['guest'] == '1';
    _autoGuestLogin = widget.autoGuestLogin || qp['auto_guest'] == '1';
    _returnHomeUrl = widget.returnHomeUrl ?? qp['home_url'];

    if (_isGuestContext && _autoGuestLogin) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _triggerGuestAutoLogin();
      });
    }
  }

  @override
  void dispose() {
    _idCtrl.dispose();
    _pwCtrl.dispose();
    super.dispose();
  }

  Future<void> _triggerGuestAutoLogin() async {
    if (_guestAutoLoginTriggered) return;
    _guestAutoLoginTriggered = true;
    await ref
        .read(authControllerProvider.notifier)
        .loginGuestSiswa(guestHomeUrl: _returnHomeUrl);
  }

  Future<void> _goBackToHome() async {
    final target = _returnHomeUrl?.trim();
    if (target == null || target.isEmpty) return;
    await openExternalUrl(target);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(authControllerProvider);
    final cs = Theme.of(context).colorScheme;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Brand header with logo
                  const SizedBox(height: 16),
                  Column(
                    children: [
                      Image.asset(
                        'assets/images/logo_horizontal.png',
                        width: 280,
                        fit: BoxFit.contain,
                      ),
                      const SizedBox(height: 16),
                      Text('Masuk untuk melanjutkan',
                          style:
                              TextStyle(color: Colors.black54, fontSize: 16)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (_isGuestContext)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: _goBackToHome,
                          icon: const Icon(Icons.arrow_back),
                          label: const Text('Kembali ke Home'),
                        ),
                      ),
                    ),

                  // Error alert (if any)
                  if (state.error != null)
                    Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.symmetric(
                          vertical: 10, horizontal: 12),
                      decoration: BoxDecoration(
                        color: Colors.red.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Text(
                        state.error!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),

                  // Card form
                  Card(
                    elevation: 2,
                    clipBehavior: Clip.antiAlias,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            TextFormField(
                              controller: _idCtrl,
                              decoration: InputDecoration(
                                labelText: 'NIS',
                                filled: true,
                                fillColor: cs.primary.withOpacity(0.06),
                              ),
                              textInputAction: TextInputAction.next,
                              validator: (v) => (v == null || v.isEmpty)
                                  ? 'Wajib diisi'
                                  : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _pwCtrl,
                              decoration: InputDecoration(
                                labelText: 'Kata Sandi',
                                filled: true,
                                fillColor: cs.primary.withOpacity(0.06),
                                suffix: TextButton(
                                  onPressed: () =>
                                      setState(() => _obscure = !_obscure),
                                  child: Text(
                                      _obscure ? 'Tampilkan' : 'Sembunyikan'),
                                ),
                              ),
                              obscureText: _obscure,
                              validator: (v) => (v == null || v.isEmpty)
                                  ? 'Wajib diisi'
                                  : null,
                            ),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Row(children: [
                                  Checkbox(
                                    value: _remember,
                                    onChanged: (v) =>
                                        setState(() => _remember = v ?? false),
                                  ),
                                  const Text('Ingat saya'),
                                ]),
                                TextButton(
                                  onPressed: () =>
                                      context.push('/forgot-password'),
                                  child: const Text('Lupa kata sandi?'),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                onPressed: state.loading
                                    ? null
                                    : () async {
                                        if (_formKey.currentState?.validate() !=
                                            true) {
                                          return;
                                        }
                                        await ref
                                            .read(
                                                authControllerProvider.notifier)
                                            .login(_idCtrl.text.trim(),
                                                _pwCtrl.text);
                                      },
                                child: state.loading
                                    ? const Padding(
                                        padding: EdgeInsets.all(8.0),
                                        child: SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2),
                                        ),
                                      )
                                    : const Text('Masuk'),
                              ),
                            ),
                            if (_isGuestContext) ...[
                              const SizedBox(height: 8),
                              SizedBox(
                                width: double.infinity,
                                child: OutlinedButton(
                                  onPressed: state.loading
                                      ? null
                                      : _triggerGuestAutoLogin,
                                  child: const Text('Masuk sebagai Guest'),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),
                  Text('© ${DateTime.now().year} RASAYA',
                      style: const TextStyle(color: Colors.black54)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
