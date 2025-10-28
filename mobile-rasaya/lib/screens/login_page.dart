import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/auth_controller.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _idCtrl = TextEditingController();
  final _pwCtrl = TextEditingController();
  bool _obscure = true;
  bool _remember = false;

  @override
  void dispose() {
    _idCtrl.dispose();
    _pwCtrl.dispose();
    super.dispose();
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
                  // Brand header
                  const SizedBox(height: 12),
                  Column(
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Text(
                            'RASAYA',
                            style: TextStyle(
                              color: cs.primary,
                              fontWeight: FontWeight.w800,
                              fontSize: 24,
                              letterSpacing: 1.0,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Container(
                            width: 10,
                            height: 10,
                            decoration: BoxDecoration(
                              color: cs.secondary,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text('Masuk untuk melanjutkan',
                          style: TextStyle(color: Colors.black54)),
                    ],
                  ),
                  const SizedBox(height: 16),

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
                                labelText: 'NIS/NUPTK',
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
                                  onPressed: null, // will be implemented later
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
