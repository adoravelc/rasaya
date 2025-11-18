import 'package:shared_preferences/shared_preferences.dart';
import 'package:dio/dio.dart';
import '../api/api_client.dart';

class AuthRepository {
  final ApiClient _client;
  AuthRepository(this._client);

  static const _kTokenKey = 'token';

  Future<String> login({
    required String identifier,
    required String password,
  }) async {
    try {
      final res = await _client.dio.post('/login', data: {
        'identifier': identifier,
        'password': password,
        'device_name': 'flutter',
      });
      final token = res.data['token'] as String;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_kTokenKey, token);
      return token;
    } on DioException catch (e) {
      final msg = e.response?.data is Map && e.response?.data['message'] != null
          ? e.response!.data['message'].toString()
          : 'Gagal login. Periksa identifier/password.';
      throw Exception(msg);
    }
  }

  Future<Map<String, dynamic>> me(String token) async {
    final authed = _client.withToken(token);
    final res = await authed.dio.get('/me');
    return Map<String, dynamic>.from(res.data);
  }

  Future<void> logout(String token) async {
    final authed = _client.withToken(token);
    try {
      await authed.dio.post('/logout');
    } catch (_) {}
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kTokenKey);
  }

  Future<String?> readSavedToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_kTokenKey);
  }

  Future<void> requestForgotPassword({
    required String identifier,
    String? email,
    String method = 'admin',
  }) async {
    final body = <String, dynamic>{
      if (identifier.isNotEmpty) 'identifier': identifier,
      if (email != null && email.isNotEmpty) 'email': email,
      'method': method,
    };
    try {
      await _client.dio.post('/forgot-password', data: body);
    } catch (_) {
      // Intentionally ignore details; API always returns ok to avoid enumeration
    }
  }
}
