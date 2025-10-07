// api_client.dart
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform;

class ApiClient {
  final Dio _dio;

  // Sesuaikan base URL:
  // - Web dev: backend Laravel lokal yg diakses browser → localhost
  // - Android emulator: 10.0.2.2
  // - iOS simulator: 127.0.0.1
  static const String _webBase = 'http://localhost:8000/api';
  static const String _androidBase = 'http://10.0.2.2:8000/api';
  static const String _iosBase = 'http://127.0.0.1:8000/api';

  static String _resolveBaseUrl() {
    if (kIsWeb) return _webBase;
    if (defaultTargetPlatform == TargetPlatform.android) return _androidBase;
    return _iosBase; // iOS/macOS/desktop default
  }

  ApiClient({String? token})
      : _dio = Dio(BaseOptions(
          baseUrl: _resolveBaseUrl(),
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 15),
          headers: token != null ? {'Authorization': 'Bearer $token'} : null,
        ));

  Dio get dio => _dio;

  ApiClient withToken(String token) => ApiClient(token: token);
}
