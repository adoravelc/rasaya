import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

class GuestSessionContext {
  final bool isGuestMode;
  final String? guestHomeUrl;

  const GuestSessionContext({
    required this.isGuestMode,
    this.guestHomeUrl,
  });
}

class AuthRepository {
  final ApiClient _client;
  AuthRepository(this._client);

  static const _kTokenKey = 'token';
  static const _kGuestModeKey = 'guest_mode';
  static const _kGuestHomeUrlKey = 'guest_home_url';

  Future<String> login({
    required String identifier,
    required String password,
  }) async {
    final res = await _client.dio.post('/login', data: {
      'identifier': identifier,
      'password': password,
      'device_name': 'flutter',
    });
    final token = res.data['token'] as String;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kTokenKey, token);
    await prefs.setBool(_kGuestModeKey, false);
    await prefs.remove(_kGuestHomeUrlKey);
    return token;
  }

  Future<String> loginGuestSiswa() async {
    final res = await _client.dio.post('/login/guest-siswa', data: {
      'device_name': 'flutter-web-guest',
    });
    final token = res.data['token'] as String;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kTokenKey, token);
    return token;
  }

  Future<void> saveGuestSessionContext({
    required bool isGuestMode,
    String? guestHomeUrl,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kGuestModeKey, isGuestMode);
    if (guestHomeUrl != null && guestHomeUrl.isNotEmpty) {
      await prefs.setString(_kGuestHomeUrlKey, guestHomeUrl);
    } else {
      await prefs.remove(_kGuestHomeUrlKey);
    }
  }

  Future<GuestSessionContext> readGuestSessionContext() async {
    final prefs = await SharedPreferences.getInstance();
    return GuestSessionContext(
      isGuestMode: prefs.getBool(_kGuestModeKey) ?? false,
      guestHomeUrl: prefs.getString(_kGuestHomeUrlKey),
    );
  }

  Future<void> clearGuestSessionContext() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kGuestModeKey, false);
    await prefs.remove(_kGuestHomeUrlKey);
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
    await prefs.setBool(_kGuestModeKey, false);
    await prefs.remove(_kGuestHomeUrlKey);
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
