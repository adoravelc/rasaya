import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/auth_controller.dart';
import '../widgets/app_scaffold.dart';

class ChangePasswordPage extends ConsumerStatefulWidget {
  const ChangePasswordPage({super.key});
  @override
  ConsumerState<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends ConsumerState<ChangePasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _currentCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();
  bool _ob1 = true, _ob2 = true, _ob3 = true;
  bool _submitting = false;

  @override
  void dispose() {
    _currentCtrl.dispose();
    _newCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    final api = ref.read(apiClientProvider);
    final res = await api.changePassword(
      currentPassword: _currentCtrl.text,
      newPassword: _newCtrl.text,
    );
    if (!mounted) return;
    setState(() => _submitting = false);
    if (res.ok) {
      _currentCtrl.clear();
      _newCtrl.clear();
      _confirmCtrl.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(children: const [
            Icon(Icons.check_circle, color: Colors.white),
            SizedBox(width: 8),
            Expanded(child: Text('Password berhasil diubah.')),
          ]),
          backgroundColor: Colors.green.shade700,
          behavior: SnackBarBehavior.floating,
        ),
      );
      Navigator.pop(context);
    } else {
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Gagal'),
          content: Text(res.errorMessage),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Tutup')),
          ],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(authControllerProvider).me ?? {};
    final needsUpdate = (me['needs_password_update'] == true);
    final initialToken = (me['initial_password_token'] ?? '') as String;
    // Prefill token if still in initial state
    if (needsUpdate && initialToken.isNotEmpty && _currentCtrl.text.isEmpty) {
      _currentCtrl.text = initialToken;
    }
    return AppScaffold(
      title: 'Ubah Password',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Masukkan detail berikut',
                        style: TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 16)),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _currentCtrl,
                      readOnly: needsUpdate,
                      obscureText: needsUpdate ? !_ob1 : _ob1,
                      decoration: InputDecoration(
                        labelText: needsUpdate
                            ? 'Token password'
                            : 'Password saat ini',
                        suffixIcon: IconButton(
                          icon: Icon(
                              _ob1 ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _ob1 = !_ob1),
                        ),
                      ),
                      validator: (v) =>
                          (v == null || v.isEmpty) ? 'Wajib diisi' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _newCtrl,
                      obscureText: _ob2,
                      decoration: InputDecoration(
                        labelText: 'Password baru (min 6 karakter)',
                        suffixIcon: IconButton(
                          icon: Icon(
                              _ob2 ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _ob2 = !_ob2),
                        ),
                      ),
                      validator: (v) {
                        if (v == null || v.isEmpty) return 'Wajib diisi';
                        if (v.length < 6) return 'Minimal 6 karakter';
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _confirmCtrl,
                      obscureText: _ob3,
                      decoration: InputDecoration(
                        labelText: 'Konfirmasi password baru',
                        suffixIcon: IconButton(
                          icon: Icon(
                              _ob3 ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _ob3 = !_ob3),
                        ),
                      ),
                      validator: (v) => v != _newCtrl.text
                          ? 'Tidak sama dengan password baru'
                          : null,
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _submitting ? null : _submit,
                        child: _submitting
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Text('Simpan'),
                      ),
                    )
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
