import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'auth_repository.dart';
import '../api/api_client.dart';
import 'package:dio/dio.dart' show DioException;

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

final authRepoProvider = Provider<AuthRepository>((ref) {
  final client = ref.watch(apiClientProvider);
  return AuthRepository(client);
});

class AuthState {
  final bool loading;
  final String? token;
  final Map<String, dynamic>? me;
  final String? error;

  const AuthState({this.loading = false, this.token, this.me, this.error});

  AuthState copy(
          {bool? loading,
          String? token,
          Map<String, dynamic>? me,
          String? error}) =>
      AuthState(
        loading: loading ?? this.loading,
        token: token ?? this.token,
        me: me ?? this.me,
        error: error,
      );
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(ref);
});

// Notifier sederhana untuk menandai perubahan pada booking (book/cancel)
final bookingRefreshCounterProvider = StateProvider<int>((ref) => 0);

class AuthController extends StateNotifier<AuthState> {
  final Ref _ref; // <— pakai Ref
  AuthController(this._ref) : super(const AuthState());

  Future<void> bootstrap() async {
    print('🔐 Auth bootstrap: reading saved token');
    final token = await _ref.read(authRepoProvider).readSavedToken();
    if (token == null) {
      print('📭 No saved token found');
      return;
    }
    print('🎫 Token found, fetching user data');
    state = state.copy(loading: true, token: token, error: null);
    try {
      final me = await _ref.read(authRepoProvider).me(token);
      print('👤 User data fetched: ${me['name']}');
      state = state.copy(loading: false, me: me);
    } catch (e) {
      print('❌ Auth bootstrap failed: $e');
      state = const AuthState(); // invalid token, reset
    }
  }

  Future<void> login(String identifier, String password) async {
    state = state.copy(loading: true, error: null);
    try {
      final token = await _ref
          .read(authRepoProvider)
          .login(identifier: identifier, password: password);
      final me = await _ref.read(authRepoProvider).me(token);
      state = AuthState(loading: false, token: token, me: me);
    } on DioException catch (e) {
      final msg =
          e.response?.data is Map && (e.response?.data['message'] != null)
              ? e.response?.data['message'].toString()
              : 'Gagal login. Cek NIS/password.';
      state = state.copy(loading: false, error: msg);
    } catch (e) {
      state =
          state.copy(loading: false, error: 'Terjadi kesalahan tak terduga.');
    }
  }

  Future<void> logout() async {
    final t = state.token;
    if (t == null) return;
    await _ref.read(authRepoProvider).logout(t);
    state = const AuthState();
  }
}
